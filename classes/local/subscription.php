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
 * Users' subscriptions to the notifications of a calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Users' subscriptions to the notifications of a calendar.
 *
 * Opt-in: a missing record means "not subscribed". The record stores the
 * subscription time (timesubscribed), so only notifications for days unlocking
 * after that time should be sent.
 *
 * The table is a plain link table without the standard persistent fields, so
 * direct queries are used instead of a persistent class.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription {
    /** @var string Name of the link table. */
    private const TABLE = 'local_eventcalendar_subscr';

    /**
     * Checks whether the user is subscribed to the calendar notifications.
     *
     * @param int $calendarid
     * @param int $userid
     * @return bool
     */
    public static function is_subscribed(int $calendarid, int $userid): bool {
        global $DB;

        return $DB->record_exists(self::TABLE, ['calendarid' => $calendarid, 'userid' => $userid]);
    }

    /**
     * Subscribes the user.
     *
     * Idempotent: an existing subscription is left untouched, keeping its original
     * time. Parallel requests are handled by the unique index (calendarid, userid):
     * the second insert fails, which is the desired outcome and not an error.
     *
     * @param int $calendarid
     * @param int $userid
     * @return void
     */
    public static function subscribe(int $calendarid, int $userid): void {
        global $DB;

        if (self::is_subscribed($calendarid, $userid)) {
            return;
        }

        try {
            $DB->insert_record(self::TABLE, (object) [
                'calendarid' => $calendarid,
                'userid' => $userid,
                'timesubscribed' => time(),
            ]);
        } catch (\dml_write_exception $e) {
            // Inserted meanwhile by a parallel request: ignore, rethrow otherwise.
            if (!self::is_subscribed($calendarid, $userid)) {
                throw $e;
            }
        }
    }

    /**
     * Unsubscribes the user. Idempotent.
     *
     * @param int $calendarid
     * @param int $userid
     * @return void
     */
    public static function unsubscribe(int $calendarid, int $userid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['calendarid' => $calendarid, 'userid' => $userid]);
    }

    /**
     * Returns the ids of all calendars the user is subscribed to, with a single query.
     *
     * @param int $userid
     * @return int[]
     */
    public static function get_subscribed_calendarids(int $userid): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select(self::TABLE, 'calendarid', 'userid = ?', [$userid]));
    }
}
