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
 * Beanstalk jobs.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc\jobs;

/**
 * Utility tasks.
 */
class utility
{
    /**
     * Run an adhoc task.
     */
    public function kick($microtime) {
        $time = microtime(true) - $microtime;
        cli_writeln("Kicked worker in {$time}ms.");

        return \tool_adhoc\beanstalk::STATUS_OK;
    }

    /**
     * Poisons this worker.
     */
    public function poison() {
        cli_writeln("Poisoned worker.");
        exit(1);
    }
}
