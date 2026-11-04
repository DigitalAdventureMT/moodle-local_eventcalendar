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

namespace local_eventcalendar\persistent;

#[\PHPUnit\Framework\Attributes\CoversClass(email_template::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(calendar::class)]
/**
 * Tests of the email templates and of the template chosen for each calendar.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\persistent\email_template
 * @covers      \local_eventcalendar\persistent\calendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_template_test extends \advanced_testcase {
    /**
     * A new template comes from a file unless told otherwise, and only the two known sources are valid.
     */
    public function test_source(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $template = new email_template(0, (object) [
            'name' => 'Winter', 'language' => 'it', 'identifier' => 'winter', 'content' => '<p>{{content}}</p>',
        ]);
        $template->create();
        $this->assertSame(email_template::SOURCE_FILE, $template->get('source'));

        $template->set('source', email_template::SOURCE_CUSTOM);
        $this->assertTrue($template->validate());

        $template->set('source', 'other');
        $this->assertArrayHasKey('source', $template->validate());
    }

    /**
     * A calendar uses its own email template, or the default one when it has none or it was deleted.
     */
    public function test_calendar_email_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $default = new email_template(0, (object) [
            'name' => 'Base', 'language' => 'it', 'identifier' => 'base', 'content' => '<p>BASE {{content}}</p>',
        ]);
        $default->create();
        $default->set_as_default();
        $winter = new email_template(0, (object) [
            'name' => 'Winter', 'language' => 'it', 'identifier' => 'winter', 'content' => '<p>WINTER {{content}}</p>',
        ]);
        $winter->create();

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['name' => 'Advent', 'emailtemplateid' => $winter->get('id')]);
        $day = $generator->create_day(['calendarid' => $calendar->get('id')]);
        $other = $generator->create_calendar(['name' => 'Lent']);

        $this->assertEquals($winter->get('id'), $calendar->get_email_template()->get('id'));
        $this->assertEquals($default->get('id'), $other->get_email_template()->get('id'));
        $this->assertStringContainsString('WINTER', \local_eventcalendar\local\email_renderer::render($day, $calendar));

        // A deleted template falls back to the default one.
        $winter->delete();
        $this->assertEquals($default->get('id'), (new calendar($calendar->get('id')))->get_email_template()->get('id'));
    }
}
