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

use local_eventcalendar\hook\day_form_definition;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\form\day_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\hook\day_form_definition::class)]
/**
 * Tests of the column layout of the day form.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\form\day_form
 * @covers      \local_eventcalendar\hook\day_form_definition
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_form_layout_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Builds the form of a new day of a new calendar.
     *
     * @return day_form
     */
    protected function create_form(): day_form {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $day = $generator->create_day(['calendarid' => $calendar->get('id'), 'daynumber' => 1]);

        return new day_form(new \moodle_url('/local/eventcalendar/days.php'), [
            'persistent' => $day,
            'calendar' => $calendar,
        ]);
    }

    /**
     * Without callbacks the layout has three columns and the preview buttons are unwrapped.
     */
    public function test_default_layout(): void {
        $layout = $this->create_form()->get_layout();

        $this->assertCount(day_form::LAYOUT_COLUMNS, $layout['columns']);
        $this->assertSame('dayinfo', $layout['columns'][0][0]);
        $this->assertContains('content', $layout['columns'][2]);
        $this->assertContains('previewcontentbtn', $layout['unwrapped']);
    }

    /**
     * An element placed by a callback goes before the given element; moving it again does not duplicate it.
     */
    public function test_place_element_from_hook(): void {
        $this->redirectHook(day_form_definition::class, function (day_form_definition $hook): void {
            $hook->get_mform()->addElement('text', 'extrafield', 'Extra field');
            $hook->get_mform()->setType('extrafield', PARAM_TEXT);
            $hook->place_element('extrafield', 1);
            $hook->place_element('extrafield', 3, 'previewcontentbtn');
            $hook->place_element('extrabutton', 2, 'testemailbtn', true);
        });

        $layout = $this->create_form()->get_layout();
        $third = $layout['columns'][2];

        $this->assertNotContains('extrafield', $layout['columns'][0]);
        $this->assertSame(array_search('previewcontentbtn', $third, true) - 1, array_search('extrafield', $third, true));
        // A button placed before a button of a row goes before the whole row.
        $second = $layout['columns'][1];
        $rowindex = count($second) - 1;
        $this->assertSame('extrabutton', $second[$rowindex - 1]);
        $this->assertContains('extrabutton', $layout['unwrapped']);
    }

    /**
     * Removing an element takes it out of the columns, also from a row of buttons; a wrong column is refused.
     */
    public function test_remove_and_invalid_column(): void {
        $form = $this->create_form();

        $form->remove_from_layout('testemailbtn');
        $form->remove_from_layout('notes');
        $layout = $form->get_layout();

        $this->assertNotContains('notes', $layout['columns'][0]);
        $this->assertSame(['previewemailbtn', 'testnotificationbtn'], end($layout['columns'][1]));
        $this->assertNotContains('testemailbtn', $layout['unwrapped']);

        $this->expectException(\coding_exception::class);
        $form->place_element('notes', 4);
    }
}
