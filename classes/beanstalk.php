<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Beanstalk API for the adhoc task manager.
 *
 * @package   tool_adhoc
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
/**
 * Beanstalk methods.
 *
 * @package   tool_adhoc
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class beanstalk
{
    const STATUS_OK = 0;
    const STATUS_ERROR = 1;
    const STATUS_RETRY = 2;
    const STATUS_BURY = 4;

    private $config;
    private $enabled;
    private $api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = get_config('tool_adhoc');
        $this->enabled = isset($this->config->beanstalk_enabled) && $this->config->beanstalk_enabled;
        if (isset($this->config->beanstalk_unavailable)) {
            if (time() - $this->config->beanstalk_unavailable < 18000) {
                // Beanstalk has been down in the last 5 minutes, don't try it.
                $this->enabled = false;
            } else {
                unset_config('beanstalk_unavailable', 'tool_adhoc');
            }
        }

        try {
            if ($this->enabled) {
                $this->api = new Pheanstalk(
                    $this->config->beanstalk_hostname,
                    $this->config->beanstalk_port,
                    $this->config->beanstalk_timeout
                );
            }
        } catch (\Exception $e) {
            // Not ready?
            $this->enabled = false;
        }
    }

    /**
     * Magic.
     */
    public function __call($method, $arguments) {
        if (!$this->enabled) {
            return false;
        }

        $result = false;

        try {
            $result = call_user_func_array(array($this->api, $method), $arguments);
        } catch (\Pheanstalk\Exception\ConnectionException $e) {
            // Not ready?
            $this->enabled = false;
            set_config('beanstalk_unavailable', time(), 'tool_adhoc');
        }

        return $result;
    }

    /**
     * Are we ready?
     */
    public function is_ready() {
        return $this->enabled;
    }

    /**
     * Return our tube name.
     */
    public function get_tube() {
        return $this->config->beanstalk_tubename;
    }

    /**
     * Tell our workers to do something.
     *
     * @param string $class    The fully qualified class name of the worker to initialize.
     * @param string $method   The method name on the worker to run.
     * @param array  $args     The args to send the above method.
     * @param int    $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param int    $delay    Seconds to wait before job becomes ready
     * @param int    $timeout  Time To Run: seconds a job can be reserved for
     */
    public function add_job($class, $method, $args = array(), $timeout = 900,
                            $priority = PheanstalkInterface::DEFAULT_PRIORITY,
                            $delay = PheanstalkInterface::DEFAULT_DELAY) {
        return $this->putInTube($this->get_tube(), json_encode(array(
            'class' => $class,
            'method' => $method,
            'args' => $args
        )), $priority, $delay, $timeout);
    }

    /**
     * Initialize worker.
     */
    public function become_worker() {
        global $DB;

        if (!$this->enabled) {
            return false;
        }

        $runversion = $DB->get_field('config', 'value', array('name' => 'beanstalk_deploy'));

        $this->watch($this->get_tube());
        while ($job = $this->reserve(300)) {
            // Check the DB is still alive.
            try {
                $currentversion = $DB->get_field('config', 'value', array('name' => 'beanstalk_deploy'));

                if ($currentversion !== $runversion) {
                    throw new \moodle_exception("Beanstalk worker requires a restart.");
                }
            } catch (\Exception $e) {
                $this->release($job);
                exit(1);
            }

            $received = json_decode($job->getData(), true);

            // Structure check.
            if (!is_array($received) || !isset($received['class']) || !isset($received['method'])) {
                cli_writeln("Received invalid job: " . json_encode($received));
                $this->delete($job);

                continue;
            }

            // We have something to do!
            $args = isset($received['args']) ? $received['args'] : array();
            $class = $received['class'];

            // Run!
            try {
                $obj = new $class();
                $ret = call_user_func_array(array($obj, $received['method']), $args);
                if ($ret === false) {
                    cli_writeln("Invalid class: " . json_encode($received));
                } else {
                    switch ($ret) {
                        case self::STATUS_RETRY:
                            // The user function is telling us to retry.
                            $this->release($job);
                        break;

                        case self::STATUS_BURY:
                            // The user function is telling us to bury.
                            $this->bury($job);
                        break;

                        case self::STATUS_ERROR:
                            cli_writeln("Job threw handled error");
                        case self::STATUS_OK:
                        default:
                            $this->delete($job);
                        break;
                    }
                }
            } catch (\Exception $e) {
                print_r($received);
                cli_writeln("Exception thrown in user function: " . $e->getMessage());
                $this->delete($job);
            }

            // Flush buffers.
            $this->flush();
        }
    }

    /**
     * Flushes various buffers.
     */
    private function flush() {
        // Flush log stores.
        get_log_manager(true);

        // Special case for splunk.
        $splunk = \logstore_splunk\splunk::instance();
        $splunk->flush();
    }

    /**
     * Hook for custom tasks.
     */
    public static function queue_custom_task($class, $method, $args = array(), $priority = PheanstalkInterface::DEFAULT_PRIORITY) {
        $beanstalk = new static();
        if ($beanstalk->is_ready()) {
            $beanstalk->add_job($class, $method, $args, 900, $priority);
        }
    }

    /**
     * Hook for queue_adhoc_task.
     */
    public static function queue_adhoc_task($id, $priority = PheanstalkInterface::DEFAULT_PRIORITY) {
        static::queue_custom_task('\\tool_adhoc\\jobs\\adhoc', 'run_task', array($id), $priority);
    }

    /**
     * Hook for kick.
     */
    public static function kick_workers() {
        static::queue_custom_task('\\tool_adhoc\\jobs\\utility', 'kick', array(microtime(true)));
    }
}
