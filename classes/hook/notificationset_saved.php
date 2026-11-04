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
 * Hook dispatched after a notification set has been saved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\notification_set;

/**
 * Hook dispatched after a notification set has been saved.
 *
 * It is dispatched by notification_set_manager::save() when the set (created or updated) and its
 * notifications are already saved, so the set has its final id also for a new one. Callbacks use it to save
 * the data they added to the set form: they read the submitted values with get_data(), the same object
 * returned by the form. They do not return a value and must not change the set.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to save their own data when a notification set is saved.')]
#[\core\attribute\tags('notificationset')]
final class notificationset_saved {
    /**
     * Creates the hook.
     *
     * @param notification_set $set The set that has been saved.
     * @param \stdClass $data Data submitted with the set form.
     * @param bool $isnew True if the set has just been created, false if it has been updated.
     */
    public function __construct(
        /** @var notification_set The set that has been saved. */
        private readonly notification_set $set,
        /** @var \stdClass Data submitted with the set form. */
        private readonly \stdClass $data,
        /** @var bool True if the set has just been created. */
        private readonly bool $isnew,
    ) {
    }

    /**
     * Returns the set that has been saved.
     *
     * @return notification_set
     */
    public function get_set(): notification_set {
        return $this->set;
    }

    /**
     * Returns the data submitted with the set form.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        return $this->data;
    }

    /**
     * Tells whether the set has just been created.
     *
     * @return bool
     */
    public function is_new(): bool {
        return $this->isnew;
    }
}
