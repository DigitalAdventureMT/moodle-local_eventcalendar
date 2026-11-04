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
 * Hook dispatched while the calendar day form is defined.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\form\day_form;
use local_eventcalendar\persistent\day;

/**
 * Hook dispatched while the calendar day form is defined.
 *
 * It is dispatched at the end of the definition of the form, right before the action buttons, so the
 * elements added by the callbacks appear before them. The callbacks can add, remove or change elements
 * through the QuickForm object, using the element names of the form. The form is shown in columns: an added
 * element is placed with place_element(), otherwise it is shown at the end of the last column. They do not
 * return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add or change the elements of the calendar day form.')]
#[\core\attribute\tags('day', 'form')]
final class day_form_definition {
    /**
     * Creates the hook.
     *
     * @param day_form $form The form being defined.
     * @param \MoodleQuickForm $mform The QuickForm object of the form.
     * @param day $day The calendar day the form is bound to.
     * @param bool $isrestday Whether the day is a weekly rest day of its calendar.
     */
    public function __construct(
        /** @var day_form The form being defined. */
        private readonly day_form $form,
        /** @var \MoodleQuickForm The QuickForm object of the form. */
        private readonly \MoodleQuickForm $mform,
        /** @var day The calendar day the form is bound to. */
        private readonly day $day,
        /** @var bool Whether the day is a weekly rest day of its calendar. */
        private readonly bool $isrestday,
    ) {
    }

    /**
     * Returns the form being defined.
     *
     * @return day_form
     */
    public function get_form(): day_form {
        return $this->form;
    }

    /**
     * Places an element in a column of the form, see day_form::place_element().
     *
     * @param string $name Name of the form element.
     * @param int $column Column, from 1 to day_form::LAYOUT_COLUMNS.
     * @param string|null $before Element before which it is placed; null: at the end of the column.
     * @param bool $unwrapped True for a button moved without its wrapper.
     */
    public function place_element(string $name, int $column, ?string $before = null, bool $unwrapped = false): void {
        $this->form->place_element($name, $column, $before, $unwrapped);
    }

    /**
     * Returns the QuickForm object of the form.
     *
     * @return \MoodleQuickForm
     */
    public function get_mform(): \MoodleQuickForm {
        return $this->mform;
    }

    /**
     * Returns the calendar day the form is bound to.
     *
     * @return day
     */
    public function get_day(): day {
        return $this->day;
    }

    /**
     * Tells whether the day is a weekly rest day of its calendar.
     *
     * @return bool
     */
    public function is_restday(): bool {
        return $this->isrestday;
    }
}
