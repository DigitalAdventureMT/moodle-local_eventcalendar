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
 * Hook dispatched before days are deleted.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

/**
 * Hook dispatched before days are deleted.
 *
 * It is dispatched by calendar_generator::delete_days() once for each call, before any day is removed, so it
 * covers every deletion of days: the whole calendar being deleted and the days left out when the dates of a
 * calendar are shortened. Callbacks use it to delete the data they keep for the days (records and files).
 * They do not return a value and must not delete the days themselves.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to delete their own data and files when days of an event calendar are deleted.')]
#[\core\attribute\tags('calendar', 'day')]
final class days_deleting {
    /**
     * Creates the hook.
     *
     * @param int[] $dayids Ids of the days being deleted.
     */
    public function __construct(
        /** @var int[] Ids of the days being deleted. */
        private readonly array $dayids,
    ) {
    }

    /**
     * Returns the ids of the days being deleted.
     *
     * @return int[]
     */
    public function get_dayids(): array {
        return $this->dayids;
    }
}
