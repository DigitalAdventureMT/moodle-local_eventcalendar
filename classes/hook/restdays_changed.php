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
 * Hook dispatched after the existing days have been realigned to a new weekly rest day setting.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Hook dispatched after the existing days have been realigned to a new weekly rest day setting.
 *
 * It is dispatched by calendar_generator::apply_restday_change() when at least one existing day changed its
 * state, after the plugin has updated those days. get_new_restdays() returns the days that became rest days
 * (the plugin has cleared their call to action and links), get_restored_days() those that are no longer rest
 * days (the plugin has given them a call to action from the generic pool of the pack). Callbacks use it to
 * realign the values they keep for the days. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to realign their own data when the weekly rest days of an event calendar change.')]
#[\core\attribute\tags('calendar', 'day')]
final class restdays_changed {
    /**
     * Creates the hook.
     *
     * @param calendar $calendar The calendar, already updated with the new setting.
     * @param day[] $newrestdays Days that became rest days.
     * @param day[] $restoreddays Days that are no longer rest days.
     * @param \stdClass|null $pack Content pack of the calendar, null if none.
     */
    public function __construct(
        /** @var calendar The calendar. */
        private readonly calendar $calendar,
        /** @var day[] Days that became rest days. */
        private readonly array $newrestdays,
        /** @var day[] Days that are no longer rest days. */
        private readonly array $restoreddays,
        /** @var \stdClass|null Content pack of the calendar. */
        private readonly ?\stdClass $pack,
    ) {
    }

    /**
     * Returns the calendar, already updated with the new setting.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the days that became rest days.
     *
     * @return day[]
     */
    public function get_new_restdays(): array {
        return $this->newrestdays;
    }

    /**
     * Returns the days that are no longer rest days.
     *
     * @return day[]
     */
    public function get_restored_days(): array {
        return $this->restoreddays;
    }

    /**
     * Returns the content pack of the calendar, null if none.
     *
     * @return \stdClass|null
     */
    public function get_pack(): ?\stdClass {
        return $this->pack;
    }
}
