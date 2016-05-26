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
 * Queue management.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$queue = required_param('queue', PARAM_PLUGIN);

$PAGE->set_url('/admin/tool/adhoc/queues.php');
$PAGE->set_context(context_system::instance());

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$all = \core_component::get_plugin_list_with_class('queue', 'queue');
$enabled = get_config('tool_adhoc', 'enabled_queues');
if (!$enabled) {
    $enabled = array();
} else {
    $enabled = array_flip(explode(',', $enabled));
}

$return = new moodle_url('/admin/settings.php', array('section' => 'managequeues'));

$syscontext = context_system::instance();

switch ($action) {
    case 'disable':
        unset($enabled[$queue]);
        set_config('enabled_queues', implode(',', array_keys($enabled)), 'tool_adhoc');
        break;

    case 'enable':
        if (!isset($all[$queue])) {
            break;
        }
        $enabled = array_keys($enabled);
        $enabled[] = $queue;
        set_config('enabled_queues', implode(',', $enabled), 'tool_adhoc');
        break;

    case 'up':
        if (!isset($enabled[$queue])) {
            break;
        }
        $enabled = array_keys($enabled);
        $enabled = array_flip($enabled);
        $current = $enabled[$queue];
        if ($current == 0) {
            break; // Already at the top.
        }
        $enabled = array_flip($enabled);
        $enabled[$current] = $enabled[$current - 1];
        $enabled[$current - 1] = $queue;
        set_config('enabled_queues', implode(',', $enabled), 'tool_adhoc');
        break;

    case 'down':
        if (!isset($enabled[$queue])) {
            break;
        }
        $enabled = array_keys($enabled);
        $enabled = array_flip($enabled);
        $current = $enabled[$queue];
        if ($current == count($enabled) - 1) {
            break; // Already at the end.
        }
        $enabled = array_flip($enabled);
        $enabled[$current] = $enabled[$current + 1];
        $enabled[$current + 1] = $queue;
        set_config('enabled_queues', implode(',', $enabled), 'tool_adhoc');
        break;
}

redirect($return);
