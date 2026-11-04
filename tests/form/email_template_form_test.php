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

namespace local_eventcalendar\form;

use local_eventcalendar\hook\emailtemplate_form_definition;
use local_eventcalendar\persistent\email_template;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\email_template_form::class)]
/**
 * Tests of the email template form.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\email_template_form
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_template_form_test extends \advanced_testcase {
    /**
     * An element added by another plugin, which is not a property of the template, does not stop the validation
     * and is returned with the data of the form; the content is not part of the form of this plugin.
     */
    public function test_extra_elements_are_returned_and_not_validated(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => 'Template', 'language' => 'it', 'identifier' => 'template', 'content' => '<p>{{content}}</p>',
            'isdefault' => 0, 'source' => email_template::SOURCE_FILE, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->redirectHook(emailtemplate_form_definition::class, function (emailtemplate_form_definition $hook): void {
            $hook->get_mform()->addElement('text', 'extrafield', 'Extra field');
            $hook->get_mform()->setType('extrafield', PARAM_TEXT);
        });

        email_template_form::mock_submit(['id' => $id, 'name' => 'Renamed', 'language' => 'en', 'extrafield' => 'kept']);
        $form = new email_template_form(
            new \moodle_url('/local/eventcalendar/email_templates.php'),
            ['persistent' => new email_template($id)]
        );
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame('Renamed', $data->name);
        $this->assertSame('kept', $data->extrafield);
        $this->assertObjectNotHasProperty('content', $data);
    }
}
