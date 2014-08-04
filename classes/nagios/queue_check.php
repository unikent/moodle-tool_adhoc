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
 * Adhoc task manager.
 *
 * @package    local_adhoc
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adhoc\nagios;

/**
 * Checks cache.
 */
class queue_check extends \local_nagios\base_check
{
    public function execute() {
        global $DB;

        $config = get_config('local_nagios');
        $errorthreshold = isset($config->nagios_error_threshhold) ? $config->nagios_error_threshhold : 25;
        $warnthreshold = isset($config->nagios_warning_threshhold) ? $config->nagios_warning_threshhold : 10;

        $count = $DB->count_records('task_adhoc');

        if ($count > $errorthreshold) {
            $this->error("{$count} adhoc tasks in the queue!");
            return;
        }

        if ($count > $warnthreshold) {
            $this->warn("{$count} adhoc tasks in the queue!");
        }
    }
}