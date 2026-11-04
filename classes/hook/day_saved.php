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
 * Hook dispatched after a day has been saved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\day;

/**
 * Hook dispatched after a day has been saved.
 *
 * It is dispatched by day_manager::save() when the day, the files of its content and its icon are already
 * saved. Callbacks use it to save the data they added to the day form (records and files): they read the
 * submitted values with get_data(), the same object returned by the form. They do not return a value and
 * must not change the day.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to save their own data and files when a day of an event calendar is saved.')]
#[\core\attribute\tags('calendar', 'day')]
final class day_saved {
    /**
     * Creates the hook.
     *
     * @param day $day The day that has been saved.
     * @param \stdClass $data Data submitted with the day form.
     * @param bool $isrestday True if the day is a weekly rest day.
     */
    public function __construct(
        /** @var day The day that has been saved. */
        private readonly day $day,
        /** @var \stdClass Data submitted with the day form. */
        private readonly \stdClass $data,
        /** @var bool True if the day is a weekly rest day. */
        private readonly bool $isrestday,
    ) {
    }

    /**
     * Returns the day that has been saved.
     *
     * @return day
     */
    public function get_day(): day {
        return $this->day;
    }

    /**
     * Returns the data submitted with the day form.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        return $this->data;
    }

    /**
     * Tells whether the day is a weekly rest day.
     *
     * @return bool
     */
    public function is_restday(): bool {
        return $this->isrestday;
    }
}
