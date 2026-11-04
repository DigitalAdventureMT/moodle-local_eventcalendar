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
 * Calendar reports: summary of all calendars, with their totals and the progress of the unlocked days.
 *
 * Only counts are shown, no data of single users. The name of each calendar links to a detailed report only if
 * another plugin provides one through the report_links hook; otherwise it is plain text.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\hook\report_links;
use local_eventcalendar\local\report_summary;
use local_eventcalendar\local\report_view;

require_login();

// This also checks the capability set for this page in settings.php, and sets context, URL, layout and breadcrumb.
admin_externalpage_setup('local_eventcalendar_report');

$context = context_system::instance();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'local_eventcalendar'));

// Summary of all calendars, the most recent first.
$table = new html_table();
$table->caption = get_string('reportcalendars', 'local_eventcalendar');
$table->captionhide = true;
$table->head = [
    get_string('calendarname', 'local_eventcalendar'),
    get_string('startdate', 'local_eventcalendar'),
    get_string('reportunlockeddays', 'local_eventcalendar'),
    get_string('reportsubscribers', 'local_eventcalendar'),
    get_string('reportvisitors', 'local_eventcalendar'),
    get_string('reportopenings', 'local_eventcalendar'),
    get_string('reportsent', 'local_eventcalendar'),
    get_string('reportnotsent', 'local_eventcalendar'),
];
$table->attributes['class'] = 'generaltable';
// The table has many columns and can scroll horizontally. The scrolling wrapper is written here instead of the
// responsive one of html_writer::table(): without the add-on the rows contain no link, so the wrapper must be
// focusable itself (tabindex), otherwise keyboard users could not scroll it (WCAG 2.1.1, axe rule
// scrollable-region-focusable). The region takes the name of the table, so screen readers announce it.
$table->responsive = false;

foreach (report_summary::get_calendars() as $row) {
    $rowname = format_string($row->name, true, ['context' => $context]);

    // No detailed report here: other plugins can provide the address of one; without it the name is plain text.
    $linkhook = new report_links((int) $row->id, null);
    \core\di::get(\core\hook\manager::class)->dispatch($linkhook);
    $rowurl = $linkhook->get_calendar_url();

    $table->data[] = [
        $rowurl ? html_writer::link($rowurl, $rowname) : $rowname,
        userdate($row->startdate, get_string('strftimedate', 'langconfig')),
        report_view::progress((int) $row->unlockeddays, (int) $row->totaldays),
        $row->subscribers,
        $row->visitors,
        $row->openings,
        $row->sent,
        $row->notsent,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('nocalendars', 'local_eventcalendar'), 'info');
} else {
    echo html_writer::div(html_writer::table($table), 'table-responsive', [
        'tabindex' => '0',
        'role' => 'region',
        'aria-label' => get_string('reportcalendars', 'local_eventcalendar'),
    ]);
}

echo $OUTPUT->footer();
