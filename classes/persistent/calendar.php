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
 * Persistent class for a calendar instance.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for a calendar instance.
 *
 * Represents a row of local_eventcalendar_calendar. The id, timecreated, timemodified and
 * usermodified properties are handled by core\persistent.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_calendar';

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
            'templatekey' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            // Language of the content pack used by the calendar (see content_pack_repository::get_for_calendar()).
            'language' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'intro' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'tagline' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            // Enabled by default for new calendars: no regular content on the weekly rest day,
            // unless the manager disables it.
            'restdayenabled' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
            'restdayweekday' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'startdate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'enddate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'enddisplaydate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'progressiveunlock' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
            'iconsetid' => [
                'type' => PARAM_INT,
            ],
            'notificationsetid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'forcehide' => [
                'type' => PARAM_BOOL,
                'default' => true,
            ],
            'bannerid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'headerfooterid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'emailtemplateid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'usercreated' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Sets usercreated to the current user on creation only.
     *
     * raw_set() is used because validation has already run at this point of the lifecycle.
     * A value already set by the caller (for example an import) is kept.
     */
    protected function before_create() {
        global $USER;

        if (empty($this->raw_get('usercreated'))) {
            $this->raw_set('usercreated', $USER->id);
        }
    }

    /**
     * Validates the icon set reference, if present.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_iconsetid($value) {
        global $DB;

        // 0 means no set: the set used by the calendar was deleted (integrity::release_iconset()). It is
        // accepted here, so that the calendar can still be updated by the services and the tasks; the calendar
        // form asks for a set before saving.
        if (empty($value)) {
            return true;
        }

        if (!$DB->record_exists('local_eventcalendar_icons', ['id' => $value])) {
            return new \lang_string('errorinvalidiconset', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the notification set reference, if present.
     *
     * empty() covers both null and the raw 0 sent by the "none" option of the form select,
     * because core\form\persistent validates a temporary instance with the raw form values.
     *
     * @param int|null $value
     * @return true|\lang_string
     */
    protected function validate_notificationsetid($value) {
        global $DB;

        if (empty($value)) {
            return true;
        }

        if (!$DB->record_exists('local_eventcalendar_notifset', ['id' => $value])) {
            return new \lang_string('errorinvalidnotificationset', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the language of the calendar, if present.
     *
     * Only the languages of the content packs shipped with the plugin are accepted, because the language
     * has no other purpose than selecting a content pack. An empty value means that no pack is used.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_language($value) {
        if (empty($value)) {
            return true;
        }

        if (!array_key_exists($value, \local_eventcalendar\local\content_pack_repository::get_languages())) {
            return new \lang_string('errorinvalidlanguage', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the weekly rest day, using the PHP date('w') numbering: 0 (Sunday) to 6 (Saturday).
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_restdayweekday($value) {
        if ($value < 0 || $value > 6) {
            return new \lang_string('errorinvalidrestweekday', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the preloaded banner reference, if present.
     *
     * @param int|null $value
     * @return true|\lang_string
     */
    protected function validate_bannerid($value) {
        global $DB;

        if (empty($value)) {
            return true;
        }

        if (!$DB->record_exists('local_eventcalendar_banner', ['id' => $value])) {
            return new \lang_string('errorinvalidbanner', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Validates the email template reference, if present.
     *
     * @param int|null $value
     * @return true|\lang_string
     */
    protected function validate_emailtemplateid($value) {
        if (empty($value)) {
            return true;
        }

        if (!email_template::record_exists($value)) {
            return new \lang_string('errorinvalidemailtemplate', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Email template of the notification emails of this calendar.
     *
     * The template chosen for the calendar, or the default template when none was chosen (calendars
     * created before the choice existed) or the chosen one was deleted.
     *
     * @return email_template|null null only when there are no email templates at all
     */
    public function get_email_template(): ?email_template {
        $id = (int) $this->get('emailtemplateid');
        if ($id && email_template::record_exists($id)) {
            return new email_template($id);
        }

        return email_template::get_record(['isdefault' => 1]) ?: null;
    }

    /**
     * Validates the preloaded header/footer pair reference, if present.
     *
     * @param int|null $value
     * @return true|\lang_string
     */
    protected function validate_headerfooterid($value) {
        global $DB;

        if (empty($value)) {
            return true;
        }

        if (!$DB->record_exists('local_eventcalendar_headfoot', ['id' => $value])) {
            return new \lang_string('errorinvalidheaderfooter', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Tells whether the calendar is currently visible.
     *
     * Boolean shortcut on get_visibility_status(). Per-user access rules are not considered;
     * they are checked by \local_eventcalendar\local\access_rules::user_can_access().
     *
     * @param \stdClass|null $vieweruser Viewer, used for the timezone; null for the current user ($USER).
     * @return bool
     */
    public function is_visible(?\stdClass $vieweruser = null): bool {
        return $this->get_visibility_status($vieweruser) === 'visible';
    }

    /**
     * Returns the visibility status, which also explains why a calendar is not visible.
     *
     * @param \stdClass|null $vieweruser Viewer; null for the current user ($USER).
     * @return string One of 'hidden_manual', 'not_started', 'expired', 'visible'.
     */
    public function get_visibility_status(?\stdClass $vieweruser = null): string {
        if ($this->get('forcehide')) {
            return 'hidden_manual';
        }

        $now = time();

        // The start is a single global instant, the same for everyone.
        $startdate = $this->get('startdate');
        if ($startdate > 0 && $now < $startdate) {
            return 'not_started';
        }

        // The end depends on the viewer: the calendar stays visible until the enddisplaydate day
        // is over in the viewer's timezone, so it never disappears before the end of the local day.
        $enddisplaydate = $this->get('enddisplaydate');
        if ($enddisplaydate > 0 && $now > $this->get_local_expiry($enddisplaydate, $vieweruser)) {
            return 'expired';
        }

        return 'visible';
    }

    /**
     * Tells whether the current date is inside the configured window (startdate/enddisplaydate).
     *
     * It ignores the manual forcehide flag, so administrators can see the date state and the
     * manual visibility as two separate pieces of information.
     *
     * @param \stdClass|null $vieweruser Viewer for the expiry calculation; null for the current user ($USER).
     * @return bool
     */
    public function is_within_active_dates(?\stdClass $vieweruser = null): bool {
        $now = time();

        $startdate = $this->get('startdate');
        if ($startdate > 0 && $now < $startdate) {
            return false;
        }

        $enddisplaydate = $this->get('enddisplaydate');
        if ($enddisplaydate > 0 && $now > $this->get_local_expiry($enddisplaydate, $vieweruser)) {
            return false;
        }

        return true;
    }

    /**
     * Tells whether a day of this calendar can be reached by a user.
     *
     * With progressiveunlock enabled the day is open only from its unlock date (day::is_open());
     * otherwise all days are always open. It is shared by the grid and the day page.
     *
     * @param day $day Day of this calendar.
     * @param \stdClass|null $vieweruser Viewer; null for the current user ($USER).
     * @return bool
     */
    public function is_day_open(day $day, ?\stdClass $vieweruser = null): bool {
        if (!$this->get('progressiveunlock')) {
            return true;
        }

        return $day->is_open($vieweruser);
    }

    /**
     * Returns the instant when the enddisplaydate day is over in the user's timezone.
     *
     * Same two-step approach as day::get_local_opendate(), but it returns the midnight of the
     * following day instead of the same day.
     *
     * @param int $enddisplaydate Stored timestamp.
     * @param \stdClass|null $vieweruser Viewer; null for the current user ($USER).
     * @return int Timestamp of the end of the enddisplaydate day for the user.
     */
    private function get_local_expiry(int $enddisplaydate, ?\stdClass $vieweruser = null): int {
        $refdate = usergetdate($enddisplaydate, 'UTC');

        $viewertimezone = $vieweruser ? \core_date::get_user_timezone($vieweruser) : 99;

        // A day number past the end of the month rolls over correctly, as in mktime().
        return make_timestamp($refdate['year'], $refdate['mon'], $refdate['mday'] + 1, 0, 0, 0, $viewertimezone);
    }

    /**
     * Returns the logo URL for the {{logo}} placeholder of the email template.
     *
     * The logo of the site is used. Other plugins can replace it with an image of the calendar (image_url hook).
     *
     * @return \moodle_url|null Null if no logo is available.
     */
    public function get_logo_url(): ?\moodle_url {
        global $OUTPUT;

        $logourl = $OUTPUT->get_logo_url();

        return $this->resolve_image_url('logo', $logourl ?: null);
    }

    /**
     * Returns the banner URL for the {{banner}} placeholder of the email template.
     *
     * The preloaded banner is used if chosen; otherwise other plugins can provide one (image_url hook).
     *
     * @return \moodle_url|null Null if no banner is available.
     */
    public function get_banner_url(): ?\moodle_url {
        $bannerid = $this->get('bannerid');

        if (!empty($bannerid)) {
            if (!banner::record_exists($bannerid)) {
                return null;
            }

            return (new banner($bannerid))->get_url();
        }

        return $this->resolve_image_url('banner', null);
    }

    /**
     * Returns the header URL for the {{header}} placeholder of the day content template.
     *
     * The preloaded pair is used if chosen; otherwise other plugins can provide the image (image_url hook), and
     * without them there is no header.
     *
     * @return \moodle_url|null Null if no header is available.
     */
    public function get_header_url(): ?\moodle_url {
        $pair = $this->get_headerfooter();

        return $pair ? $pair->get_header_url() : $this->resolve_image_url('headerimage', null);
    }

    /**
     * Returns the footer URL for the {{footer}} placeholder of the day content template.
     *
     * Same logic as get_header_url().
     *
     * @return \moodle_url|null Null if no footer is available.
     */
    public function get_footer_url(): ?\moodle_url {
        $pair = $this->get_headerfooter();

        return $pair ? $pair->get_footer_url() : $this->resolve_image_url('footerimage', null);
    }

    /**
     * Returns the preloaded header and footer pair of the calendar, if chosen and still existing.
     *
     * A pair chosen and then removed gives no header and footer, without asking the other plugins: the choice
     * was a preloaded pair.
     *
     * @return headerfooter|null
     */
    private function get_headerfooter(): ?headerfooter {
        $headerfooterid = $this->get('headerfooterid');

        if (empty($headerfooterid)) {
            return null;
        }

        return headerfooter::record_exists($headerfooterid) ? new headerfooter($headerfooterid) : null;
    }

    /**
     * Lets other plugins provide or replace the address of an image of this calendar (image_url hook).
     *
     * Called for the logo always and for banner, header and footer only when no preloaded element is chosen,
     * so a preloaded element always wins.
     *
     * @param string $area 'logo', 'banner', 'headerimage' or 'footerimage'.
     * @param \moodle_url|null $url Address found by this plugin, null if none.
     * @return \moodle_url|null
     */
    private function resolve_image_url(string $area, ?\moodle_url $url): ?\moodle_url {
        $hook = new \local_eventcalendar\hook\image_url($this, $area, $url);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return $hook->get_url();
    }
}
