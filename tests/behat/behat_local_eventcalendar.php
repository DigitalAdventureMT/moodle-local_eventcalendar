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
 * Behat steps of local_eventcalendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps of local_eventcalendar.
 *
 * Calendar ids are not known when a feature is written, so the viewer pages are reached through the core
 * step "I am on the ... page" and resolved here from the calendar name:
 * - I am on the "Advent" "local_eventcalendar > Calendar" page (view.php);
 * - I am on the "Advent > 3" "local_eventcalendar > Day" page (view_day.php, day number 3).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_eventcalendar extends behat_base {
    /**
     * Resolves the url of a page of a specific calendar.
     *
     * The identifier is the calendar name; for the day page it is followed by " > " and the day number.
     * The day number is not checked, so that the pages can also be tested with a day that does not exist.
     *
     * @param string $type page type: Calendar or Day
     * @param string $identifier calendar name, plus " > daynumber" for the Day page
     * @return moodle_url
     * @throws ExpectationException if the type is unknown or the identifier is malformed
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'calendar':
                return new moodle_url('/local/eventcalendar/view.php', [
                    'id' => $this->get_calendar_id($identifier),
                ]);

            case 'day':
                $parts = array_map('trim', explode('>', $identifier));
                if (count($parts) !== 2 || !ctype_digit($parts[1])) {
                    throw new ExpectationException(
                        'The identifier of a Day page must be "<calendar name> > <day number>", "' . $identifier . '" given',
                        $this->getSession()
                    );
                }
                return new moodle_url('/local/eventcalendar/view_day.php', [
                    'id' => $this->get_calendar_id($parts[0]),
                    'day' => (int) $parts[1],
                ]);

            default:
                throw new ExpectationException(
                    'Unrecognised local_eventcalendar page type "' . $type . '"',
                    $this->getSession()
                );
        }
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
