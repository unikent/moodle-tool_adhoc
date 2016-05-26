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
 * Cron queue for the adhoc task manager.
 *
 * @package   queue_cron
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace queue_cron;

if (!class_exists("\\Pheanstalk\\Pheanstalk")) {
    require_once(dirname(__FILE__) . "/../vendor/autoload.php");
}

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

/**
 * Cron queue API.
 *
 * @package   queue_cron
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue extends \tool_adhoc\queue
{
    private $config;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = get_config('queue_cron');
    }

    /**
     * Push an item onto the queue.
     *
     * @param  int $id       ID of the adhoc task.
     * @param  int $priority Priority (higher = lower priority)
     * @param  int $timeout  Timeout for the task to complete.
     * @param  int $delay    Delay before executing task.
     * @return bool          [description]
     */
    public function push($id, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $timeout = 900, $delay = PheanstalkInterface::DEFAULT_DELAY) {
        // We don't actually do anything.
        // The manager automatically adds it to the DB which is all we need.
        // We could do some extention to support priorities and delays if we ever make it into core.
    }

    /**
     * Are we ready?
     */
    public function is_ready() {
        if (!isset($this->config->lastran)) {
            return false;
        }
        return $this->config->lastran > 0;
    }
}
