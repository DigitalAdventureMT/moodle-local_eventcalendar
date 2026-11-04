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
 * Persistent class for a day of a calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\persistent;

/**
 * Persistent class for a day (box) of a calendar.
 *
 * One row of local_eventcalendar_days, which also holds the content of the day. notiftitle and
 * notifbody are text copied when the calendar is generated, not references to the source notification
 * set. The icon is likewise a file copied into the 'dayicon' filearea (itemid = id of this row). Other
 * plugins keep their own data of a day in their tables (for example the call to action).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class day extends \core\persistent {
    /** Table associated with this persistent class. */
    const TABLE = 'local_eventcalendar_days';

    /**
     * Defines the persistent properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'calendarid' => [
                'type' => PARAM_INT,
            ],
            'daynumber' => [
                'type' => PARAM_INT,
            ],
            'opendate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'notiftitle' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'notifbody' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'notifbodyformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'content' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'contentformat' => [
                'type' => PARAM_INT,
                'default' => FORMAT_HTML,
            ],
            'isready' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'notes' => [
                'type' => PARAM_TEXT,
                'default' => '',
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
     * See classes/persistent/calendar.php: usercreated is not a mandatory core\persistent property.
     */
    protected function before_create() {
        global $USER;

        if (empty($this->raw_get('usercreated'))) {
            $this->raw_set('usercreated', $USER->id);
        }
    }

    /**
     * Validates the parent calendar reference.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_calendarid($value) {
        if (!calendar::record_exists($value)) {
            return new \lang_string('errorinvalidcalendarid', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Prevents two boxes with the same day number in the same calendar.
     *
     * The unique index in install.xml already guarantees it; this check only returns a readable message
     * instead of a generic database error.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_daynumber($value) {
        global $DB;

        $calendarid = $this->get('calendarid');

            // If calendarid is not set yet, uniqueness is still guaranteed by the unique DB index on save.
        if (empty($calendarid)) {
            return true;
        }

        $existing = $DB->get_record('local_eventcalendar_days', [
            'calendarid' => $calendarid,
            'daynumber' => $value,
        ]);

        if ($existing && (int) $existing->id !== (int) $this->get('id')) {
            return new \lang_string('errorduplicatedaynumber', 'local_eventcalendar');
        }

        return true;
    }

    /**
     * Tells whether the box is unlocked, in the timezone of the viewing user.
     *
     * It does not consider the progressiveunlock flag of the calendar: when that flag is off every box
     * is reachable, and the calling code (the calendar grid) combines the two factors.
     *
     * @param \stdClass|null $vieweruser User to check; null means the current session user ($USER).
     * @return bool
     */
    public function is_open(?\stdClass $vieweruser = null): bool {
        return time() >= $this->get_local_opendate($vieweruser);
    }

    /**
     * Calculates the effective unlock instant of this box for a user, in the user timezone.
     *
     * opendate is a single absolute timestamp, so comparing it with time() would unlock the box at the
     * same instant for everyone, not at each user's local midnight. The calculation has two steps:
     * 1. The calendar date of the box is read from opendate in UTC, a fixed reference independent of
     *    any user account (the calendar form and the date resolvers also build opendate in UTC).
     * 2. Midnight of that same date is recomputed in the timezone of the given user.
     *
     * @param \stdClass|null $vieweruser User record with the 'timezone' property; null means the current
     *                                  session user ($USER).
     * @return int Timestamp of the unlock instant for the given user.
     */
    public function get_local_opendate(?\stdClass $vieweruser = null): int {
        return $this->get_local_timestamp($vieweruser, 0, 0);
    }

    /**
     * Returns the instant when local hour $hour (0-23) of the day of this box occurs for the user.
     *
     * Used to send the notification at a morning time. It is computed from the UTC reference date and
     * the user timezone, not as unlock time plus hours, because on daylight saving change days a day
     * lasts 23 or 25 hours.
     *
     * @param int $hour Local hour, 0-23.
     * @param \stdClass|null $user User; null means the current session user ($USER).
     * @return int
     */
    public function get_local_time(int $hour, ?\stdClass $user = null): int {
        return $this->get_local_timestamp($user, $hour, 0);
    }

    /**
     * Returns the instant when the day of this box ends for the user (the next local midnight).
     *
     * @param \stdClass|null $user User; null means the current session user ($USER).
     * @return int
     */
    public function get_local_day_end(?\stdClass $user = null): int {
        return $this->get_local_timestamp($user, 0, 1);
    }

    /**
     * Shared base of the three calculations above.
     *
     * Takes the date of the day read in UTC, shifts it by $dayoffset days and returns it at local hour
     * $hour in the user timezone. make_timestamp() accepts a day past the end of the month.
     *
     * @param \stdClass|null $user
     * @param int $hour
     * @param int $dayoffset
     * @return int
     */
    private function get_local_timestamp(?\stdClass $user, int $hour, int $dayoffset): int {
        $refdate = usergetdate($this->get('opendate'), 'UTC');

        $timezone = $user ? \core_date::get_user_timezone($user) : 99;

        return make_timestamp($refdate['year'], $refdate['mon'], $refdate['mday'] + $dayoffset, $hour, 0, 0, $timezone);
    }

    /**
     * Returns the URL of the icon of this day, if any.
     *
     * The icon is a file copied into the 'dayicon' filearea at generation time, never a reference to
     * the source iconitem.
     *
     * @return \moodle_url|null Null when no icon is assigned.
     */
    public function get_icon_url(): ?\moodle_url {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_eventcalendar',
            'dayicon',
            $this->get('id'),
            'id',
            false
        );

        $file = reset($files);

        if (!$file) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }
}
