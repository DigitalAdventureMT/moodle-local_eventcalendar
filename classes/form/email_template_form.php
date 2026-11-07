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
 * Form to edit an email template.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\form;

use local_eventcalendar\hook\emailtemplate_form_definition;
use local_eventcalendar\local\email_template_preview;
use local_eventcalendar\persistent\email_template;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to edit an email template.
 *
 * The form changes the name and the language of a template and shows a static preview of its content.
 * Other plugins can add elements through the emailtemplate_form_definition hook; elements that are not
 * properties of the template are kept out of its validation (see filter_data_for_persistent()).
 *
 * Templates are not created here: they come from the automatic scan, and this
 * form only edits those already imported.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_template_form extends \core\form\persistent {
    /** @var string Persistent class bound to the form. */
    protected static $persistentclass = email_template::class;

    /**
     * Defines the form elements.
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('emailtplname', 'local_eventcalendar'), ['size' => '50']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'language', get_string('emailtpllanguage', 'local_eventcalendar'), ['size' => '10']);
        $mform->setType('language', PARAM_ALPHANUMEXT);
        $mform->addRule('language', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('language', 'emailtpllanguage', 'local_eventcalendar');

        // Static preview of the saved content; it is not updated while typing, and the placeholders stay
        // unresolved. The content is raw HTML that add-ons can let users change or import, so it is never
        // printed in this page: it is loaded in an iframe from email_template_preview.php, which shows it with
        // the table borders forced visible under a sandbox without scripts (same sandbox on the frame and, as a
        // header, on the page). The module email_template_preview fits the height of the frame to its content.
        // The element keeps the name contentpreview, which add-ons use to place their fields before it.
        $templateid = (int) $this->get_persistent()->get('id');
        $previewhtml = '';
        if ($templateid) {
            $previewhtml = \html_writer::tag('iframe', '', [
                'src' => email_template_preview::get_url($templateid)->out(false),
                'sandbox' => email_template_preview::SANDBOX,
                'title' => get_string('emailtplpreview', 'local_eventcalendar'),
                'class' => 'local-eventcalendar-emailpreview-frame',
                'data-region' => 'local-eventcalendar-emailpreview',
            ]);
            $PAGE->requires->js_call_amd('local_eventcalendar/email_template_preview', 'init');
        }

        $mform->addElement('static', 'contentpreview', get_string('emailtplpreview', 'local_eventcalendar'), $previewhtml);
        $mform->addHelpButton('contentpreview', 'emailtplpreview', 'local_eventcalendar');

        // Other plugins can add or change elements; they come before the buttons.
        \core\di::get(\core\hook\manager::class)->dispatch(
            new emailtemplate_form_definition($this, $mform, $this->get_persistent())
        );

        $this->add_action_buttons();
    }

    /**
     * Keeps only the properties of the template for its validation.
     *
     * Elements added by other plugins through the definition hook are not properties of the persistent, which
     * would refuse them; they stay in the data returned by get_data(), for the emailtemplate_saved hook.
     *
     * @param \stdClass $data
     * @return \stdClass
     */
    protected function filter_data_for_persistent($data) {
        $data = parent::filter_data_for_persistent($data);

        return (object) array_intersect_key((array) $data, email_template::properties_definition());
    }
}
