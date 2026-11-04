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
 * Lists the days of a calendar and edits one day at a time.
 *
 * Days are shown read-only, except the one selected with ?editid=N, which
 * is replaced by the full edit form (classes/form/day_form.php).
 * Not part of the admin tree: access is checked with
 * access::require_manage_content() (managecontent or managecalendars).
 *
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;
use local_eventcalendar\persistent\notification_set;
use local_eventcalendar\form\day_form;
use local_eventcalendar\local\content_pack_repository;
use local_eventcalendar\local\calendar_generator;
use local_eventcalendar\local\day_manager;
use local_eventcalendar\local\access;
use local_eventcalendar\local\bootstrap;

require_login();
access::require_manage_content();

$calendarid = required_param('calendarid', PARAM_INT);
$editid = optional_param('editid', 0, PARAM_INT);

if (!calendar::record_exists($calendarid)) {
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

$calendar = new calendar($calendarid);
$context = context_system::instance();

$baseurl = new moodle_url('/local/eventcalendar/days.php', ['calendarid' => $calendarid]);

$PAGE->set_url($editid ? new moodle_url($baseurl, ['editid' => $editid]) : $baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managedays', 'local_eventcalendar', format_string($calendar->get('name'))));
// The page heading is the site name, as on the admin pages of Moodle and on the other pages of the plugin; the
// title of the page is printed once, in the body.
$PAGE->set_heading(format_string($SITE->fullname));

// Return page: the calendar management list for users who can manage
// calendars, the calendar list otherwise.
$returnurl = has_capability('local/eventcalendar:managecalendars', $context)
    ? new moodle_url('/local/eventcalendar/manage.php')
    : new moodle_url('/local/eventcalendar/list.php');
navigation_node::override_active_url($returnurl);

$days = array_values(day::get_records(['calendarid' => $calendarid], 'daynumber', 'ASC'));

if (empty($days)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managedays', 'local_eventcalendar', format_string($calendar->get('name'))));
    echo $OUTPUT->notification(get_string('nodaysgenerated', 'local_eventcalendar'), 'info');
    echo html_writer::link($returnurl, get_string('backtolist', 'local_eventcalendar'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    exit;
}

// Weekly rest day numbers, computed once for all days (empty when the
// calendar option is off). Used by the list and by the day being edited.
$restdaynumbers = calendar_generator::rest_daynumbers($calendar, $days);

// An editid that does not match a day of this calendar is ignored (stale
// link) and the page falls back to the read-only list.
$editday = null;
if ($editid) {
    foreach ($days as $candidate) {
        if ($candidate->get('id') === $editid) {
            $editday = $candidate;
            break;
        }
    }
}

// Pools for the "choose from pack" selects: titles and bodies come from the
// calendar notification set, contents from the pack of the template and of the
// language chosen in the calendar (never the language of the user). Empty pools
// hide the selects.
$titlepool = [];
$bodypool = [];
$contentpool = [];
$restcontentpool = [];

$notificationsetid = $calendar->get('notificationsetid');
if (!empty($notificationsetid) && notification_set::record_exists($notificationsetid)) {
    $notificationset = new notification_set($notificationsetid);
    // The texts are taken as they are copied into the days, with the images of the bodies pointing to their file.
    $titlepool = array_map(
        fn ($n) => $n->get_message_text(),
        array_values($notificationset->get_titles())
    );
    $bodypool = array_map(
        fn ($n) => $n->get_message_text(),
        array_values($notificationset->get_bodies())
    );
}

$pack = content_pack_repository::get_for_calendar($calendar);
if ($pack) {
    $contentpool = $pack->daycontents ?? [];
    $restcontentpool = $pack->restday_daycontents ?? [];
}

$form = null;

// Whether the edited day is a weekly rest day.
$isrestday = false;

if ($editday) {
    $editurl = new moodle_url($baseurl, ['editid' => $editid]);

    $isrestday = in_array($editday->get('daynumber'), $restdaynumbers, true);

    // In a rest day the content select offers only the rest day contents of
    // the pack, falling back to the generic ones when the pack has none.
    $editcontentpool = ($isrestday && !empty($restcontentpool)) ? $restcontentpool : $contentpool;

    $form = new day_form($editurl, [
        'persistent' => $editday,
        'calendar' => $calendar,
        'titlepool' => $titlepool,
        'bodypool' => $bodypool,
        'contentpool' => $editcontentpool,
        'isrestday' => $isrestday,
    ]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url($baseurl, [], 'day-' . $editid));
    } else if ($data = $form->get_data()) {
        // The service saves the day and its content files, and announces the save to other plugins.
        day_manager::save($editday, $data, $isrestday);

        // The save and continue button returns to the same day. It is
        // read with optional_param() because day_form removes it from the data.
        $saveandcontinue = optional_param('saveandcontinue', '', PARAM_RAW) !== '';

        redirect(
            $saveandcontinue
                ? new moodle_url($baseurl, ['editid' => $editid], 'day-' . $editid)
                : new moodle_url($baseurl, [], 'day-' . $editid),
            get_string('dayssaved', 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // The draft area of the content editor is prepared by
    // day_form::get_default_data().
}

// Behaviour of the edit form (pack selects, preview window, columns).
// The column layout comes from the form, so that elements added by other plugins can be placed too.
$PAGE->requires->js_call_amd(
    'local_eventcalendar/day_editor',
    'init',
    [$form ? $form->get_layout() : ['columns' => [], 'unwrapped' => []]]
);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('managedays', 'local_eventcalendar', format_string($calendar->get('name'))));
echo html_writer::div(
    html_writer::link($returnurl, get_string('backtolist', 'local_eventcalendar'), ['class' => 'btn btn-secondary mb-3'])
);

// Day heading, for example "Day 6 - Sunday 6/12/26". The weekday comes from
// the plugin language strings using the UTC date; rest days are shown in red, with a hidden text for
// screen readers since the colour alone does not convey the information.
$dayheading = function (day $dayforheading) use ($restdaynumbers): string {
    $weekdayname = s(get_string('weekday' . gmdate('w', (int) $dayforheading->get('opendate')), 'local_eventcalendar'));

    if (in_array($dayforheading->get('daynumber'), $restdaynumbers, true)) {
        $weekdayname = html_writer::span($weekdayname, 'text-danger')
            . html_writer::span(' (' . get_string('restdayweekday', 'local_eventcalendar') . ')', 'accesshide');
    }

    return get_string('dayheading', 'local_eventcalendar', (object) [
        'daynumber' => $dayforheading->get('daynumber'),
        'weekday' => $weekdayname,
        'opendate' => userdate($dayforheading->get('opendate'), get_string('strftimedatefullshort', 'langconfig'), 'UTC'),
    ]);
};

foreach ($days as $dayitem) {
    $dayid = $dayitem->get('id');

    echo html_writer::start_tag('div', ['id' => 'day-' . $dayid, 'class' => 'card mb-3']);
    echo html_writer::start_div('card-body');

    if ($editday && $editday->get('id') === $dayid) {
        // Edited day: show the full form instead of the row.
        echo $OUTPUT->heading($dayheading($dayitem), 3, 'h4');
        echo html_writer::start_div('eventcalendar-day-edit');
        $form->display();
        echo html_writer::end_div();
    } else {
        // Read-only day: same three columns as the edit form.
        $opendatelabel = userdate($dayitem->get('opendate'), get_string('strftimedatefullshort', 'langconfig'), 'UTC');

        $notiftitlepreview = $dayitem->get('notiftitle') ?: get_string('emptyfield', 'local_eventcalendar');
        // The texts are HTML: html_to_text() keeps a space between paragraphs and line breaks, which strip_tags()
        // would join into a single word. The result is reduced to one line, since the preview is truncated anyway.
        $plainpreview = function (?string $html): string {
            $text = html_to_text((string) $html, 0, false);
            return trim(preg_replace('/\s+/u', ' ', $text));
        };
        $notifbodypreview = $plainpreview($dayitem->get('notifbody')) ?: get_string('emptyfield', 'local_eventcalendar');
        $contentpreview = $plainpreview($dayitem->get('content')) ?: get_string('emptyfield', 'local_eventcalendar');
        $notespreview = $dayitem->get('notes') ?: get_string('emptyfield', 'local_eventcalendar');

        $truncate = function (string $text, int $length = 100): string {
            return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
        };

        echo html_writer::start_div('d-flex flex-wrap align-items-start local-eventcalendar-gap-lg');

        // Column 1: info, status, notes and edit button.
        echo html_writer::start_div('local-eventcalendar-daycol');
        echo html_writer::tag('h3', $dayheading($dayitem), ['class' => 'h5']);
        echo $dayitem->get('isready')
            ? html_writer::span(get_string('dayready', 'local_eventcalendar'), bootstrap::badge('success'))
            : html_writer::span(get_string('dayinprogress', 'local_eventcalendar'), bootstrap::badge('secondary'));
        echo html_writer::tag('p', '<strong>' . get_string('daynotes', 'local_eventcalendar') . ':</strong><br>'
            . format_string($truncate($notespreview)), ['class' => 'mt-2']);

        $editrowurl = new moodle_url($baseurl, ['editid' => $dayid], 'day-' . $dayid);
        // The hidden heading text tells screen reader users which day each "Edit" button refers to.
        echo html_writer::link(
            $editrowurl,
            get_string('edit') . html_writer::span(' ' . strip_tags($dayheading($dayitem)), 'accesshide'),
            ['class' => 'btn btn-primary btn-sm']
        );
        echo html_writer::end_div();

        // Icon column: the icon copied into the day file area, in full colour.
        $dayiconurl = $dayitem->get_icon_url();
        echo html_writer::start_div('local-eventcalendar-daycol-icon');
        if ($dayiconurl) {
            echo html_writer::empty_tag('img', [
                'src' => $dayiconurl->out(false),
                'alt' => '',
                'role' => 'presentation',
                'class' => 'local-eventcalendar-dayicon-small',
            ]);
        }
        echo html_writer::end_div();

        // Column 2: notification title and body.
        echo html_writer::start_div('local-eventcalendar-daycol');
        echo html_writer::tag('p', '<strong>' . get_string('notificationtitle', 'local_eventcalendar') . ':</strong><br>'
            . format_string($notiftitlepreview));
        echo html_writer::tag('p', '<strong>' . get_string('defaultmessage', 'local_eventcalendar') . ':</strong><br>'
            . format_string($truncate($notifbodypreview)));
        echo html_writer::end_div();

        // Column 3: content.
        echo html_writer::start_div('local-eventcalendar-daycol');
        echo html_writer::tag('p', '<strong>' . get_string('daycontent', 'local_eventcalendar') . ':</strong><br>'
            . format_string($truncate($contentpreview)));
        echo html_writer::end_div();

        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
