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
 * Service for the management of notification sets.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\notificationset_saved;
use local_eventcalendar\persistent\notification_set;

/**
 * Service for the management of notification sets.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_set_manager {
    /**
     * Saves a notification set from the data of the set form.
     *
     * The data must already be validated by the form. Name and description are always copied. A new set with a
     * pack (importpack) records the pack and its version and gets the titles and bodies of the pack: the pack is
     * read again from disk here, never taken from the submitted values, so the set matches the file at the
     * moment of the save. The notifications of an existing set are not touched. In the end the
     * notificationset_saved hook is dispatched: other plugins use it to save the data they added to the form.
     *
     * It checks no capability and shows nothing: the caller does both.
     *
     * @param notification_set $set The set to save, existing or new (id 0).
     * @param \stdClass $data Validated data returned by the set form.
     */
    public static function save(notification_set $set, \stdClass $data): void {
        $isnew = empty($set->get('id'));

        $set->set('name', $data->name);
        $set->set('description', $data->description ?? '');

        if (!$isnew) {
            $set->update();
        } else {
            $pack = !empty($data->importpack) ? content_pack_repository::get($data->importpack) : null;

            if ($pack) {
                $set->set('sourcepack', $data->importpack);
                $set->set('sourcepackversion', $pack->version);
            }
            $set->create();

            if ($pack) {
                content_pack_repository::create_notifications($set, $pack);
            }
        }

        \core\di::get(\core\hook\manager::class)->dispatch(new notificationset_saved($set, $data, $isnew));
    }

    /**
     * Permanently deletes a notification set with its notifications and their files.
     *
     * If the set is assigned to calendars, the reference is removed from them first: the days already
     * generated keep their texts (they are copies), while the days generated later will have empty titles and
     * bodies. The pages warn the user, listing the calendars, before calling this method. It asks for no
     * confirmation and checks no capability: the caller must have done both.
     *
     * @param notification_set $set The set to delete.
     */
    public static function delete(notification_set $set): void {
        integrity::release_notificationset((int) $set->get('id'));

        $context = \context_system::instance();
        $fs = get_file_storage();

        foreach ($set->get_notifications() as $notification) {
            $fs->delete_area_files($context->id, 'local_eventcalendar', 'notificationmessage', $notification->get('id'));
            $notification->delete();
        }

        $set->delete();
    }
}
