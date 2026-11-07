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
 * Log of the first opening of each day by each user.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Log of the first opening of each day by each user.
 *
 * The log drives the visited state of the days, the report counters and the day_opened event, all of
 * which assume one row per user and day. The unique index (dayid, userid) on the table enforces it in
 * the database; this class turns that guarantee into a simple answer for the caller: true only for the
 * request that actually wrote the row, so the event is triggered exactly once even when two requests of
 * the same user arrive together (double click, prefetch, reload).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class day_opening_log {
    /** Table of the log. */
    private const TABLE = 'local_eventcalendar_log';

    /**
     * Records the first opening of a day by a user.
     *
     * The flow has two levels:
     * - a read first: after the first opening every visit takes this fast path, without writing and
     *   without raising database errors;
     * - then the insert, protected by the unique index. If a parallel request has written the same row
     *   between the read and the insert, the database refuses the duplicate: the exception is caught and
     *   the row written by the other request is accepted. Any other write error is rethrown.
     *
     * The method must not run inside a database transaction: on PostgreSQL a refused insert would abort
     * the whole transaction. The day page calls it outside any transaction.
     *
     * @param int $calendarid calendar of the day
     * @param int $dayid opened day
     * @param int $userid user who opened the day
     * @return bool true if this call wrote the row (first opening), false if it already existed
     */
    public static function record_first_opening(int $calendarid, int $dayid, int $userid): bool {
        global $DB;

        $conditions = ['dayid' => $dayid, 'userid' => $userid];
        if ($DB->record_exists(self::TABLE, $conditions)) {
            return false;
        }

        try {
            $DB->insert_record(self::TABLE, (object) [
                'calendarid' => $calendarid,
                'dayid' => $dayid,
                'userid' => $userid,
                'timeopened' => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // Written meanwhile by a parallel request: not an error, but this call is not the first opening.
            if ($DB->record_exists(self::TABLE, $conditions)) {
                return false;
            }
            throw $e;
        }

        return true;
    }
}
