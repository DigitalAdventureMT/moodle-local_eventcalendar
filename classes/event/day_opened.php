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
 * Event fired when a user opens a day page for the first time.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\event;

/**
 * Event fired when a user opens a day page for the first time.
 *
 * It is triggered by view_day.php when the first visit of a user to a day is logged.
 * The event uses the system context and adds no personal data beyond the acting user.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class day_opened extends \core\event\base {
    /**
     * Initialises the event as a read action at participating level, with the day as object.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_eventcalendar_days';
    }

    /**
     * Returns the event name.
     *
     * @return string Localised event name.
     */
    public static function get_name() {
        return get_string('eventdayopened', 'local_eventcalendar');
    }

    /**
     * Returns the event description for the logs.
     *
     * @return string Description shown in the log report.
     */
    public function get_description() {
        return get_string('eventdayopeneddesc', 'local_eventcalendar', (object) [
            'userid' => $this->userid,
            'daynumber' => $this->other['daynumber'],
            'calendarid' => $this->other['calendarid'],
        ]);
    }

    /**
     * Returns the URL of the opened day.
     *
     * @return \moodle_url Day page.
     */
    public function get_url() {
        return new \moodle_url('/local/eventcalendar/view_day.php', [
            'id' => $this->other['calendarid'],
            'day' => $this->other['daynumber'],
        ]);
    }

    /**
     * Checks that the calendar id and day number are set, as they are needed for the description and URL.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['calendarid'])) {
            throw new \coding_exception('The \'calendarid\' value must be set in other.');
        }

        if (!isset($this->other['daynumber'])) {
            throw new \coding_exception('The \'daynumber\' value must be set in other.');
        }
    }

    /**
     * Returns the object id mapping for restore; days are not part of Moodle backups, so it is not mapped.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_eventcalendar_days', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Returns the mapping of the 'other' fields for restore; not mapped, as above.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'calendarid' => ['db' => 'local_eventcalendar_calendar', 'restore' => \core\event\base::NOT_MAPPED],
        ];
    }
}
