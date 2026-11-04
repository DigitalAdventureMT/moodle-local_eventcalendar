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
 * Behat data generator for local_eventcalendar.
 *
 * @package     local_eventcalendar
 * @category    test
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for local_eventcalendar.
 *
 * Entities for the step 'the following "local_eventcalendar > ..." exist':
 * - calendars: name required; the other fields have the defaults of the data generator
 *   (visible calendar, from yesterday to ten days from now);
 * - days: calendar (name); optional daynumber and opendate (unlocked today by default);
 * - access rules: calendar (name), ruletype, subject (role shortname, cohort idnumber or id),
 *   optional comparison, value and group number;
 * - day openings: calendar (name), user (username), optional daynumber (1 by default) and timeopened.
 *
 * @package     local_eventcalendar
 * @category    test
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_eventcalendar_generator extends behat_generator_base {
    /**
     * Entities that can be created by Behat steps.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'calendars' => [
                'singular' => 'calendar',
                'datagenerator' => 'calendar',
                'required' => ['name'],
            ],
            'days' => [
                'singular' => 'day',
                'datagenerator' => 'day',
                'required' => ['calendar'],
                'switchids' => ['calendar' => 'calendarid'],
            ],
            'access rules' => [
                'singular' => 'access rule',
                'datagenerator' => 'access_rule',
                'required' => ['calendar', 'ruletype', 'subject'],
                'switchids' => ['calendar' => 'calendarid'],
            ],
            'day openings' => [
                'singular' => 'day opening',
                'datagenerator' => 'day_opening',
                'required' => ['calendar', 'user'],
                'switchids' => ['calendar' => 'calendarid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Returns the id of a calendar from its name.
     *
     * @param string $name calendar name
     * @return int
     */
    protected function get_calendar_id(string $name): int {
        global $DB;

        return (int) $DB->get_field('local_eventcalendar_calendar', 'id', ['name' => $name], MUST_EXIST);
    }
}
