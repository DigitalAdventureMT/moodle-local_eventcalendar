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
 * Access checks, in order: the calendar exists; the calendar is visible and its access rules are
 * satisfied; the day exists ("day" is the day number, not its id); the day is unlocked, otherwise the
 * user is sent back to the grid with a warning. Users who can manage content bypass the visibility
 * and unlock checks and see a notice when viewing something not reachable by regular users.
 *
 * For everyone else a missing calendar, an unreachable calendar and a missing day all produce the same
 * generic page (access::deny_calendar_view()), and no data of the calendar is put in the page before
 * the checks have passed: the page cannot be used to read the name of a hidden calendar or to probe
 * which calendar ids and day numbers exist. The day existence is checked only after the visibility,
 * so it is never evaluated for a calendar the user cannot reach.
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
use local_eventcalendar\local\day_opening_log;
use local_eventcalendar\event\day_opened;
use local_eventcalendar\local\bootstrap;

require_login();

$context = context_system::instance();
require_capability('local/eventcalendar:view', $context);

$calendarid = required_param('id', PARAM_INT);
$daynumber = required_param('day', PARAM_INT);

// Managers can also see hidden calendars and locked days, with edit and preview links.
$canmanage = access::can_manage_content();

$gridurl = new moodle_url('/local/eventcalendar/view.php', ['id' => $calendarid]);
$pageurl = new moodle_url('/local/eventcalendar/view_day.php', ['id' => $calendarid, 'day' => $daynumber]);

// The page is set up from the request parameters only, before anything is read from the calendar:
// the refusal page needs it and must not depend on the calendar data. Title and heading, which show
// the calendar name, are set only after all the checks below.
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// Step 1: the calendar exists. A missing calendar is refused like an unreachable one, otherwise the
// error would tell which ids exist. Managers can see every existing calendar anyway, so for them the
// explicit error is kept, as it helps with wrong links.
$calendar = calendar::get_record(['id' => $calendarid]);
if (!$calendar) {
    if (!$canmanage) {
        access::deny_calendar_view();
    }
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

// Step 2: the calendar is reachable. Access rules are part of visibility: a restricted calendar behaves
// like a hidden one, including for logging. The value is kept for managers too, who pass the check but
// must not generate log rows for calendars that regular users cannot see.
$calendarvisible = $calendar->is_visible() && access_rules::user_can_access($calendar, $USER);
if (!$canmanage && !$calendarvisible) {
    access::deny_calendar_view();
}

// Step 3: the day exists. It is checked only now, so the existence of the days of a calendar the user
// cannot reach is never disclosed. For a reachable calendar a wrong day number gets the same page as
// the other refusals: it can only come from a hand-edited link.
$dayitem = day::get_record(['calendarid' => $calendarid, 'daynumber' => $daynumber]);
if (!$dayitem) {
    if (!$canmanage) {
        access::deny_calendar_view();
    }
    throw new moodle_exception('errorinvaliddayid', 'local_eventcalendar');
}

// Step 4: the day is unlocked. The user can already see the grid of this calendar, where every day is
// listed, so the redirect to the grid discloses nothing new.
$dayopen = $calendar->is_day_open($dayitem);
if (!$canmanage && !$dayopen) {
    redirect(
        $gridurl,
        get_string('daylockedmessage', 'local_eventcalendar'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Only from here on the page shows data of the calendar. The title, which also contains the day date,
// is set further down.
$PAGE->set_heading(format_string($calendar->get('name')));

// Log only the first opening of each user, and only when the day is reachable by the viewer.
// day_opening_log tells whether this request wrote the row: concurrent requests of the same user
// (double click, prefetch, reload) are resolved by the unique index, so only one of them gets true.
if ($calendarvisible && $dayopen) {
    $firstopening = day_opening_log::record_first_opening($calendarid, (int) $dayitem->get('id'), (int) $USER->id);

    if ($firstopening) {
        // The event is triggered only by the request that wrote the log row, so once per user and day.
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
