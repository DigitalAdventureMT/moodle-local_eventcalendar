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
 * Management page for notification sets: list, view, create from a pack, update from the pack, delete.
 *
 * A set is created from a standard pack (classes/form/notification_set_form.php): the user chooses the pack,
 * loads it to see its titles and bodies, and saves. The notifications are copied from the pack and cannot be
 * edited, so the set always matches the pack; when the pack gets a newer version the list offers to update the
 * set. Other plugins can add actions to the list and fields to the form (notificationset_list,
 * notificationset_form_definition hooks).
 *
 * Emails are always sent; the in-app notification is sent unless the user disabled it in the
 * Moodle notification preferences (rule defined by the message provider in db/messages.php).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_eventcalendar\hook\notificationset_list;
use local_eventcalendar\persistent\notification_set;
use local_eventcalendar\form\notification_set_form;
use local_eventcalendar\local\content_pack_repository;
use local_eventcalendar\local\bootstrap;
use local_eventcalendar\local\integrity;
use local_eventcalendar\local\notification_set_manager;

require_login();

// The capability local/eventcalendar:managecalendars is checked by admin_externalpage_setup().
admin_externalpage_setup('local_eventcalendar_notifications');

$context = context_system::instance();

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$returnurl = new moodle_url('/local/eventcalendar/notifications.php');

switch ($action) {
    case 'create':
        $persistent = new notification_set(0);

        // No-submit button: redirect to a clean GET with the chosen packkey, because after a submit
        // QuickForm would give priority to the submitted values over a later set_data().
        if (optional_param('loadpack', 0, PARAM_RAW)) {
            redirect(new moodle_url('/local/eventcalendar/notifications.php', [
                'action' => 'create', 'packkey' => optional_param('importpack', '', PARAM_TEXT),
            ]));
        }

        // The form action URL includes packkey, so it survives the final save POST and the form is rebuilt
        // in the same mode it was displayed with.
        $packkey = optional_param('packkey', '', PARAM_TEXT);
        $formactionparams = ['action' => 'create'];
        if ($packkey !== '') {
            $formactionparams['packkey'] = $packkey;
        }

        $form = new notification_set_form(
            new moodle_url('/local/eventcalendar/notifications.php', $formactionparams),
            ['persistent' => $persistent, 'packkey' => $packkey]
        );

        // Prefill only on first load: default name, editable before saving, made of the name of the template and
        // of the language of the pack, as shown in the pack list.
        if (!$form->is_submitted() && $form->is_from_pack()) {
            $pack = content_pack_repository::get($packkey);
            $form->set_data([
                'name' => content_pack_repository::get_label($pack),
                'importpack' => $packkey,
            ]);
        }

        if ($form->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $form->get_data()) {
            // The service creates the set with the notifications of the pack and announces the save to other plugins.
            notification_set_manager::save($persistent, $data);

            redirect(
                $returnurl,
                get_string('notificationsetsaved', 'local_eventcalendar'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('createnotificationset', 'local_eventcalendar'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'view':
        if (!notification_set::record_exists($id)) {
            throw new moodle_exception('errorinvalidnotificationset', 'local_eventcalendar');
        }

        $persistent = new notification_set($id);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('viewnotificationset', 'local_eventcalendar', format_string($persistent->get('name'))));

        if ($persistent->is_locked()) {
            echo $OUTPUT->notification(get_string('lockedsetnotice', 'local_eventcalendar'), 'info');
        }

        if ($persistent->get('description')) {
            echo html_writer::div(format_text($persistent->get('description'), FORMAT_PLAIN), 'mb-3');
        }

        $titlesinset = $persistent->get_titles();
        $bodiesinset = $persistent->get_bodies();

        echo $OUTPUT->heading(get_string('notificationtitlesinset', 'local_eventcalendar'), 3, 'h4');

        if (empty($titlesinset)) {
            echo $OUTPUT->notification(get_string('notitlesinset', 'local_eventcalendar'), 'info');
        } else {
            $titlelist = '';
            foreach ($titlesinset as $titleitem) {
                $titlelist .= html_writer::tag('li', format_string($titleitem->get('defaultmessage')));
            }
            echo html_writer::tag('ul', $titlelist, ['class' => 'mb-4']);
        }

        echo $OUTPUT->heading(get_string('notificationsinset', 'local_eventcalendar'), 3, 'h4');

        if (empty($bodiesinset)) {
            echo $OUTPUT->notification(get_string('nonotificationsinset', 'local_eventcalendar'), 'info');
        } else {
            foreach ($bodiesinset as $bodyitem) {
                // The text with the images pointing to their file, as it is copied into the days.
                $formattedcontent = format_text(
                    $bodyitem->get_message_text(),
                    $bodyitem->get('defaultmessageformat'),
                    ['context' => $context]
                );
                echo html_writer::div($formattedcontent, 'card card-body mb-3');
            }
        }

        echo html_writer::link($returnurl, get_string('backtolist', 'local_eventcalendar'), ['class' => 'btn btn-secondary']);

        echo $OUTPUT->footer();
        break;

    case 'syncpack':
        require_sesskey();

        if (!notification_set::record_exists($id)) {
            throw new moodle_exception('errorinvalidnotificationset', 'local_eventcalendar');
        }

        $set = new notification_set($id);
        $outcome = content_pack_repository::sync_set($set);

        if ($outcome === 'updated') {
            $message = get_string('packsyncupdatedone', 'local_eventcalendar', format_string($set->get('name')));
        } else if ($outcome !== null) {
            $message = $outcome;
        } else {
            $message = get_string('packsyncnone', 'local_eventcalendar');
        }

        redirect($returnurl, $message, null, \core\output\notification::NOTIFY_INFO);
        break;

    case 'delete':
        if (!notification_set::record_exists($id)) {
            throw new moodle_exception('errorinvalidnotificationset', 'local_eventcalendar');
        }

        $persistent = new notification_set($id);

        // A set assigned to calendars can be deleted: the calendars lose the reference and the user is warned,
        // with the list of the calendars involved, before confirming.
        $usedby = integrity::get_calendar_names_using_notificationset($id);

        if (!$confirm) {
            echo $OUTPUT->header();
            if (!empty($usedby)) {
                echo $OUTPUT->notification(
                    html_writer::tag('p', get_string('notificationsetinuseintro', 'local_eventcalendar'), ['class' => 'mb-2'])
                        . html_writer::alist(array_map('format_string', array_values($usedby)), ['class' => 'mb-0']),
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            echo $OUTPUT->confirm(
                get_string('confirmdeletenotificationset', 'local_eventcalendar', format_string($persistent->get('name'))),
                new moodle_url('/local/eventcalendar/notifications.php', [
                    'action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey(),
                ]),
                $returnurl
            );
            echo $OUTPUT->footer();
            break;
        }

        require_sesskey();

        // The service removes the references from the calendars, deletes the notifications of the set with
        // their files, then the set.
        notification_set_manager::delete($persistent);

        redirect(
            $returnurl,
            get_string('notificationsetdeleted', 'local_eventcalendar'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;

    case 'list':
    default:
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('notificationsets', 'local_eventcalendar'));

        $sets = notification_set::get_records([], 'name', 'ASC');

        // Other plugins can add buttons to the toolbar and actions to the rows.
        $listhook = new notificationset_list($sets);
        \core\di::get(\core\hook\manager::class)->dispatch($listhook);

        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/eventcalendar/notifications.php', ['action' => 'create']),
                get_string('createnotificationset', 'local_eventcalendar'),
                ['class' => 'btn btn-primary mb-3']
            ) . implode('', array_map(fn ($button) => ' ' . $button, $listhook->get_toolbar_buttons()))
        );

        if (empty($sets)) {
            echo $OUTPUT->notification(get_string('nonotificationsets', 'local_eventcalendar'), 'info');
        } else {
            $table = new html_table();
            $table->head = [
                get_string('notificationsetname', 'local_eventcalendar'),
                get_string('notificationtitlesinset', 'local_eventcalendar'),
                get_string('notificationsinset', 'local_eventcalendar'),
                get_string('actions', 'local_eventcalendar'),
            ];

            foreach ($sets as $setitem) {
                $titlecount = count($setitem->get_titles());
                $bodycount = count($setitem->get_bodies());

                $namelabel = format_string($setitem->get('name'));
                if ($setitem->is_locked()) {
                    $namelabel .= ' ' . html_writer::span(
                        get_string('lockedbadge', 'local_eventcalendar'),
                        bootstrap::badge('secondary')
                    );
                }

                $viewurl = new moodle_url('/local/eventcalendar/notifications.php', [
                    'action' => 'view', 'id' => $setitem->get('id'),
                ]);
                $deleteurl = new moodle_url('/local/eventcalendar/notifications.php', [
                    'action' => 'delete', 'id' => $setitem->get('id'),
                ]);

                $actionlinks = [html_writer::link($viewurl, get_string('view'))];

                // Actions of other plugins come before the delete action.
                foreach ($listhook->get_row_actions((int) $setitem->get('id')) as $extraaction) {
                    $actionlinks[] = $extraaction;
                }

                $actionlinks[] = html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']);

                // Per-row check: for an imported set whose pack on disk is newer than the saved one, add an update link.
                if ($setitem->is_locked()) {
                    $currentpack = content_pack_repository::get($setitem->get('sourcepack'));
                    $savedversion = (int) ($setitem->get('sourcepackversion') ?? 0);

                    if ($currentpack && $currentpack->version > $savedversion) {
                        $syncurl = new moodle_url('/local/eventcalendar/notifications.php', [
                            'action' => 'syncpack', 'id' => $setitem->get('id'), 'sesskey' => sesskey(),
                        ]);
                        $actionlinks[] = html_writer::link($syncurl, get_string('updatepack', 'local_eventcalendar'));
                    }
                }

                $table->data[] = [
                    $namelabel,
                    $titlecount,
                    $bodycount,
                    implode(' | ', $actionlinks),
                ];
            }

            echo html_writer::table($table);
        }

        echo $OUTPUT->footer();
        break;
}
