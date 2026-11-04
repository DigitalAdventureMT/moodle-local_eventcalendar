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

namespace local_eventcalendar\external;

use local_eventcalendar\local\access_rules;

defined('MOODLE_INTERNAL') || die();

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\external\search_rule_subjects::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\external\save_access_rules::class)]
/**
 * Tests for the access rules web services: permissions, subject search, saving and response format.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\external\search_rule_subjects
 * @covers      \local_eventcalendar\external\save_access_rules
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_rules_external_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        access_rules::reset_cache();

        // Loading the classes of the web services defines, in Moodle 4.1, the core_external aliases used below.
        class_exists(search_rule_subjects::class);
        class_exists(save_access_rules::class);
    }

    public function test_search_rule_subjects(): void {
        $this->setAdminUser();
        $this->getDataGenerator()->create_cohort(['name' => 'Central editorial office', 'idnumber' => 'RED']);
        $this->getDataGenerator()->create_cohort(['name' => 'Commerciale']);

        $result = \core_external\external_api::clean_returnvalue(
            search_rule_subjects::execute_returns(),
            search_rule_subjects::execute('cohort', 'editorial')
        );
        $this->assertCount(1, $result);
        $this->assertStringContainsString('Central editorial office', $result[0]['label']);

        // A rule type without search support must be rejected.
        $this->expectException(\invalid_parameter_exception::class);
        search_rule_subjects::execute('role', '');
    }

    public function test_save_access_rules(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();

        $groups = [
            ['rules' => [
                ['ruletype' => 'cohort', 'subject' => (string) $cohort->id, 'comparison' => 'is', 'value' => ''],
                ['ruletype' => 'cohort', 'subject' => (string) $cohort->id, 'comparison' => 'isnot', 'value' => ''],
            ]],
            ['rules' => []],
        ];

        $result = \core_external\external_api::clean_returnvalue(
            save_access_rules::execute_returns(),
            save_access_rules::execute((int) $calendar->get('id'), $groups)
        );

        $this->assertCount(1, $result['groups']);
        $this->assertCount(2, $result['groups'][0]['rules']);
        $this->assertFalse($result['groups'][0]['rules'][0]['invalid']);
        $this->assertTrue(access_rules::has_rules((int) $calendar->get('id')));
    }

    public function test_save_requires_capability(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        save_access_rules::execute((int) $calendar->get('id'), []);
    }

    public function test_save_unknown_calendar(): void {
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        save_access_rules::execute(999999, []);
    }
}
