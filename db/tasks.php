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
 * Scheduled tasks of the plugin.
 *
 * - send_notifications: runs every 10 minutes because the send time is evaluated in each
 *   user's timezone (including half-hour and 45-minute offsets).
 * - hide_expired_calendars: hourly is enough; expiry is measured in days.
 * - check_access_rules: Tuesday and Thursday morning, at a non-round minute to avoid
 *   the many tasks that start on the hour.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_eventcalendar\task\send_notifications',
        'blocking' => 0,
        'minute' => '*/10',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_eventcalendar\task\hide_expired_calendars',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_eventcalendar\task\check_access_rules',
        'blocking' => 0,
        'minute' => '23',
        'hour' => '7',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '2,4',
    ],
];
