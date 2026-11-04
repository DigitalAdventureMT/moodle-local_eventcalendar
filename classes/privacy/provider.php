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
 * Privacy API implementation: personal data stored by the plugin, and how it is exported and deleted.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation of the plugin (GDPR).
 *
 * Personal data is stored in two categories, all in the system context:
 * 1. Usage data of each user: first opening of each day (log), notification subscriptions
 *    (subscr) and received day notifications (sent). Exported per calendar and fully deleted
 *    on request.
 * 2. Author of configuration items (usercreated/usermodified fields). Exported as the list of
 *    items created or modified by the user. On deletion the items are kept, since they are site
 *    configuration, and anonymized by clearing the user reference.
 *
 * Messages are sent through the Message API and events are stored by the log stores, so both are
 * covered by the privacy providers of their own components. Access rules refer to roles, cohorts,
 * courses, badges and profile fields, not to individual people, so they hold no personal data.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** Tables with usage data of each user (userid field). */
    protected const USAGE_TABLES = [
        'local_eventcalendar_log' => ['calendarid', 'dayid', 'userid', 'timeopened'],
        'local_eventcalendar_subscr' => ['calendarid', 'userid', 'timesubscribed'],
        'local_eventcalendar_sent' => ['calendarid', 'dayid', 'userid', 'timesent', 'attempts'],
    ];

    /**
     * Configuration tables with an author: table => fields holding the user id, and the field
     * used to describe the item in the export.
     */
    protected const AUTHORED_TABLES = [
        'local_eventcalendar_calendar' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'name'],
        'local_eventcalendar_days' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'daynumber'],
        'local_eventcalendar_notifset' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'name'],
        'local_eventcalendar_notif' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'kind'],
        'local_eventcalendar_icons' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'name'],
        'local_eventcalendar_iconitem' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'filename'],
        'local_eventcalendar_emailtpl' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'name'],
        'local_eventcalendar_banner' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'name'],
        'local_eventcalendar_headfoot' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'identifier'],
        'local_eventcalendar_datelook' => ['fields' => ['usercreated', 'usermodified'], 'label' => 'templatekey'],
        'local_eventcalendar_rulegrp' => ['fields' => ['usermodified'], 'label' => 'calendarid'],
        'local_eventcalendar_rule' => ['fields' => ['usermodified'], 'label' => 'ruletype'],
    ];

    /**
     * Describes the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::USAGE_TABLES as $table => $fields) {
            $fieldstrings = [];
            foreach ($fields as $field) {
                $fieldstrings[$field] = 'privacy:metadata:' . $table . ':' . $field;
            }
            $collection->add_database_table($table, $fieldstrings, 'privacy:metadata:' . $table);
        }

        foreach (self::AUTHORED_TABLES as $table => $info) {
            $fieldstrings = [];
            foreach ($info['fields'] as $field) {
                $fieldstrings[$field] = 'privacy:metadata:' . $field;
            }
            $collection->add_database_table($table, $fieldstrings, 'privacy:metadata:' . $table);
        }

        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');

        return $collection;
    }

    /**
     * Contexts with user data: only the system context, if the user appears in any table.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Users with data in the given context (system context only).
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        foreach (array_keys(self::USAGE_TABLES) as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}}", []);
        }

        foreach (self::AUTHORED_TABLES as $table => $info) {
            foreach ($info['fields'] as $field) {
                $userlist->add_from_sql($field, "SELECT $field FROM {{$table}} WHERE $field > 0", []);
            }
        }
    }

    /**
     * Exports the user data.
     *
     * Under the plugin folder there is one folder per calendar with usage data (subscription,
     * opened days, received notifications) and one folder with the configuration items created or
     * modified by the user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        if (!self::has_system_context($contextlist)) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $context = \context_system::instance();
        $root = get_string('pluginname', 'local_eventcalendar');

        foreach (self::get_usage_by_calendar($userid) as $calendarid => $data) {
            $subcontext = [$root, self::calendar_folder_name($calendarid)];
            writer::with_context($context)->export_data($subcontext, (object) $data);
        }

        $authored = self::get_authored_items($userid);
        if (!empty($authored)) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:authoreditems', 'local_eventcalendar')],
                (object) ['items' => $authored]
            );
        }
    }

    /**
     * Deletes the data of all users in the context: all usage data; configuration is kept, anonymized.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        foreach (array_keys(self::USAGE_TABLES) as $table) {
            $DB->delete_records($table);
        }

        foreach (self::AUTHORED_TABLES as $table => $info) {
            foreach ($info['fields'] as $field) {
                $DB->set_field_select($table, $field, 0, "$field > 0");
            }
        }
    }

    /**
     * Deletes the data of a user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (!self::has_system_context($contextlist)) {
            return;
        }

        self::delete_users([(int) $contextlist->get_user()->id]);
    }

    /**
     * Deletes the data of several users in the given context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }

        self::delete_users(array_map('intval', $userlist->get_userids()));
    }

    /**
     * Deletes usage data of the users and anonymizes the configuration items they authored.
     *
     * @param int[] $userids
     * @return void
     */
    protected static function delete_users(array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        foreach (array_keys(self::USAGE_TABLES) as $table) {
            $DB->delete_records_select($table, "userid $insql", $params);
        }

        foreach (self::AUTHORED_TABLES as $table => $info) {
            foreach ($info['fields'] as $field) {
                $DB->set_field_select($table, $field, 0, "$field $insql", $params);
            }
        }
    }

    /**
     * Whether the user appears in at least one plugin table.
     *
     * @param int $userid
     * @return bool
     */
    protected static function user_has_data(int $userid): bool {
        global $DB;

        foreach (array_keys(self::USAGE_TABLES) as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }

        foreach (self::AUTHORED_TABLES as $table => $info) {
            foreach ($info['fields'] as $field) {
                if ($DB->record_exists($table, [$field => $userid])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Usage data of the user, grouped by calendar.
     *
     * @param int $userid
     * @return array [calendarid => ['subscription' => ..., 'openeddays' => [...], 'receivednotifications' => [...]]]
     */
    protected static function get_usage_by_calendar(int $userid): array {
        global $DB;

        $data = [];
        $daynumbers = [];

        $daynumber = function (int $dayid) use ($DB, &$daynumbers) {
            if (!array_key_exists($dayid, $daynumbers)) {
                $daynumbers[$dayid] = $DB->get_field('local_eventcalendar_days', 'daynumber', ['id' => $dayid]);
            }
            return $daynumbers[$dayid] === false ? null : (int) $daynumbers[$dayid];
        };

        foreach ($DB->get_records('local_eventcalendar_subscr', ['userid' => $userid]) as $row) {
            $data[(int) $row->calendarid]['subscription'] = [
                'timesubscribed' => transform::datetime($row->timesubscribed),
            ];
        }

        foreach ($DB->get_records('local_eventcalendar_log', ['userid' => $userid], 'timeopened') as $row) {
            $data[(int) $row->calendarid]['openeddays'][] = [
                'daynumber' => $daynumber((int) $row->dayid),
                'timeopened' => transform::datetime($row->timeopened),
            ];
        }

        foreach ($DB->get_records('local_eventcalendar_sent', ['userid' => $userid], 'timesent') as $row) {
            $data[(int) $row->calendarid]['receivednotifications'][] = [
                'daynumber' => $daynumber((int) $row->dayid),
                'timesent' => $row->timesent === null
                    ? get_string('privacy:notsent', 'local_eventcalendar')
                    : transform::datetime($row->timesent),
                'attempts' => (int) $row->attempts,
            ];
        }

        return $data;
    }

    /**
     * Configuration items created or modified by the user.
     *
     * @param int $userid
     * @return array
     */
    protected static function get_authored_items(int $userid): array {
        global $DB;

        $items = [];

        foreach (self::AUTHORED_TABLES as $table => $info) {
            foreach ($info['fields'] as $field) {
                $records = $DB->get_records(
                    $table,
                    [$field => $userid],
                    'id',
                    'id, ' . $info['label'] . ', timecreated, timemodified'
                );

                foreach ($records as $record) {
                    $items[] = [
                        'table' => $table,
                        'id' => (int) $record->id,
                        'description' => (string) $record->{$info['label']},
                        'role' => get_string('privacy:' . $field, 'local_eventcalendar'),
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Export folder name of a calendar: name and id (the name may not be unique); only the id if
     * the calendar no longer exists.
     *
     * @param int $calendarid
     * @return string
     */
    protected static function calendar_folder_name(int $calendarid): string {
        global $DB;

        $name = $DB->get_field('local_eventcalendar_calendar', 'name', ['id' => $calendarid]);
        $name = $name === false ? get_string('privacy:calendarid', 'local_eventcalendar') : format_string($name);

        return $name . ' (' . $calendarid . ')';
    }

    /**
     * Whether the approved list contains the system context.
     *
     * @param approved_contextlist $contextlist
     * @return bool
     */
    protected static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                return true;
            }
        }
        return false;
    }
}
