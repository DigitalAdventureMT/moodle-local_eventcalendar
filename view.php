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
 * Grid of the days of a calendar.
 *
 * Access check: the calendar exists, calendar::is_visible() and the calendar access rules
 * (classes/local/access_rules.php); the last two are bypassed by users who can manage content.
 * Users who fail any of the checks see the generic "calendar not available" page (access::deny_calendar_view()),
 * identical for a missing and for an unreachable calendar, so it does not reveal that the calendar exists.
 * Managers get the explicit "invalid calendar" error instead, which helps them with wrong links.
 *
 * The grid is a standard Bootstrap grid: 4 cards per row on desktop, 2 on tablet, 1 on mobile.
 *
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;
use local_eventcalendar\local\access;
use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\subscription;
use local_eventcalendar\local\notification_dispatcher;
use local_eventcalendar\local\bootstrap;

require_login();
require_capability('local/eventcalendar:view', context_system::instance());

$calendarid = required_param('id', PARAM_INT);
$context = context_system::instance();

// Content managers also see hidden calendars and locked days, with the edit and preview links.
$canmanage = access::can_manage_content();

// The page is set up from the request parameters only, before anything is read from the calendar:
// the refusal page below needs it and must not depend on the calendar data.
$PAGE->set_url('/local/eventcalendar/view.php', ['id' => $calendarid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// A missing calendar is refused like an unreachable one, otherwise the error would tell which ids exist.
// Managers can see every existing calendar anyway, so for them the explicit error is kept.
$calendar = calendar::get_record(['id' => $calendarid]);
if (!$calendar) {
    if (!$canmanage) {
        access::deny_calendar_view();
    }
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

// Users who can manage content bypass the visibility and access rule checks, so they can review
// hidden or expired calendars. Everyone else is subject to both.
if (!$canmanage && (!$calendar->is_visible() || !access_rules::user_can_access($calendar, $USER))) {
    access::deny_calendar_view();
}

// Only from here on the page shows data of the calendar.
$PAGE->set_title(format_string($calendar->get('name')));
$PAGE->set_heading(format_string($calendar->get('name')));

// Subscribe/unsubscribe to the calendar notifications. State change: POST with a valid sesskey only,
// followed by a redirect with a confirmation message (post/redirect/get) so a refresh does not
// repeat the action. Guests have no subscription to manage.
$subscriptionaction = optional_param('subscriptionaction', '', PARAM_ALPHA);
if ($subscriptionaction !== '' && data_submitted() && !isguestuser()) {
    require_sesskey();

    $subscriptionmessage = null;
    if ($subscriptionaction === 'subscribe') {
        subscription::subscribe($calendarid, $USER->id);
        $subscriptionmessage = 'notificationssubscribed';
    } else if ($subscriptionaction === 'unsubscribe') {
        subscription::unsubscribe($calendarid, $USER->id);
        $subscriptionmessage = 'notificationsunsubscribed';
    }

    if ($subscriptionmessage !== null) {
        redirect(
            $PAGE->url,
            get_string($subscriptionmessage, 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Test sends of a day (corner icons, managers only). Both go only to the current user:
// - testemailday: the email of that day, as saved;
// - testnotificationday: the real notification, sent by the same provider used for subscribers.
// POST with a valid sesskey, then a reload with a message. Access is checked again here.
$testemaildayid = optional_param('testemailday', 0, PARAM_INT);
$testnotificationdayid = optional_param('testnotificationday', 0, PARAM_INT);

if (($testemaildayid || $testnotificationdayid) && data_submitted()) {
    require_sesskey();
    access::require_manage_content();

    $istestemail = (bool) $testemaildayid;
    $testdayid = $istestemail ? $testemaildayid : $testnotificationdayid;

    // The day must belong to this calendar.
    $testday = day::get_record(['id' => $testdayid, 'calendarid' => $calendarid]);
    if (!$testday) {
        throw new moodle_exception('errorinvaliddayid', 'local_eventcalendar');
    }

    if ($istestemail) {
        $testresult = notification_dispatcher::send_test_email($calendar, $testday, $USER);
        $testmessage = notification_dispatcher::get_test_email_message($testresult, $USER);
    } else {
        $testresult = notification_dispatcher::send_test_notification($calendar, $testday, $USER);
        $testmessage = notification_dispatcher::get_test_notification_message($testresult);
    }

    $testtypes = [
        notification_dispatcher::TEST_SENT => \core\output\notification::NOTIFY_SUCCESS,
        notification_dispatcher::TEST_EMPTY => \core\output\notification::NOTIFY_WARNING,
        notification_dispatcher::TEST_FAILED => \core\output\notification::NOTIFY_ERROR,
    ];

    redirect($PAGE->url, $testmessage, null, $testtypes[$testresult]);
}

$days = day::get_records(['calendarid' => $calendarid], 'daynumber', 'ASC');

// Localised date format, always read in UTC: the date a box represents is the same for everyone
// (see day::get_local_opendate()), unlike the unlock instant which depends on the user.
$dateformat = get_string('strftimedaydate', 'local_eventcalendar');

echo $OUTPUT->header();

$bannerurl = $calendar->get_banner_url();
if ($bannerurl) {
    echo html_writer::empty_tag('img', [
        'src' => $bannerurl->out(false),
        'alt' => '',
        'role' => 'presentation',
        // Margin larger than usual to keep a gap between the banner and the day boxes, since the
        // heading is already printed by the theme through $PAGE->set_heading().
        'class' => 'mb-4 local-eventcalendar-banner',
    ]);
}

if (!empty($calendar->get('tagline'))) {
    echo html_writer::tag('p', format_string($calendar->get('tagline')), ['class' => 'lead text-center mb-4']);
}

// The "intro" field is not shown here: it is an internal description for managers, while the
// public text for users is the tagline above.

// Notification subscribe/unsubscribe button, always shown. The same Bootstrap classes exist in
// Bootstrap 4 and 5. It is a small form with sesskey, handled at the top of this file.
if (!isguestuser()) {
    $issubscribed = subscription::is_subscribed($calendarid, $USER->id);

    echo html_writer::div(
        html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
        . html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'subscriptionaction',
            'value' => $issubscribed ? 'unsubscribe' : 'subscribe',
        ])
        . html_writer::tag(
            'button',
            get_string($issubscribed ? 'unsubscribenotifications' : 'subscribenotifications', 'local_eventcalendar'),
            ['type' => 'submit', 'class' => 'btn ' . ($issubscribed ? 'btn-outline-success' : 'btn-success')]
        )
        . html_writer::end_tag('form'),
        'text-center mb-4'
    );
}

if (empty($days)) {
    echo $OUTPUT->notification(get_string('nodaysgenerated', 'local_eventcalendar'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Builds a small icon button that POSTs (with sesskey) a test send for one day. It is the only
// place generating the markup of the two test icons (email and in-app notification). Sending
// messages is a state-changing action, so it is never a GET link.
$buildtestbutton = function (string $paramname, int $dayid, string $iconname, string $label) use ($OUTPUT, $PAGE): string {
    return html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $PAGE->url->out(false),
            'class' => 'local-eventcalendar-inlineform',
        ])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $paramname, 'value' => $dayid])
        . html_writer::tag(
            'button',
            $OUTPUT->pix_icon($iconname, $label),
            [
                'type' => 'submit',
                'class' => 'btn btn-link p-0 border-0 position-relative local-eventcalendar-overlink',
                'title' => $label,
            ]
        )
        . html_writer::end_tag('form') . ' ';
};

// A single Bootstrap .row with .justify-content-center, so every wrapped row (including an
// incomplete last one) is centred; the breakpoints (4, 2 and 1 cards per row) come from
// col-lg-3, col-md-6 and col-12 on each card. CSS columns are not used because they would break the
// chronological reading order.
echo html_writer::start_div('row justify-content-center', ['role' => 'list']);

foreach ($days as $dayitem) {
    $dayid = $dayitem->get('id');
    // Use is_day_open() rather than day::is_open(): it also honours the progressiveunlock flag of
    // the calendar (without progressive unlock every day is always reachable).
    $isopen = $calendar->is_day_open($dayitem);

    $isvisited = $isopen && $DB->record_exists('local_eventcalendar_log', [
        'dayid' => $dayid, 'userid' => $USER->id,
    ]);

    // Three states from a single coloured icon file, derived with CSS filters: locked is faded, not
    // visited is fully grayscale, visited keeps its colour.
    if (!$isopen) {
        $iconstate = 'local-eventcalendar-dayicon-locked';
        $statelabel = get_string('daystatelocked', 'local_eventcalendar');
    } else if (!$isvisited) {
        $iconstate = 'local-eventcalendar-dayicon-unvisited';
        $statelabel = get_string('daystateunvisited', 'local_eventcalendar');
    } else {
        $iconstate = '';
        $statelabel = get_string('daystatevisited', 'local_eventcalendar');
    }

    $datelabel = userdate($dayitem->get('opendate'), $dateformat, 'UTC');

    $iconurl = $dayitem->get_icon_url();
    $iconhtml = $iconurl
        ? html_writer::empty_tag('img', [
            'src' => $iconurl->out(false),
            'alt' => '',
            'role' => 'presentation',
            'class' => trim('local-eventcalendar-dayicon ' . $iconstate),
        ])
        : '';

    // The state is conveyed by the icon colour and repeated as hidden text, so that it is part of the
    // accessible name of the day link.
    $dateclasses = 'text-center mb-3 local-eventcalendar-bold h3 local-eventcalendar-daydate';
    $cardinner = html_writer::div(format_string($datelabel), $dateclasses)
        . html_writer::div($iconhtml, '', ['title' => $statelabel])
        . html_writer::span($statelabel, 'accesshide');

    $adminextras = '';

    if ($canmanage) {
        $editurl = new moodle_url('/local/eventcalendar/days.php', [
            'calendarid' => $calendarid, 'editid' => $dayid,
        ], 'day-' . $dayid);

        $editlink = html_writer::link(
            $editurl,
            $OUTPUT->pix_icon('t/edit', get_string('editdaylink', 'local_eventcalendar')),
            [
                'class' => 'position-relative local-eventcalendar-overlink',
                'title' => get_string('editdaylink', 'local_eventcalendar'),
            ]
        );

        // Preview link next to the pencil, only for days still locked: unlocked days are reached by
        // clicking the card. It lets managers review the content before it unlocks.
        $previewlink = '';
        if (!$isopen) {
            $previewurl = new moodle_url('/local/eventcalendar/view_day.php', [
                'id' => $calendarid, 'day' => $dayitem->get('daynumber'),
            ]);
            $previewlink = html_writer::link(
                $previewurl,
                // T/hide (open eye) is used because Moodle names these icons after the action, not the state.
                $OUTPUT->pix_icon('t/hide', get_string('previewdaylink', 'local_eventcalendar')),
                [
                    'class' => 'position-relative local-eventcalendar-overlink',
                    'title' => get_string('previewdaylink', 'local_eventcalendar'),
                ]
            ) . ' ';
        }

        // Envelope: sends only this day's email to the current user. Speech bubble: sends the real
        // notification to the current user. Order in the corner: envelope, bubble, eye, pencil.
        $testemail = $buildtestbutton(
            'testemailday',
            $dayid,
            'i/email',
            get_string('testemaillink', 'local_eventcalendar')
        );
        $testnotification = $buildtestbutton(
            'testnotificationday',
            $dayid,
            't/message',
            get_string('testnotificationlink', 'local_eventcalendar')
        );

        $adminextras .= html_writer::div(
            $testemail . $testnotification . $previewlink . $editlink,
            'position-absolute local-eventcalendar-corner-tr local-eventcalendar-corner-sm'
        );

        // Status badge: it needs real text or icon content, because an empty Bootstrap badge collapses to
        // zero size and is invisible.
        $readybadgeclass = bootstrap::badge($dayitem->get('isready') ? 'success' : 'danger');
        $readybadgesymbol = $dayitem->get('isready') ? '✓' : '✕';
        $readybadgelabel = $dayitem->get('isready')
            ? get_string('dayready', 'local_eventcalendar')
            : get_string('dayinprogress', 'local_eventcalendar');

        $adminextras .= html_writer::div(
            html_writer::span(
                html_writer::span($readybadgesymbol, '', ['aria-hidden' => 'true'])
                    . html_writer::span($readybadgelabel, 'accesshide'),
                $readybadgeclass,
                ['title' => $readybadgelabel]
            ),
            'position-absolute local-eventcalendar-corner-bl local-eventcalendar-corner-sm'
        );
    }

    if ($isopen) {
        $dayurl = new moodle_url('/local/eventcalendar/view_day.php', ['id' => $calendarid, 'day' => $dayitem->get('daynumber')]);
        $cardinner = html_writer::link($dayurl, $cardinner, [
            'class' => 'stretched-link text-decoration-none text-reset',
        ]);
    } else {
        // Locked: no link and an explicit not-allowed cursor.
        $cardinner = html_writer::div($cardinner, 'local-eventcalendar-locked');
    }

    // The classes "card h-100" give all cards of a row the same height; "py-5" adds vertical padding
    // and "shadow" adds the card shadow.
    $card = html_writer::div(
        html_writer::div($cardinner, 'card-body py-5 local-eventcalendar-daycard') . $adminextras,
        'card h-100 shadow position-relative'
    );

    echo html_writer::div($card, 'col-lg-3 col-md-6 col-12 mb-4', ['role' => 'listitem']);
}

echo html_writer::end_div();

echo $OUTPUT->footer();
