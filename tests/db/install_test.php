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

namespace local_eventcalendar\db;

use local_eventcalendar\local\date_resolver\lookup_table;
use local_eventcalendar\local\template_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/eventcalendar/db/install.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('local_eventcalendar_seed_date_lookup')]
/**
 * Tests of the data seeded by the installation of the plugin.
 *
 * The PHPUnit environment installs the plugin, so the rows written by xmldb_local_eventcalendar_install() are
 * already present when each test starts; resetAfterTest() restores them after the tests that change them.
 *
 * @package     local_eventcalendar
 * @covers      ::local_eventcalendar_seed_date_lookup
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class install_test extends \advanced_testcase {
    /**
     * The installation fills the lookup dates of the Chinese New Year from 2026 to 2040, at midnight UTC.
     */
    public function test_chinese_newyear_dates_are_seeded(): void {
        global $DB;

        $rows = $DB->get_records('local_eventcalendar_datelook', ['templatekey' => 'chinese_newyear'], 'eventyear');
        $this->assertCount(15, $rows);
        $this->assertSame(range(2026, 2040), array_map('intval', array_column($rows, 'eventyear')));

        // Two reference dates, and the resolver used by the calendar form reads them as they are.
        $resolver = new lookup_table('chinese_newyear');
        $this->assertSame(gmmktime(0, 0, 0, 2, 17, 2026), $resolver->resolve(2026));
        $this->assertSame(gmmktime(0, 0, 0, 2, 12, 2040), $resolver->resolve(2040));

        // The template of the lookup dates exists, so a calendar created from it gets default dates.
        $template = template_repository::get('chinese_newyear');
        $this->assertNotNull($template);
        $this->assertSame('lookup_table', $template->date_rule->type);
    }

    /**
     * Running the seeding again inserts nothing and keeps the dates changed by an administrator.
     */
    public function test_seeding_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();

        $corrected = gmmktime(0, 0, 0, 2, 18, 2026);
        $DB->set_field(
            'local_eventcalendar_datelook',
            'eventdate',
            $corrected,
            ['templatekey' => 'chinese_newyear', 'eventyear' => 2026]
        );

        $this->assertSame(0, local_eventcalendar_seed_date_lookup());
        $this->assertSame(15, $DB->count_records('local_eventcalendar_datelook', ['templatekey' => 'chinese_newyear']));
        $this->assertEquals($corrected, $DB->get_field(
            'local_eventcalendar_datelook',
            'eventdate',
            ['templatekey' => 'chinese_newyear', 'eventyear' => 2026]
        ));

        // A missing year is added back.
        $DB->delete_records('local_eventcalendar_datelook', ['templatekey' => 'chinese_newyear', 'eventyear' => 2030]);
        $this->assertSame(1, local_eventcalendar_seed_date_lookup());
    }
}
