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
 * Icon sets: card list and scan of the standard sets.
 *
 * The standard sets are folders of pix/iconsets/ (of the plugin or of a graphics pack), imported by the scan;
 * they are shown read only. Custom sets, created by other plugins, are listed with them: those plugins add
 * their buttons and actions through the iconset_list hook.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\hook\iconset_list;
use local_eventcalendar\persistent\iconset;
use local_eventcalendar\local\bootstrap;

require_login();

// This also checks the capability attached to the page in settings.php.
admin_externalpage_setup('local_eventcalendar_iconsets');

$action = optional_param('action', 'list', PARAM_ALPHA);

$returnurl = new moodle_url('/local/eventcalendar/iconsets.php');

switch ($action) {
    case 'scan':
        require_sesskey();

        $scanresult = \local_eventcalendar\local\iconset_scanner::scan();

        if (!empty($scanresult->created)) {
            $message = get_string('iconsetscancreated', 'local_eventcalendar', count($scanresult->created));
        } else {
            $message = get_string('iconsetscannone', 'local_eventcalendar');
        }

        if (!empty($scanresult->skipped)) {
            $skiplines = [];
            foreach ($scanresult->skipped as $skip) {
                $skiplines[] = $skip->identifier . ': ' . $skip->reason;
            }
            $message .= ' ' . get_string('iconsetscanskipped', 'local_eventcalendar', implode('; ', $skiplines));
        }

        redirect($returnurl, $message, null, \core\output\notification::NOTIFY_INFO);
        break;

    case 'list':
    default:
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('manage_iconsets', 'local_eventcalendar'));

        $iconsets = iconset::get_records([], 'name', 'ASC');

        // Other plugins can add buttons to the toolbar and actions to the cards.
        $listhook = new iconset_list($iconsets);
        \core\di::get(\core\hook\manager::class)->dispatch($listhook);

        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/eventcalendar/iconsets.php', ['action' => 'scan', 'sesskey' => sesskey()]),
                get_string('scaniconsets', 'local_eventcalendar'),
                ['class' => 'btn btn-secondary mb-3']
            )
            . implode('', array_map(fn ($button) => ' ' . $button, $listhook->get_toolbar_buttons()))
        );

        if (empty($iconsets)) {
            echo $OUTPUT->notification(get_string('noiconsets', 'local_eventcalendar'), 'info');
        } else {
            echo html_writer::start_div('row');

            foreach ($iconsets as $iconsetitem) {
                $iconurls = $iconsetitem->get_icon_urls();

                $iconshtml = '';
                foreach ($iconurls as $iconurl) {
                    $iconshtml .= html_writer::empty_tag('img', [
                        'src' => $iconurl->out(false),
                        'alt' => '',
                        'class' => 'mb-2 local-eventcalendar-me local-eventcalendar-iconthumb',
                    ]);
                }

                if (empty($iconurls)) {
                    $iconshtml = html_writer::span(
                        get_string('noiconfiles', 'local_eventcalendar'),
                        'text-muted small'
                    );
                }

                $sourcelabel = $iconsetitem->is_custom()
                    ? get_string('sourcecustom', 'local_eventcalendar')
                    : get_string('sourcestandard', 'local_eventcalendar');

                $headerleft = html_writer::span(
                    format_string($iconsetitem->get('name')),
                    'local-eventcalendar-bold local-eventcalendar-me'
                ) . html_writer::span($sourcelabel, bootstrap::badge('secondary'));

                // Actions of other plugins: this page has none, standard sets are read only.
                $headeractions = implode('', array_map(
                    fn ($action) => ' ' . $action,
                    $listhook->get_row_actions((int) $iconsetitem->get('id'))
                ));
                $cardheader = html_writer::div(
                    html_writer::div($headerleft) . html_writer::div($headeractions),
                    'card-header d-flex justify-content-between align-items-center'
                );

                $cardbody = html_writer::div($iconshtml, 'card-body d-flex flex-wrap');

                $card = html_writer::div($cardheader . $cardbody, 'card h-100');

                echo html_writer::div($card, 'col-md-4 mb-4');
            }

            echo html_writer::end_div();
        }

        echo $OUTPUT->footer();
        break;
}
