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
 * Service for the management of the days of a calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\day_saved;
use local_eventcalendar\persistent\day;

/**
 * Service for the management of the days of a calendar.
 *
 * It holds the logic that was written directly in days.php, so that every caller behaves in the same way and
 * the other plugins can take part through the day_saved hook. The page only checks permissions, builds the
 * form and shows the result.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class day_manager {
    /**
     * Saves a day from the data of the day form.
     *
     * The data must already be validated by the form. The steps are:
     *
     *  1. the texts and the status are copied onto the day (title, body, notes, ready flag) and the day is
     *     updated;
     *  2. the content is saved, because the images embedded in the editor are stored with the day id as itemid
     *     and need the draft area to be moved;
     *  3. the day_saved hook is dispatched, with the rest day flag, so that other plugins save their own fields
     *     (and clear those that do not apply to rest days).
     *
     * It checks no capability and shows nothing: the caller does both.
     *
     * @param day $day The day to save (existing).
     * @param \stdClass $data Validated data returned by the day form.
     * @param bool $isrestday Whether the day is a weekly rest day of its calendar.
     */
    public static function save(day $day, \stdClass $data, bool $isrestday): void {
        $day->set('notiftitle', $data->notiftitle ?? '');
        $day->set('notifbody', $data->notifbodytext ?? '');
        $day->set('notes', $data->notes ?? '');
        $day->set('isready', !empty($data->isready));

        $day->update();

        // The content editor comes last: the file area itemid is the day id, and the text returned by the
        // draft area save replaces the draft URLs of the images. The form returns the text and the format
        // already split (content, contentformat) plus the draft area in contentitemid.
        if (isset($data->content) && isset($data->contentitemid)) {
            $finaltext = file_save_draft_area_files(
                (int) $data->contentitemid,
                \context_system::instance()->id,
                'local_eventcalendar',
                'content',
                $day->get('id'),
                null,
                (string) $data->content
            );
            $day->set('content', $finaltext);
            $day->set('contentformat', $data->contentformat ?? FORMAT_HTML);
            $day->update();
        }

        \core\di::get(\core\hook\manager::class)->dispatch(new day_saved($day, $data, $isrestday));
    }
}
