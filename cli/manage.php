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
 * @package    tool_adhoc
 * @copyright  2015 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(__FILE__) . '/../../../../config.php');
require_once("{$CFG->libdir}/clilib.php");
require_once("{$CFG->libdir}/cronlib.php");

if (moodle_needs_upgrading()) {
    cli_error("Moodle upgrade pending, cannot execute tasks.");
}

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
\$sudo -u www-data /usr/bin/php admin/tool/adhoc/cli/run_adhoc_task.php --execute=1

";

    cli_write($help);
    die;
}

if ($options['list']) {
    cli_heading("List of adhoc tasks ({$CFG->wwwroot})");

    $shorttime = get_string('strftimedatetimeshort');

    cli_writeln(str_pad('ID', 6, ' ') . ' ' . str_pad('Class Name', 150, ' '));

    $tasks = $DB->get_records('task_adhoc');
    foreach ($tasks as $task) {
        cli_writeln(str_pad($task->id, 6, ' ') . ' ' . str_pad($task->classname, 150, ' '));
    }

    exit(0);
}

if (!empty($options['execute'])) {
    $execute = $options['execute'];

    // Increase memory limit.
    raise_memory_limit(MEMORY_EXTRA);

    // Emulate normal session - we use admin account by default.
    cron_setup_user();

    // Get the record.
    $record = $DB->get_record('task_adhoc', array(
        'id' => $execute
    ), '*', \MUST_EXIST);

    // Execute.
    \tool_adhoc\manager::run_tasks(array($record));
}

if (!empty($options['delete'])) {
    $delete = $options['delete'];

    // Get the record.
    $record = $DB->get_record('task_adhoc', array(
        'id' => $delete
    ));

    if (!$record) {
        cli_problem("Task '{$delete}' not found.");
        exit(1);
    }

    $DB->delete_records('task_adhoc', array(
        'id' => $delete
    ));

    cli_writeln("Task '{$delete}' deleted.");
}