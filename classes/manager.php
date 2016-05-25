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
     * Run a given set of tasks.
     *
     * @param array $records An array of adhoc DB records to run.
     * @return bool True if we succeeded, false if we didnt.
     */
    public static function run_tasks($records, $ignoreblocking = false) {
        global $CFG, $DB;

        require_once("{$CFG->libdir}/clilib.php");

        // Get the cron lock factory.
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        $cronlock = null;

        foreach ($records as $record) {
            // Grab the task.
            $task = \core\task\manager::adhoc_task_from_record($record);
            if (!$task) {
                cli_problem("Task '{$record->id}' could not be loaded.");
                continue;
            }

            if ($ignoreblocking && $task->is_blocking()) {
                continue;
            }

            // Set pre-task performance vars.
            $predbqueries = $DB->perf_get_queries();
            $pretime = microtime(true);

            try {
                // Grab a task lock.
                if (!$lock = $cronlockfactory->get_lock('adhoc_' . $record->id, 10)) {
                    cli_problem('Cannot obtain task lock');
                    continue;
                }

                // Set lock info on task.
                $task->set_lock($lock);
                if ($task->is_blocking()) {
                    if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
                        cli_problem('Cannot obtain cron lock');
                        continue;
                    }
                    $task->set_cron_lock($cronlock);
                }

                // Run the task.
                $task->execute();

                // Echo out performance info.
                if (isset($predbqueries)) {
                    cli_writeln("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
                    cli_writeln("... used " . (microtime(1) - $pretime) . " seconds");
                }
                cli_writeln("Task {$record->id} completed.");

                // Set the task as complete.
                \core\task\manager::adhoc_task_complete($task);
            } catch (\Exception $e) {
                if ($DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();
                }

                cli_writeln("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
                cli_writeln("... used " . (microtime(true) - $pretime) . " seconds");
                cli_problem("Task failed: " . $e->getMessage());

                // We failed.
                \core\task\manager::adhoc_task_failed($task);

                // Release the global lock before we throw errors.
                if ($cronlock) {
                    $cronlock->release();
                    unset($cronlock);
                }

                throw $e;
            }
        }

        // Release the global lock.
        if ($cronlock) {
            $cronlock->release();
            unset($cronlock);
        }
    }
}
