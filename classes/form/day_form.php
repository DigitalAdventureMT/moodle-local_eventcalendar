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
 * Form for editing a single calendar day.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\form;

use local_eventcalendar\hook\day_form_data;
use local_eventcalendar\hook\day_form_definition;
use local_eventcalendar\hook\day_form_validation;
use local_eventcalendar\persistent\day;

/**
 * Form for editing a single calendar day.
 *
 * Only one day is edited at a time; days.php shows the others in a read-only table. Every field is
 * a normal independent element with the predictable id "fitem_id_{fieldname}", and the column layout
 * is done in CSS in days.php.
 *
 * The "choose from pack" selects only copy text into the target field with JavaScript; they are not
 * persistent properties.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class day_form extends \core\form\persistent {
    /** @var string Persistent class bound to the form. */
    protected static $persistentclass = day::class;

    /** Number of columns of the layout of the form. */
    public const LAYOUT_COLUMNS = 3;

    /**
     * Default columns of the layout. Each column lists element names in display order; an array groups
     * buttons on the same row.
     */
    private const DEFAULT_COLUMNS = [
        ['dayinfo', 'restdayinfo', 'iconpreview', 'notes', 'isready'],
        [
            'notiftitle', 'packtitlechoice', 'notifbodytext', 'packbodychoice',
            ['previewemailbtn', 'testemailbtn', 'testnotificationbtn'],
        ],
        ['content', 'packcontentchoice', 'previewcontentbtn'],
    ];

    /** Buttons moved without their standard wrapper (only the input is moved). */
    private const DEFAULT_UNWRAPPED = ['previewemailbtn', 'testemailbtn', 'testnotificationbtn', 'previewcontentbtn'];

    /**
     * @var array Layout passed to the local_eventcalendar/day_editor module: 'columns' and 'unwrapped' as in the
     * constants above. Other plugins change it through the day_form_definition hook (place_element()).
     */
    private array $layout = ['columns' => self::DEFAULT_COLUMNS, 'unwrapped' => self::DEFAULT_UNWRAPPED];

    /**
     * @var array UI fields removed from $data. The pack selects are only client-side helpers and
     * 'dayinfo'/'iconpreview' are static elements.
     */
    protected static $fieldstoremove = [
        'submitbutton', 'cancel', 'previewemailbtn', 'testemailbtn', 'testnotificationbtn', 'previewcontentbtn',
        'packtitlechoice', 'packbodychoice', 'packcontentchoice', 'dayinfo', 'iconpreview',
        'restdayinfo', 'saveandcontinue',
    ];

    /**
     * @var array Fields excluded only from the persistent validation (they stay in $data).
     * 'notifbodytext' matches no persistent property: the service (day_manager) maps it to notifbody.
     * 'contentitemid' is added by convert_fields() and carries the draft area of the content editor.
     */
    protected static $foreignfields = [
        'packtitlechoice', 'packbodychoice', 'packcontentchoice', 'notifbodytext', 'contentitemid',
    ];

    /**
     * Converts the editor fields and keeps the draft area of the content editor.
     *
     * The parent method replaces each editor value (text, format, itemid) with the plain text and the format
     * property, dropping the itemid. Without the itemid the images added in the content editor cannot be moved
     * from the draft area to the file area of the day, so it is saved here in 'contentitemid'.
     *
     * @param \stdClass $data The whole data set.
     * @return \stdClass The amended data set.
     */
    protected static function convert_fields(\stdClass $data) {
        $itemid = null;
        if (isset($data->content) && is_array($data->content)) {
            $itemid = $data->content['itemid'] ?? null;
        }

        $data = parent::convert_fields($data);

        if ($itemid !== null) {
            $data->contentitemid = (int) $itemid;
        }

        return $data;
    }

    /**
     * Keeps only the properties of the day in the data validated by the persistent.
     *
     * The elements added by other plugins are not properties: without this filter the persistent would refuse
     * them as unknown fields. They are still returned by get_data().
     *
     * @param \stdClass $data Submitted data.
     * @return \stdClass
     */
    protected function filter_data_for_persistent($data) {
        $data = parent::filter_data_for_persistent($data);
        return (object) array_intersect_key((array) $data, day::properties_definition());
    }

    /**
     * Prepares the draft area of the 'content' editor and sets 'notifbodytext'.
     *
     * core\form\persistent detects editor fields by the property naming convention (field and
     * fieldformat), so it needs the specific filearea and itemid set here. 'notifbody' has a
     * 'notifbodyformat' twin, so it would be wrongly treated as an editor field; the form field is
     * therefore named 'notifbodytext', which matches no persistent property, and it is mapped by hand
     * to and from 'notifbody' here and in the controller.
     *
     * @return \stdClass
     */
    protected function get_default_data() {
        $data = parent::get_default_data();

        $day = $this->get_persistent();
        $context = \context_system::instance();

        $draftitemid = file_get_submitted_draft_itemid('content');
        $preparedtext = file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'local_eventcalendar',
            'content',
            $day->get('id'),
            null,
            $day->get('content')
        );

        $data->content = [
            'text' => $preparedtext,
            'format' => $day->get('contentformat'),
            'itemid' => $draftitemid,
        ];

        $data->notifbodytext = $day->get('notifbody');

        // Other plugins set the initial values of the fields they added.
        \core\di::get(\core\hook\manager::class)->dispatch(new day_form_data((int) $day->get('id'), $data));

        return $data;
    }

    /**
     * Validation specific to the plugin (title and body from the pack); the errors are then passed to the other
     * plugins, which validate the fields they added and can remove errors.
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
        // Title and body must come from the pools of the notification set, or be the values already saved: the
        // fields are read only, but a request can be made by hand. The saved values are read from the database,
        // because the persistent form has already loaded the submitted values into its own copy of the day.
        $saved = day::get_record(['id' => (int) $this->get_persistent()->get('id')]);
        $checks = [
            'notiftitle' => [$data->notiftitle ?? '', $this->_customdata['titlepool'] ?? [],
                $saved ? $saved->get('notiftitle') : '', 'errortitlenotinpack'],
            'notifbodytext' => [$data->notifbodytext ?? '', $this->_customdata['bodypool'] ?? [],
                $saved ? $saved->get('notifbody') : '', 'errorbodynotinpack'],
        ];
        foreach ($checks as $field => [$value, $pool, $saved, $error]) {
            $allowed = array_map([self::class, 'normalise_text'], array_merge($pool, [(string) $saved]));
            if (!in_array(self::normalise_text((string) $value), $allowed, true)) {
                $errors[$field] = get_string($error, 'local_eventcalendar');
            }
        }

        $hook = new day_form_validation($data, (array) $files, $errors);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $errors = $hook->get_errors();

        return $errors;
    }

    /**
     * Returns a text in the form used to compare it with the pools: line endings converted to LF, spaces at
     * start and end removed. The browser sends the lines of a textarea separated by CRLF.
     *
     * @param string $text
     * @return string
     */
    private static function normalise_text(string $text): string {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * Defines the form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $day = $this->get_persistent();

        $titlepool = $this->_customdata['titlepool'] ?? [];
        $bodypool = $this->_customdata['bodypool'] ?? [];
        $contentpool = $this->_customdata['contentpool'] ?? [];
        $calendar = $this->_customdata['calendar'];

        // Weekly rest day, decided by the controller: a notice is shown, and the hook tells it to the other
        // plugins, which can leave out the fields that do not apply to rest days.
        $isrestday = !empty($this->_customdata['isrestday']);

        $mform->addElement('hidden', 'calendarid', $day->get('calendarid'));
        $mform->setType('calendarid', PARAM_INT);

        // Column 1: day information and status.
        $opendatelabel = userdate($day->get('opendate'), get_string('strftimedatefullshort', 'langconfig'), 'UTC');
        $mform->addElement(
            'static',
            'dayinfo',
            get_string('dayinfo', 'local_eventcalendar'),
            '<strong>' . get_string('daynumberlabel', 'local_eventcalendar', $day->get('daynumber')) . '</strong><br>'
            . get_string('opendatelabel', 'local_eventcalendar', $opendatelabel)
        );

        // Rest day notice shown under the day information, in bold red.
        if ($isrestday) {
            $mform->addElement(
                'static',
                'restdayinfo',
                '',
                \html_writer::div(
                    get_string('restdayeditnotice', 'local_eventcalendar'),
                    'text-danger local-eventcalendar-bold'
                )
            );
        }

        // Day icon: preview of the file copied into the day ('dayicon' file area). In this plugin the icon is
        // assigned when the day is generated and cannot be changed; other plugins can add a choice and update
        // the preview, which they find by its id.
        $currenticonurl = $day->get_icon_url();
        $mform->addElement(
            'static',
            'iconpreview',
            get_string('dayicon', 'local_eventcalendar'),
            \html_writer::empty_tag('img', [
                'id' => 'eventcalendar-icon-preview',
                'src' => $currenticonurl ? $currenticonurl->out(false) : '',
                'alt' => '',
                'role' => 'presentation',
                'class' => 'local-eventcalendar-iconpreview',
                'style' => 'display:' . ($currenticonurl ? 'block' : 'none') . ';',
            ])
        );

        $mform->addElement('select', 'isready', get_string('dayisready', 'local_eventcalendar'), [
            0 => get_string('dayinprogress', 'local_eventcalendar'),
            1 => get_string('dayready', 'local_eventcalendar'),
        ]);
        $mform->setType('isready', PARAM_INT);
        $mform->addHelpButton('isready', 'dayisready', 'local_eventcalendar');

        // Column 2: notification title and body.
        // Title and body are chosen from the notification set of the calendar with the "choose from pack" selects:
        // the fields are read only, and the validation accepts only a text of the pool or the saved one. Other
        // plugins can make them editable and remove the errors.
        $mform->addElement('text', 'notiftitle', get_string('notificationtitle', 'local_eventcalendar'), [
            'size' => 40,
            'readonly' => 'readonly',
        ]);
        $mform->setType('notiftitle', PARAM_TEXT);

        if (!empty($titlepool)) {
            $this->add_pack_choice_select(
                'packtitlechoice',
                $titlepool,
                'notiftitle',
                get_string('choosefromtitlepack', 'local_eventcalendar')
            );
        }

        $mform->addElement(
            'textarea',
            'notifbodytext',
            get_string('daynotifbody', 'local_eventcalendar'),
            ['rows' => 6, 'cols' => 40, 'readonly' => 'readonly']
        );
        $mform->setType('notifbodytext', PARAM_RAW);
        $mform->addHelpButton('notifbodytext', 'daynotifbody', 'local_eventcalendar');

        if (!empty($bodypool)) {
            $this->add_pack_choice_select(
                'packbodychoice',
                $bodypool,
                'notifbodytext',
                get_string('choosefrombodypack', 'local_eventcalendar')
            );
        }

        $previewemailurl = new \moodle_url('/local/eventcalendar/preview.php', [
            'dayid' => $day->get('id'), 'type' => 'email', 'sesskey' => sesskey(),
        ]);
        $mform->addElement('submit', 'previewemailbtn', get_string('previewemail', 'local_eventcalendar'), [
            'class' => 'btn btn-secondary',
            'formnovalidate' => 'formnovalidate',
            'formaction' => $previewemailurl->out(false),
            'formtarget' => 'eventcalendar_preview_iframe',
            'formmethod' => 'post',
            'data-eventcalendar-preview' => 'email',
        ]);

        // Test email button: sends this day's email only to the editing user, using the values currently
        // typed in the form (even unsaved), like the preview.
        $testemailurl = new \moodle_url('/local/eventcalendar/preview.php', [
            'dayid' => $day->get('id'), 'type' => 'testemail', 'sesskey' => sesskey(),
        ]);
        $mform->addElement('submit', 'testemailbtn', get_string('testemail', 'local_eventcalendar'), [
            'class' => 'btn btn-secondary',
            'formnovalidate' => 'formnovalidate',
            'formaction' => $testemailurl->out(false),
            'formtarget' => 'eventcalendar_preview_iframe',
            'formmethod' => 'post',
            'data-eventcalendar-preview' => 'testemail',
            'title' => get_string('testemailtitle', 'local_eventcalendar'),
        ]);

        // Test notification button: sends the real notification only to the editing user.
        $testnotificationurl = new \moodle_url('/local/eventcalendar/preview.php', [
            'dayid' => $day->get('id'), 'type' => 'testnotification', 'sesskey' => sesskey(),
        ]);
        $mform->addElement('submit', 'testnotificationbtn', get_string('testnotification', 'local_eventcalendar'), [
            'class' => 'btn btn-secondary',
            'formnovalidate' => 'formnovalidate',
            'formaction' => $testnotificationurl->out(false),
            'formtarget' => 'eventcalendar_preview_iframe',
            'formmethod' => 'post',
            'data-eventcalendar-preview' => 'testnotification',
            'title' => get_string('testnotificationtitle', 'local_eventcalendar'),
        ]);

        // Column 3: day content.
        // Only images can be added: the file picker applies the list on the client side only, so the
        // pluginfile callback also sends any other type as a download (see pluginfile_access).
        $mform->addElement('editor', 'content', get_string('daycontent', 'local_eventcalendar'), null, [
            'maxfiles' => -1,
            'accepted_types' => ['web_image'],
            'noclean' => true,
            'context' => \context_system::instance(),
        ]);

        if (!empty($contentpool)) {
            $this->add_pack_choice_select(
                'packcontentchoice',
                $contentpool,
                'content',
                get_string('choosefromcontentpack', 'local_eventcalendar')
            );
        }

        $previewcontenturl = new \moodle_url('/local/eventcalendar/preview.php', [
            'dayid' => $day->get('id'), 'type' => 'content', 'sesskey' => sesskey(),
        ]);
        $mform->addElement('submit', 'previewcontentbtn', get_string('previewcontent', 'local_eventcalendar'), [
            'class' => 'btn btn-secondary',
            'formnovalidate' => 'formnovalidate',
            'formaction' => $previewcontenturl->out(false),
            'formtarget' => 'eventcalendar_preview_iframe',
            'formmethod' => 'post',
            'data-eventcalendar-preview' => 'content',
        ]);

        // Column 4: internal notes and the buttons.
        $mform->addElement('textarea', 'notes', get_string('daynotes', 'local_eventcalendar'), ['rows' => 6, 'cols' => 30]);
        $mform->setType('notes', PARAM_TEXT);
        $mform->addHelpButton('notes', 'daynotes', 'local_eventcalendar');

        // Other plugins can add or change elements; they come before the buttons.
        \core\di::get(\core\hook\manager::class)->dispatch(new day_form_definition($this, $mform, $day, $isrestday));

        // The buttons are built by hand because add_action_buttons() only supports two of them. The group
        // keeps the name 'buttonar' because days.php moves it by id. 'saveandcontinue' is read by the
        // controller with optional_param() and is listed in $fieldstoremove.
        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = $mform->createElement(
            'submit',
            'saveandcontinue',
            get_string('saveandcontinue', 'local_eventcalendar')
        );
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->setType('buttonar', PARAM_RAW);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Returns the layout of the form for the local_eventcalendar/day_editor module.
     *
     * Elements not listed in the layout (for example added by another plugin without placing them) are shown
     * by the module at the end of the last column.
     *
     * @return array ['columns' => array[], 'unwrapped' => string[]]
     */
    public function get_layout(): array {
        return $this->layout;
    }

    /**
     * Places an element in a column of the layout, moving it if it is already listed.
     *
     * @param string $name Name of the form element.
     * @param int $column Column, from 1 to LAYOUT_COLUMNS.
     * @param string|null $before Element before which it is placed (also a button of a row); null or not found:
     *     at the end of the column.
     * @param bool $unwrapped True for a button whose input is moved without its wrapper, like the preview buttons.
     * @throws \coding_exception If the column does not exist.
     */
    public function place_element(string $name, int $column, ?string $before = null, bool $unwrapped = false): void {
        if ($column < 1 || $column > self::LAYOUT_COLUMNS) {
            throw new \coding_exception('Invalid column of the day form layout: ' . $column);
        }

        $this->remove_from_layout($name);

        $entries = $this->layout['columns'][$column - 1];
        $position = count($entries);
        if ($before !== null) {
            foreach ($entries as $index => $entry) {
                if ($entry === $before || (is_array($entry) && in_array($before, $entry, true))) {
                    $position = $index;
                    break;
                }
            }
        }
        array_splice($entries, $position, 0, [$name]);
        $this->layout['columns'][$column - 1] = $entries;

        if ($unwrapped) {
            $this->layout['unwrapped'][] = $name;
        }
    }

    /**
     * Removes an element from the layout; the module then shows it at the end of the last column, if it exists.
     *
     * @param string $name Name of the form element.
     */
    public function remove_from_layout(string $name): void {
        foreach ($this->layout['columns'] as $c => $entries) {
            $kept = [];
            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    $entry = array_values(array_diff($entry, [$name]));
                    if (!empty($entry)) {
                        $kept[] = $entry;
                    }
                } else if ($entry !== $name) {
                    $kept[] = $entry;
                }
            }
            $this->layout['columns'][$c] = $kept;
        }

        $this->layout['unwrapped'] = array_values(array_diff($this->layout['unwrapped'], [$name]));
    }

    /**
     * Adds a "choose from pack" select that helps filling a field.
     *
     * The select is not saved; JavaScript copies the chosen text into the field named by $targetfield.
     * Only the standard option array is used (no per-option attributes); the full pool is passed in a
     * single data-pool JSON attribute on the select element.
     *
     * @param string $elementname
     * @param string[] $pool Pack texts (titles, bodies or contents).
     * @param string $targetfield Target field name ('notiftitle', 'notifbodytext' or 'content').
     * @param string $label
     * @return void
     */
    private function add_pack_choice_select(string $elementname, array $pool, string $targetfield, string $label): void {
        $mform = $this->_form;

        $options = ['' => get_string('choosedots')];

        foreach ($pool as $index => $text) {
            $plaintext = trim(strip_tags($text));
            $options[$index] = mb_strlen($plaintext) > 60 ? mb_substr($plaintext, 0, 60) . '…' : $plaintext;
        }

        // Only data-target and data-pool are set: a custom "class" does not reach the DOM, because the
        // Moodle select template replaces it. The JavaScript in days.php finds these selects through
        // the two data attributes.
        $mform->addElement('select', $elementname, $label, $options);
        $mform->getElement($elementname)->updateAttributes([
            'data-target' => $targetfield,
            'data-pool' => json_encode(array_values($pool)),
        ]);
    }
}
