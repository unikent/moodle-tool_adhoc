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
 * Main API for the adhoc task manager.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc;

/**
 * Interface for queue managers.
 *
 * @package   tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface queue
{
    /**
     * Push an item onto the queue.
     *
     * @param  int $id       ID of the adhoc task.
     * @param  int $priority Priority (higher = lower priority)
     * @param  int $timeout  Timeout for the task to complete.
     * @param  int $delay    Delay before executing task.
     * @return bool          True on success, false otherwise.
     */
    public function push($id, $priority = 512, $timeout = 900, $delay = 0);

    /**
     * Are we ready?
     *
     * @return bool True if ready, false otherwise.
     */
    public function is_ready();
}
