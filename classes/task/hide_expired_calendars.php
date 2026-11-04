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
 * Scheduled task: permanently hides expired calendars.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\task;

use local_eventcalendar\persistent\calendar;

/**
 * Scheduled task: permanently hides expired calendars.
 *
 * The effective visibility is already computed at runtime by calendar::get_visibility_status(), so
 * this task does not make the calendar invisible. It sets the manual "forcehide" flag, so an expired
 * calendar does not become visible again by mistake (for example by moving the end date forward)
 * and stays hidden until a manager reactivates it on purpose.
 *
 * It runs once the end date is over in all timezones: the westernmost one (UTC-12) reaches the end
 * of the date 12 hours after UTC, so the task waits 24 hours after the end of the date in UTC.
 * Frequency is defined in db/tasks.php.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hide_expired_calendars extends \core\task\scheduled_task {
    /**
     * Returns the name of the scheduled task.
     *
     * @return string Name shown in the list of scheduled tasks.
     */
    public function get_name(): string {
        return get_string('taskhideexpired', 'local_eventcalendar');
    }

    /**
     * Sets forcehide on the calendars whose end date has passed in every timezone.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $now = time();

        // Wide SQL preselection (end date already past, calendar not yet hidden by hand); the exact
        // threshold, which depends on the UTC date, is computed in PHP on the few candidates.
        $candidates = calendar::get_records_select(
            'forcehide = 0 AND enddisplaydate > 0 AND enddisplaydate < :now',
            ['now' => $now]
        );

        $hidden = 0;

        foreach ($candidates as $calendar) {
            // End of the enddisplaydate date in UTC (as in calendar::get_local_expiry()), plus 24 hours.
            $refdate = usergetdate((int) $calendar->get('enddisplaydate'), 'UTC');
            $dayend = make_timestamp($refdate['year'], $refdate['mon'], $refdate['mday'] + 1, 0, 0, 0, 'UTC');

            if ($now < $dayend + DAYSECS) {
                continue;
            }

            // Update only the field directly, without calendar::update(): the persistent update would
            // revalidate every property, and an invalid reference in an expired calendar would prevent
            // hiding it.
            $DB->update_record(calendar::TABLE, (object) [
                'id' => $calendar->get('id'),
                'forcehide' => 1,
                'timemodified' => $now,
            ]);

            $hidden++;
        }

        mtrace('local_eventcalendar: expired calendars hidden: ' . $hidden);
    }
}
