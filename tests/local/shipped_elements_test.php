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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\shipped_elements::class)]
/**
 * Tests of the import of the shipped elements, run at installation and at every upgrade.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\shipped_elements
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shipped_elements_test extends \advanced_testcase {
    /**
     * Counts the standard icon sets, the email templates, the banners and the header/footer pairs.
     *
     * @return int[]
     */
    protected function counts(): array {
        global $DB;

        return [
            'iconsets' => $DB->count_records('local_eventcalendar_icons', ['source' => 'standard']),
            'icons' => $DB->count_records('local_eventcalendar_iconitem'),
            'templates' => $DB->count_records('local_eventcalendar_emailtpl'),
            'banners' => $DB->count_records('local_eventcalendar_banner'),
            'headerfooters' => $DB->count_records('local_eventcalendar_headfoot'),
        ];
    }

    /**
     * An element missing from the database, as one added by a new version, is imported; running the import
     * again changes nothing.
     */
    public function test_import_adds_missing_only(): void {
        global $DB;

        $this->resetAfterTest();
        $installed = $this->counts();
        $this->assertGreaterThan(0, $installed['iconsets'], 'The installation imports the shipped icon sets');

        // An icon set shipped by a new version is like a set missing from the database.
        $set = $DB->get_record('local_eventcalendar_icons', ['source' => 'standard'], '*', IGNORE_MULTIPLE);
        $DB->delete_records('local_eventcalendar_iconitem', ['iconsetid' => $set->id]);
        $DB->delete_records('local_eventcalendar_icons', ['id' => $set->id]);

        shipped_elements::import();
        $this->assertSame($installed, $this->counts());
        $this->assertTrue($DB->record_exists('local_eventcalendar_icons', ['identifier' => $set->identifier]));

        shipped_elements::import();
        $this->assertSame($installed, $this->counts(), 'A second import adds nothing');
    }

    /**
     * An element already imported is not changed by the import.
     */
    public function test_import_keeps_existing(): void {
        global $DB;

        $this->resetAfterTest();
        $set = $DB->get_record('local_eventcalendar_icons', ['source' => 'standard'], '*', IGNORE_MULTIPLE);
        $DB->set_field('local_eventcalendar_icons', 'name', 'Renamed by the manager', ['id' => $set->id]);

        shipped_elements::import();

        $this->assertSame('Renamed by the manager', $DB->get_field('local_eventcalendar_icons', 'name', ['id' => $set->id]));
    }
}
