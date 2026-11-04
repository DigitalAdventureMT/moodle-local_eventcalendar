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
 * Site-wide list of the available calendars.
 *
 * The 'standard' page layout is used because the page is aimed at end users.
 *
 * Two views:
 * - Managers see all calendars, with status badges (manually hidden, in use
 *   according to the configured dates) and quick links to edit the calendar
 *   settings and manage its days.
 * - Other users see only the visible calendars whose access rules they satisfy.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\local\access;
use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\subscription;
use local_eventcalendar\local\bootstrap;

require_login();
require_capability('local/eventcalendar:view', context_system::instance());

$context = context_system::instance();
// Two management levels:
// - Content managers (managecontent or managecalendars) get the management view
// with all calendars, dates, status badges and the link to manage days.
// - Calendar managers (managecalendars only) also get the edit settings link,
// which leads to manage.php.
$canmanage = access::can_manage_content();
$canmanagecalendars = has_capability('local/eventcalendar:managecalendars', $context);

$PAGE->set_url('/local/eventcalendar/list.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_eventcalendar'));
$PAGE->set_heading(get_string('pluginname', 'local_eventcalendar'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('listheading', 'local_eventcalendar'));

// All calendars are read, ordered by start date. The visibility filter depends on
// the viewer's timezone and access rules, so it is applied in PHP afterwards.
$allcalendars = calendar::get_records([], 'startdate', 'ASC');

// Calendars the user is subscribed to, read with a single query for the card indicator.
$subscribedids = isguestuser() ? [] : subscription::get_subscribed_calendarids($USER->id);

if ($canmanage) {
    // Managers see all calendars, visible or not.
    $displayedcalendars = $allcalendars;
} else {
    // Check the cheaper visibility first, then the access rules.
    $displayedcalendars = array_filter($allcalendars, function (calendar $calendaritem) use ($USER): bool {
        return $calendaritem->is_visible() && access_rules::user_can_access($calendaritem, $USER);
    });
}

if (empty($displayedcalendars)) {
    echo $OUTPUT->notification(get_string('nocalendarsavailable', 'local_eventcalendar'), 'info');
} else {
    echo html_writer::start_div('row', ['role' => 'list']);

    foreach ($displayedcalendars as $calendaritem) {
        $calendarid = $calendaritem->get('id');
        $calendarurl = new moodle_url('/local/eventcalendar/view.php', ['id' => $calendarid]);

        // The whole card is clickable through the Bootstrap stretched-link. The admin
        // icons and badges below use position-relative and a higher z-index to stay
        // clickable above the pseudo-element covering the card.
        $bannerurl = $calendaritem->get_banner_url();
        $bannerhtml = $bannerurl
            ? html_writer::empty_tag('img', [
                'src' => $bannerurl->out(false),
                'alt' => '',
                'role' => 'presentation',
                'class' => 'mb-2 local-eventcalendar-banner',
            ])
            : '';

        // An h3 below the h2 of the page, styled as h5 to keep the card compact.
        $titlehtml = $bannerhtml . html_writer::tag(
            'h3',
            html_writer::link($calendarurl, format_string($calendaritem->get('name')), [
                'class' => 'stretched-link text-decoration-none text-reset',
            ]),
            ['class' => 'card-title h5 mb-0 text-center']
        );

        if ($canmanage) {
            // Dates are always read in UTC, the plugin's canonical reference, and
            // formatted according to the user's language.
            $dateformat = get_string('strftimedatefullshort', 'langconfig');
            $startdatelabel = userdate($calendaritem->get('startdate'), $dateformat, 'UTC');
            $enddatelabel = userdate($calendaritem->get('enddate'), $dateformat, 'UTC');

            $titlehtml .= html_writer::div(
                $startdatelabel . ' - ' . $enddatelabel,
                'text-center text-muted small'
            );
        }

        $adminextras = '';

        if ($canmanage) {
            // Top right corner: edit settings (managecalendars only) and manage days.
            $daysurl = new moodle_url('/local/eventcalendar/days.php', ['calendarid' => $calendarid]);

            $topicons = '';
            if ($canmanagecalendars) {
                $editurl = new moodle_url('/local/eventcalendar/manage.php', ['action' => 'edit', 'id' => $calendarid]);
                $topicons .= html_writer::link(
                    $editurl,
                    $OUTPUT->pix_icon('t/edit', get_string('editcalendarlink', 'local_eventcalendar')),
                    [
                        'class' => 'position-relative local-eventcalendar-overlink',
                        'title' => get_string('editcalendarlink', 'local_eventcalendar'),
                    ]
                ) . ' ';
            }
            $topicons .= html_writer::link(
                $daysurl,
                $OUTPUT->pix_icon('t/preferences', get_string('managedayslinktitle', 'local_eventcalendar')),
                [
                    'class' => 'position-relative local-eventcalendar-overlink',
                    'title' => get_string('managedayslinktitle', 'local_eventcalendar'),
                ]
            );

            $adminextras .= html_writer::div($topicons, 'position-absolute local-eventcalendar-corner-tr');

            // Bottom left corner: a single grey badge.
            // Moodle names the eye icons after the available action, not the current
            // state: t/hide (open eye) is used for the visible state and t/show
            // (crossed eye) for the hidden state.
            $ishidden = (bool) $calendaritem->get('forcehide');
            $eyeicon = $ishidden
                ? $OUTPUT->pix_icon('t/show', get_string('calendarhidden', 'local_eventcalendar'))
                : $OUTPUT->pix_icon('t/hide', get_string('calendarvisible', 'local_eventcalendar'));

            $badgecontent = html_writer::span($eyeicon, '', [
                'title' => $ishidden
                    ? get_string('calendarhidden', 'local_eventcalendar')
                    : get_string('calendarvisible', 'local_eventcalendar'),
            ]);

            // The calendar icon is added only when the calendar is in use according to its dates.
            if ($calendaritem->is_within_active_dates()) {
                $badgecontent .= ' ' . html_writer::span(
                    $OUTPUT->pix_icon('i/calendar', get_string('calendarinuse', 'local_eventcalendar')),
                    '',
                    ['title' => get_string('calendarinuse', 'local_eventcalendar')]
                );
            }

            $adminextras .= html_writer::div(
                html_writer::span($badgecontent, bootstrap::badge('secondary')),
                'position-absolute local-eventcalendar-corner-bl'
            );
        }

        // Notification subscription indicator, bottom right, only for subscribed calendars.
        $subscriptionbadge = '';
        if (in_array($calendarid, $subscribedids, true)) {
            $subscriptionbadge = html_writer::div(
                html_writer::span(get_string('notificationsactive', 'local_eventcalendar'), bootstrap::badge('success')),
                'position-absolute local-eventcalendar-corner-br'
            );
        }

        // Every card keeps room at the bottom for the corner badges (subscription, and for managers the status),
        // also when it has none, so that the title never runs under a badge and all cards have the same layout.
        // Managers also get room at the top for the icons in the top right corner.
        $bodyclasses = 'card-body local-eventcalendar-listcard' . ($canmanage ? ' local-eventcalendar-listcard-manage' : '');
        $cardcontent = html_writer::div($titlehtml . $adminextras . $subscriptionbadge, $bodyclasses);

        $card = html_writer::div($cardcontent, 'card h-100 position-relative');

        echo html_writer::div($card, 'col-md-4 mb-4', ['role' => 'listitem']);
    }

    echo html_writer::end_div();
}

echo $OUTPUT->footer();
