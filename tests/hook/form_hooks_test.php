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

namespace local_eventcalendar\hook;

use local_eventcalendar\form\email_template_form;
use local_eventcalendar\persistent\email_template;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\calendar_form_validation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\day_form_validation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\notificationset_form_validation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\calendar_form_data::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\day_form_data::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\emailtemplate_form_definition::class)]
/**
 * Tests of the hooks that extend the forms of the plugin.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\hook\calendar_form_validation
 * @covers      \local_eventcalendar\hook\day_form_validation
 * @covers      \local_eventcalendar\hook\notificationset_form_validation
 * @covers      \local_eventcalendar\hook\calendar_form_data
 * @covers      \local_eventcalendar\hook\day_form_data
 * @covers      \local_eventcalendar\hook\emailtemplate_form_definition
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_hooks_test extends \advanced_testcase {
    /**
     * Validation hooks of the three forms.
     *
     * @return array
     */
    public static function validation_hook_provider(): array {
        return [
            'calendar' => [calendar_form_validation::class],
            'day' => [day_form_validation::class],
            'notification set' => [notificationset_form_validation::class],
        ];
    }

    /**
     * The validation hooks start from the errors of the form, and errors can be added, replaced and removed.
     *
     * @dataProvider validation_hook_provider
     * @param string $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validation_hook_provider')]
    public function test_validation_hook_errors(string $class): void {
        $data = (object) ['name' => 'x'];
        $hook = new $class($data, ['file' => 'y'], ['name' => 'Required', 'other' => 'Other error']);

        $this->assertSame($data, $hook->get_data());
        $this->assertSame(['file' => 'y'], $hook->get_files());

        $hook->add_error('extra', 'Extra error');
        $hook->add_error('name', 'Replaced');
        $hook->remove_error('other');
        $hook->remove_error('missing');

        $this->assertSame(['name' => 'Replaced', 'extra' => 'Extra error'], $hook->get_errors());
    }

    /**
     * The data hooks write the values into the object of the form and expose the id.
     */
    public function test_data_hooks_set_defaults(): void {
        $defaults = (object) ['name' => 'Keep'];
        $hook = new calendar_form_data(0, $defaults);
        $hook->set_defaults(['extra' => 5, 'name' => 'Changed']);

        $this->assertSame(0, $hook->get_calendarid());
        $this->assertSame($defaults, $hook->get_defaults());
        $this->assertSame(5, $defaults->extra);
        $this->assertSame('Changed', $defaults->name);

        $dayhook = new day_form_data(7, new \stdClass());
        $this->assertSame(7, $dayhook->get_dayid());
    }

    /**
     * An element added by a callback of the definition hook is part of the rendered form.
     */
    public function test_definition_hook_adds_element(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/eventcalendar/email_templates.php');

        $id = $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => 'Template', 'language' => 'it', 'identifier' => 'template', 'content' => '<p>{{content}}</p>',
            'isdefault' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $seenid = null;
        $this->redirectHook(
            emailtemplate_form_definition::class,
            function (emailtemplate_form_definition $hook) use (&$seenid): void {
                $seenid = (int) $hook->get_template()->get('id');
                $hook->get_mform()->addElement('text', 'extrafield', 'Extra field');
                $hook->get_mform()->setType('extrafield', PARAM_TEXT);
            }
        );

        $form = new email_template_form(
            new \moodle_url('/local/eventcalendar/email_templates.php'),
            ['persistent' => new email_template($id)]
        );
        $html = $form->render();

        $this->assertSame($id, $seenid);
        $this->assertStringContainsString('id_extrafield', $html);
    }
}
