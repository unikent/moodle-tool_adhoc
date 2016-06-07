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
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc;

/**
 * Adhoc manager methods.
 *
 * @package   tool_adhoc
 * @author    Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright 2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager
{
    /**
     * Given the name of a queue, returns it's interface.
     */
    public static function get_queue($queue) {
        static $map = array();

        if (!isset($map[$queue])) {
            $class = "\\$queue\\queue";
            $map[$queue] = new $class();
        }

        return $map[$queue];
    }

    /**
     * Returns all queues in order.
     */
    public static function get_queues() {
        $enabled = get_config('tool_adhoc', 'enabled_queues');
        if (!$enabled) {
            return array();
        }

        $plugins = explode(',', $enabled);
        return array_map(array("\\tool_adhoc\\manager", 'get_queue'), $plugins);
    }

    /**
     * Check a plugin is enabled.
     */
    public static function is_enabled($plugin) {
        $enabled = get_config('tool_adhoc', 'enabled_queues');
        if (!$enabled) {
            return false;
        }

        $enabled = explode(',', $enabled);
        return in_array("queue_{$plugin}", $enabled);
    }

    /**
     * Hook for queue_adhoc_task.
     */
    public static function queue_adhoc_task($id, $priority = 512, $timeout = 900, $delay = 0) {
        $queues = self::get_queues();
        foreach ($queues as $queue) {
            if ($queue->is_ready()) {
                return $queue->push($id, $priority, $timeout, $delay);
            }
        }

        return false;
    }

    /**
     * Run an adhoc task.
     *
     * @param stdClass $record The task to run.
     * @return bool True if we succeeded, false if we didnt.
     */
    public static function run_task($record) {
        global $DB;

        // Grab the task.
        $task = \core\task\manager::adhoc_task_from_record($record);
        if (!$task) {
            throw new \moodle_exception("Task '{$record->id}' could not be loaded.");
        }

        // Grab a task lock.
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$tasklock = $cronlockfactory->get_lock('adhoc_' . $record->id, 600)) {
            debugging('Cannot obtain task lock.');
            return false;
        }

        // Set lock info on task.
        $task->set_lock($tasklock);
        if ($task->is_blocking()) {
            if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
                debugging('Cannot obtain cron lock');
                return false;
            }

            $task->set_cron_lock($cronlock);
        }

        try {
            // Run the task.
            $task->execute();

            // Set the task as complete.
            \core\task\manager::adhoc_task_complete($task);
        } catch (\Exception $e) {
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }

            \core\task\manager::adhoc_task_failed($task);

            throw $e;
        }

        return true;
    }

    /**
     * Run a given set of tasks.
     * You should only call this with a set of tasks you know will
     * not complete elsewhere.
     *
     * @param array $records An array of adhoc DB records to run.
     * @param bool $ignoreblocking DEPRECATED - Don't block, even when the task says we should.
     * @return bool True if we succeeded, false if we didnt.
     */
    public static function run_tasks($records, $ignoreblocking = null) {
        debugging("This method is deprecated, you should use run_task and only run tasks you currently own.");

        foreach ($records as $record) {
            self::run_task($record);
        }
    }
}
