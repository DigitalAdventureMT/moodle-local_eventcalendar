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
 * Access rules shared by several pages: content management and the refusal of a calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Access rules shared by several pages: content management and the refusal of a calendar.
 *
 * Content can be managed by users with local/eventcalendar:managecontent or
 * local/eventcalendar:managecalendars. The rule is defined here because it applies to several pages;
 * operations on the calendars themselves are checked directly with local/eventcalendar:managecalendars.
 *
 * The class also owns the single refusal page of the viewer pages (view.php, view_day.php), so that
 * a missing calendar, a missing day and a calendar the user cannot reach all look the same.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Tells whether the current user can manage the content of the days.
     *
     * @return bool
     */
    public static function can_manage_content(): bool {
        return has_any_capability([
            'local/eventcalendar:managecalendars',
            'local/eventcalendar:managecontent',
        ], \context_system::instance());
    }

    /**
     * Same as require_capability() for the "content or calendars" rule.
     *
     * Moodle has no "any" variant of require_capability(), so the same exception is thrown; the
     * capability named in the message is managecontent.
     *
     * @return void
     * @throws \required_capability_exception If the user has neither capability.
     */
    public static function require_manage_content(): void {
        if (!self::can_manage_content()) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/eventcalendar:managecontent',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Prints the generic "calendar not available" page and stops the script.
     *
     * Used by the viewer pages for users who cannot manage content, whenever the requested calendar
     * or day is missing, hidden, outside its dates or restricted by the access rules. The output is
     * identical in all these cases and never contains data of the calendar (the title is the plugin
     * name), so the page cannot be used to read the name of a hidden calendar or to probe which
     * calendar ids and day numbers exist.
     *
     * The caller must have already set the page url, context and layout, using only the request
     * parameters: this method sets the title and prints the page, nothing else.
     *
     * @return never
     */
    public static function deny_calendar_view(): never {
        global $PAGE, $OUTPUT;

        $PAGE->set_title(get_string('pluginname', 'local_eventcalendar'));

        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('calendarnotavailable', 'local_eventcalendar'), 'error');
        echo $OUTPUT->footer();
        exit;
    }
}
