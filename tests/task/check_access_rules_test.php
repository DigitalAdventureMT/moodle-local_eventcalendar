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

namespace local_eventcalendar\task;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\accessrule\registry;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\task\check_access_rules::class)]
/**
 * Tests for the task that notifies calendar managers about invalid access rules.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\task\check_access_rules
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class check_access_rules_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        access_rules::reset_cache();
        registry::reset();
    }

    public function test_no_invalid_rules_no_messages(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        access_rules::save((int) $calendar->get('id'), [
            ['rules' => [['ruletype' => 'cohort', 'subject' => (string) $cohort->id, 'comparison' => 'is', 'value' => '']]],
        ]);

        $sink = $this->redirectMessages();
        ob_start();
        (new check_access_rules())->execute();
        ob_end_clean();

        $this->assertCount(0, $sink->get_messages());
    }

    public function test_invalid_rules_notify_managers(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('local_eventcalendar');
        $calendar = $generator->create_calendar(['name' => 'Advent']);
        $cohort = $this->getDataGenerator()->create_cohort();
        access_rules::save((int) $calendar->get('id'), [
            ['rules' => [['ruletype' => 'cohort', 'subject' => (string) $cohort->id, 'comparison' => 'is', 'value' => '']]],
        ]);
        cohort_delete_cohort($cohort);
        access_rules::reset_cache();
        registry::reset();

        $manager = $this->getDataGenerator()->create_user();
        $suspendedmanager = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $plainuser = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerrole, $manager->id, \context_system::instance()->id);
        role_assign($managerrole, $suspendedmanager->id, \context_system::instance()->id);

        $sink = $this->redirectMessages();
        ob_start();
        (new check_access_rules())->execute();
        ob_end_clean();

        $recipients = [];
        foreach ($sink->get_messages() as $message) {
            $this->assertSame('local_eventcalendar', $message->component);
            $this->assertSame('invalidaccessrules', $message->eventtype);
            $this->assertStringContainsString('accessrules.php?calendarid=' . $calendar->get('id'), $message->fullmessagehtml);
            $recipients[] = (int) $message->useridto;
        }

        $this->assertContains((int) $manager->id, $recipients);
        $this->assertContains((int) get_admin()->id, $recipients);
        $this->assertNotContains((int) $suspendedmanager->id, $recipients);
        $this->assertNotContains((int) $plainuser->id, $recipients);
    }
}
