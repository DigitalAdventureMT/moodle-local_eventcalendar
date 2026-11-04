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
 * Error handling shared by the validation hooks of the forms.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Error handling shared by the validation hooks of the forms.
 *
 * The hook receives a copy of the errors found by the form (those of the persistent and those of the
 * plugin) and the callbacks add or remove errors on it. When the hook returns, the form replaces its errors
 * with the resulting list. Each error is a message indexed by the name of the form element it refers to.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait hook_errors {
    /** @var string[] Error messages indexed by element name. */
    private array $errors = [];

    /**
     * Adds an error to an element, replacing the one already present.
     *
     * @param string $field Name of the form element.
     * @param string $message Error message.
     */
    public function add_error(string $field, string $message): void {
        $this->errors[$field] = $message;
    }

    /**
     * Removes the error of an element, if any.
     *
     * @param string $field Name of the form element.
     */
    public function remove_error(string $field): void {
        unset($this->errors[$field]);
    }

    /**
     * Returns the errors, indexed by element name.
     *
     * @return string[]
     */
    public function get_errors(): array {
        return $this->errors;
    }
}
