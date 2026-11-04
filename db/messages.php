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
 * Message providers of the plugin: they define how Moodle delivers the messages.
 *
 * daynotification - day unlock notification.
 * - email: always (MESSAGE_FORCED). Messages are sent only to users subscribed to the calendar (opt-in),
 *   and unsubscribing stops them.
 * - in-app popup: delivered unless the user disabled it in the notification preferences
 *   (MESSAGE_PERMITTED lets the user choose, MESSAGE_DEFAULT_ENABLED turns it on by default).
 *
 * invalidaccessrules - alert to calendar managers about invalid access rules
 * (classes/task/check_access_rules.php). Email and popup are enabled by default and can be disabled.
 *
 * MESSAGE_DEFAULT_ENABLED is used instead of MESSAGE_DEFAULT_LOGGEDIN/LOGGEDOFF, which are deprecated
 * since Moodle 4.0 and removed in later versions. The plugin requires Moodle 4.1 or later.
 *
 * The visible name of each provider is the "messageprovider:{name}" language string. The plugin version
 * must be increased after changing this file, otherwise Moodle does not reread it.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Day unlock notification, for users subscribed to the calendar.
    'daynotification' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_FORCED,
        ],
    ],

    // Alert about invalid access rules, for calendar managers.
    'invalidaccessrules' => [
        'capability' => 'local/eventcalendar:managecalendars',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
