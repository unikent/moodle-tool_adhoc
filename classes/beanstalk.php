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
    private $api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api = new Pheanstalk('127.0.0.1', PheanstalkInterface::DEFAULT_PORT, 1);
    }

    /**
     * Magic.
     */
    public function __call($method, $arguments) {
        return call_user_func_array(array($this->api, $method), $arguments);
    }

    /**
     * Return our tube name.
     */
    public function get_tube() {
        global $CFG;

        return "moodle-{$CFG->kent->distribution}-tasks";
    }

    /**
     * Tell our workers to do something.
     *
     * @param string $class    The fully qualified class name of the worker to initialize.
     * @param string $method   The method name on the worker to run.
     * @param array  $args     The args to send the above method.
     * @param int    $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param int    $delay    Seconds to wait before job becomes ready
     * @param int    $ttr      Time To Run: seconds a job can be reserved for
     */
    public function add_job($class, $method, $args = array(), $timeout = 900,
                            $priority = PheanstalkInterface::DEFAULT_PRIORITY,
                            $delay = PheanstalkInterface::DEFAULT_DELAY) {
        global $CFG;

        return $this->api->putInTube($this->get_tube(), json_encode(array(
            'class' => $class,
            'method' => $method,
            'args' => $args
        )), $priority, $delay, $timeout);
    }

    /**
     * Initialize worker.
     */
    public function become_worker() {
        global $CFG;

        $this->api->watch($this->get_tube());
        while($job = $this->api->reserve()) {
            $received = json_decode($job->getData(), true);

            // Sanity check.
            if (!is_array($received)) {
                continue;
            }

            // Structure check.
            if (!isset($recieved['class']) || !isset($recieved['method'])) {
                echo "Recieved invalid job: " . json_encode($received);
            }

            // We have something to do!
            $args = isset($recieved['args']) ? $recieved['args'] : array();
            $class = $recieved['class'];
            $obj = new $class();
            if (call_user_method(array($obj, $recieved['method']), $args)) {
                // Success!
                $this->api->delete($job);
            } else {
                // Fail.
                $this->api->bury($job);
            }
        }
    }

    /**
     * Return beanstalk stats.
     */
    public function tube_stats() {
        global $CFG;

        return $this->api->statsTube($this->get_tube());
    }
}
