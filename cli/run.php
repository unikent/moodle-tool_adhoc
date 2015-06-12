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
 * Run all adhoc tasks.
 *
 * @package    tool_adhoc
 * @copyright  2015 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(__FILE__) . '/../../../../config.php');
require_once("{$CFG->libdir}/clilib.php");
require_once("{$CFG->libdir}/cronlib.php");

if (moodle_needs_upgrading()) {
    mtrace("Moodle upgrade pending, cannot execute tasks.");
    exit(1);
}

// Increase memory limit.
raise_memory_limit(MEMORY_EXTRA);

// Emulate normal session - we use admin account by default.
cron_setup_user();

// NOTE: it would be tricky to move this code to \core\task\manager class,
//       because we want to do detailed error reporting.
$cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
    mtrace('Cannot obtain cron lock');
    exit(129);
}

$tasks = $DB->get_records('task_adhoc');

foreach ($tasks as $record) {
    $task = \core\task\manager::adhoc_task_from_record($record);

    if (!$task) {
        mtrace("Task '{$record->id}' could not be executed.");
        exit(1);
    }

    $predbqueries = $DB->perf_get_queries();
    $pretime = microtime(true);
    try {
        if (!$lock = $cronlockfactory->get_lock('adhoc_' . $record->id, 10)) {
            mtrace('Cannot obtain task lock');
            continue;
        }
        $task->set_lock($lock);
        if ($task->is_blocking()) {
            $task->set_cron_lock($cronlock);
        }
        $task->execute();
        if (isset($predbqueries)) {
            mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
            mtrace("... used " . (microtime(1) - $pretime) . " seconds");
        }
        mtrace("Task {$record->id} completed.");
        \core\task\manager::adhoc_task_complete($task);
        exit(0);
    } catch (Exception $e) {
        if ($DB->is_transaction_started()) {
            $DB->force_transaction_rollback();
        }
        mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
        mtrace("... used " . (microtime(true) - $pretime) . " seconds");
        mtrace("Task failed: " . $e->getMessage());
        \core\task\manager::adhoc_task_failed($task);
        $cronlock->release();
        throw $e;
        exit(1);
    }
}

$cronlock->release();
