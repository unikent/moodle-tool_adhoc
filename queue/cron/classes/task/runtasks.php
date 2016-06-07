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

namespace queue_cron\task;

/**
 * Task runner.
 */
class runtasks extends \core\task\scheduled_task
{
    public function get_name() {
        return "Cron queue runner";
    }

    public function execute() {
        $config = get_config('queue_cron');

        $count = 0;
        $sql = 'SELECT * FROM {task_adhoc} WHERE nextruntime >= :time LIMIT 1';
        while (($config->maxtasks == 0 || $count <= $config->maxtasks) &&
                $record = $DB->get_record_sql($sql, array('time' => time()))) {
            \tool_adhoc\manager::run_task($record);
            $count++;
        }

        set_config('lastran', time(), 'queue_cron');

        return true;
    }
}
