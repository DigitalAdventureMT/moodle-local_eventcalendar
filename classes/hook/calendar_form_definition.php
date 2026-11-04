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
 * Hook dispatched while the calendar form is defined.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;

/**
 * Hook dispatched while the calendar form is defined.
 *
 * It is dispatched at the end of the definition of the form, right before the action buttons, so the
 * elements added by the callbacks appear before them. The callbacks can add, remove or change elements
 * through the QuickForm object, using the element names of the form. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add or change the elements of the calendar form.')]
#[\core\attribute\tags('calendar', 'form')]
final class calendar_form_definition {
    /**
     * Creates the hook.
     *
     * @param \moodleform $form The form being defined.
     * @param \MoodleQuickForm $mform The QuickForm object of the form.
     * @param calendar $calendar The calendar the form is bound to (id 0 if new).
     */
    public function __construct(
        /** @var \moodleform The form being defined. */
        private readonly \moodleform $form,
        /** @var \MoodleQuickForm The QuickForm object of the form. */
        private readonly \MoodleQuickForm $mform,
        /** @var calendar The calendar the form is bound to. */
        private readonly calendar $calendar,
    ) {
    }

    /**
     * Returns the form being defined.
     *
     * @return \moodleform
     */
    public function get_form(): \moodleform {
        return $this->form;
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
     * Returns the calendar the form is bound to.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }
}
