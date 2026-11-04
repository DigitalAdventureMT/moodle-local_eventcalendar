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
 * Page of a single calendar day, reached from the calendar grid (view.php).
 *
 * Access checks, in order: the calendar and the day exist ("day" is the day number, not its id);
 * the calendar is visible and its access rules are satisfied; the day is unlocked, otherwise the
 * user is sent back to the grid with a warning. Users who can manage calendars bypass the last
 * two checks and see a notice when viewing something not reachable by regular users.
 *
 * Only the first opening of each day by each user is logged and triggers the day_opened event,
 * and only when the day is genuinely reachable by the viewer.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;
use local_eventcalendar\local\daycontent_renderer;
use local_eventcalendar\local\access;
use local_eventcalendar\local\access_rules;
use local_eventcalendar\event\day_opened;
use local_eventcalendar\local\bootstrap;

require_login();

$context = context_system::instance();
require_capability('local/eventcalendar:view', $context);

$calendarid = required_param('id', PARAM_INT);
$daynumber = required_param('day', PARAM_INT);

if (!calendar::record_exists($calendarid)) {
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

$calendar = new calendar($calendarid);
$dayitem = day::get_record(['calendarid' => $calendarid, 'daynumber' => $daynumber]);

if (!$dayitem) {
    throw new moodle_exception('errorinvaliddayid', 'local_eventcalendar');
}

// Managers can also see hidden calendars and locked days, with edit and preview links.
$canmanage = access::can_manage_content();

$gridurl = new moodle_url('/local/eventcalendar/view.php', ['id' => $calendarid]);
$pageurl = new moodle_url('/local/eventcalendar/view_day.php', ['id' => $calendarid, 'day' => $daynumber]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(format_string($calendar->get('name')));

// Access rules are part of visibility: a restricted calendar behaves like a hidden one, including for logging.
$calendarvisible = $calendar->is_visible() && access_rules::user_can_access($calendar, $USER);
$dayopen = $calendar->is_day_open($dayitem);

if (!$canmanage) {
    if (!$calendarvisible) {
        $PAGE->set_title(format_string($calendar->get('name')));

        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('calendarnotavailable', 'local_eventcalendar'), 'error');
        echo $OUTPUT->footer();
        exit;
    }

    if (!$dayopen) {
        redirect(
            $gridurl,
            get_string('daylockedmessage', 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Log only the first opening of each user, and only when the day is reachable by the viewer.
if ($calendarvisible && $dayopen) {
    $alreadyopened = $DB->record_exists('local_eventcalendar_log', [
        'dayid' => $dayitem->get('id'),
        'userid' => $USER->id,
    ]);

    if (!$alreadyopened) {
        $DB->insert_record('local_eventcalendar_log', (object) [
            'calendarid' => $calendarid,
            'dayid' => $dayitem->get('id'),
            'userid' => $USER->id,
            'timeopened' => time(),
        ]);

        // The event is triggered together with the log row, so once per user and day.
        day_opened::create([
            'objectid' => $dayitem->get('id'),
            'context' => $context,
            'other' => [
                'calendarid' => $calendarid,
                'daynumber' => $dayitem->get('daynumber'),
            ],
        ])->trigger();
    }
}

// The date is always read in UTC, as in the grid card.
$datelabel = userdate($dayitem->get('opendate'), get_string('strftimedaydate', 'local_eventcalendar'), 'UTC');

$PAGE->set_title(format_string($calendar->get('name')) . ': ' . $datelabel);

echo $OUTPUT->header();

// Top bar: back button, plus status and quick edit link for managers.
$backbutton = html_writer::link(
    $gridurl,
    get_string('backtocalendar', 'local_eventcalendar'),
    ['class' => 'btn btn-secondary']
);

$manageractions = '';
if ($canmanage) {
    $editurl = new moodle_url('/local/eventcalendar/days.php', [
        'calendarid' => $calendarid, 'editid' => $dayitem->get('id'),
    ], 'day-' . $dayitem->get('id'));

    $isready = (bool) $dayitem->get('isready');

    $statusbadge = html_writer::span(
        $isready ? get_string('dayready', 'local_eventcalendar') : get_string('dayinprogress', 'local_eventcalendar'),
        bootstrap::badge($isready ? 'success' : 'danger')
    );

    $editlink = html_writer::link(
        $editurl,
        $OUTPUT->pix_icon('t/edit', get_string('editdaylink', 'local_eventcalendar')),
        ['title' => get_string('editdaylink', 'local_eventcalendar')]
    );

    $manageractions = html_writer::div($statusbadge . ' ' . $editlink, 'd-flex align-items-center local-eventcalendar-gap-sm');
}

echo html_writer::div($backbutton . $manageractions, 'd-flex justify-content-between align-items-center mb-4');

// Tell managers when the day or calendar is not yet reachable by regular users.
if ($canmanage && (!$calendarvisible || !$dayopen)) {
    echo $OUTPUT->notification(get_string('managerpreviewnotice', 'local_eventcalendar'), 'info');
}

echo html_writer::tag('h2', format_string($datelabel), ['class' => 'h3 text-center mb-4']);

echo daycontent_renderer::render($dayitem, $calendar);

echo $OUTPUT->footer();
