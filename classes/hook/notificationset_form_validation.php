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
 * Hook dispatched when the notification set form is validated.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\local\hook_errors;

/**
 * Hook dispatched when the notification set form is validated.
 *
 * It is dispatched after the validation of the persistent and of the plugin, with the errors found so far.
 * The callbacks validate the fields they added with add_error() and can remove an error of the plugin with
 * remove_error(). When all the callbacks have run, the form uses the resulting errors. They do not return a
 * value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to validate the notification set form and to change its validation errors.')]
#[\core\attribute\tags('notificationset', 'form')]
final class notificationset_form_validation {
    use hook_errors;

    /**
     * Creates the hook.
     *
     * @param \stdClass $data Submitted data.
     * @param array $files Submitted files.
     * @param string[] $errors Errors found so far, indexed by element name.
     */
    public function __construct(
        /** @var \stdClass Submitted data. */
        private readonly \stdClass $data,
        /** @var array Submitted files. */
        private readonly array $files,
        array $errors,
    ) {
        $this->errors = $errors;
    }

    /**
     * Returns the submitted data.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        return $this->data;
    }

    /**
     * Returns the submitted files.
     *
     * @return array
     */
    public function get_files(): array {
        return $this->files;
    }
}
