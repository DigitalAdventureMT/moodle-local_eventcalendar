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
 * Calendar create and edit form.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\form;

use local_eventcalendar\hook\calendar_form_data;
use local_eventcalendar\hook\calendar_form_definition;
use local_eventcalendar\hook\calendar_form_validation;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\banner;
use local_eventcalendar\persistent\headerfooter;
use local_eventcalendar\local\content_pack_repository;
use local_eventcalendar\local\template_repository;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Calendar create and edit form.
 *
 * Supports the "template" and "custom" creation modes; duplication is handled directly from manage.php.
 * The "loadtemplate" no-submit button recomputes the template defaults and reapplies them to the form.
 *
 * The intro field is a plain textarea. The logo of the emails is always the logo of the site. The banner is
 * chosen among the preloaded banners (it is required) and the header and footer pair among the preloaded
 * pairs, or none. Other plugins can add options to the two galleries (add_gallery_option()), elements to the
 * form (calendar_form_definition hook) and their initial values (calendar_form_data hook).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_form extends \core\form\persistent {
    /** @var string Associated persistent class. */
    protected static $persistentclass = calendar::class;

    /** @var array UI fields that are not persistent properties. */
    protected static $fieldstoremove = ['submitbutton', 'creationmode', 'loadtemplate'];

    /** @var array Fields excluded from persistent validation. */
    protected static $foreignfields = ['creationmode', 'loadtemplate'];

    /** Name of the banner gallery, for add_gallery_option(). */
    const GALLERY_BANNER = 'banner';

    /** Name of the header and footer gallery, for add_gallery_option(). */
    const GALLERY_HEADERFOOTER = 'headerfooter';

    /** @var array[] Options added by other plugins, by gallery: value, label (HTML) and checked. */
    protected array $extragalleryoptions = [self::GALLERY_BANNER => [], self::GALLERY_HEADERFOOTER => []];

    /**
     * Defines the form elements.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $persistent = $this->get_persistent();
        $isnew = empty($persistent->get('id'));

        // Creation mode: only for new calendars.
        if ($isnew) {
            $modes = [
                'custom' => get_string('mode_custom', 'local_eventcalendar'),
                'template' => get_string('mode_template', 'local_eventcalendar'),
            ];
            $mform->addElement('select', 'creationmode', get_string('creationmode', 'local_eventcalendar'), $modes);
            $mform->setType('creationmode', PARAM_ALPHA);
            $mform->setDefault('creationmode', 'custom');
        }

        $templates = template_repository::get_all();
        $templateoptions = ['' => get_string('choosedots')];
        foreach ($templates as $key => $template) {
            // The labels of a select are printed as HTML, so the names go through format_string() like any
            // other name shown on a page (the same applies to the sets below).
            $templateoptions[$key] = format_string($template->name);
        }

        $mform->addElement('select', 'templatekey', get_string('template', 'local_eventcalendar'), $templateoptions);
        $mform->setType('templatekey', PARAM_ALPHANUMEXT);

        if ($isnew) {
            $mform->hideIf('templatekey', 'creationmode', 'eq', 'custom');

            // No-submit button: reloads the form with the chosen template defaults.
            $mform->registerNoSubmitButton('loadtemplate');
            $mform->addElement('submit', 'loadtemplate', get_string('loadtemplate', 'local_eventcalendar'));
            $mform->hideIf('loadtemplate', 'creationmode', 'eq', 'custom');
        } else {
            // The origin template cannot be changed after creation.
            $mform->hardFreeze('templatekey');
        }

        // Language of the calendar: it selects the content pack (tagline, day contents, call to action) together
        // with the template, so it is offered only for calendars that have a template. Only the languages of
        // the available content packs are listed. A new calendar starts with the language of the user, when
        // a pack exists for it, and the choice can be changed freely afterwards.
        if ($isnew || !empty($persistent->get('templatekey'))) {
            $languageoptions = ['' => get_string('choosedots')] + content_pack_repository::get_languages();

            $mform->addElement('select', 'language', get_string('calendarlanguage', 'local_eventcalendar'), $languageoptions);
            $mform->setType('language', PARAM_ALPHANUMEXT);
            $mform->addHelpButton('language', 'calendarlanguage', 'local_eventcalendar');

            if ($isnew) {
                $mform->hideIf('language', 'creationmode', 'eq', 'custom');

                if (isset($languageoptions[current_language()])) {
                    $mform->setDefault('language', current_language());
                }
            }
        }

        $mform->addElement('text', 'name', get_string('calendarname', 'local_eventcalendar'), ['size' => '60']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('textarea', 'intro', get_string('calendarintro', 'local_eventcalendar'), ['rows' => 5, 'cols' => 60]);
        $mform->setType('intro', PARAM_TEXT);

        $mform->addElement('text', 'tagline', get_string('calendartagline', 'local_eventcalendar'), ['size' => 60]);
        $mform->setType('tagline', PARAM_TEXT);
        $mform->addHelpButton('tagline', 'calendartagline', 'local_eventcalendar');

        // Dates are always interpreted in UTC, not in the user's timezone, so that a calendar's reference
        // date does not depend on who edits it.
        $dateoptions = ['timezone' => 'UTC'];

        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'local_eventcalendar'), $dateoptions);

        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'local_eventcalendar'), $dateoptions);

        if (!$isnew) {
            // Edit only: on creation no days exist yet. Always shown because the need depends on a
            // server-side comparison with the generated days.
            $mform->addElement(
                'advcheckbox',
                'confirmshrink',
                get_string('confirmshrinklabel', 'local_eventcalendar')
            );
            $mform->addHelpButton('confirmshrink', 'confirmshrink', 'local_eventcalendar');
        }

        $mform->addElement('date_selector', 'enddisplaydate', get_string('enddisplaydate', 'local_eventcalendar'), $dateoptions);
        $mform->addHelpButton('enddisplaydate', 'enddisplaydate', 'local_eventcalendar');

        $mform->addElement('advcheckbox', 'progressiveunlock', get_string('progressiveunlock', 'local_eventcalendar'));
        $mform->setDefault('progressiveunlock', 1);
        $mform->addHelpButton('progressiveunlock', 'progressiveunlock', 'local_eventcalendar');

        // Weekly rest day: on that day the page content uses the content pack rest texts and has no
        // call-to-action button or links. Notifications are not affected. Numbering follows date('w'),
        // 0 = Sunday.
        $mform->addElement('advcheckbox', 'restdayenabled', get_string('restdayenabled', 'local_eventcalendar'));
        $mform->addHelpButton('restdayenabled', 'restdayenabled', 'local_eventcalendar');

        // Weekday names from the plugin language pack, indexed like date('w').
        $weekdaynames = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $weekdaynames[$weekday] = get_string('weekday' . $weekday, 'local_eventcalendar');
        }
        $mform->addElement('select', 'restdayweekday', get_string('restdayweekday', 'local_eventcalendar'), $weekdaynames);
        $mform->setType('restdayweekday', PARAM_INT);
        $mform->addHelpButton('restdayweekday', 'restdayweekday', 'local_eventcalendar');

        // Hidden when the rest day is off. hideIf also drops the field from the submission, so the stored
        // weekday is left unchanged.
        $mform->hideIf('restdayweekday', 'restdayenabled', 'notchecked');

        if (!$isnew) {
            // Edit only: on creation there are no days to overwrite. The comparison with previous values
            // happens server side on save.
            $mform->addElement(
                'static',
                'restdaynotice',
                '',
                \html_writer::div(get_string('restdaynotice', 'local_eventcalendar'), 'alert alert-warning mb-0')
            );
        }

        // The iconset is always chosen manually among existing iconsets.
        $iconsetoptions = [];
        $iconrecords = $DB->get_records('local_eventcalendar_icons', null, 'name ASC', 'id, name');
        foreach ($iconrecords as $record) {
            $iconsetoptions[$record->id] = format_string($record->name);
        }
        if (empty($iconsetoptions)) {
            $iconsetoptions[0] = get_string('noiconsetsavailable', 'local_eventcalendar');
        } else if ($this->get_persistent()->get('id') && !isset($iconsetoptions[$this->get_persistent()->get('iconsetid')])) {
            // The set of the calendar was deleted: an empty first option, so that no set is chosen silently and
            // the validation asks for one.
            $iconsetoptions = [0 => get_string('choosedots')] + $iconsetoptions;
        }
        $mform->addElement('select', 'iconsetid', get_string('iconset', 'local_eventcalendar'), $iconsetoptions);
        $mform->setType('iconsetid', PARAM_INT);

        // Notification set: optional.
        $notificationsetoptions = [0 => get_string('notificationsetnone', 'local_eventcalendar')];
        $notificationsetrecords = $DB->get_records('local_eventcalendar_notifset', null, 'name ASC', 'id, name');
        foreach ($notificationsetrecords as $record) {
            $notificationsetoptions[$record->id] = format_string($record->name);
        }
        $mform->addElement(
            'select',
            'notificationsetid',
            get_string('notificationset', 'local_eventcalendar'),
            $notificationsetoptions
        );
        $mform->setType('notificationsetid', PARAM_INT);

        // Email template of the notification emails. A calendar without a choice (new, or created before the
        // choice existed) or with a deleted template shows the default template, so that saving the form makes
        // the template in use explicit.
        $emailtemplateoptions = [];
        $defaulttemplateid = 0;
        foreach (\local_eventcalendar\persistent\email_template::get_records([], 'name', 'ASC') as $template) {
            $emailtemplateoptions[$template->get('id')] = format_string($template->get('name'))
                . ' (' . \core_text::strtoupper($template->get('language')) . ')';
            if ($template->get('isdefault')) {
                $defaulttemplateid = (int) $template->get('id');
            }
        }
        if (empty($emailtemplateoptions)) {
            $emailtemplateoptions[0] = get_string('noemailtplavailable', 'local_eventcalendar');
        }
        $mform->addElement('select', 'emailtemplateid', get_string('emailtemplate', 'local_eventcalendar'), $emailtemplateoptions);
        $mform->setType('emailtemplateid', PARAM_INT);
        $mform->addHelpButton('emailtemplateid', 'emailtemplate', 'local_eventcalendar');
        if (!isset($emailtemplateoptions[(int) $persistent->get('emailtemplateid')])) {
            $persistent->set('emailtemplateid', $defaulttemplateid ?: null);
        }

        $mform->addElement('advcheckbox', 'forcehide', get_string('forcehide', 'local_eventcalendar'));
        // New calendars start force-hidden; template mode overrides this default.
        $mform->setDefault('forcehide', 1);
        $mform->addHelpButton('forcehide', 'forcehide', 'local_eventcalendar');

        // Galleries of the preloaded banners and header/footer pairs. They are radio groups built as raw HTML,
        // to get the image-above-radio layout, so they are not tracked by mform: the page reads the choices with
        // optional_param() and the validation below checks them. The HTML is written in definition_after_data(),
        // after the other plugins have added their options with add_gallery_option().
        $mform->addElement('static', 'bannergallery', get_string('bannergallery', 'local_eventcalendar'), '');
        $mform->addHelpButton('bannergallery', 'bannergallery', 'local_eventcalendar');

        $mform->addElement('static', 'headerfootergallery', get_string('headerfootergallery', 'local_eventcalendar'), '');
        $mform->addHelpButton('headerfootergallery', 'headerfootergallery', 'local_eventcalendar');

        // Other plugins can add or change elements; they come before the buttons.
        \core\di::get(\core\hook\manager::class)->dispatch(
            new calendar_form_definition($this, $mform, $persistent)
        );

        $this->add_action_buttons();
    }

    /**
     * Adds an option to a gallery, after the preloaded elements.
     *
     * It is meant for the callbacks of the calendar_form_definition hook. The value is sent with the radio
     * group (bannerchoice or headerfooterchoice): it must not be a number, which is the id of a preloaded
     * element, nor 'none'. A checked option is chosen when the calendar has no preloaded element.
     *
     * @param string $gallery GALLERY_BANNER or GALLERY_HEADERFOOTER.
     * @param string $value Value of the radio.
     * @param string $label Label of the option, HTML already escaped.
     * @param bool $checked Whether the option is the current choice of the calendar.
     */
    public function add_gallery_option(string $gallery, string $value, string $label, bool $checked = false): void {
        if (!isset($this->extragalleryoptions[$gallery]) || ctype_digit($value) || $value === 'none') {
            throw new \coding_exception('Invalid gallery option');
        }
        $this->extragalleryoptions[$gallery][] = ['value' => $value, 'label' => $label, 'checked' => $checked];
    }

    /**
     * Writes the HTML of the two galleries, now that the options of the other plugins are known.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        $persistent = $this->get_persistent();

        // Banner: the preloaded banner of the calendar, otherwise an option of another plugin; a new calendar
        // starts with the first banner, because the banner is required.
        $banners = banner::get_records([], 'name', 'ASC');
        $options = [];
        foreach ($banners as $item) {
            $options[] = [
                'value' => (string) $item->get('id'),
                'label' => \html_writer::empty_tag('img', [
                    'src' => $item->get_url()->out(false), 'alt' => '', 'role' => 'presentation',
                    'class' => 'local-eventcalendar-gallery-image',
                ]) . \html_writer::tag('span', format_string($item->get('name')), ['class' => 'local-eventcalendar-gallery-name']),
            ];
        }
        $checked = $this->get_checked_value(
            self::GALLERY_BANNER,
            (string) $persistent->get('bannerid'),
            array_column($options, 'value'),
            empty($persistent->get('id')) && $options ? $options[0]['value'] : ''
        );
        $mform->getElement('bannergallery')->setText(
            $this->render_gallery('banner', 'bannerchoice', get_string('bannergallery', 'local_eventcalendar'), $options, $checked)
        );

        // Header and footer: the empty option first, then the preloaded pairs, then the options of other plugins.
        $options = [[
            'value' => 'none',
            'label' => \html_writer::tag('span', get_string('headerfooternone', 'local_eventcalendar'), [
                'class' => 'local-eventcalendar-gallery-label',
            ]),
            'class' => 'local-eventcalendar-gallery-none',
        ]];
        $pairnumber = 0;
        foreach (headerfooter::get_records([], 'id', 'ASC') as $pair) {
            $pairnumber++;
            $options[] = [
                'value' => (string) $pair->get('id'),
                'label' => \html_writer::empty_tag('img', [
                    'src' => $pair->get_header_url()->out(false), 'alt' => '', 'role' => 'presentation',
                    'class' => 'local-eventcalendar-gallery-header',
                ]) . \html_writer::empty_tag('img', [
                    'src' => $pair->get_footer_url()->out(false), 'alt' => '', 'role' => 'presentation',
                    'class' => 'local-eventcalendar-gallery-footer',
                ]),
                // Pairs have no name and the previews are decorative: the radio gets a numbered name.
                'arialabel' => get_string('headerfooterpair', 'local_eventcalendar', $pairnumber),
            ];
        }
        $checked = $this->get_checked_value(
            self::GALLERY_HEADERFOOTER,
            (string) $persistent->get('headerfooterid'),
            array_column($options, 'value'),
            'none'
        );
        $mform->getElement('headerfootergallery')->setText($this->render_gallery(
            'headerfooter',
            'headerfooterchoice',
            get_string('headerfootergallery', 'local_eventcalendar'),
            $options,
            $checked
        ));
    }

    /**
     * Returns the value of the option to check in a gallery.
     *
     * A submitted choice wins, so that a form shown again after a validation error keeps it. Then the preloaded
     * element of the calendar, if it still exists, then a checked option of another plugin, then the default.
     *
     * @param string $gallery
     * @param string $current Id of the preloaded element of the calendar, '' if none.
     * @param string[] $values Values of the options of this plugin.
     * @param string $default Value to check when nothing else applies, '' for none.
     * @return string
     */
    protected function get_checked_value(string $gallery, string $current, array $values, string $default): string {
        $radioname = $gallery === self::GALLERY_BANNER ? 'bannerchoice' : 'headerfooterchoice';
        $submitted = optional_param($radioname, null, PARAM_ALPHANUMEXT);
        if ($submitted !== null) {
            return $submitted;
        }
        if ($current !== '' && in_array($current, $values, true)) {
            return $current;
        }
        foreach ($this->extragalleryoptions[$gallery] as $option) {
            if ($option['checked']) {
                return $option['value'];
            }
        }
        return $default;
    }

    /**
     * Returns the HTML of a gallery: a radio group named after the field, with the options of this plugin
     * followed by those of the other plugins.
     *
     * @param string $gallery Short name, used in the CSS class.
     * @param string $radioname Name of the radio group.
     * @param string $label Accessible name of the group.
     * @param array[] $options Options of this plugin: value, label, and optionally class and arialabel.
     * @param string $checked Value of the checked option.
     * @return string
     */
    protected function render_gallery(string $gallery, string $radioname, string $label, array $options, string $checked): string {
        $html = \html_writer::start_div('eventcalendar-' . $gallery . '-gallery local-eventcalendar-gallery', [
            'role' => 'radiogroup',
            'aria-label' => $label,
        ]);

        // The options of the other plugins get the class that aligns them to the bottom of the row.
        $extra = array_map(
            fn($option) => $option + ['class' => 'local-eventcalendar-gallery-upload'],
            $this->extragalleryoptions[$gallery]
        );

        foreach (array_merge($options, $extra) as $option) {
            $radio = [
                'type' => 'radio', 'name' => $radioname, 'value' => $option['value'],
                'class' => 'eventcalendar-' . $radioname,
            ];
            if (!empty($option['arialabel'])) {
                $radio['aria-label'] = $option['arialabel'];
            }
            if ($option['value'] === $checked) {
                $radio['checked'] = 'checked';
            }
            $html .= \html_writer::tag(
                'label',
                $option['label'] . \html_writer::empty_tag('input', $radio),
                ['class' => trim('local-eventcalendar-gallery-item ' . ($option['class'] ?? ''))]
            );
        }

        return $html . \html_writer::end_div();
    }

    /**
     * Keeps only the properties of the calendar in the data validated by the persistent.
     *
     * The elements added by other plugins are not properties: without this filter the persistent would refuse
     * them as unknown fields. They are still returned by get_data().
     *
     * @param \stdClass $data Submitted data.
     * @return \stdClass
     */
    protected function filter_data_for_persistent($data) {
        $data = parent::filter_data_for_persistent($data);
        return (object) array_intersect_key((array) $data, calendar::properties_definition());
    }

    /**
     * Prepares the initial values of the form and lets other plugins set those of the fields they added.
     *
     * The persistent form calls this method from its constructor, after the definition, and passes the result
     * to set_data().
     *
     * @return \stdClass
     */
    protected function get_default_data() {
        $data = parent::get_default_data();

        \core\di::get(\core\hook\manager::class)->dispatch(
            new calendar_form_data((int) $this->get_persistent()->get('id'), $data)
        );

        return $data;
    }

    /**
     * Additional validation on top of the automatic persistent validation.
     *
     * Only the extra errors are returned: the base class already calls this method internally.
     * @param array $data data submitted by the form
     * @param array $files files submitted by the form
     * @param array $errors errors already found by the persistent validation
     * @return array additional errors, keyed by field name
     */
    protected function extra_validation($data, $files, array &$errors) {
        if (!empty($data->enddate) && !empty($data->startdate) && $data->enddate < $data->startdate) {
            $errors['enddate'] = get_string('errorenddatebeforestart', 'local_eventcalendar');
        }

        if (empty($data->iconsetid)) {
            $errors['iconsetid'] = get_string('errornoiconsetselected', 'local_eventcalendar');
        }

        // The banner is required and must be a preloaded banner, when the site has any. Other plugins that add
        // an option to the gallery remove this error when their option is chosen.
        $bannerchoice = optional_param('bannerchoice', '', PARAM_ALPHANUMEXT);
        $validbanner = ctype_digit($bannerchoice) && banner::record_exists((int) $bannerchoice);
        if (!$validbanner && banner::count_records() > 0) {
            $errors['bannergallery'] = get_string('errornobannerselected', 'local_eventcalendar');
        }

        // A language without a pack for the template of the calendar would silently leave the pack texts
        // empty, so it is refused. An empty language is allowed: the calendar then uses no pack. The template
        // is read from the form for a new calendar, and from the saved calendar when editing (it is frozen).
        $templatekey = $data->templatekey ?? $this->get_persistent()->get('templatekey');
        if (
            !empty($data->language) && !empty($templatekey)
                && content_pack_repository::get($data->language . ':' . $templatekey) === null
        ) {
            $errors['language'] = get_string('errornopackforlanguage', 'local_eventcalendar');
        }

        // Check which generated days would fall outside the new date range (end moved earlier or start
        // moved later). Done here so that, on error, Moodle redisplays the form with all values preserved.
        $persistentid = $this->get_persistent()->get('id');
        if (!empty($persistentid) && !empty($data->startdate) && !empty($data->enddate) && $data->enddate >= $data->startdate) {
            $preview = \local_eventcalendar\local\calendar_generator::sync_preview(
                $persistentid,
                (int) $data->startdate,
                (int) $data->enddate
            );

            if (!empty($preview->extradays) && empty($data->confirmshrink)) {
                $dateformat = get_string('strftimedaydate', 'local_eventcalendar');
                $daylist = implode(', ', array_map(
                    fn ($d) => userdate($d->get('opendate'), $dateformat, 'UTC'),
                    $preview->extradays
                ));

                $errors['confirmshrink'] = get_string(
                    'confirmshrinkwarning',
                    'local_eventcalendar',
                    count($preview->extradays)
                ) . ' ' . $daylist;
            }
        }

        // Other plugins validate the fields they added and can remove errors. The errors are passed by
        // reference by the persistent form, so replacing them here is enough for the form to use the result.
        $hook = new calendar_form_validation($data, (array) $files, $errors);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $errors = $hook->get_errors();

        return $errors;
    }
}
