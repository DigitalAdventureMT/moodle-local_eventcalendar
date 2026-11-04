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
 * Hook dispatched when the initial values of the calendar day form are prepared.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

/**
 * Hook dispatched when the initial values of the calendar day form are prepared.
 *
 * The callbacks set the values of the elements they added (for example the current value of a field that
 * is saved in their own table) with set_defaults(), using the names of the elements. The values of the
 * form of the plugin are already in place and can be read with get_defaults(). They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to set the initial values of the fields they added to the calendar day form.')]
#[\core\attribute\tags('day', 'form')]
final class day_form_data {
    /**
     * Creates the hook.
     *
     * @param int $dayid Id of the calendar day (always existing).
     * @param \stdClass $defaults Initial values of the form, modified by set_defaults().
     */
    public function __construct(
        /** @var int Id of the calendar day. */
        private readonly int $dayid,
        /** @var \stdClass Initial values of the form. */
        private readonly \stdClass $defaults,
    ) {
    }

    /**
     * Returns the id of the calendar day, 0 if it is new.
     *
     * @return int
     */
    public function get_dayid(): int {
        return $this->dayid;
    }

    /**
     * Returns the initial values of the form.
     *
     * @return \stdClass
     */
    public function get_defaults(): \stdClass {
        return $this->defaults;
    }

    /**
     * Sets the initial values of some elements of the form.
     *
     * @param array $values Values indexed by element name.
     */
    public function set_defaults(array $values): void {
        foreach ($values as $name => $value) {
            $this->defaults->{$name} = $value;
        }
    }
}
