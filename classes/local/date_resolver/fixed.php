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
 * Date resolver for holidays on a fixed day and month.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\date_resolver;

/**
 * Date resolver for holidays on a fixed day and month.
 *
 * Covers dates that need no calculation, such as 25 December or 1 January.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fixed implements date_resolver_interface {
    /** @var int Month (1-12). */
    private int $month;

    /** @var int Day (1-31), validated against the month in the constructor. */
    private int $day;

    /**
     * Constructor.
     *
     * @param int $month Fixed month (1-12).
     * @param int $day Fixed day (1-31).
     * @throws \invalid_parameter_exception If month and day do not form a valid date.
     */
    public function __construct(int $month, int $day) {
        // A leap reference year (2000) is used so that 29 February is accepted.
        if (!checkdate($month, $day, 2000)) {
            throw new \invalid_parameter_exception(
                "Invalid month/day combination for a fixed date: {$month}/{$day}"
            );
        }

        $this->month = $month;
        $this->day = $day;
    }

    /**
     * Returns the event date for the given year.
     *
     * @param int $year Calendar year.
     * @return int Unix timestamp (midnight UTC).
     */
    public function resolve(int $year): int {
        // UTC is explicit so the result does not depend on the timezone of the user generating it.
        return make_timestamp($year, $this->month, $this->day, 0, 0, 0, 'UTC');
    }
}
