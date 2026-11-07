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
 * Admin entries for local_eventcalendar.
 *
 * Creates a category under "Local plugins" with the global settings page (sending of the notifications)
 * and links (admin_externalpage) to the management and report pages. Everything else is configured per
 * calendar through dedicated forms.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The category and the links to the pages are added for every user: each page is shown only to users with its
// capability, so that the plugin roles (which do not have moodle/site:config) can reach their pages. The global
// settings are only for site administrators.
// Up to Moodle 4.5 the "Local plugins" category exists only for site administrators: for the other users it is
// created here, as Moodle 5.0 and later do.
if (!$ADMIN->locate('localplugins')) {
    $ADMIN->add('modules', new admin_category('localplugins', new lang_string('localplugins')));
}
$ADMIN->add(
    'localplugins',
    new admin_category('local_eventcalendar', get_string('pluginname', 'local_eventcalendar'))
);

// Global settings. The number of notifications sent by each run of the sending task spreads the
// emails over time, so that the sending domain is not flagged as a spam source; 0 means no limit.
if ($hassiteconfig) {
    $page = new admin_settingpage('local_eventcalendar_settings', get_string('settings', 'local_eventcalendar'));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_configtext(
            'local_eventcalendar/maxemailsperrun',
            get_string('maxemailsperrun', 'local_eventcalendar'),
            get_string('maxemailsperrun_desc', 'local_eventcalendar'),
            \local_eventcalendar\local\notification_dispatcher::DEFAULT_MAX_EMAILS,
            PARAM_INT
        ));

        // Maximum length of a calendar. All the days of a calendar are created in the request that saves it,
        // so the limit keeps that request and the pages listing the days within reasonable time and memory.
        // Only positive integers are accepted (the regular expression replaces PARAM_INT, which would also
        // accept 0 and negative values): there is deliberately no "no limit" value.
        $page->add(new admin_setting_configtext(
            'local_eventcalendar/maxdays',
            get_string('maxdays', 'local_eventcalendar'),
            get_string('maxdays_desc', 'local_eventcalendar'),
            \local_eventcalendar\local\calendar_generator::DEFAULT_MAX_DAYS,
            '/^[1-9][0-9]{0,4}$/',
            6
        ));
    }
    $ADMIN->add('local_eventcalendar', $page);
}

// Link to the calendar management page (manage.php).
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_manage',
        get_string('manage_calendars', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/manage.php'),
        'local/eventcalendar:managecalendars'
    )
);

// Link to the notification catalogue (notifications.php), covering both the notifications and the reusable
// sets grouping them. Same capability as manage.php: it is configuration shared by all calendars, not day
// content.
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_notifications',
        get_string('manage_notifications', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/notifications.php'),
        'local/eventcalendar:managecalendars'
    )
);

// Link to the iconset management (iconsets.php), with the same capability as the entries above.
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_iconsets',
        get_string('manage_iconsets', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/iconsets.php'),
        'local/eventcalendar:managecalendars'
    )
);

// Link to the email template management (email_templates.php): HTML wrapper used to send notifications by email.
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_emailtemplates',
        get_string('manage_emailtemplates', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/email_templates.php'),
        'local/eventcalendar:managecalendars'
    )
);

// Link to the calendar reports (report.php): counts per calendar and per day. The capability is separate from
// the management ones, to allow a reporting-only profile.
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_report',
        get_string('report', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/report.php'),
        'local/eventcalendar:viewreports'
    )
);

// Link to the graphics page (graphics.php): preloaded banners and header/footer pairs, read only.
$ADMIN->add(
    'local_eventcalendar',
    new admin_externalpage(
        'local_eventcalendar_graphics',
        get_string('manage_graphics', 'local_eventcalendar'),
        new moodle_url('/local/eventcalendar/graphics.php'),
        'local/eventcalendar:managecalendars'
    )
);

// The $settings variable must be set (even to null) so the core does not generate an extra admin_settingpage.
$settings = null;
