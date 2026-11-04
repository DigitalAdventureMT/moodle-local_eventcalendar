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
 * Preview of the day content or of the notification email for a single day.
 *
 * A bare page without Moodle header, footer or navigation, meant to be loaded in
 * an iframe inside the day management modal. It outputs only the rendered HTML
 * with minimal styling.
 *
 * The rendered HTML is not filtered: the email templates are full HTML documents and the day content
 * allows trusted HTML. To keep scripts out of the origin of the site, the response carries a
 * Content-Security-Policy sandbox without allow-scripts, so the browser runs no script and no event
 * handler of the page, also when it is opened directly in a tab instead of the iframe (the GET request
 * shows the saved version and needs no sesskey). The iframe of the modal has the same sandbox.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\persistent\day;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\local\daycontent_renderer;
use local_eventcalendar\local\email_renderer;
use local_eventcalendar\local\access;
use local_eventcalendar\local\notification_dispatcher;

require_login();
access::require_manage_content();

// The page context must be set explicitly: require_login() does not set it outside a
// course, and text filters that read $PAGE (such as the emoticon filter) would throw
// a coding_exception when format_text() runs in the renderers.
$PAGE->set_context(context_system::instance());

$dayid = required_param('dayid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

if (!in_array($type, ['content', 'email', 'testemail', 'testnotification'], true)) {
    throw new moodle_exception('errorinvalidpreviewtype', 'local_eventcalendar');
}

// The page URL is set explicitly: renderers and themes read it (for example while building the logo URL),
// and without it Moodle shows a debugging message. The sesskey is not part of it.
$PAGE->set_url(new moodle_url('/local/eventcalendar/preview.php', ['dayid' => $dayid, 'type' => $type]));

if (!day::record_exists($dayid)) {
    throw new moodle_exception('errorinvaliddayid', 'local_eventcalendar');
}

$day = new day($dayid);

if (!calendar::record_exists($day->get('calendarid'))) {
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

$calendar = new calendar($day->get('calendarid'));

// Live preview: on a POST request (the preview buttons of the day form submit here
// instead of saving) the day values are overridden in memory with the submitted
// ones, without touching the database. A plain GET shows the saved version.
//
// The test email and test notification buttons also use the submitted values. They
// send a message, so they require a POST request with a valid sesskey.
$istest = in_array($type, ['testemail', 'testnotification'], true);
if ($istest && !data_submitted()) {
    throw new moodle_exception('invalidrequest');
}
if ($istest) {
    require_sesskey();
}

// Values of the form typed and not yet saved. Other plugins read the fields they added to the form from them,
// through the day_cta hook, and clean them: this page does not know those fields.
$formvalues = [];

if (data_submitted() && confirm_sesskey()) {
    $formvalues = (array) data_submitted();
    unset($formvalues['sesskey']);

    $day->set('notiftitle', optional_param('notiftitle', $day->get('notiftitle'), PARAM_TEXT));

    // The form field is named 'notifbodytext', not 'notifbody', to avoid the
    // field plus field format heuristic of core\form\persistent.
    $day->set('notifbody', optional_param('notifbodytext', $day->get('notifbody'), PARAM_RAW));

    // The 'content' element is an editor submitting an array {text, format, itemid}.
    // Draft images use draftfile.php URLs already reachable in the current session, so
    // no rewriting is needed for the preview.
    $contentarray = optional_param_array('content', null, PARAM_RAW);
    if ($contentarray !== null && isset($contentarray['text'])) {
        $day->set('content', $contentarray['text']);
    }
}

if ($istest) {
    // Sent only to the current user: no request parameter can change the recipient.
    // The result is shown in the preview window, with a colour for each outcome.
    if ($type === 'testemail') {
        $result = notification_dispatcher::send_test_email($calendar, $day, $USER);
        $resultmessage = notification_dispatcher::get_test_email_message($result, $USER);
    } else {
        $result = notification_dispatcher::send_test_notification($calendar, $day, $USER);
        $resultmessage = notification_dispatcher::get_test_notification_message($result);
    }

    $colors = [
        notification_dispatcher::TEST_SENT => '#1e7e34',
        notification_dispatcher::TEST_EMPTY => '#856404',
        notification_dispatcher::TEST_FAILED => '#c0392b',
    ];

    $bodyhtml = html_writer::tag(
        'p',
        $resultmessage,
        ['style' => 'color:' . $colors[$result] . '; font-family:Arial,sans-serif; padding:20px; margin:0;']
    );
} else if ($type === 'content') {
    $bodyhtml = daycontent_renderer::render($day, $calendar, $formvalues);
} else {
    $bodyhtml = email_renderer::render($day, $calendar);

    if ($bodyhtml === null) {
        $bodyhtml = html_writer::tag(
            'p',
            get_string('errornodefaultemailtemplate', 'local_eventcalendar'),
            ['style' => 'color:#c0392b; font-family:Arial,sans-serif; padding:20px;']
        );
    }
}

// No header or footer on purpose: the bare page is meant for the iframe.
//
// The sandbox blocks every script of the page. allow-same-origin keeps the origin of the site, which
// is needed to load the images of the session (draft and plugin files) and to let the modal read the
// height of the page; without allow-scripts it gives the page no way to run code. The popup tokens
// let the links of the preview open in a new tab, outside the sandbox.
header('Content-Type: text/html; charset=utf-8');
header('Content-Security-Policy: sandbox allow-same-origin allow-popups allow-popups-to-escape-sandbox');
// The page is printed without $OUTPUT->header(), which sends this header for the other pages: without it,
// another site could load the preview in a frame. The modal of the site is on the same origin. As in the
// core, the header is left out when the administrator allows the site to be embedded in frames.
if (empty($CFG->allowframembedding)) {
    header('X-Frame-Options: SAMEORIGIN');
}
echo '<!DOCTYPE html><html lang="' . s(str_replace('_', '-', current_language())) . '"><head><meta charset="utf-8">'
    . '<title>' . s(get_string('previewwindow', 'local_eventcalendar')) . '</title>'
    . '<style>body{margin:16px; background-color:#f5f5f5; font-family:Arial,Helvetica,sans-serif;}</style>'
    . '</head><body>'
    . $bodyhtml
    . '</body></html>';
