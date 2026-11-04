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
 * Persistent class for a notification set.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for a notification set.
 *
 * A set owns its notifications (one-to-many). In this plugin a set is always
 * created from a standard pack and its notifications are those of the pack.
 * Calendars reference a set through calendar::notificationsetid.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_set extends \core\persistent {
    /** Table of this persistent class. */
    const TABLE = 'local_eventcalendar_notifset';

    /**
     * Defines the persistent properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'sourcepack' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'sourcepackversion' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'usercreated' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Sets usercreated to the current user on creation only.
     */
    protected function before_create() {
        global $USER;

        if (empty($this->raw_get('usercreated'))) {
            $this->raw_set('usercreated', $USER->id);
        }
    }

    /**
     * Returns all the notifications of the set (titles and bodies), ordered by id.
     *
     * @return notification[]
     */
    public function get_notifications(): array {
        return notification::get_records(['setid' => $this->get('id')], 'id', 'ASC');
    }

    /**
     * Returns only the titles (kind='title').
     *
     * @return notification[]
     */
    public function get_titles(): array {
        return notification::get_records(['setid' => $this->get('id'), 'kind' => 'title'], 'id', 'ASC');
    }

    /**
     * Returns only the bodies (kind='body').
     *
     * @return notification[]
     */
    public function get_bodies(): array {
        return notification::get_records(['setid' => $this->get('id'), 'kind' => 'body'], 'id', 'ASC');
    }

    /**
     * Tells whether the set was imported from a standard pack.
     *
     * A locked set cannot be edited from the interface, so that its content
     * always matches the source pack, and it can be updated when the pack
     * gets a newer version. Sets without a pack can only be created by other
     * plugins.
     *
     * @return bool
     */
    public function is_locked(): bool {
        return !empty($this->get('sourcepack'));
    }
}
