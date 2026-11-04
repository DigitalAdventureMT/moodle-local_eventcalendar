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

use local_eventcalendar\hook\day_saved;
use local_eventcalendar\persistent\day;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\day_manager::class)]
/**
 * Tests for the service that manages the days of a calendar.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\day_manager
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class day_manager_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a day in a new calendar.
     *
     * @return day
     */
    protected function create_day(): day {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();

        return $generator->create_day(['calendarid' => $calendar->get('id'), 'daynumber' => 1]);
    }

    /**
     * Returns form data for a day, with an empty content draft area.
     *
     * @param array $overrides Values replacing the defaults.
     * @return \stdClass
     */
    protected function form_data(array $overrides = []): \stdClass {
        return (object) ($overrides + [
            'notiftitle' => 'Title',
            'notifbodytext' => 'Body',
            'notes' => 'Note',
            'isready' => 1,
            'content' => '<p>Content</p>',
            'contentformat' => FORMAT_HTML,
            'contentitemid' => file_get_unused_draft_itemid(),
        ]);
    }

    /**
     * Texts, status and content are saved, and the hook receives the saved day.
     */
    public function test_save_texts_and_hook(): void {
        $day = $this->create_day();

        $seen = [];
        $this->redirectHook(day_saved::class, function (day_saved $hook) use (&$seen): void {
            $seen[] = [
                'id' => (int) $hook->get_day()->get('id'),
                'title' => $hook->get_day()->get('notiftitle'),
                'rest' => $hook->is_restday(),
                'data' => $hook->get_data()->notes,
            ];
        });

        day_manager::save($day, $this->form_data(), false);

        $saved = new day($day->get('id'));
        $this->assertSame('Title', $saved->get('notiftitle'));
        $this->assertSame('Body', $saved->get('notifbody'));
        $this->assertSame('Note', $saved->get('notes'));
        $this->assertTrue((bool) $saved->get('isready'));
        $this->assertSame('<p>Content</p>', $saved->get('content'));

        $this->assertSame([['id' => (int) $day->get('id'), 'title' => 'Title', 'rest' => false, 'data' => 'Note']], $seen);
    }

    /**
     * A weekly rest day saves the texts and tells the hook that the day is a rest day.
     */
    public function test_rest_day_is_passed_to_the_hook(): void {
        $day = $this->create_day();

        $hookrest = null;
        $this->redirectHook(day_saved::class, function (day_saved $hook) use (&$hookrest): void {
            $hookrest = $hook->is_restday();
        });

        day_manager::save($day, $this->form_data(), true);

        $this->assertSame('Title', (new day($day->get('id')))->get('notiftitle'));
        $this->assertTrue($hookrest);
    }

    /**
     * The images of the content editor are moved from the draft area to the file area of the day.
     */
    public function test_content_files_are_saved(): void {
        global $USER;

        $day = $this->create_day();
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'photo.png',
        ], 'image');

        day_manager::save($day, $this->form_data([
            'content' => '<img src="@@PLUGINFILE@@/photo.png">',
            'contentformat' => FORMAT_HTML,
            'contentitemid' => $draftitemid,
        ]), false);

        $this->assertTrue(get_file_storage()->file_exists(
            \context_system::instance()->id,
            'local_eventcalendar',
            'content',
            $day->get('id'),
            '/',
            'photo.png'
        ));
        $this->assertStringContainsString('@@PLUGINFILE@@/photo.png', (new day($day->get('id')))->get('content'));
    }
}
