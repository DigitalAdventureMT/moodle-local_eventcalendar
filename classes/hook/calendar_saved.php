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
 * Hook dispatched after a calendar has been saved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;

/**
 * Hook dispatched after a calendar has been saved.
 *
 * It is dispatched by calendar_manager::save() when the calendar (created or updated), its days and its
 * files are already saved, so the calendar has its final id also for a new one. Callbacks use it to save the
 * data they added to the calendar form (records and files): they read the submitted values with get_data(),
 * the same object returned by the form. They do not return a value and must not change the calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to save their own data and files when an event calendar is saved.')]
#[\core\attribute\tags('calendar')]
final class calendar_saved {
    /**
     * Creates the hook.
     *
     * @param calendar $calendar The calendar that has been saved.
     * @param \stdClass $data Data submitted with the calendar form.
     * @param bool $isnew True if the calendar has just been created, false if it has been updated.
     */
    public function __construct(
        /** @var calendar The calendar that has been saved. */
        private readonly calendar $calendar,
        /** @var \stdClass Data submitted with the calendar form. */
        private readonly \stdClass $data,
        /** @var bool True if the calendar has just been created. */
        private readonly bool $isnew,
    ) {
    }

    /**
     * Returns the calendar that has been saved.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the data submitted with the calendar form.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        return $this->data;
    }

    /**
     * Tells whether the calendar has just been created.
     *
     * @return bool
     */
    public function is_new(): bool {
        return $this->isnew;
    }
}
