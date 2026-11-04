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
 * Capability definitions of local_eventcalendar.
 *
 * Moodle capabilities are evaluated by role and context, so the fine per-calendar access rules are checked at
 * runtime by the plugin instead. All capabilities are defined at system context:
 *
 * - local/eventcalendar:view: coarse access to the plugin, granted by default to authenticated users;
 * - local/eventcalendar:managecalendars: create, edit, delete and duplicate calendars;
 * - local/eventcalendar:managecontent: manage the content of the day boxes;
 * - local/eventcalendar:viewreports: view the calendar reports page, kept separate from the management
 *   capabilities to allow a reporting-only profile. The custom reports built on the Report Builder
 *   sources of the plugin are controlled by Report Builder itself (report audiences).
 *
 * The custom roles receive these capabilities in db/install.php.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Coarse access to the plugin, granted to authenticated users. Per-calendar rules are checked at runtime.
    'local/eventcalendar:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => 0,
        'archetypes'   => [
            'user'   => CAP_ALLOW,
            'guest'  => CAP_PREVENT,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Create, edit, delete and duplicate calendars, and manage notification sets, iconsets, email templates and
    // graphics. It also covers content management: content pages accept this capability or managecontent.
    // RISK_XSS is set besides RISK_CONFIG because email templates are output as unfiltered HTML (also in the
    // preview, on the site origin), so whoever can edit them can run scripts in the viewer's session.
    'local/eventcalendar:managecalendars' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_CONFIG | RISK_XSS,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage the content of the day boxes (day edit form, previews, calendar list in management view) without
    // creating, editing or deleting calendars.
    'local/eventcalendar:managecontent' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_XSS,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Calendar reports page: totals per calendar and per day, and the list of the users who opened each
    // day, hence RISK_PERSONAL. Kept independent from the other capabilities to separate duties.
    'local/eventcalendar:viewreports' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

];
