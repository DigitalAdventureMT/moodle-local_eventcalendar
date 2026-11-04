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
 * Date resolution strategy based on a unified lookup table.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\date_resolver;

/**
 * Date resolution strategy based on a unified lookup table.
 *
 * Covers holidays whose date cannot be reduced to a simple Gregorian formula (lunar or lunisolar
 * calendars, e.g. Chinese New Year), reading from local_eventcalendar_datelook: one table shared by
 * all templates, filtered by templatekey and year.
 *
 * Dates entered in local_eventcalendar_datelook must be stored as UTC midnight, like the dates of the
 * calendar form and of the fixed/computed resolvers (see day::get_local_opendate()).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lookup_table implements date_resolver_interface {
    /** @var string Key of the template the date to resolve belongs to. */
    private string $templatekey;

    /**
     * Constructor.
     *
     * @param string $templatekey Template key (e.g. 'capodanno_cinese').
     */
    public function __construct(string $templatekey) {
        $this->templatekey = $templatekey;
    }

    /**
     * Returns the reference date of the event for the given year.
     *
     * @param int $year Calendar year.
     * @return int Unix timestamp (midnight UTC).
     */
    public function resolve(int $year): int {
        global $DB;

        $eventdate = $DB->get_field(
            'local_eventcalendar_datelook',
            'eventdate',
            ['templatekey' => $this->templatekey, 'eventyear' => $year]
        );

        // A missing lookup row is an operational problem (the admin has not yet filled in the date for
        // that year and template), not a programming error, so a readable message is used instead of a
        // coding_exception.
        if ($eventdate === false) {
            $errorinfo = (object) [
                'templatekey' => $this->templatekey,
                'year' => $year,
            ];

            throw new \moodle_exception(
                'errormissinglookupdate',
                'local_eventcalendar',
                '',
                $errorinfo
            );
        }

        return (int) $eventdate;
    }
}
