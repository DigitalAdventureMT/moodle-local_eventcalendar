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
 * Test data generator for local_eventcalendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Test data generator for local_eventcalendar.
 *
 * Creates the minimum valid data (icon set, calendar, days) with sensible
 * defaults that can be overridden. The default calendar is visible: not
 * manually hidden, started yesterday, with the hide date in thirty days.
 * Dates are in UTC.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_eventcalendar_generator extends component_generator_base {
    /** @var int Counter for unique names and identifiers. */
    protected $count = 0;

    /**
     * Creates an icon set (required by every calendar).
     *
     * @param array $record Fields to override.
     * @return stdClass
     */
    public function create_iconset(array $record = []): stdClass {
        global $DB;

        $this->count++;
        $record = (object) array_merge([
            'name' => 'Icon set ' . $this->count,
            'source' => 'custom',
            'identifier' => 'testset' . $this->count,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);

        $record->id = $DB->insert_record('local_eventcalendar_icons', $record);

        return $record;
    }

    /**
     * Creates a visible calendar without days.
     *
     * @param array $record Fields to override.
     * @return calendar
     */
    public function create_calendar(array $record = []): calendar {
        $this->count++;
        $today = self::utc_midnight(time());

        $data = array_merge([
            'name' => 'Calendar ' . $this->count,
            'startdate' => $today - DAYSECS,
            'enddate' => $today + 10 * DAYSECS,
            'enddisplaydate' => $today + 30 * DAYSECS,
            'forcehide' => 0,
            'progressiveunlock' => 1,
            'restdayenabled' => 0,
        ], $record);

        if (empty($data['iconsetid'])) {
            $data['iconsetid'] = $this->create_iconset()->id;
        }

        $calendar = new calendar(0, (object) $data);
        $calendar->create();

        return $calendar;
    }

    /**
     * Creates a day of a calendar.
     *
     * @param array $record Must contain calendarid; daynumber defaults to the next one.
     * @return day
     */
    public function create_day(array $record): day {
        global $DB;

        if (empty($record['daynumber'])) {
            $record['daynumber'] = 1 + (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(daynumber), 0) FROM {local_eventcalendar_days} WHERE calendarid = ?',
                [$record['calendarid']]
            );
        }

        $data = array_merge([
            'opendate' => self::utc_midnight(time()),
            'notiftitle' => 'Day title ' . $record['daynumber'],
            'notifbody' => '<p>Day body ' . $record['daynumber'] . '</p>',
            'content' => '<p>Day content ' . $record['daynumber'] . '</p>',
        ], $record);

        $day = new day(0, (object) $data);
        $day->create();

        return $day;
    }

    /**
     * Creates an access rule of a calendar, written directly in the database so that rules no longer
     * valid (for example pointing to a deleted cohort) can also be created.
     *
     * The rule is added to the group with the given number (1 by default), created if missing. For
     * role and cohort rules the subject can also be the role shortname or the cohort idnumber.
     *
     * @param array $record calendarid, ruletype, subject, comparison (default 'is'), value, group
     * @return stdClass the rule record
     */
    public function create_access_rule(array $record): stdClass {
        global $DB, $USER;

        $calendarid = (int) $record['calendarid'];
        $groupnumber = (int) ($record['group'] ?? 1);
        $subject = (string) $record['subject'];

        if ($record['ruletype'] === 'role' && !ctype_digit($subject)) {
            $subject = (string) $DB->get_field('role', 'id', ['shortname' => $subject], MUST_EXIST);
        } else if ($record['ruletype'] === 'cohort' && !ctype_digit($subject)) {
            $subject = (string) $DB->get_field('cohort', 'id', ['idnumber' => $subject], MUST_EXIST);
        }

        $now = time();
        $group = $DB->get_record('local_eventcalendar_rulegrp', ['calendarid' => $calendarid, 'sortorder' => $groupnumber]);
        if (!$group) {
            $group = (object) [
                'calendarid' => $calendarid,
                'sortorder' => $groupnumber,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $USER->id,
            ];
            $group->id = $DB->insert_record('local_eventcalendar_rulegrp', $group);
        }

        $rule = (object) [
            'groupid' => $group->id,
            'calendarid' => $calendarid,
            'ruletype' => $record['ruletype'],
            'subject' => $subject,
            'comparison' => $record['comparison'] ?? 'is',
            'value' => $record['value'] ?? '',
            'sortorder' => $DB->count_records('local_eventcalendar_rule', ['groupid' => $group->id]) + 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $USER->id,
        ];
        $rule->id = $DB->insert_record('local_eventcalendar_rule', $rule);

        \local_eventcalendar\local\access_rules::reset_cache();

        return $rule;
    }

    /**
     * Records that a user opened a day of a calendar, as the day page does at the first opening.
     *
     * @param array $record calendarid, userid, daynumber (default 1), timeopened (default now)
     * @return stdClass the log record
     */
    public function create_day_opening(array $record): stdClass {
        global $DB;

        $calendarid = (int) $record['calendarid'];
        $dayid = (int) $DB->get_field(
            'local_eventcalendar_days',
            'id',
            ['calendarid' => $calendarid, 'daynumber' => (int) ($record['daynumber'] ?? 1)],
            MUST_EXIST
        );

        $log = (object) [
            'calendarid' => $calendarid,
            'dayid' => $dayid,
            'userid' => (int) $record['userid'],
            'timeopened' => (int) ($record['timeopened'] ?? time()),
        ];
        $log->id = $DB->insert_record('local_eventcalendar_log', $log);

        return $log;
    }

    /**
     * Returns the UTC midnight of the day containing the timestamp.
     *
     * @param int $timestamp
     * @return int
     */
    public static function utc_midnight(int $timestamp): int {
        return $timestamp - ($timestamp % DAYSECS);
    }
}
