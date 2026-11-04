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
 * Service for the management of email templates.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\emailtemplate_saved;
use local_eventcalendar\persistent\email_template;

/**
 * Service for the management of email templates.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_template_manager {
    /**
     * Saves an existing email template from the data of the template form.
     *
     * The name and the language are always copied. The HTML content is copied only when the form sent it, so
     * a form that does not show the content never empties it. The template is updated (the persistent
     * validates it) and then the emailtemplate_saved hook is dispatched, so that other plugins can save the
     * data they added to the form. It checks no capability and shows nothing: the caller does both.
     *
     * @param email_template $template The template to save (existing).
     * @param \stdClass $data Validated data returned by the template form.
     */
    public static function save(email_template $template, \stdClass $data): void {
        $template->set('name', $data->name);
        $template->set('language', $data->language);

        if (isset($data->content)) {
            $template->set('content', $data->content);
        }

        $template->update();

        \core\di::get(\core\hook\manager::class)->dispatch(new emailtemplate_saved($template, $data));
    }

    /**
     * Permanently deletes an email template.
     *
     * The calendars that used the template go back to the default one. If the deleted template was the
     * default, another template is promoted, so that a fallback always exists. It asks for no confirmation
     * and checks no capability: the caller must have done both.
     *
     * @param email_template $template The template to delete.
     */
    public static function delete(email_template $template): void {
        $templateid = (int) $template->get('id');
        $wasdefault = (bool) $template->get('isdefault');

        $template->delete();

        integrity::release_emailtemplate($templateid);

        if ($wasdefault) {
            $remaining = email_template::get_records([], 'id', 'ASC', 0, 1);
            if (!empty($remaining)) {
                reset($remaining)->set_as_default();
            }
        }
    }
}
