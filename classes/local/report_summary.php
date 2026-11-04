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
 * Figures of the calendar reports page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Figures of the calendar reports page (report.php): counts only, no data of single users.
 *
 * Each figure is a subquery, so that a single query returns all the figures of all calendars:
 * - subscribers: current subscriptions to the notifications;
 * - visitors: users who opened at least one day;
 * - openings: days opened, counting each user once per day;
 * - sent: notifications sent;
 * - notsent: notifications not sent, because of failures or invalid email addresses;
 * - totaldays and unlockeddays: days of the calendar, and days whose date has come (server time).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_summary {
    /**
     * Figures of all calendars, the most recent first, or of one calendar.
     *
     * @param int $calendarid only this calendar, 0 for all
     * @return \stdClass[] records with id, name, startdate, subscribers, visitors, openings, sent, notsent,
     *                     totaldays, unlockeddays
     */
    public static function get_calendars(int $calendarid = 0): array {
        global $DB;

        $where = $calendarid ? 'WHERE c.id = :calendarid' : '';
        $params = ['now' => time()];
        if ($calendarid) {
            $params['calendarid'] = $calendarid;
        }

        $sql = "SELECT c.id, c.name, c.startdate,
                       (SELECT COUNT(1) FROM {local_eventcalendar_subscr} s WHERE s.calendarid = c.id) AS subscribers,
                       (SELECT COUNT(DISTINCT l.userid) FROM {local_eventcalendar_log} l WHERE l.calendarid = c.id) AS visitors,
                       (SELECT COUNT(1) FROM {local_eventcalendar_log} l WHERE l.calendarid = c.id) AS openings,
                       (SELECT COUNT(1) FROM {local_eventcalendar_sent} n
                         WHERE n.calendarid = c.id AND n.timesent IS NOT NULL) AS sent,
                       (SELECT COUNT(1) FROM {local_eventcalendar_sent} n
                         WHERE n.calendarid = c.id AND n.timesent IS NULL) AS notsent,
                       (SELECT COUNT(1) FROM {local_eventcalendar_days} d WHERE d.calendarid = c.id) AS totaldays,
                       (SELECT COUNT(1) FROM {local_eventcalendar_days} d
                         WHERE d.calendarid = c.id AND d.opendate <= :now) AS unlockeddays
                  FROM {local_eventcalendar_calendar} c
                $where
              ORDER BY c.startdate DESC, c.id DESC";

        return array_values($DB->get_records_sql($sql, $params));
    }
}
