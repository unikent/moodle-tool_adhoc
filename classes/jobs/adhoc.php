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
 * Utility jobs.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc\jobs;

/**
 * Adhoc task tasks.
 */
class adhoc
{
    /**
     * Run an adhoc task.
     */
    public function run_task($id) {
        global $DB;

        $task = $DB->get_record('task_adhoc', array('id' => $id));
        if ($task) {
            \tool_adhoc\manager::run_tasks(array($task), true);
        }

        return \tool_adhoc\manager::STATUS_OK;
    }

    /**
     * Kick a worker.
     */
    public function kick($microtime) {
        $time = microtime(true) - $microtime;
        cli_writeln("Kicked worker in {$time}ms.");

        return \tool_adhoc\manager::STATUS_OK;
    }

    /**
     * Poisons this worker.
     */
    public function poison() {
        cli_writeln("Poisoned worker.");
        exit(1);
    }
}
