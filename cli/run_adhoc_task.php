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
 * CLI task execution.
 *
 * @package    local_adhoc
 * @copyright  2014 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(__FILE__) . '/../../../config.php');
require_once("{$CFG->libdir}/clilib.php");
require_once("{$CFG->libdir}/cronlib.php");

list($options, $unrecognized) = cli_get_params(
    array('help' => false, 'list' => false, 'execute' => false, 'delete' => false),
    array('h' => 'help')
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] or (!$options['list'] and !$options['execute'] and !$options['delete'])) {
    $help = "Adhoc cron tasks.

Options:
--execute=id  Execute adhoc task manually
--delete=id   Delete adhoc task manually
--list        List all adhoc tasks
-h, --help    Print out this help

Example:
\$sudo -u www-data /usr/bin/php local/adhoc/cli/run_adhoc_task.php --execute=1

";

    echo $help;
    die;
}

if ($options['list']) {
    cli_heading("List of adhoc tasks ({$CFG->wwwroot})");

    $shorttime = get_string('strftimedatetimeshort');

    echo str_pad('ID', 6, ' ') . ' ' . str_pad('Class Name', 150, ' ') . "\n";

    $tasks = $DB->get_records('task_adhoc');
    foreach ($tasks as $task) {
        echo str_pad($task->id, 6, ' ') . ' ' . str_pad($task->classname, 150, ' ') . "\n";
    }

    exit(0);
}

if ($execute = $options['execute']) {
    // Get the record.
    $record = $DB->get_record('task_adhoc', array(
        'id' => $execute
    ));

    if (!$record) {
        mtrace("Task '{$execute}' not found.");
        exit(1);
    }

    $task = \core\task\manager::adhoc_task_from_record($record);

    if (!$task) {
        mtrace("Task '{$execute}' could not be created.");
        exit(1);
    }

    if (moodle_needs_upgrading()) {
        mtrace("Moodle upgrade pending, cannot execute tasks.");
        exit(1);
    }

    // Increase memory limit.
    raise_memory_limit(MEMORY_EXTRA);

    // Emulate normal session - we use admin account by default.
    cron_setup_user();

    $predbqueries = $DB->perf_get_queries();
    $pretime = microtime(true);
    try {
        // NOTE: it would be tricky to move this code to \core\task\manager class,
        //       because we want to do detailed error reporting.
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
            mtrace('Cannot obtain cron lock');
            exit(129);
        }
        if (!$lock = $cronlockfactory->get_lock('adhoc_' . $record->id, 10)) {
            mtrace('Cannot obtain task lock');
            exit(130);
        }
        $task->set_lock($lock);
        if (!$task->is_blocking()) {
            $cronlock->release();
        } else {
            $task->set_cron_lock($cronlock);
        }
        $task->execute();
        if (isset($predbqueries)) {
            mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
            mtrace("... used " . (microtime(1) - $pretime) . " seconds");
        }
        mtrace("Task completed.");
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
        exit(1);
    }
}

if ($delete = $options['delete']) {
    // Get the record.
    $record = $DB->get_record('task_adhoc', array(
        'id' => $delete
    ));

    if (!$record) {
        mtrace("Task '{$delete}' not found.");
        exit(1);
    }

    $DB->delete_records('task_adhoc', array(
        'id' => $delete
    ));

    mtrace("Task '{$delete}' deleted.");
}