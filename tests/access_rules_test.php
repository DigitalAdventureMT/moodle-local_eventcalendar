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

namespace local_eventcalendar;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\local\accessrule\registry;
use local_eventcalendar\persistent\calendar;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\access_rules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\membership::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\role::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\cohort::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_eventcalendar\local\accessrule\registry::class)]
/**
 * Tests for the access rules: evaluation, the rule types of the plugin (role, cohort), invalid rules, rules of a
 * type that is no longer registered, saving, copying and deletion.
 *
 * Other plugins can register more types: the tests use only role and cohort and never assume the full list.
 *
 * @package     local_eventcalendar
 * @covers      \local_eventcalendar\local\access_rules
 * @covers      \local_eventcalendar\local\accessrule\base
 * @covers      \local_eventcalendar\local\accessrule\membership
 * @covers      \local_eventcalendar\local\accessrule\role
 * @covers      \local_eventcalendar\local\accessrule\cohort
 * @covers      \local_eventcalendar\local\accessrule\registry
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_rules_test extends \advanced_testcase {
    /**
     * Starts each test with a clean database and empty caches, as the rule classes keep request-level data.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        access_rules::reset_cache();
        registry::reset();
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
     * Saves the rules and clears the caches, as happens between two requests.
     *
     * @param calendar $calendar
     * @param array $groups
     */
    protected function save(calendar $calendar, array $groups): void {
        access_rules::save((int) $calendar->get('id'), $groups);
        access_rules::reset_cache();
        registry::reset();
    }

    /**
     * Builds a rule in the web service format.
     *
     * @param string $type
     * @param string $subject
     * @param string $comparison
     * @param string $value
     * @return array
     */
    protected static function rule(string $type, string $subject, string $comparison, string $value = ''): array {
        return ['ruletype' => $type, 'subject' => $subject, 'comparison' => $comparison, 'value' => $value];
    }

    /**
     * Returns the full user record, as $USER in a page.
     *
     * @param int $userid
     * @return \stdClass
     */
    protected static function user(int $userid): \stdClass {
        return \core_user::get_user($userid);
    }

    public function test_no_rules_open_to_all(): void {
        $calendar = $this->generator()->create_calendar();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(access_rules::has_rules((int) $calendar->get('id')));
        $this->assertTrue(access_rules::user_can_access($calendar, $user));
    }

    public function test_groups_or_and_rules_and(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $roleid = $this->create_system_role('reviewer');
        $otherroleid = $this->create_system_role('auditor');

        $inboth = $this->getDataGenerator()->create_user();
        $cohortonly = $this->getDataGenerator()->create_user();
        $auditor = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $inboth->id);
        cohort_add_member($cohort->id, $cohortonly->id);
        role_assign($roleid, $inboth->id, \context_system::instance()->id);
        role_assign($otherroleid, $auditor->id, \context_system::instance()->id);

        // Group 1: cohort and role reviewer. Group 2: role auditor.
        $this->save($calendar, [
            ['rules' => [
                self::rule('cohort', (string) $cohort->id, 'is'),
                self::rule('role', (string) $roleid, 'is'),
            ]],
            ['rules' => [self::rule('role', (string) $otherroleid, 'is')]],
        ]);

        $this->assertTrue(access_rules::user_can_access($calendar, self::user($inboth->id)));
        $this->assertFalse(access_rules::user_can_access($calendar, self::user($cohortonly->id)));
        $this->assertTrue(access_rules::user_can_access($calendar, self::user($auditor->id)));
        $this->assertFalse(access_rules::user_can_access($calendar, self::user($nobody->id)));
    }

    public function test_role_rule(): void {
        $calendar = $this->generator()->create_calendar();
        $roleid = $this->create_system_role('reviewer');

        $reviewer = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        role_assign($roleid, $reviewer->id, \context_system::instance()->id);

        $this->save($calendar, [['rules' => [self::rule('role', (string) $roleid, 'is')]]]);
        $this->assertTrue(access_rules::user_can_access($calendar, $reviewer));
        $this->assertFalse(access_rules::user_can_access($calendar, $other));

        $this->save($calendar, [['rules' => [self::rule('role', (string) $roleid, 'isnot')]]]);
        $this->assertFalse(access_rules::user_can_access($calendar, $reviewer));
        $this->assertTrue(access_rules::user_can_access($calendar, $other));
    }

    public function test_role_rule_rejects_implicit_and_course_roles(): void {
        global $CFG, $DB;

        $calendar = $this->generator()->create_calendar();
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);

        foreach ([$CFG->defaultuserroleid, $studentrole] as $roleid) {
            try {
                access_rules::save((int) $calendar->get('id'), [['rules' => [self::rule('role', (string) $roleid, 'is')]]]);
                $this->fail('Role not assignable at system level accepted: ' . $roleid);
            } catch (\moodle_exception $e) {
                $this->assertSame('errorinvalidaccessrule', $e->errorcode);
            }
        }
    }

    public function test_deleted_element_fails_closed_even_with_negation(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();

        $this->save($calendar, [['rules' => [self::rule('cohort', (string) $cohort->id, 'isnot')]]]);
        $this->assertTrue(access_rules::user_can_access($calendar, $user));

        cohort_delete_cohort($cohort);
        access_rules::reset_cache();
        registry::reset();

        $this->assertFalse(access_rules::user_can_access($calendar, $user));
        $this->assertSame([(int) $calendar->get('id') => 1], access_rules::get_invalid_counts());

        $state = access_rules::get_ui_state((int) $calendar->get('id'));
        $this->assertTrue($state[0]['rules'][0]['invalid']);
        $this->assertNotEmpty($state[0]['rules'][0]['invalidreason']);
    }

    public function test_save_validation(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $calendarid = (int) $calendar->get('id');

        $invalid = [
            'tipo sconosciuto' => self::rule('unknown', '1', 'is'),
            'missing cohort' => self::rule('cohort', '999999', 'is'),
            'operator not allowed' => self::rule('cohort', (string) $cohort->id, 'contains', 'x'),
            'missing role' => self::rule('role', '999999', 'is'),
        ];

        foreach ($invalid as $label => $rule) {
            try {
                access_rules::save($calendarid, [['rules' => [$rule]]]);
                $this->fail('Condition accepted: ' . $label);
            } catch (\moodle_exception $e) {
                $this->assertSame('errorinvalidaccessrule', $e->errorcode, $label);
            }
        }

        // Empty groups are discarded and the value is ignored for operators without value.
        $this->save($calendar, [
            ['rules' => []],
            ['rules' => [self::rule('cohort', (string) $cohort->id, 'is', 'ignorato')]],
        ]);
        $groups = access_rules::get_groups($calendarid);
        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['rules'][0]->value);
    }

    /**
     * The subject of a type that uses ids is accepted only as a positive integer: other texts that PHP would
     * read as the same id are rejected instead of being saved as they are.
     */
    public function test_save_rejects_malformed_ids(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $roleid = $this->create_system_role('reviewer');
        $calendarid = (int) $calendar->get('id');

        $malformed = [
            'cohort with text' => self::rule('cohort', $cohort->id . ' OR 1=1', 'is'),
            'cohort with leading zero' => self::rule('cohort', '0' . $cohort->id, 'is'),
            'cohort as decimal' => self::rule('cohort', $cohort->id . '.0', 'is'),
            'role with text' => self::rule('role', $roleid . 'abc', 'is'),
        ];

        foreach ($malformed as $label => $rule) {
            try {
                access_rules::save($calendarid, [['rules' => [$rule]]]);
                $this->fail('Condition accepted: ' . $label);
            } catch (\moodle_exception $e) {
                $this->assertSame('errorinvalidaccessrule', $e->errorcode, $label);
            }
        }

        // The same ids written correctly are accepted, with the spaces around them removed.
        $this->save($calendar, [['rules' => [
            self::rule('cohort', ' ' . $cohort->id . ' ', 'is'),
            self::rule('role', (string) $roleid, 'is'),
        ]]]);
        $this->assertSame((string) $cohort->id, access_rules::get_groups($calendarid)[0]['rules'][0]->subject);
    }

    /**
     * The number of blocks and of conditions in a block is limited; the limits themselves are accepted.
     */
    public function test_save_limits(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $calendarid = (int) $calendar->get('id');
        $rule = self::rule('cohort', (string) $cohort->id, 'is');

        $atlimit = array_fill(0, access_rules::MAX_GROUPS, ['rules' => array_fill(0, access_rules::MAX_RULES_PER_GROUP, $rule)]);
        $this->save($calendar, $atlimit);
        $this->assertCount(access_rules::MAX_GROUPS, access_rules::get_groups($calendarid));

        $toomanygroups = array_fill(0, access_rules::MAX_GROUPS + 1, ['rules' => [$rule]]);
        $toomanyrules = [['rules' => array_fill(0, access_rules::MAX_RULES_PER_GROUP + 1, $rule)]];
        foreach (['groups' => $toomanygroups, 'rules' => $toomanyrules] as $label => $groups) {
            try {
                access_rules::save($calendarid, $groups);
                $this->fail('Limit not applied: ' . $label);
            } catch (\moodle_exception $e) {
                $this->assertSame('errortoomanyaccessrules', $e->errorcode, $label);
            }
        }

        // The rejected saves left the previous rules untouched.
        access_rules::reset_cache();
        $this->assertCount(access_rules::MAX_GROUPS, access_rules::get_groups($calendarid));
    }

    /**
     * The labels given to the rules page are plain text, because the JavaScript writes them as text; the
     * description shown in the list of calendars stays HTML, with the name escaped.
     */
    public function test_labels_for_page_are_plain_text(): void {
        global $DB;

        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Sales & marketing']);
        $roleid = $this->create_system_role('reviewer');
        $DB->set_field('role', 'name', 'R&D reviewers', ['id' => $roleid]);
        $this->save($calendar, [['rules' => [self::rule('cohort', (string) $cohort->id, 'is')]]]);

        $state = access_rules::get_ui_state((int) $calendar->get('id'));
        $this->assertSame('Sales & marketing', $state[0]['rules'][0]['subjectlabel']);

        $config = access_rules::get_ui_config();
        $roletype = array_values(array_filter($config['types'], fn($type) => $type['type'] === 'role'))[0];
        $this->assertContains('R&D reviewers', array_column($roletype['subjects'], 'label'));

        $rule = access_rules::get_groups((int) $calendar->get('id'))[0]['rules'][0];
        $this->assertStringContainsString('Sales &amp; marketing', access_rules::describe_rule($rule));
    }

    public function test_save_keeps_existing_invalid_rule(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $rule = self::rule('cohort', (string) $cohort->id, 'is');

        $this->save($calendar, [['rules' => [$rule]]]);
        cohort_delete_cohort($cohort);

        // Saving the same rule again, now invalid, is allowed.
        $roleid = $this->create_system_role('reviewer');
        $this->save($calendar, [['rules' => [$rule, self::rule('role', (string) $roleid, 'is')]]]);
        $this->assertCount(2, access_rules::get_groups((int) $calendar->get('id'))[0]['rules']);
    }

    public function test_copy_and_delete(): void {
        $source = $this->generator()->create_calendar();
        $target = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $roleid = $this->create_system_role('reviewer');

        $this->save($source, [
            ['rules' => [self::rule('cohort', (string) $cohort->id, 'is'), self::rule('role', (string) $roleid, 'isnot')]],
            ['rules' => [self::rule('role', (string) $roleid, 'is')]],
        ]);

        access_rules::copy((int) $source->get('id'), (int) $target->get('id'));
        $copied = access_rules::get_groups((int) $target->get('id'));
        $this->assertCount(2, $copied);
        $this->assertCount(2, $copied[0]['rules']);
        $this->assertSame('isnot', $copied[0]['rules'][1]->comparison);

        access_rules::delete_for_calendar((int) $source->get('id'));
        $this->assertFalse(access_rules::has_rules((int) $source->get('id')));
        $this->assertTrue(access_rules::has_rules((int) $target->get('id')));
    }

    public function test_prefetch_gives_same_result(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort();
        $roleid = $this->create_system_role('reviewer');
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
            if ($i < 3) {
                cohort_add_member($cohort->id, $users[$i]->id);
            }
            if ($i % 2 === 0) {
                role_assign($roleid, $users[$i]->id, \context_system::instance()->id);
            }
        }

        $this->save($calendar, [['rules' => [
            self::rule('cohort', (string) $cohort->id, 'is'),
            self::rule('role', (string) $roleid, 'is'),
        ]]]);

        $expected = [];
        foreach ($users as $i => $user) {
            $expected[$i] = access_rules::user_can_access($calendar, self::user($user->id));
        }

        access_rules::reset_cache();
        registry::reset();
        access_rules::prefetch((int) $calendar->get('id'), array_column($users, 'id'));

        foreach ($users as $i => $user) {
            $this->assertSame($expected[$i], access_rules::user_can_access($calendar, self::user($user->id)));
        }
        $this->assertSame([true, false, true, false, false], $expected);
    }

    public function test_describe_rule(): void {
        $calendar = $this->generator()->create_calendar();
        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Editorial office']);
        $this->save($calendar, [['rules' => [self::rule('cohort', (string) $cohort->id, 'isnot')]]]);

        $rule = access_rules::get_groups((int) $calendar->get('id'))[0]['rules'][0];
        $description = access_rules::describe_rule($rule);

        $this->assertStringContainsString(get_string('ruletype_cohort', 'local_eventcalendar'), $description);
        $this->assertStringContainsString(get_string('ruleop_cohort_isnot', 'local_eventcalendar'), $description);
        $this->assertStringContainsString('Editorial office', $description);
    }

    public function test_ui_config_lists_plugin_types_first(): void {
        $config = access_rules::get_ui_config();
        $types = array_column($config['types'], 'type');

        // Types added by other plugins, if any, come after those of this plugin.
        $this->assertSame(['role', 'cohort'], array_slice($types, 0, 2));
        $this->assertContains('is', $config['novalueoperators']);
    }

    /**
     * A rule of a type that is not registered (for example added by a plugin that was uninstalled) stays saved,
     * is not valid, never matches and is described with the generic message.
     */
    public function test_unregistered_type_fails_closed(): void {
        global $DB;

        $calendar = $this->generator()->create_calendar();
        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->save($calendar, [['rules' => [self::rule('cohort', (string) $cohort->id, 'isnot')]]]);

        // The rule type is changed directly in the database to a type nobody registers.
        $DB->set_field('local_eventcalendar_rule', 'ruletype', 'unregisteredtype', ['calendarid' => $calendar->get('id')]);
        access_rules::reset_cache();
        registry::reset();

        $this->assertNull(registry::get('unregisteredtype'));
        $this->assertTrue(access_rules::has_rules((int) $calendar->get('id')));
        $this->assertFalse(access_rules::user_can_access($calendar, self::user($user->id)));

        $state = access_rules::get_ui_state((int) $calendar->get('id'));
        $this->assertTrue($state[0]['rules'][0]['invalid']);
        $this->assertSame(get_string('ruleinvalid_type', 'local_eventcalendar'), $state[0]['rules'][0]['invalidreason']);
    }

    /**
     * Creates a role assignable in the system context.
     *
     * @param string $shortname
     * @return int
     */
    protected function create_system_role(string $shortname): int {
        $roleid = create_role(ucfirst($shortname), $shortname, '');
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        return (int) $roleid;
    }
}
