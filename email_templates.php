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
 * Email template management: list, edit (name and language), delete, manual scan and default selection.
 *
 * Templates are created by scanning email_templates/{language}/{identifier}.html. Other plugins can add
 * actions to the list (emailtemplate_list hook) and fields to the form (emailtemplate_form_definition hook).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\hook\emailtemplate_list;
use local_eventcalendar\persistent\email_template;
use local_eventcalendar\form\email_template_form;
use local_eventcalendar\local\email_template_manager;
use local_eventcalendar\local\integrity;
use local_eventcalendar\local\email_template_scanner;

require_login();

// Admin_externalpage_setup checks the local/eventcalendar:managecalendars capability set in settings.php.
admin_externalpage_setup('local_eventcalendar_emailtemplates');

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$returnurl = new moodle_url('/local/eventcalendar/email_templates.php');

switch ($action) {
    case 'edit':
        if (!email_template::record_exists($id)) {
            throw new moodle_exception('errorinvalidemailtemplate', 'local_eventcalendar');
        }

        $persistent = new email_template($id);
        $form = new email_template_form(
            new moodle_url('/local/eventcalendar/email_templates.php', ['action' => 'edit', 'id' => $id]),
            ['persistent' => $persistent]
        );

        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $form->get_data()) {
            // The service saves the template and announces the save to other plugins.
            email_template_manager::save($persistent, $data);

            redirect(
                $returnurl,
                get_string('emailtplsaved', 'local_eventcalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editemailtpl', 'local_eventcalendar', format_string($persistent->get('name'))));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'setdefault':
        require_sesskey();

        if (!email_template::record_exists($id)) {
            throw new moodle_exception('errorinvalidemailtemplate', 'local_eventcalendar');
        }

        $persistent = new email_template($id);
        $persistent->set_as_default();

        redirect(
            $returnurl,
            get_string('emailtpldefaultset', 'local_eventcalendar', format_string($persistent->get('name'))),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'delete':
        if (!email_template::record_exists($id)) {
            throw new moodle_exception('errorinvalidemailtemplate', 'local_eventcalendar');
        }

        $persistent = new email_template($id);

        // A template assigned to calendars can be deleted: they go back to the default one. The user is warned,
        // with the list of the calendars involved, before confirming.
        $usedby = integrity::get_calendar_names_using_emailtemplate($id);

        if (!$confirm) {
            echo $OUTPUT->header();
            if (!empty($usedby)) {
                echo $OUTPUT->notification(
                    html_writer::tag('p', get_string('emailtplinuseintro', 'local_eventcalendar'), ['class' => 'mb-2'])
                        . html_writer::alist(array_map('format_string', array_values($usedby)), ['class' => 'mb-0']),
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            echo $OUTPUT->confirm(
                get_string('confirmdeleteemailtpl', 'local_eventcalendar', format_string($persistent->get('name'))),
                new moodle_url('/local/eventcalendar/email_templates.php', [
                    'action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey(),
                ]),
                $returnurl
            );
            echo $OUTPUT->footer();
            break;
        }

        require_sesskey();

        // The service deletes the template, sends the calendars that used it back to the default template and,
        // if it was the default, promotes another one so that a fallback always exists.
        email_template_manager::delete($persistent);

        redirect(
            $returnurl,
            get_string('emailtpldeleted', 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'scan':
        require_sesskey();

        $scanresult = email_template_scanner::scan();

        if (!empty($scanresult->created)) {
            $message = get_string('emailtplscancreated', 'local_eventcalendar', count($scanresult->created));
        } else {
            $message = get_string('emailtplscannone', 'local_eventcalendar');
        }

        if (!empty($scanresult->skipped)) {
            $skiplines = [];
            foreach ($scanresult->skipped as $skip) {
                $skiplines[] = $skip->identifier . ': ' . $skip->reason;
            }
            $message .= ' ' . get_string('emailtplscanskipped', 'local_eventcalendar', implode('; ', $skiplines));
        }

        redirect($returnurl, $message, null, \core\output\notification::NOTIFY_INFO);
        break;

    case 'list':
    default:
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('manage_emailtemplates', 'local_eventcalendar'));

        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/eventcalendar/email_templates.php', ['action' => 'scan', 'sesskey' => sesskey()]),
                get_string('scanemailtpl', 'local_eventcalendar'),
                ['class' => 'btn btn-secondary mb-3']
            )
        );

        $templates = email_template::get_records([], 'name', 'ASC');

        // Other plugins can add actions to the rows.
        $listhook = new emailtemplate_list($templates);
        \core\di::get(\core\hook\manager::class)->dispatch($listhook);

        if (empty($templates)) {
            echo $OUTPUT->notification(get_string('noemailtpl', 'local_eventcalendar'), 'info');
        } else {
            $table = new html_table();
            $table->head = [
                get_string('emailtplname', 'local_eventcalendar'),
                get_string('emailtpllanguage', 'local_eventcalendar'),
                get_string('emailtpldefault', 'local_eventcalendar'),
                get_string('actions', 'local_eventcalendar'),
            ];

            foreach ($templates as $templateitem) {
                $isdefault = (bool) $templateitem->get('isdefault');

                $namelabel = format_string($templateitem->get('name'));

                $editurl = new moodle_url('/local/eventcalendar/email_templates.php', [
                    'action' => 'edit', 'id' => $templateitem->get('id'),
                ]);
                $deleteurl = new moodle_url('/local/eventcalendar/email_templates.php', [
                    'action' => 'delete', 'id' => $templateitem->get('id'),
                ]);

                $actionlinks = [
                    html_writer::link($editurl, get_string('edit')),
                ];

                if (!$templateitem->get('isdefault')) {
                    $setdefaulturl = new moodle_url('/local/eventcalendar/email_templates.php', [
                        'action' => 'setdefault', 'id' => $templateitem->get('id'), 'sesskey' => sesskey(),
                    ]);
                    $actionlinks[] = html_writer::link($setdefaulturl, get_string('emailtplsetdefault', 'local_eventcalendar'));
                }

                // Actions of other plugins come before the delete action.
                foreach ($listhook->get_row_actions((int) $templateitem->get('id')) as $extraaction) {
                    $actionlinks[] = $extraaction;
                }

                $actionlinks[] = html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']);

                $languagelabel = strtoupper($templateitem->get('language'));
                $defaultlabel = $isdefault ? get_string('yes') : get_string('no');

                // The default template is the one used when a calendar has none chosen: name, language and
                // the default column are in bold so that it stands out in the list.
                if ($isdefault) {
                    $namelabel = html_writer::span($namelabel, 'local-eventcalendar-bold');
                    $languagelabel = html_writer::span($languagelabel, 'local-eventcalendar-bold');
                    $defaultlabel = html_writer::span($defaultlabel, 'local-eventcalendar-bold');
                }

                $table->data[] = [
                    $namelabel,
                    $languagelabel,
                    $defaultlabel,
                    implode(' | ', $actionlinks),
                ];
            }

            echo html_writer::table($table);
        }

        echo $OUTPUT->footer();
        break;
}
