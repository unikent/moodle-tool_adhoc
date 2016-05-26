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
 * Run an adhoc task.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
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
    array('help' => false, 'data' => false, 'class' => false),
    array('h' => 'help')
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || (!$options['data'] && !$options['class'])) {
    $help = "Adhoc cron tasks.

Options:
--class=classname  Run an adhoc task of this classname
--data=JSON        JSON data
-h, --help         Print out this help

Example:
\$/usr/bin/php admin/tool/adhoc/cli/run.php --class=\classname\task\adhoc

";

    cli_write($help);
    die;
}

// Increase memory limit.
raise_memory_limit(MEMORY_EXTRA);

// Emulate normal session - we use admin account by default.
cron_setup_user();

// Run all tasks.
$class = $options['class'];
$obj = new $class();
if (!empty($options['data'])) {
    $obj->set_custom_data(json_decode($options['data']));
}
$obj->execute();
