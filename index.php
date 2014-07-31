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


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('adhoctaskmanager');

$renderer = $PAGE->get_renderer('local_adhoc');
$action = optional_param('action', '', PARAM_ALPHA);
$task = optional_param('task', '', PARAM_INT);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_adhoc'));

if ($action == 'delete' && !empty($task)) {
    require_sesskey();

    $DB->delete_records('task_adhoc', array(
        'id' => $task
    ));

    echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
    echo \html_writer::empty_tag('br');
}

echo $renderer->adhoc_tasks_table();

echo $OUTPUT->footer();