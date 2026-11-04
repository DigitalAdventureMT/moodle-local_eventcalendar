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
 * Access rules for the files served by the pluginfile callbacks.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Access rules for the files served by the pluginfile callbacks.
 *
 * The pages of the plugin hide a calendar that is not visible or whose access rules the user does not
 * satisfy, and a day that is still locked. The files attached to those calendars and days must follow
 * the same rules, otherwise anyone knowing the URL could read them in advance or from outside the
 * intended audience. The rules are collected here so that the callbacks of this plugin and of its
 * subplugins apply exactly the same checks as view.php and view_day.php:
 *
 *  - files of a calendar (images of the grid and of the emails): the calendar must be visible to the
 *    user and its access rules satisfied;
 *  - icon of a day: same as the calendar, since the grid shows the icon of locked days too (faded);
 *  - content of a day: also the day must be unlocked for the user.
 *
 * Users who can manage the content bypass these checks, as they do on the pages, because they edit and
 * preview hidden calendars and locked days.
 *
 * The class also decides how a file is sent: only images are shown inline. Any other type (an HTML page,
 * for example) is always downloaded, so that it never runs in the origin of the site even if it was
 * uploaded by bypassing the file types accepted by the editor, which the file picker checks only on
 * the client side.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginfile_access {
    /**
     * Tells whether the current user can read the files of a calendar.
     *
     * @param int $calendarid Id of the calendar (itemid of the calendar file areas).
     * @return bool False also when the calendar does not exist.
     */
    public static function can_view_calendar_files(int $calendarid): bool {
        if (access::can_manage_content()) {
            return true;
        }

        $calendar = calendar::get_record(['id' => $calendarid]);

        return $calendar && self::is_calendar_reachable($calendar);
    }

    /**
     * Tells whether the current user can read a file of a day.
     *
     * @param int $dayid Id of the day (itemid of the day file areas).
     * @param string $filearea 'dayicon' or 'content'; only the content also needs the day to be unlocked.
     * @return bool False also when the day or its calendar does not exist.
     */
    public static function can_view_day_file(int $dayid, string $filearea): bool {
        if (access::can_manage_content()) {
            return true;
        }

        $day = day::get_record(['id' => $dayid]);
        if (!$day) {
            return false;
        }

        $calendar = calendar::get_record(['id' => $day->get('calendarid')]);
        if (!$calendar || !self::is_calendar_reachable($calendar)) {
            return false;
        }

        // The icon is shown in the grid also for locked days, so only the content waits for the unlock.
        return $filearea !== 'content' || $calendar->is_day_open($day);
    }

    /**
     * Tells whether a file must be downloaded instead of being shown in the browser.
     *
     * Only the types of the Moodle 'web_image' group (GIF, JPEG, PNG, SVG and, on recent versions, WebP)
     * are shown inline; Moodle already forces the download of SVG images on its own. The check uses the
     * stored MIME type, which Moodle derives from the file name extension.
     *
     * @param \stored_file $file
     * @return bool
     */
    public static function must_force_download(\stored_file $file): bool {
        return !file_mimetype_in_typegroup($file->get_mimetype(), 'web_image');
    }

    /**
     * Same visibility test as the calendar pages: visible now and access rules satisfied.
     *
     * @param calendar $calendar
     * @return bool
     */
    private static function is_calendar_reachable(calendar $calendar): bool {
        global $USER;

        return $calendar->is_visible() && access_rules::user_can_access($calendar, $USER);
    }
}
