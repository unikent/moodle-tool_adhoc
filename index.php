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
 * This page displays adhoc tasks and allows basic management.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('adhoctaskmanagerreport');

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
        $olduser = $USER;
        $redirecturl = new \moodle_url('/admin/tool/adhoc/index.php');
        $redirectmessage = '';

        $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');

        // If the task is supposed to block cron, do it.
        if ($task->is_blocking()) {
            if ($cronlock = $cronlockfactory->get_lock('core_cron', 10)) {
                $task->set_cron_lock($cronlock);
            } else {
                redirect($redirecturl, get_string('error_cron_lock', 'tool_adhoc'), 2);
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

                $redirectmessage = get_string('task_complete', 'tool_adhoc') . ' ' . $messages;
            } catch (\Exception $e) {
                \core\task\manager::adhoc_task_failed($task);
                throw $e;
            }
        } else {
            $redirectmessage = get_string('error_task_lock', 'tool_adhoc');
        }

        if ($USER->id !== $olduser->id) {
            $olduser = $DB->get_record('user', array('id' => $olduser->id));
            \core\session\manager::set_user($olduser);
        }

        redirect($redirecturl, $redirectmessage);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_adhoc'));

$tasks = $DB->get_records('task_adhoc');

if (empty($tasks)) {
    echo \html_writer::tag('p', get_string('notasks', 'tool_adhoc'));
} else {
    $columns = array(
        'id' => get_string('id', 'tool_adhoc'),
        'component' => get_string('component', 'tool_task'),
        'delete' => get_string('delete'),
        'run' => get_string('run', 'tool_adhoc'),
        'name' => get_string('name'),
        'nextruntime' => get_string('nextruntime', 'tool_task'),
        'faildelay' => get_string('faildelay', 'tool_task'),
        'customdata' => get_string('customdata', 'tool_adhoc'),
        'blocking' => get_string('blocking', 'tool_adhoc')
    );

    $table = new \flexible_table('adhoctaskinfo');
    $table->set_attribute('class', 'table flexible');
    $table->define_columns(array_keys($columns));
    $table->define_headers(array_values($columns));
    $table->define_baseurl($PAGE->url);
    $table->setup();
    foreach ($tasks as $task) {
        $configureurl = new moodle_url('/admin/tool/adhoc/index.php', array(
            'action' => 'delete',
            'task' => $task->id,
            'sesskey' => sesskey()
        ));

        $editlink = $OUTPUT->action_icon($configureurl, new pix_icon('t/delete', get_string('deletetask', 'tool_adhoc')));

        $runurl = new moodle_url('/admin/tool/adhoc/index.php', array(
            'action' => 'run',
            'task' => $task->id,
            'sesskey' => sesskey()
        ));

        $runlink = $OUTPUT->action_icon($runurl, new pix_icon('t/go', get_string('runtask', 'tool_adhoc')));

        $component = $task->component;
        list($type, $plugin) = core_component::normalize_component($component);
        if ($type === 'core') {
            $componentcell = get_string('corecomponent', 'tool_task');
        } else {
            if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
                $plugininfo->init_display_name();
                $componentcell = $plugininfo->displayname;
            } else {
                $componentcell = $component;
            }
        }

        $table->add_data(array(
            $task->id,
            $componentcell,
            $editlink,
            $runlink,
            $task->classname,
            $task->nextruntime,
            $task->faildelay,
            $task->customdata,
            $task->blocking
        ));
    }

    $table->finish_output();
}

echo $OUTPUT->footer();
