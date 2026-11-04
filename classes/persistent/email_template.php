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
 * Persistent class for an email template.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for an email template.
 *
 * A fixed HTML wrapper (header, button, footer) with placeholders such as {{content}}, into which the
 * content of a notification is inserted when an email is sent. Templates are imported as soon as they
 * are detected in email_templates/{language}/{identifier}.html (classes/local/email_template_scanner.php);
 * this plugin lets the user change their name and language. The source tells whether the template is still
 * the one of the file (SOURCE_FILE) or was created or changed by other means (SOURCE_CUSTOM), for example by
 * another plugin through the hooks of the template form.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_template extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_emailtpl';

    /** Source: imported by the scan of email_templates/, content unchanged. */
    const SOURCE_FILE = 'file';

    /** Source: created or changed by other means (copy, content edited, imported with a calendar). */
    const SOURCE_CUSTOM = 'custom';

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
            'language' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'identifier' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'content' => [
                'type' => PARAM_RAW,
            ],
            'isdefault' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'source' => [
                'type' => PARAM_ALPHA,
                'default' => self::SOURCE_FILE,
                'choices' => [self::SOURCE_FILE, self::SOURCE_CUSTOM],
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
     * Sets this template as the default, clearing the flag on any other (only one record can have it).
     */
    public function set_as_default(): void {
        global $DB;

        $DB->set_field('local_eventcalendar_emailtpl', 'isdefault', 0, ['isdefault' => 1]);

        $this->set('isdefault', true);
        $this->update();
    }
}
