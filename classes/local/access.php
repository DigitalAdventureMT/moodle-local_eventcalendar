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
 * Single access rule for managing the content of the days.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Single access rule for managing the content of the days.
 *
 * Content can be managed by users with local/eventcalendar:managecontent or
 * local/eventcalendar:managecalendars. The rule is defined here because it applies to several pages;
 * operations on the calendars themselves are checked directly with local/eventcalendar:managecalendars.
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
}
