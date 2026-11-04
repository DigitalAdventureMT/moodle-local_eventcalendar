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

namespace local_eventcalendar\local;

use local_eventcalendar\hook\emailtemplate_saved;
use local_eventcalendar\persistent\email_template;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\email_template_manager::class)]
/**
 * Tests for the service that manages email templates.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\email_template_manager
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_template_manager_test extends \advanced_testcase {
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        // The installation imports the email templates shipped with the plugin: the tests start from an empty
        // table, so that the templates they create are the only ones and the identifiers do not collide.
        $DB->delete_records('local_eventcalendar_emailtpl');
    }

    /**
     * Returns the plugin test data generator.
     *
     * @return \local_eventcalendar_generator
     */
    protected function generator(): \local_eventcalendar_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
    }

    /**
     * Inserts an email template and returns it.
     *
     * @param string $identifier
     * @param bool $isdefault
     * @return email_template
     */
    protected function create_template(string $identifier, bool $isdefault): email_template {
        global $DB;

        $id = $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => $identifier, 'language' => 'it', 'identifier' => $identifier, 'content' => '<p>{{content}}</p>',
            'isdefault' => (int) $isdefault, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        return new email_template($id);
    }

    /**
     * Calendars using the deleted template go back to the default one; the default template is untouched.
     */
    public function test_delete_releases_calendars(): void {
        global $DB;

        $default = $this->create_template('default', true);
        $custom = $this->create_template('custom', false);
        $using = $this->generator()->create_calendar(['emailtemplateid' => $custom->get('id')]);

        email_template_manager::delete($custom);

        $this->assertFalse(email_template::record_exists($custom->get('id')));
        $this->assertNull($DB->get_field('local_eventcalendar_calendar', 'emailtemplateid', ['id' => $using->get('id')]));
        $this->assertTrue((bool) (new email_template($default->get('id')))->get('isdefault'));
    }

    /**
     * Deleting the default template promotes another one.
     */
    public function test_delete_default_promotes_another(): void {
        $default = $this->create_template('default', true);
        $other = $this->create_template('other', false);

        email_template_manager::delete($default);

        $this->assertFalse(email_template::record_exists($default->get('id')));
        $this->assertTrue((bool) (new email_template($other->get('id')))->get('isdefault'));
    }

    /**
     * Deleting the only template is allowed and leaves no template.
     */
    public function test_delete_last_template(): void {
        $only = $this->create_template('only', true);

        email_template_manager::delete($only);

        $this->assertSame([], email_template::get_records());
    }

    /**
     * Name, language and content are saved and the hook receives the saved template and the submitted data.
     */
    public function test_save_updates_template_and_dispatches_hook(): void {
        $template = $this->create_template('custom', false);

        $seen = [];
        $this->redirectHook(emailtemplate_saved::class, function (emailtemplate_saved $hook) use (&$seen): void {
            $seen[] = [
                'id' => (int) $hook->get_template()->get('id'),
                'name' => $hook->get_template()->get('name'),
                'extra' => $hook->get_data()->extra,
            ];
        });

        email_template_manager::save($template, (object) [
            'name' => 'New name', 'language' => 'en', 'content' => '<p>New {{content}}</p>', 'extra' => 'x',
        ]);

        $saved = new email_template($template->get('id'));
        $this->assertSame('New name', $saved->get('name'));
        $this->assertSame('en', $saved->get('language'));
        $this->assertSame('<p>New {{content}}</p>', $saved->get('content'));
        $this->assertSame([['id' => (int) $template->get('id'), 'name' => 'New name', 'extra' => 'x']], $seen);
    }

    /**
     * A form that does not send the content leaves the saved content untouched.
     */
    public function test_save_without_content_keeps_content(): void {
        $template = $this->create_template('custom', false);

        email_template_manager::save($template, (object) ['name' => 'Renamed', 'language' => 'it']);

        $saved = new email_template($template->get('id'));
        $this->assertSame('Renamed', $saved->get('name'));
        $this->assertSame('<p>{{content}}</p>', $saved->get('content'));
    }
}
