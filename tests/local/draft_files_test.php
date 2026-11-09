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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\draft_files::class)]
/**
 * Tests of the server-side check of the file types in a draft area.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\draft_files
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class draft_files_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a draft area of the current user with the given files, as the file picker does.
     *
     * @param string[] $filenames the MIME type is derived from the extension
     * @return int draft item id
     */
    protected function create_draft(array $filenames): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        foreach ($filenames as $filename) {
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ], 'test content');
        }

        return $draftitemid;
    }

    /**
     * With a type group, only the files outside the group are reported.
     */
    public function test_type_group(): void {
        $draftitemid = $this->create_draft(['photo.png', 'picture.jpg', 'page.html', 'document.pdf']);

        $names = draft_files::find_not_accepted($draftitemid, ['web_image']);
        sort($names);

        $this->assertSame(['document.pdf', 'page.html'], $names);
    }

    /**
     * With a list of extensions, the other images are reported too.
     */
    public function test_extensions(): void {
        $draftitemid = $this->create_draft(['logo.svg', 'logo.png', 'logo.gif']);

        $this->assertSame(['logo.gif'], draft_files::find_not_accepted($draftitemid, ['.svg', '.png', '.jpg', '.jpeg']));
    }

    /**
     * An empty or missing draft area has nothing to report.
     */
    public function test_empty(): void {
        $this->assertSame([], draft_files::find_not_accepted(0, ['web_image']));
        $this->assertSame([], draft_files::find_not_accepted(file_get_unused_draft_itemid(), ['web_image']));
        $this->assertNull(draft_files::get_error(0, ['web_image']));
    }

    /**
     * The error lists the files to remove.
     */
    public function test_get_error(): void {
        $draftitemid = $this->create_draft(['photo.png', 'page.html']);

        $this->assertSame(
            get_string('errorfilesnotaccepted', 'local_eventcalendar', 'page.html'),
            draft_files::get_error($draftitemid, ['web_image'])
        );
    }
}
