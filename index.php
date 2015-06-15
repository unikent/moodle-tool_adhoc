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


require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('adhoctaskmanager');

$renderer = $PAGE->get_renderer('tool_adhoc');
$action = optional_param('action', '', PARAM_ALPHA);
$task = optional_param('task', '', PARAM_INT);

if ($action == 'delete' && !empty($task)) {
    require_sesskey();

    $DB->delete_records('task_adhoc', array(
        'id' => $task
    ));

    redirect(new \moodle_url('/admin/tool/adhoc/index.php'), get_string('success'), 1);
}

if ($action == 'run' && !empty($task)) {
    require_sesskey();

    raise_memory_limit(MEMORY_EXTRA);

    // Run the task.
    $record = $DB->get_record('task_adhoc', array(
        'id' => $task
    ), '*', \MUST_EXIST);

    // Create the task.
    $task = \core\task\manager::adhoc_task_from_record($record);
    if ($task) {
        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');

        // If the task is supposed to block cron, do it.
        if ($task->is_blocking()) {
            if ($cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
                $task->set_cron_lock($cronlock);
            } else {
                redirect(new \moodle_url('/admin/tool/adhoc/index.php'), 'Could not obtain cron lock!', 2);
            }
        }

        // Try and get a lock on the task.
        if ($lock = $cronlockfactory->get_lock('adhoc_' . $record->id, 10)) {
            $task->set_lock($lock);

            // All okay! Execute.
            try {
                ob_start();
                $task->execute();
                $messages = \ob_get_clean();

                \core\task\manager::adhoc_task_complete($task);
                redirect(new \moodle_url('/admin/tool/adhoc/index.php'), 'Task complete! ' . $messages, 1);
            } catch (\Exception $e) {
                \core\task\manager::adhoc_task_failed($task);
                throw $e;
            }
        } else {
            redirect(new \moodle_url('/admin/tool/adhoc/index.php'), 'Could not obtain task lock!', 2);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_adhoc'));

echo $renderer->adhoc_tasks_table();

echo $OUTPUT->footer();