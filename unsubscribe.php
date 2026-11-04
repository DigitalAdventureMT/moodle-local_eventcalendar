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
 * Unsubscribe from the notifications of a calendar, from the link of a notification email.
 *
 * The page does not require login, so that the link works from any email client: the token of the
 * link (classes/local/unsubscribe.php) proves that it was sent to that user for that calendar, and
 * also protects the form instead of the session key.
 *
 * - GET shows a confirmation button. The subscription is never removed on GET, because email security
 *   scanners open the links of the emails they check.
 * - POST removes the subscription. This also handles the one-click unsubscribe of email clients
 *   (RFC 8058), which send a POST request to the List-Unsubscribe URL.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- The page is reached from emails, access is checked by the token.
require(__DIR__ . '/../../config.php');

use local_eventcalendar\local\subscription;
use local_eventcalendar\local\unsubscribe;
use local_eventcalendar\persistent\calendar;

$calendarid = required_param('calendar', PARAM_INT);
$userid = required_param('user', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUM);

$url = new moodle_url('/local/eventcalendar/unsubscribe.php', ['calendar' => $calendarid, 'user' => $userid, 'token' => $token]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('unsubscribenotifications', 'local_eventcalendar'));
$PAGE->set_heading(format_string($SITE->fullname));

// An invalid token and a calendar that no longer exists give the same message, so that the page does
// not reveal which calendars exist.
$valid = unsubscribe::is_valid_token($calendarid, $userid, $token) && calendar::record_exists($calendarid);
if ($valid) {
    $calendar = new calendar($calendarid);
    $name = format_string($calendar->get('name'), true, ['context' => context_system::instance()]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unsubscribenotifications', 'local_eventcalendar'));

if (!$valid) {
    echo $OUTPUT->notification(get_string('unsubscribeinvalid', 'local_eventcalendar'), 'error');
} else if (data_submitted()) {
    subscription::unsubscribe($calendarid, $userid);
    echo $OUTPUT->notification(get_string('unsubscribedone', 'local_eventcalendar', $name), 'success');
} else if (!subscription::is_subscribed($calendarid, $userid)) {
    echo $OUTPUT->notification(get_string('unsubscribenotsubscribed', 'local_eventcalendar', $name), 'info');
} else {
    // Confirmation form: the parameters are in the action URL, the hidden field makes the POST body non-empty.
    echo html_writer::tag('p', get_string('unsubscribeconfirm', 'local_eventcalendar', $name));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    $label = get_string('unsubscribenotifications', 'local_eventcalendar');
    echo html_writer::tag('button', $label, ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
