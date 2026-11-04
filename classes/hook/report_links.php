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
 * Hook dispatched when the link to the report of a calendar is built.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

/**
 * Hook dispatched when the link to the report of a calendar is built.
 *
 * It is dispatched by report.php for each row of the summary of the calendars, with the address of the report
 * of the calendar provided by the plugin, if any. The callbacks can replace it with set_calendar_url(), or
 * remove it with null: the name of the calendar is then shown without a link. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to set the link to the detailed report of an event calendar.')]
#[\core\attribute\tags('calendar', 'report')]
final class report_links {
    /**
     * Creates the hook.
     *
     * @param int $calendarid Id of the calendar.
     * @param \moodle_url|null $calendarurl Address of the report of the calendar, null for no link.
     */
    public function __construct(
        /** @var int Id of the calendar. */
        private readonly int $calendarid,
        /** @var \moodle_url|null Address of the report of the calendar. */
        private ?\moodle_url $calendarurl,
    ) {
    }

    /**
     * Returns the id of the calendar.
     *
     * @return int
     */
    public function get_calendarid(): int {
        return $this->calendarid;
    }

    /**
     * Returns the address of the report of the calendar, null for no link.
     *
     * @return \moodle_url|null
     */
    public function get_calendar_url(): ?\moodle_url {
        return $this->calendarurl;
    }

    /**
     * Sets the address of the report of the calendar.
     *
     * @param \moodle_url|null $url The address, null for no link.
     */
    public function set_calendar_url(?\moodle_url $url): void {
        $this->calendarurl = $url;
    }
}
