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
 * Database upgrade steps for local_eventcalendar.
 *
 * The first public release (2.0.0) has no upgrade steps: its schema is the one defined in db/install.xml.
 * The steps of the following releases are added here, each in its own "if ($oldversion < ...)" block
 * closed by upgrade_plugin_savepoint().
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the upgrade steps from the installed version to the current one.
 *
 * @param int $oldversion Version installed before the upgrade.
 * @return bool
 */
function xmldb_local_eventcalendar_upgrade($oldversion) {
    return true;
}
