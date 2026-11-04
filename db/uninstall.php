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
 * Uninstall routine of local_eventcalendar.
 *
 * Tables from install.xml and capabilities from db/access.php are removed by Moodle core.
 * This file handles what core does not remove: the custom roles created by db/install.php
 * and the files stored in the shared files table under the plugin component.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstalls the plugin.
 *
 * Deletes the custom roles (if still present) and all files stored under the plugin component.
 */
function xmldb_local_eventcalendar_uninstall() {
    global $DB;

    $roleshortnames = [
        'eventcalendarcoordinator',
        'eventcalendarcurator',
    ];

    foreach ($roleshortnames as $shortname) {
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);

        if ($roleid) {
            // Removes the role definition, its assignments and its capabilities.
            delete_role($roleid);
        }
    }

    // Delete every file area of the plugin: the files table is shared and not managed by install.xml,
    // so leftover records and files in moodledata would otherwise remain.
    $systemcontext = context_system::instance();
    $fs = get_file_storage();

    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'iconitem');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'content');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'dayicon');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'notificationmessage');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'logo');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'banner');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'headerimage');
    $fs->delete_area_files($systemcontext->id, 'local_eventcalendar', 'footerimage');
}
