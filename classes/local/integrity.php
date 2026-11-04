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
 * Referential integrity checks.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Referential integrity checks.
 *
 * The tables of the plugin declare no foreign keys, and the database does not enforce any: a record can
 * reference another one (for example a calendar references a notification set) without any constraint. This
 * class holds the checks and the clean-ups that keep those references consistent, so that every page and
 * every service behaves in the same way. The cascade deletion of a calendar is in calendar_manager.
 *
 * Icon sets in use cannot be deleted. Notification sets and email templates in use can: the references are
 * removed (release_*) and the pages first show the list of the calendars involved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integrity {
    /**
     * Returns the names of the calendars that use an icon set.
     *
     * An empty array means the set is not in use. A set in use can be deleted, after warning the user with
     * this list: see release_iconset().
     *
     * @param int $iconsetid
     * @return string[] Calendar names, by calendar id, ordered by name.
     */
    public static function get_calendar_names_using_iconset(int $iconsetid): array {
        global $DB;

        return $DB->get_records_menu('local_eventcalendar_calendar', ['iconsetid' => $iconsetid], 'name ASC', 'id, name');
    }

    /**
     * Removes the reference to an icon set from all the calendars that use it.
     *
     * The column cannot be null, so the calendars get 0, which means no set. The days already generated keep
     * their icon, because it is a copy (dayicon area); the days generated later have no icon. The calendar
     * form asks for a new set before the calendar can be saved again.
     *
     * @param int $iconsetid
     */
    public static function release_iconset(int $iconsetid): void {
        global $DB;

        $DB->set_field('local_eventcalendar_calendar', 'iconsetid', 0, ['iconsetid' => $iconsetid]);
    }

    /**
     * Returns the names of the calendars that use a notification set.
     *
     * An empty array means the set is not in use. A set in use can be deleted, after warning the user with
     * this list: see release_notificationset().
     *
     * @param int $setid
     * @return string[] Calendar names, by calendar id, ordered by name.
     */
    public static function get_calendar_names_using_notificationset(int $setid): array {
        global $DB;

        return $DB->get_records_menu('local_eventcalendar_calendar', ['notificationsetid' => $setid], 'name ASC', 'id, name');
    }

    /**
     * Removes the reference to a notification set from all the calendars that use it.
     *
     * The days already generated are not touched, because they hold a copy of the texts. The calendars have
     * no notification set afterwards, so the days generated later will have empty titles and bodies.
     *
     * @param int $setid
     */
    public static function release_notificationset(int $setid): void {
        global $DB;

        $DB->set_field('local_eventcalendar_calendar', 'notificationsetid', null, ['notificationsetid' => $setid]);
    }

    /**
     * Returns the names of the calendars that use an email template.
     *
     * An empty array means the template is not in use. A template in use can be deleted, after warning the
     * user with this list: see release_emailtemplate().
     *
     * @param int $templateid
     * @return string[] Calendar names, by calendar id, ordered by name.
     */
    public static function get_calendar_names_using_emailtemplate(int $templateid): array {
        global $DB;

        return $DB->get_records_menu('local_eventcalendar_calendar', ['emailtemplateid' => $templateid], 'name ASC', 'id, name');
    }

    /**
     * Removes the reference to an email template from all the calendars that use it.
     *
     * The calendars that used the template go back to the default one, which is chosen when the email is sent.
     *
     * @param int $templateid
     */
    public static function release_emailtemplate(int $templateid): void {
        global $DB;

        $DB->set_field('local_eventcalendar_calendar', 'emailtemplateid', null, ['emailtemplateid' => $templateid]);
    }
}
