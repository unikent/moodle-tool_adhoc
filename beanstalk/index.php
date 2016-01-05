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
 * This page displays beanstalk stats.
 *
 * @package    tool_adhoc
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('beanstalktaskstats');

echo $OUTPUT->header();
echo $OUTPUT->heading('Beanstalk information');

echo $OUTPUT->heading('Tube stats', 2);

$ms = microtime(true);
$beanstalk = new \tool_adhoc\beanstalk();

$info = $beanstalk->statsTube($beanstalk->get_tube());

$pod = new \local_kent\arbitrarypod();
foreach ($info as $k => $v) {
    $pod->$k = $v;
}

$table = $pod->get_flexible_table();
$table->finish_output();

echo $OUTPUT->heading('Beanstalk stats', 2);

$beanstalk = new \tool_adhoc\beanstalk();
$info = $beanstalk->stats();

$pod = new \local_kent\arbitrarypod();
foreach ($info as $k => $v) {
    $pod->$k = $v;
}

$table = $pod->get_flexible_table();
$table->finish_output();

echo $OUTPUT->footer();