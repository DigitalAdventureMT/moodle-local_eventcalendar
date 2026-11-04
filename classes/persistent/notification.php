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
 * Persistent class for a notification.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for a notification.
 *
 * A notification belongs to one set (setid) and is either a 'title' or a 'body' (field 'kind'):
 *
 * - 'title': short plain text, used both as the Moodle message title and as the email subject;
 * - 'body': message body, HTML with optional images added through the editor.
 *
 * Titles and bodies are two independent pools inside a set, not fixed pairs: they are picked
 * separately when a calendar is generated.
 *
 * Images in the body (component local_eventcalendar, filearea 'notificationmessage', itemid = id of this
 * notification; never used for titles) are stored with the @@PLUGINFILE@@ placeholder of the editor. The
 * text copied into the days is get_message_text(), with the placeholder already turned into the address of
 * the images; the emails then give each recipient an address with a personal token
 * (notification_dispatcher::add_file_tokens()), because mail clients have no Moodle session.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_notif';

    /**
     * Defines the persistent properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'setid' => [
                'type' => PARAM_INT,
            ],
            'kind' => [
                'type' => PARAM_ALPHA,
                'default' => 'body',
            ],
            'defaultmessage' => [
                'type' => PARAM_RAW,
            ],
            'defaultmessageformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
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
     * Validates the setid field.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_setid($value) {
        if (!notification_set::record_exists($value)) {
            return new \lang_string('errorinvalidnotificationset', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the kind field.
     *
     * @param string $value
     * @return true|\lang_string
     */
    protected function validate_kind($value) {
        if (!in_array($value, ['title', 'body'], true)) {
            return new \lang_string('errorinvalidnotificationkind', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Tells whether the notification is a title (otherwise it is a body).
     *
     * @return bool
     */
    public function is_title(): bool {
        return $this->get('kind') === 'title';
    }

    /**
     * Returns the message as it is copied into the days: the images of a body point to their file.
     *
     * The editor saves the images with the @@PLUGINFILE@@ placeholder, which only this notification can resolve:
     * a day that keeps the placeholder would show broken images, because it does not know which notification
     * its text came from. Titles are plain text and are returned as they are.
     *
     * @return string
     */
    public function get_message_text(): string {
        global $CFG;

        $message = (string) $this->get('defaultmessage');
        if ($this->is_title() || strpos($message, '@@PLUGINFILE@@') === false) {
            return $message;
        }

        // The function is defined in lib/filelib.php, which Moodle does not always load (CLI tasks, for example).
        require_once($CFG->libdir . '/filelib.php');

        return file_rewrite_pluginfile_urls(
            $message,
            'pluginfile.php',
            \context_system::instance()->id,
            'local_eventcalendar',
            'notificationmessage',
            (int) $this->get('id')
        );
    }
}
