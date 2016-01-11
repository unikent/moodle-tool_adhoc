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
 * @copyright 2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class beanstalk
{
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
                $this->api = new Pheanstalk($this->config->beanstalk_hostname, $this->config->beanstalk_port, $this->config->beanstalk_timeout);
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
        if (!$this->enabled) {
            return false;
        }

        $this->watch($this->get_tube());
        while ($job = $this->reserve()) {
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
                if (!call_user_func_array(array($obj, $received['method']), $args)) {
                    cli_writeln("Invalid class: " . json_encode($received));
                }
            } catch (\Exception $e) {
                cli_writeln("Exception thrown: " . $e->getMessage());
            }

            $this->delete($job);
        }
    }

    /**
     * Hook for queue_adhoc_task.
     */
    public static function queue_adhoc_task($id, $priority = PheanstalkInterface::DEFAULT_PRIORITY) {
        $beanstalk = new static();
        $beanstalk->add_job('\\tool_adhoc\\jobs\\adhoc', 'run_task', array($id), 900, $priority);
    }
}
