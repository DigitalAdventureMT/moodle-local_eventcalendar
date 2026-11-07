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
 * Calendar management: list, create, edit and delete.
 *
 * Other plugins can add actions to the rows of the list and blocks below the
 * table (calendar_list hook), and fields to the calendar form.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\hook\calendar_list;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\form\calendar_form;
use local_eventcalendar\local\template_repository;
use local_eventcalendar\local\calendar_manager;
use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\publish_snippets;

require_login();

// This also checks the capability set for this page in settings.php, and sets context, URL, layout and breadcrumb.
admin_externalpage_setup('local_eventcalendar_manage');

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$returnurl = new moodle_url('/local/eventcalendar/manage.php');

switch ($action) {
    case 'create':
        $persistent = new calendar(0);
        $form = new calendar_form(
            new moodle_url('/local/eventcalendar/manage.php', ['action' => 'create']),
            ['persistent' => $persistent]
        );

        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $form->get_data()) {
            // The gallery radios are not tracked by mform, so the page reads the choices and the service
            // saves the calendar and its days.
            calendar_manager::save(
                $persistent,
                $data,
                optional_param('bannerchoice', '', PARAM_ALPHANUMEXT),
                optional_param('headerfooterchoice', 'none', PARAM_ALPHANUMEXT)
            );

            redirect(
                $returnurl,
                get_string('calendarcreated', 'local_eventcalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else if (optional_param('loadtemplate', 0, PARAM_RAW)) {
            // No-submit button: the form was already posted, so QuickForm would give the
            // submitted values precedence over set_data(). Redirect to a clean GET with the
            // template in the query string, so the defaults can be applied and stay editable.
            $templatekey = optional_param('templatekey', '', PARAM_ALPHANUMEXT);
            $redirectparams = [
                'action' => 'create',
                'creationmode' => 'template',
                'templatekey' => $templatekey,
            ];
            // The language already chosen in the form is kept across the reload.
            $chosenlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
            if ($chosenlanguage !== '') {
                $redirectparams['language'] = $chosenlanguage;
            }
            redirect(new moodle_url('/local/eventcalendar/manage.php', $redirectparams));
        } else {
            // Clean GET (first display or after the redirect above): prefill the defaults
            // from the template in the query string, if any.
            $creationmode = optional_param('creationmode', 'custom', PARAM_ALPHA);
            $templatekey = optional_param('templatekey', '', PARAM_ALPHANUMEXT);

            $defaults = null;

            if ($creationmode === 'template' && $templatekey !== '') {
                $template = template_repository::get($templatekey);

                if ($template) {
                    $defaults = template_repository::compute_calendar_defaults($template);
                    $defaults->creationmode = 'template';
                }
            }

            if ($defaults === null) {
                $defaults = new stdClass();
            }

            // Language kept from the form before the reload (see the loadtemplate branch above).
            $requestedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
            if ($requestedlanguage !== '') {
                $defaults->language = $requestedlanguage;
            }

            $form->set_data($defaults);
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('createcalendar', 'local_eventcalendar'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'edit':
        if (!calendar::record_exists($id)) {
            throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
        }

        $persistent = new calendar($id);
        $form = new calendar_form(
            new moodle_url('/local/eventcalendar/manage.php', ['action' => 'edit', 'id' => $id]),
            ['persistent' => $persistent]
        );

        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $form->get_data()) {
            // The validation of the form has already required the confirmation if the new dates shorten
            // the calendar. The service saves the calendar and aligns its days.
            $result = calendar_manager::save(
                $persistent,
                $data,
                optional_param('bannerchoice', '', PARAM_ALPHANUMEXT),
                optional_param('headerfooterchoice', 'none', PARAM_ALPHANUMEXT)
            );

            if ($result->restoverwritten > 0) {
                \core\notification::add(
                    get_string('restdayoverwritten', 'local_eventcalendar', $result->restoverwritten),
                    \core\output\notification::NOTIFY_INFO
                );
            }

            redirect(
                $returnurl,
                get_string('calendarupdated', 'local_eventcalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcalendar', 'local_eventcalendar', format_string($persistent->get('name'))));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'delete':
        if (!calendar::record_exists($id)) {
            throw new moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
        }

        $persistent = new calendar($id);

        if (!$confirm) {
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirmdeletecalendar', 'local_eventcalendar', format_string($persistent->get('name'))),
                new moodle_url('/local/eventcalendar/manage.php', [
                    'action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey(),
                ]),
                $returnurl
            );
            echo $OUTPUT->footer();
            break;
        }

        require_sesskey();

        // The service deletes the calendar with its days, logs, subscriptions, access rules and files, and
        // dispatches the hooks that let other plugins delete their own data.
        calendar_manager::delete($persistent);

        redirect(
            $returnurl,
            get_string('calendardeleted', 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'list':
    default:
        // Publish column: dialogs with the HTML snippets to copy.
        $PAGE->requires->js_call_amd('local_eventcalendar/publish_dialog', 'init');

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('manage_calendars', 'local_eventcalendar'));

        // Links that open in a new window carry the standard icon, whose text tells it to screen readers too.
        $newwindowicon = ' ' . $OUTPUT->pix_icon('i/externallink', get_string('opensinnewwindow'));
        $newwindow = ['target' => '_blank', 'rel' => 'noopener'];

        // Main action, and a link to the list of calendars as users see it (list.php), in a new window.
        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/eventcalendar/manage.php', ['action' => 'create']),
                get_string('createcalendar', 'local_eventcalendar'),
                ['class' => 'btn btn-primary']
            )
            . html_writer::link(
                new moodle_url('/local/eventcalendar/list.php'),
                get_string('userview', 'local_eventcalendar') . $newwindowicon,
                ['class' => 'btn btn-secondary'] + $newwindow
            ),
            'd-flex flex-wrap local-eventcalendar-gap-sm mb-3'
        );

        $calendars = calendar::get_records([], 'name', 'ASC');

        // Other plugins can add actions to the rows and blocks under the table.
        $listhook = new calendar_list($calendars);
        \core\di::get(\core\hook\manager::class)->dispatch($listhook);

        if (empty($calendars)) {
            echo $OUTPUT->notification(get_string('nocalendars', 'local_eventcalendar'), 'info');
        } else {
            $table = new html_table();
            $table->head = [
                get_string('calendarname', 'local_eventcalendar'),
                get_string('template', 'local_eventcalendar'),
                get_string('liststatus', 'local_eventcalendar'),
                get_string('accessrules', 'local_eventcalendar'),
                get_string('publish', 'local_eventcalendar'),
                get_string('listdays', 'local_eventcalendar'),
                get_string('actions', 'local_eventcalendar'),
            ];

            $statuslabels = [
                'hidden_manual' => get_string('statushiddenmanual', 'local_eventcalendar'),
                'not_started' => get_string('statusnotstarted', 'local_eventcalendar'),
                'expired' => get_string('statusexpired', 'local_eventcalendar'),
                'visible' => get_string('statusvisible', 'local_eventcalendar'),
            ];

            $dialogs = [];
            foreach ($calendars as $calendaritem) {
                // Name of the template, in the language of the user; the key is shown only if the template no longer
                // exists (definition file removed), so that the column is never empty.
                $templatekey = (string) $calendaritem->get('templatekey');
                if ($templatekey === '') {
                    $templatelabel = get_string('mode_custom', 'local_eventcalendar');
                } else {
                    $template = template_repository::get($templatekey);
                    $templatelabel = $template ? format_string($template->name) : s($templatekey);
                }

                $statuslabel = $statuslabels[$calendaritem->get_visibility_status()];

                // The calendar page as users see it, in a new window; managers also see hidden calendars and
                // locked days.
                $viewurl = new moodle_url('/local/eventcalendar/view.php', ['id' => $calendaritem->get('id')]);
                $editurl = new moodle_url('/local/eventcalendar/manage.php', [
                    'action' => 'edit', 'id' => $calendaritem->get('id'),
                ]);
                $deleteurl = new moodle_url('/local/eventcalendar/manage.php', [
                    'action' => 'delete', 'id' => $calendaritem->get('id'),
                ]);

                $actions = html_writer::link($viewurl, get_string('view') . $newwindowicon, $newwindow)
                    . ' | ' . html_writer::link($editurl, get_string('edit'));

                // Actions of other plugins come before the delete action, which stays last.
                foreach ($listhook->get_row_actions((int) $calendaritem->get('id')) as $extraaction) {
                    $actions .= ' | ' . $extraaction;
                }

                $actions .= ' | ' . html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']);

                // Publish column: the button opens the dialog with the snippets of this calendar. The
                // dialogs are printed after the table, a table cell being a poor place for them.
                $dialogid = 'local-eventcalendar-publish-' . $calendaritem->get('id');
                $publishbutton = html_writer::tag('button', get_string('publishcode', 'local_eventcalendar'), [
                    'type' => 'button',
                    'class' => 'btn btn-link p-0',
                    'data-action' => 'local-eventcalendar-publish-open',
                    'data-dialog' => $dialogid,
                    'aria-haspopup' => 'dialog',
                ]);
                // Each option gets its position ("2 of 7"), read by screen readers in the carousel.
                $snippets = publish_snippets::get_snippets($calendaritem);
                foreach ($snippets as $index => $snippet) {
                    $snippets[$index]['slidelabel'] = get_string('publishslidelabel', 'local_eventcalendar', (object) [
                        'index' => $index + 1,
                        'total' => count($snippets),
                    ]);
                }
                // The template escapes the title ({{title}}), so the name is formatted without escaping:
                // format_string() would otherwise turn "&" into "&amp;" and the dialog would show it literally.
                $dialogs[] = $OUTPUT->render_from_template('local_eventcalendar/publish_dialog', [
                    'id' => $dialogid,
                    'title' => get_string(
                        'publishtitle',
                        'local_eventcalendar',
                        format_string($calendaritem->get('name'), true, ['escape' => false])
                    ),
                    'snippets' => $snippets,
                ]);

                $accesscell = local_eventcalendar_access_rules_cell((int) $calendaritem->get('id'));

                $daysurl = new moodle_url('/local/eventcalendar/days.php', ['calendarid' => $calendaritem->get('id')]);
                $dayscount = $DB->count_records('local_eventcalendar_days', ['calendarid' => $calendaritem->get('id')]);
                $dayslink = $dayscount > 0
                    ? html_writer::link($daysurl, get_string('managedayslink', 'local_eventcalendar', $dayscount))
                    : html_writer::span(get_string('nodaysgenerated', 'local_eventcalendar'), 'text-muted');

                $table->data[] = [
                    format_string($calendaritem->get('name')),
                    $templatelabel,
                    $statuslabel,
                    $accesscell,
                    $publishbutton,
                    $dayslink,
                    $actions,
                ];
            }

            echo html_writer::table($table);
            echo implode('', $dialogs);
        }

        // Blocks added by other plugins under the table, also when there are no calendars.
        echo implode('', $listhook->get_footers());

        echo $OUTPUT->footer();
        break;
}

/**
 * Builds the access rules cell of the calendars table.
 *
 * It always contains a link to the rules page. Without rules it shows the open
 * to all label. With rules it shows a native details/summary element, which
 * works without JavaScript or Bootstrap classes, listing the groups: conditions
 * are joined by "and" and groups by "or". If any condition is no longer valid,
 * a red icon is shown and the condition is highlighted.
 *
 * @param int $calendarid
 * @return string HTML of the cell.
 */
function local_eventcalendar_access_rules_cell(int $calendarid): string {
    global $OUTPUT;

    $editurl = new moodle_url('/local/eventcalendar/accessrules.php', ['calendarid' => $calendarid]);
    $editlink = html_writer::link(
        $editurl,
        $OUTPUT->pix_icon('t/edit', get_string('accesseditrules', 'local_eventcalendar')),
        ['title' => get_string('accesseditrules', 'local_eventcalendar')]
    );

    $groups = access_rules::get_groups($calendarid);

    if (empty($groups)) {
        $content = html_writer::span(get_string('accessopentoall', 'local_eventcalendar'));
    } else {
        $hasinvalid = false;
        $items = [];
        $and = ' ' . html_writer::span(
            core_text::strtoupper(get_string('accessand', 'local_eventcalendar')),
            'local-eventcalendar-bold'
        ) . ' ';
        $or = html_writer::tag(
            'li',
            core_text::strtoupper(get_string('accessor', 'local_eventcalendar')),
            ['class' => 'local-eventcalendar-bold text-muted']
        );

        foreach ($groups as $index => $group) {
            $conditions = [];
            foreach ($group['rules'] as $rule) {
                $description = access_rules::describe_rule($rule);
                if (!access_rules::is_rule_valid($rule)) {
                    $hasinvalid = true;
                    $description = html_writer::span($description, 'text-danger');
                }
                $conditions[] = $description;
            }

            if ($index > 0) {
                $items[] = $or;
            }
            $items[] = html_writer::tag('li', implode($and, $conditions));
        }

        $count = count($groups);
        $summarytext = $count === 1
            ? get_string('accessgroupscountone', 'local_eventcalendar')
            : get_string('accessgroupscount', 'local_eventcalendar', $count);

        $icon = $hasinvalid
            ? $OUTPUT->pix_icon(
                'i/risk_dataloss',
                get_string('accesshasinvalidrules', 'local_eventcalendar'),
                'moodle',
                ['class' => 'text-danger']
            ) . ' '
            : '';

        $content = html_writer::tag(
            'details',
            html_writer::tag('summary', $icon . $summarytext, ['class' => 'local-eventcalendar-clickable'])
            . html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled small mt-1 mb-0'])
        );
    }

    return html_writer::div($editlink . $content, 'd-flex align-items-start local-eventcalendar-gap-xs');
}
