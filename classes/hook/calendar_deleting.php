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
 * Hook dispatched before a calendar is deleted.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

/**
 * Hook dispatched before a calendar is deleted.
 *
 * It is dispatched by calendar_manager::delete() before anything is removed: the calendar, its days and its
 * files still exist, so a callback can read them. Callbacks use it to delete the data they keep for the
 * calendar (records and files). They do not return a value and must not delete the calendar itself.
 *
 * The days of the calendar are deleted afterwards, and announced by the days_deleting hook.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to delete their own data and files when an event calendar is deleted.')]
#[\core\attribute\tags('calendar')]
final class calendar_deleting {
    /**
     * Creates the hook.
     *
     * @param int $calendarid Id of the calendar being deleted.
     */
    public function __construct(
        /** @var int Id of the calendar being deleted. */
        private readonly int $calendarid,
    ) {
    }

    /**
     * Returns the id of the calendar being deleted.
     *
     * @return int
     */
    public function get_calendarid(): int {
        return $this->calendarid;
    }
}
