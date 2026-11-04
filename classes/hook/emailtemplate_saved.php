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
 * Hook dispatched after an email template has been saved.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\email_template;

/**
 * Hook dispatched after an email template has been saved.
 *
 * It is dispatched by email_template_manager::save() when the template is already updated. Callbacks use it
 * to save the data they added to the template form: they read the submitted values with get_data(), the same
 * object returned by the form. They do not return a value. They must not change name, language and content,
 * already saved from the form, but they can change the source and save the template again.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to save their own data when an email template is saved.')]
#[\core\attribute\tags('emailtemplate')]
final class emailtemplate_saved {
    /**
     * Creates the hook.
     *
     * @param email_template $template The template that has been saved.
     * @param \stdClass $data Data submitted with the template form.
     */
    public function __construct(
        /** @var email_template The template that has been saved. */
        private readonly email_template $template,
        /** @var \stdClass Data submitted with the template form. */
        private readonly \stdClass $data,
    ) {
    }

    /**
     * Returns the template that has been saved.
     *
     * @return email_template
     */
    public function get_template(): email_template {
        return $this->template;
    }

    /**
     * Returns the data submitted with the template form.
     *
     * @return \stdClass
     */
    public function get_data(): \stdClass {
        return $this->data;
    }
}
