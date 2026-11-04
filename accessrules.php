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
 * Access rules page of a calendar.
 *
 * PHP prints only the page skeleton and two JSON blocks (condition type
 * configuration and saved rules); the AMD module local_eventcalendar/access_rules
 * builds the rest. Rules change only with the save button (web service
 * local_eventcalendar_save_access_rules, a single transaction).
 *
 * Data is passed in JSON script blocks rather than js_call_amd() arguments,
 * because Moodle discourages large arguments. The JSON_HEX_* flags stop a
 * text containing a closing script tag from ending the block.
 *
 * Restricted to users who manage calendars, like manage.php.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\persistent\calendar;
use local_eventcalendar\local\access_rules;

require_login();

$context = context_system::instance();
require_capability('local/eventcalendar:managecalendars', $context);

$calendarid = required_param('calendarid', PARAM_INT);

if (!calendar::record_exists($calendarid)) {
    throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
}

$calendar = new calendar($calendarid);
$calendarname = format_string($calendar->get('name'), true, ['context' => $context]);
$returnurl = new moodle_url('/local/eventcalendar/manage.php');

$PAGE->set_url('/local/eventcalendar/accessrules.php', ['calendarid' => $calendarid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('accessrulesheading', 'local_eventcalendar', $calendarname));
// The page heading is the site name, as on the admin pages of Moodle and on the other pages of the plugin; the
// title of the page is printed once, in the body.
$PAGE->set_heading(format_string($SITE->fullname));
navigation_node::override_active_url($returnurl);

$rootid = 'local-eventcalendar-access-rules';
$jsonflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$PAGE->requires->js_call_amd('local_eventcalendar/access_rules', 'init', [$rootid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('accessrulesheading', 'local_eventcalendar', $calendarname));

echo html_writer::tag('p', get_string('accessrulesintro', 'local_eventcalendar'), ['class' => 'text-muted']);

echo html_writer::tag('script', json_encode(access_rules::get_ui_config(), $jsonflags), [
    'type' => 'application/json',
    'id' => $rootid . '-config',
]);
echo html_writer::tag('script', json_encode(access_rules::get_ui_state($calendarid), $jsonflags), [
    'type' => 'application/json',
    'id' => $rootid . '-state',
]);

// Container managed by the JavaScript: page buttons on top, then the groups,
// then a second save button and the add group button at the bottom. The
// buttons use data-action because the JavaScript listens for clicks on the
// container, also for elements added later.
echo html_writer::start_div('', ['id' => $rootid, 'data-calendarid' => $calendarid]);

echo html_writer::div(
    html_writer::link(
        $returnurl,
        get_string('backtolist', 'local_eventcalendar'),
        ['class' => 'btn btn-secondary local-eventcalendar-me']
    )
    . html_writer::tag('button', get_string('accesssave', 'local_eventcalendar'), [
        'type' => 'button',
        'class' => 'btn btn-primary',
        'data-action' => 'save',
    ]),
    'mb-4'
);

echo html_writer::div('', '', ['data-region' => 'groups']);

echo html_writer::div(
    html_writer::tag('button', get_string('accesssave', 'local_eventcalendar'), [
        'type' => 'button',
        'class' => 'btn btn-primary local-eventcalendar-me',
        'data-action' => 'save',
    ])
    . html_writer::tag('button', get_string('accessaddgroup', 'local_eventcalendar'), [
        'type' => 'button',
        'class' => 'btn btn-secondary',
        'data-action' => 'add-group',
    ]),
    'mt-3'
);

echo html_writer::end_div();

echo $OUTPUT->footer();
