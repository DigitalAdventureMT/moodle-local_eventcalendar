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
 * closed by upgrade_plugin_savepoint(). Every step must leave the schema identical to db/install.xml.
 * After the steps, every upgrade imports the elements shipped with the plugin (shipped_elements).
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
    global $DB;

    $dbman = $DB->get_manager();

    // 2.1.0: one opening record per user and day.
    //
    // Up to 2.0.0 the index (dayid, userid) of the opening log was not unique, so two concurrent requests of the
    // same user could both log the first opening of a day. The duplicates are removed first, otherwise the unique
    // index cannot be created; then the index is recreated as unique, with the same name and fields.
    //
    // Of each group of duplicates the row with the lowest id is kept: it is the first one written, so its
    // timeopened is the real first opening. The ids to delete are read with a single query (only the extra rows,
    // normally very few) and deleted in chunks, to stay within the limits of the IN clause of every database.
    if ($oldversion < 2026110600) {
        $sql = "SELECT l.id
                  FROM {local_eventcalendar_log} l
                  JOIN (SELECT dayid, userid, MIN(id) AS keepid
                          FROM {local_eventcalendar_log}
                      GROUP BY dayid, userid
                        HAVING COUNT(1) > 1) d
                    ON d.dayid = l.dayid AND d.userid = l.userid
                 WHERE l.id <> d.keepid";
        $duplicateids = $DB->get_fieldset_sql($sql);
        foreach (array_chunk($duplicateids, 1000) as $chunk) {
            $DB->delete_records_list('local_eventcalendar_log', 'id', $chunk);
        }

        $table = new xmldb_table('local_eventcalendar_log');

        $oldindex = new xmldb_index('dayid_userid', XMLDB_INDEX_NOTUNIQUE, ['dayid', 'userid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        $newindex = new xmldb_index('dayid_userid', XMLDB_INDEX_UNIQUE, ['dayid', 'userid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026110600, 'local', 'eventcalendar');
    }

    // At every upgrade, after the version steps: import the elements shipped with the new version (icon sets,
    // email templates, banners, header/footer pairs) or with a graphics pack installed meanwhile, as at the
    // installation. It is not a version step, so it has no savepoint and runs every time: the scans only add
    // what is missing, and an error of a scan is only reported, without stopping the upgrade.
    \local_eventcalendar\local\shipped_elements::import();

    return true;
}
