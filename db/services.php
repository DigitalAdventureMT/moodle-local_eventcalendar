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
 * Web service definitions of the plugin.
 *
 * Used only by the access rules page through core/ajax, with the logged-in
 * user's session. They are not added to any external service.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_eventcalendar_search_rule_subjects' => [
        'classname' => 'local_eventcalendar\external\search_rule_subjects',
        'description' => 'Search the subjects of an access condition (cohorts, courses, badges).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/eventcalendar:managecalendars',
    ],
    'local_eventcalendar_save_access_rules' => [
        'classname' => 'local_eventcalendar\external\save_access_rules',
        'description' => 'Save the access rules of a calendar.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/eventcalendar:managecalendars',
    ],
];
