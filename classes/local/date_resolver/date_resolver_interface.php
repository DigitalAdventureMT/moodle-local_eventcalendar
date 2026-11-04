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
 * Common interface of the date resolution strategies.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\date_resolver;

/**
 * Common interface of the date resolution strategies.
 *
 * Each strategy (fixed, computed, lookup_table) is a separate class implementing
 * this interface. Callers depend only on the interface; the factory picks the
 * concrete class.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface date_resolver_interface {
    /**
     * Resolves the anchor date of the event for a given year.
     *
     * Always returns a Unix timestamp at midnight (the timezone is handled by
     * make_timestamp() in the implementations).
     *
     * @param int $year Calendar year to resolve the date for.
     * @return int Unix timestamp of the resolved date.
     */
    public function resolve(int $year): int;
}
