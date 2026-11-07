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
 * Server-side check of the file types in a draft area.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Server-side check of the file types in a draft area.
 *
 * The accepted_types option of the editor and of the file manager is applied by the file picker in the browser
 * only: file_save_draft_area_files() ignores it, so a request built by hand can store any type. The forms call
 * this class in their validation, with the same list of types given to the element, and refuse the submission
 * when something else is in the draft area. It is public so that the add-ons can use it for their own file
 * managers.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class draft_files {
    /**
     * Returns the names of the files of a draft area whose type is not accepted.
     *
     * The draft area belongs to the current user (the file picker always uploads into the user's own draft
     * area), so it is read in the user context. The type is the MIME type that Moodle assigns from the file name
     * when the file is stored, the same value used when the file is served. The accepted types are given as for
     * the accepted_types option: type groups (for example 'web_image') and extensions with the dot (for example
     * '.png'), in any mix. An empty or missing draft area returns an empty list.
     *
     * @param int $draftitemid
     * @param string[] $acceptedtypes
     * @return string[] file names, escaped for HTML, ready for an error message
     */
    public static function find_not_accepted(int $draftitemid, array $acceptedtypes): array {
        global $USER;

        if ($draftitemid <= 0) {
            return [];
        }

        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

        $names = [];
        foreach ($files as $file) {
            if (!file_mimetype_in_typegroup($file->get_mimetype(), $acceptedtypes)) {
                $names[] = s($file->get_filename());
            }
        }

        return $names;
    }

    /**
     * Returns the validation error of a draft area, or null when all its files are accepted.
     *
     * Shortcut for the forms: the message lists the files to remove, with a string of this plugin, so the
     * add-ons do not need their own.
     *
     * @param int $draftitemid
     * @param string[] $acceptedtypes see find_not_accepted()
     * @return string|null
     */
    public static function get_error(int $draftitemid, array $acceptedtypes): ?string {
        $names = self::find_not_accepted($draftitemid, $acceptedtypes);

        return $names ? get_string('errorfilesnotaccepted', 'local_eventcalendar', implode(', ', $names)) : null;
    }
}
