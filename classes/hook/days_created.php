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
 * Hook dispatched after new days of a calendar have been generated.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Hook dispatched after new days of a calendar have been generated.
 *
 * It is dispatched by calendar_generator::sync_extend() when at least one day has been created: at the creation
 * of the calendar and when its dates are extended. The days are already saved with the values assigned by the
 * plugin (texts, content, icon). get_pack() returns the content pack used for the generation, null if the
 * calendar has none; get_restdaynumbers() the numbers of the new days that are weekly rest days. Callbacks use
 * it to assign their own values to the new days. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to assign their own values to the days generated for an event calendar.')]
#[\core\attribute\tags('calendar', 'day')]
final class days_created {
    /**
     * Creates the hook.
     *
     * @param calendar $calendar The calendar.
     * @param day[] $days The days just created, by increasing day number.
     * @param \stdClass|null $pack Content pack used for the generation, null if none.
     * @param int[] $restdaynumbers Numbers of the new days that are weekly rest days.
     */
    public function __construct(
        /** @var calendar The calendar. */
        private readonly calendar $calendar,
        /** @var day[] The days just created. */
        private readonly array $days,
        /** @var \stdClass|null Content pack used for the generation. */
        private readonly ?\stdClass $pack,
        /** @var int[] Numbers of the new days that are weekly rest days. */
        private readonly array $restdaynumbers,
    ) {
    }

    /**
     * Returns the calendar.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the days just created, by increasing day number.
     *
     * @return day[]
     */
    public function get_days(): array {
        return $this->days;
    }

    /**
     * Returns the content pack used for the generation, null if none.
     *
     * @return \stdClass|null
     */
    public function get_pack(): ?\stdClass {
        return $this->pack;
    }

    /**
     * Returns the numbers of the new days that are weekly rest days.
     *
     * @return int[]
     */
    public function get_restdaynumbers(): array {
        return $this->restdaynumbers;
    }
}
