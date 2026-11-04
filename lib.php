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
 * Library functions of local_eventcalendar, called by the Moodle core.
 *
 * These are Moodle plugin callbacks: the names are prefixed with the component
 * and the core finds and calls them automatically.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves the files of the file areas managed by the plugin.
 *
 * All areas live in the system context. File areas:
 * - 'content': files embedded in the day page editor (itemid: day id);
 * - 'dayicon': icon of a day, copied when the calendar is generated (itemid: day id);
 * - 'notificationmessage': images in a notification body (itemid: notification id);
 *
 * Access is limited to users with the local/eventcalendar:view capability. The files of the days follow
 * the same rules as the day pages (see \local_eventcalendar\local\pluginfile_access): the calendar must be
 * visible and its access rules satisfied, and the content of a day is readable only once the day is unlocked.
 * Users who manage the content bypass these checks, as on the pages. Files linked from emails carry the token
 * of the recipient (tokenpluginfile.php, see notification_dispatcher::add_file_tokens()), since mail clients
 * have no Moodle session; the checks then apply to the recipient.
 *
 * Only images are shown inline: any other file is sent as a download, so that an HTML page uploaded in an
 * editor can never run in the origin of the site.
 *
 * @param stdClass|null $course Always null: the plugin is not course based.
 * @param stdClass|null $cm Always null: the plugin is not course based.
 * @param context $context Request context, expected to be CONTEXT_SYSTEM.
 * @param string $filearea Name of the requested file area.
 * @param array $args Remaining path arguments (itemid, optional subpath, file name).
 * @param bool $forcedownload Whether to force the download instead of inline display.
 * @param array $options Additional file sending options.
 * @return bool False if the file cannot be served (no permission or missing file).
 */
function local_eventcalendar_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    // The file areas live at system level, so no course enrolment check applies.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if (!has_capability('local/eventcalendar:view', $context)) {
        return false;
    }

    $allowedfileareas = [
        'content', 'dayicon', 'notificationmessage',
    ];
    if (!in_array($filearea, $allowedfileareas, true)) {
        return false;
    }

    // Split the arguments into itemid, optional subpath and file name.
    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_eventcalendar', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    // Files of a day: same visibility, access rule and unlock checks as view.php and view_day.php.
    // The bodies of the notifications are not tied to a calendar and keep the capability check only.
    $isdayfile = in_array($filearea, ['content', 'dayicon'], true);
    if ($isdayfile && !\local_eventcalendar\local\pluginfile_access::can_view_day_file((int) $itemid, $filearea)) {
        return false;
    }

    // Anything that is not an image is downloaded, never rendered by the browser.
    if (\local_eventcalendar\local\pluginfile_access::must_force_download($file)) {
        $forcedownload = true;
    }

    // This call ends the script execution.
    send_stored_file($file, null, 0, $forcedownload, $options);
}
