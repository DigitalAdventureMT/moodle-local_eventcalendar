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
 * Hook dispatched when the list of calendars is shown.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\local\hook_row_actions;
use local_eventcalendar\persistent\calendar;

/**
 * Hook dispatched when the list of calendars is shown.
 *
 * The page dispatches it before printing the list, also when the list is empty, so the callbacks can
 * contribute row actions and a block under the table. The items shown are available with
 * get_calendars(). The HTML added by the callbacks must be already escaped. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add actions to the list of calendars.')]
#[\core\attribute\tags('calendar', 'list')]
final class calendar_list {
    use hook_row_actions;

    /** @var string[] HTML blocks shown under the table. */
    private array $footers = [];

    /**
     * Creates the hook.
     *
     * @param calendar[] $calendars The calendars shown in the list.
     */
    public function __construct(
        /** @var calendar[] The calendars shown in the list. */
        private readonly array $calendars,
    ) {
    }

    /**
     * Returns the calendars shown in the list.
     *
     * @return calendar[]
     */
    public function get_calendars(): array {
        return $this->calendars;
    }

    /**
     * Adds a block of HTML under the table.
     *
     * @param string $html HTML of the block, already escaped.
     */
    public function add_footer(string $html): void {
        $this->footers[] = $html;
    }

    /**
     * Returns the blocks of HTML added under the table.
     *
     * @return string[] HTML of the blocks, in the order they were added.
     */
    public function get_footers(): array {
        return $this->footers;
    }
}
