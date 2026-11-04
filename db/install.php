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
 * Installation routine of local_eventcalendar.
 *
 * Creates the two custom roles, both defined at system context level:
 *
 * - eventcalendarcoordinator (calendar coordinator): creates and configures calendars. Receives
 *   managecalendars and viewreports.
 * - eventcalendarcurator (content curator): manages only the content of the day boxes. Receives
 *   managecontent and viewreports.
 *
 * local/eventcalendar:view is not assigned to these roles, because db/access.php already grants it to the
 * authenticated user archetype. Both roles can only be assigned at system level, not per calendar.
 *
 * It then imports the elements shipped with the plugin (icon sets, email templates, banners, header/footer
 * pairs) and fills the date lookup table with the dates of the templates that cannot be calculated, so that
 * those templates can be used right after the installation.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin installation function.
 *
 * Run once, at first installation. The record_exists check before each create_role avoids duplicates if
 * the routine is run again (for example after a backup restore that already includes the roles).
 */
function xmldb_local_eventcalendar_install() {
    global $DB;

    // Moodle runs install.php before syncing the capabilities declared in db/access.php, so they must be
    // updated explicitly or assign_capability() fails with "Capability ... was not found!".
    update_capabilities('local_eventcalendar');

    // Roles definition: shortname, name and description strings (always from the language file) and the
    // capabilities to allow at system context. "view" is listed explicitly so the roles keep working even if
    // an admin removes it from authenticated users.
    $roles = [
        'eventcalendarcoordinator' => [
            'name'         => get_string('role_eventcalendarcoordinator_name', 'local_eventcalendar'),
            'description'  => get_string('role_eventcalendarcoordinator_desc', 'local_eventcalendar'),
            'capabilities' => [
                'local/eventcalendar:managecalendars',
                'local/eventcalendar:viewreports',
                'local/eventcalendar:view',
            ],
        ],
        'eventcalendarcurator' => [
            'name'         => get_string('role_eventcalendarcurator_name', 'local_eventcalendar'),
            'description'  => get_string('role_eventcalendarcurator_desc', 'local_eventcalendar'),
            'capabilities' => [
                'local/eventcalendar:managecontent',
                'local/eventcalendar:viewreports',
                'local/eventcalendar:view',
            ],
        ],
    ];

    $systemcontext = context_system::instance();

    foreach ($roles as $shortname => $definition) {
        // Do not recreate the role if it already exists.
        if ($DB->record_exists('role', ['shortname' => $shortname])) {
            continue;
        }

        // No archetype is passed: the roles are specific to the plugin.
        $roleid = create_role(
            $definition['name'],
            $shortname,
            $definition['description'],
            ''
        );

        // The role can only be assigned at system context.
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        // Allow each capability of the role at system context.
        foreach ($definition['capabilities'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }
    }

    // Seed the standard iconsets found in pix/iconsets/. An error (for example a malformed iconset.json)
    // must not make the installation fail; it is only reported, and the rescan button in iconsets.php
    // remains available.
    try {
        \local_eventcalendar\local\iconset_scanner::scan();
    } catch (\Throwable $e) {
        debugging(
            'Automatic scan of standard icon sets failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }

    // Seed the email templates found in email_templates/, with the same error handling as the iconsets.
    try {
        \local_eventcalendar\local\email_template_scanner::scan();
    } catch (\Throwable $e) {
        debugging(
            'Automatic scan of email templates failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }

    // Seed the banners found in pix/banners/, with the same error handling as the iconsets.
    try {
        \local_eventcalendar\local\banner_scanner::scan();
    } catch (\Throwable $e) {
        debugging(
            'Automatic scan of preloaded banners failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }

    // Seed the header/footer pairs found in pix/headerfooters/, with the same error handling as the iconsets.
    try {
        \local_eventcalendar\local\headerfooter_scanner::scan();
    } catch (\Throwable $e) {
        debugging(
            'Automatic scan of header/footer pairs failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }

    // Seed the dates of the lookup_table templates. Like the scans above, this is not critical: without the
    // rows the calendar form simply does not propose default dates for the template.
    try {
        local_eventcalendar_seed_date_lookup();
    } catch (\Throwable $e) {
        debugging(
            'Seeding of the date lookup table failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }
}

/**
 * Fills local_eventcalendar_datelook with the dates of the templates whose date cannot be calculated.
 *
 * The only such template is chinese_newyear (date_rule.type = lookup_table): the first day of the lunar new
 * year follows the lunisolar calendar and has no simple Gregorian formula. The dates from 2026 to 2040 are
 * listed here; later years can be added by a future version of the plugin, while years without a row only
 * lose the default dates of the calendar form (the dates can always be entered by hand).
 *
 * Dates are stored as midnight UTC, the convention of all the date resolvers (see the lookup_table class),
 * so gmmktime() is used instead of make_timestamp(), which would depend on the server timezone.
 *
 * The function is idempotent: a row already present for a template and year is left unchanged, so a value
 * corrected by an administrator is never overwritten and running it again does not violate the unique
 * index on templatekey and eventyear.
 *
 * @return int number of rows inserted
 */
function local_eventcalendar_seed_date_lookup(): int {
    global $DB;

    // Gregorian date (month, day) of the first day of the Chinese new year, by year.
    $dates = [
        'chinese_newyear' => [
            2026 => [2, 17],
            2027 => [2, 6],
            2028 => [1, 26],
            2029 => [2, 13],
            2030 => [2, 3],
            2031 => [1, 23],
            2032 => [2, 11],
            2033 => [1, 31],
            2034 => [2, 19],
            2035 => [2, 8],
            2036 => [1, 28],
            2037 => [2, 15],
            2038 => [2, 4],
            2039 => [1, 24],
            2040 => [2, 12],
        ],
    ];

    $now = time();
    $inserted = 0;

    foreach ($dates as $templatekey => $years) {
        foreach ($years as $year => [$month, $day]) {
            if ($DB->record_exists('local_eventcalendar_datelook', ['templatekey' => $templatekey, 'eventyear' => $year])) {
                continue;
            }

            // The rows are created by the installation, not by a user: usercreated and usermodified stay 0.
            $DB->insert_record('local_eventcalendar_datelook', (object) [
                'templatekey' => $templatekey,
                'eventyear' => $year,
                'eventdate' => gmmktime(0, 0, 0, $month, $day, $year),
                'usercreated' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => 0,
            ]);
            $inserted++;
        }
    }

    return $inserted;
}
