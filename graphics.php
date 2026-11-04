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
 * Lists the banners and header/footer pairs shipped with the plugin.
 *
 * The page is read-only: a single button runs both scanners to detect new files added on disk.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\persistent\banner;
use local_eventcalendar\persistent\headerfooter;
use local_eventcalendar\local\banner_scanner;
use local_eventcalendar\local\headerfooter_scanner;

require_login();

// This also checks the capability attached to the page in settings.php.
admin_externalpage_setup('local_eventcalendar_graphics');

$action = optional_param('action', 'list', PARAM_ALPHA);
$returnurl = new moodle_url('/local/eventcalendar/graphics.php');

switch ($action) {
    case 'scan':
        require_sesskey();

        $bannerresult = banner_scanner::scan();
        $headfootresult = headerfooter_scanner::scan();

        $totalcreated = count($bannerresult->created) + $headfootresult->created;

        if ($totalcreated > 0) {
            $message = get_string('graphicsscancreated', 'local_eventcalendar', $totalcreated);
        } else {
            $message = get_string('graphicsscannone', 'local_eventcalendar');
        }

        $allskipped = array_merge($bannerresult->skipped, $headfootresult->skipped);

        if (!empty($allskipped)) {
            $skiplines = [];
            foreach ($allskipped as $skip) {
                $skiplines[] = $skip->identifier . ': ' . $skip->reason;
            }
            $message .= ' ' . get_string('graphicsscanskipped', 'local_eventcalendar', implode('; ', $skiplines));
        }

        redirect($returnurl, $message, null, \core\output\notification::NOTIFY_INFO);
        break;

    case 'list':
    default:
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('manage_graphics', 'local_eventcalendar'));

        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/eventcalendar/graphics.php', ['action' => 'scan', 'sesskey' => sesskey()]),
                get_string('scangraphics', 'local_eventcalendar'),
                ['class' => 'btn btn-secondary mb-3']
            )
        );

        // Banners section.
        echo $OUTPUT->heading(get_string('sectionbanners', 'local_eventcalendar'), 3);

        $banners = banner::get_records([], 'name', 'ASC');

        if (empty($banners)) {
            echo $OUTPUT->notification(get_string('nobanners', 'local_eventcalendar'), 'info');
        } else {
            echo html_writer::start_div('row mb-4');

            foreach ($banners as $banneritem) {
                $cardheader = html_writer::div(
                    html_writer::span(format_string($banneritem->get('name')), 'local-eventcalendar-bold'),
                    'card-header'
                );

                $cardbody = html_writer::div(
                    html_writer::empty_tag('img', [
                        'src' => $banneritem->get_url()->out(false),
                        'alt' => format_string($banneritem->get('name')),
                        'class' => 'local-eventcalendar-graphic',
                    ]),
                    'card-body'
                );

                $card = html_writer::div($cardheader . $cardbody, 'card h-100');

                echo html_writer::div($card, 'col-md-4 mb-4');
            }

            echo html_writer::end_div();
        }

        // Header/footer pairs section.
        echo $OUTPUT->heading(get_string('sectionheaderfooters', 'local_eventcalendar'), 3);

        $pairs = headerfooter::get_records([], 'id', 'ASC');

        if (empty($pairs)) {
            echo $OUTPUT->notification(get_string('noheaderfooters', 'local_eventcalendar'), 'info');
        } else {
            echo html_writer::start_div('row');

            foreach ($pairs as $pairitem) {
                // Pairs have no name: the card shows only the header and footer previews.
                $cardbody = html_writer::div(
                    html_writer::empty_tag('img', [
                        'src' => $pairitem->get_header_url()->out(false),
                        'alt' => '',
                        'role' => 'presentation',
                        'class' => 'local-eventcalendar-graphic mb-2',
                    ])
                    . html_writer::empty_tag('img', [
                        'src' => $pairitem->get_footer_url()->out(false),
                        'alt' => '',
                        'role' => 'presentation',
                        'class' => 'local-eventcalendar-graphic',
                    ]),
                    'card-body'
                );

                $card = html_writer::div($cardbody, 'card h-100');

                echo html_writer::div($card, 'col-md-4 mb-4');
            }

            echo html_writer::end_div();
        }

        echo $OUTPUT->footer();
        break;
}
