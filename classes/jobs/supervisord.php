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
 * Beanstalk jobs.
 *
 * @package    tool_adhoc
 * @author     Skylar Kelty <S.Kelty@kent.ac.uk>
 * @copyright  2016 University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_adhoc\jobs;

use Supervisor\Supervisor;
use Supervisor\Connector\XmlRpc;
use fXmlRpc\Client;

/**
 * Supervisord tasks.
 */
class supervisord
{
    /**
     * Restart supervisord.
     */
    public function restart() {
        $client = new Client(
            'unix:///var/run/supervisor/supervisor.sock'
        );
        $connector = new XmlRpc($client);

        $supervisor = new Supervisor($connector);
        $supervisor->restart();

        return true;
    }
}
