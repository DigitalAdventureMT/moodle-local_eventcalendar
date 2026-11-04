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
 * Form to create a notification set from a content pack and to change its name and description.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\form;

use local_eventcalendar\hook\notificationset_form_definition;
use local_eventcalendar\hook\notificationset_form_validation;
use local_eventcalendar\persistent\notification_set;
use local_eventcalendar\local\content_pack_repository;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create a notification set from a content pack and to change its name and description.
 *
 * In this plugin a set is always created from a standard pack. For a new set the form shows the select of the
 * packs not yet imported and a "no submit" button that reloads the page with the chosen pack (packkey in the
 * custom data, see notifications.php). After the reload the form is "from pack": the name gets a default value
 * and the titles and bodies of the pack are shown read only, as static elements. They are not fields: when the
 * set is saved the notifications are read again from the pack on disk (notification_set_manager::save()), so a
 * set imported from a pack always matches it.
 *
 * The validation of a new set asks for a pack, which must be still available, and for the chosen pack to be the
 * one loaded: a pack chosen in the select but not loaded is refused, because what was shown would not be what
 * is saved. For an existing set the form only has name and description.
 *
 * Other plugins can add elements (notificationset_form_definition hook, dispatched before the buttons) and change
 * the errors (notificationset_form_validation hook). They can tell the two modes apart with is_from_pack().
 * The elements they add are not properties of the set: filter_data_for_persistent() keeps only the properties,
 * so they reach the validation of the persistent neither as errors nor as unknown fields, and they are still
 * returned by get_data().
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_set_form extends \core\form\persistent {
    /** @var string Persistent class of the form. */
    protected static $persistentclass = notification_set::class;

    /** @var array Fields removed from the data returned by get_data(). */
    protected static $fieldstoremove = ['submitbutton', 'loadpack'];

    /** @var \stdClass|null Pack loaded in the form, null when no pack has been loaded. */
    protected ?\stdClass $pack = null;

    /**
     * Form elements definition.
     */
    public function definition() {
        $mform = $this->_form;
        $persistent = $this->get_persistent();
        $isnew = empty($persistent->get('id'));

        // The pack is accepted only for a new set and only if it can still be imported.
        $packkey = (string) ($this->_customdata['packkey'] ?? '');
        $available = $isnew ? content_pack_repository::get_available_for_import() : [];
        $this->pack = ($packkey !== '' && isset($available[$packkey])) ? $available[$packkey] : null;

        if ($isnew) {
            $packoptions = ['' => get_string('choosedots')];
            foreach ($available as $key => $pack) {
                $packoptions[$key] = content_pack_repository::get_label($pack);
            }

            $mform->addElement('select', 'importpack', get_string('importpack', 'local_eventcalendar'), $packoptions);
            $mform->setType('importpack', PARAM_TEXT);
            $mform->addHelpButton('importpack', 'importpack', 'local_eventcalendar');

            $mform->registerNoSubmitButton('loadpack');
            $mform->addElement('submit', 'loadpack', get_string('loadpack', 'local_eventcalendar'));
        }

        $mform->addElement('text', 'name', get_string('notificationsetname', 'local_eventcalendar'), ['size' => '50']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('notificationsetdescription', 'local_eventcalendar'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        if ($this->pack) {
            $this->add_pack_preview($mform, $this->pack);
        }

        // Other plugins can add or change elements; they come before the buttons.
        \core\di::get(\core\hook\manager::class)->dispatch(
            new notificationset_form_definition($this, $mform, $persistent)
        );

        $this->add_action_buttons();
    }

    /**
     * Tells whether a pack has been loaded in the form, so that its titles and bodies are shown.
     *
     * @return bool
     */
    public function is_from_pack(): bool {
        return $this->pack !== null;
    }

    /**
     * Adds the read only preview of the titles and of the bodies of the pack.
     *
     * The texts come from the pack files of the plugin, so they are trusted. The bodies are formatted as HTML
     * in the system context, as they will be in the notifications.
     *
     * @param \MoodleQuickForm $mform
     * @param \stdClass $pack
     */
    protected function add_pack_preview(\MoodleQuickForm $mform, \stdClass $pack): void {
        $context = \context_system::instance();

        $mform->addElement('header', 'titlesheader', get_string('notificationtitlesinset', 'local_eventcalendar'));
        $mform->setExpanded('titlesheader', true);
        if (empty($pack->subjects)) {
            $titles = get_string('notitlesinset', 'local_eventcalendar');
        } else {
            $titles = \html_writer::alist(array_map(fn($title) => format_string($title), $pack->subjects));
        }
        $mform->addElement('static', 'packtitles', '', $titles);

        $mform->addElement('header', 'notificationsheader', get_string('notificationsinset', 'local_eventcalendar'));
        $mform->setExpanded('notificationsheader', true);
        if (empty($pack->notifications)) {
            $bodies = get_string('nonotificationsinset', 'local_eventcalendar');
        } else {
            $bodies = '';
            foreach ($pack->notifications as $body) {
                $bodies .= \html_writer::div(format_text($body, FORMAT_HTML, ['context' => $context]), 'card card-body mb-3');
            }
        }
        $mform->addElement('static', 'packbodies', '', $bodies);
    }

    /**
     * Keeps only the properties of the set in the data validated by the persistent.
     *
     * @param \stdClass $data Submitted data.
     * @return \stdClass
     */
    protected function filter_data_for_persistent($data) {
        $data = parent::filter_data_for_persistent($data);
        return (object) array_intersect_key((array) $data, notification_set::properties_definition());
    }

    /**
     * Validation of the pack of a new set; the errors are then passed to the other plugins, which validate the
     * fields they added and can remove errors.
     *
     * The persistent form passes the errors by reference, so replacing them here is enough for the form to
     * use the result.
     *
     * @param \stdClass $data Submitted data.
     * @param array $files Submitted files.
     * @param array $errors Errors found so far.
     * @return array
     */
    protected function extra_validation($data, $files, array &$errors) {
        if (empty($this->get_persistent()->get('id'))) {
            $importpack = (string) ($data->importpack ?? '');
            $available = content_pack_repository::get_available_for_import();

            if ($importpack === '' || !isset($available[$importpack])) {
                $errors['importpack'] = get_string('errornotificationsetpack', 'local_eventcalendar');
            } else if (!$this->pack || (string) ($this->_customdata['packkey'] ?? '') !== $importpack) {
                $errors['importpack'] = get_string('errornotificationsetloadpack', 'local_eventcalendar');
            }
        }

        $hook = new notificationset_form_validation($data, (array) $files, $errors);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $errors = $hook->get_errors();

        return $errors;
    }
}
