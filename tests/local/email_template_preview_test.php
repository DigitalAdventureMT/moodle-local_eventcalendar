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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\email_template_preview::class)]
/**
 * Tests of the structure preview of the email templates.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\email_template_preview
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_template_preview_test extends \advanced_testcase {
    /**
     * A full document gets the style inside its own head, and nothing else changes.
     */
    public function test_full_document(): void {
        $content = '<html><head><title>T</title></head><body><table><tr><td>{{content}}</td></tr></table></body></html>';

        $document = email_template_preview::build_document($content);

        $this->assertStringStartsWith('<html><head><title>T</title><style>', $document);
        $this->assertStringContainsString('</style></head><body><table>', $document);
        $this->assertSame(1, substr_count($document, '<html'));
    }

    /**
     * A fragment is wrapped in a minimal document with the style.
     */
    public function test_fragment(): void {
        $document = email_template_preview::build_document('<p>{{content}}</p>');

        $this->assertStringStartsWith('<!DOCTYPE html><html', $document);
        $this->assertStringContainsString('<style>', $document);
        $this->assertStringContainsString('<body><p>{{content}}</p></body>', $document);
    }

    /**
     * The edit form no longer prints the content of the template in the page: it shows a sandboxed frame.
     */
    public function test_form_uses_sandboxed_frame(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $id = $DB->insert_record('local_eventcalendar_emailtpl', (object) [
            'name' => 'Test', 'language' => 'en', 'identifier' => 'test', 'isdefault' => 0,
            'content' => '<p><img src="x" onerror="alert(1)">Marker</p>',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $form = new \local_eventcalendar\form\email_template_form(null, [
            'persistent' => new \local_eventcalendar\persistent\email_template($id),
        ]);
        $html = $form->render();

        // The markup of the template must not be in the page as markup. It may appear escaped, for example in the
        // content field that an add-on adds to the form.
        $this->assertStringNotContainsString('<img src="x" onerror', $html);
        $this->assertStringContainsString('sandbox="' . email_template_preview::SANDBOX . '"', $html);
        $this->assertStringContainsString(email_template_preview::get_url($id)->out(), $html);
    }
}
