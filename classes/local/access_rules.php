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

/**
 * Access rules for calendars: reading, evaluation, saving, copying and anomaly detection.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\local\accessrule\base;
use local_eventcalendar\local\accessrule\registry;
use local_eventcalendar\persistent\calendar;

/**
 * Access rules for calendars: reading, evaluation, saving, copying and anomaly detection.
 *
 * Evaluation logic: with no saved block the calendar is visible to everybody; otherwise the user gets access
 * when at least one block is satisfied (OR), and a block is satisfied when all its conditions are met (AND).
 * An invalid condition (deleted item, disabled feature) is never satisfied, so a restricted calendar does not
 * open by itself when an item is deleted. Users who manage calendars or contents bypass these rules in the
 * pages. Conditions and their validity are cached for the duration of the request.
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_rules {
    /** Table of condition blocks. */
    public const TABLE_GROUP = 'local_eventcalendar_rulegrp';

    /** Table of conditions. */
    public const TABLE_RULE = 'local_eventcalendar_rule';

    /**
     * Maximum number of blocks of a calendar and of conditions in a block.
     *
     * The rules are evaluated for every user on every page and notification, so their number is bounded: the
     * limits are far above any real use and stop a request from writing thousands of rows in one save.
     */
    public const MAX_GROUPS = 50;

    /** Maximum number of conditions in a block (see MAX_GROUPS). */
    public const MAX_RULES_PER_GROUP = 20;

    /** @var array blocks read, by calendar: [calendarid => [['id' => int, 'rules' => stdClass[]]]] */
    protected static $groups = [];

    /** @var array validity of the conditions already checked: [ruleid => bool] */
    protected static $validity = [];

    /**
     * Blocks of a calendar with their conditions, in order.
     *
     * @param int $calendarid
     * @return array [['id' => int, 'rules' => \stdClass[]], ...]
     */
    public static function get_groups(int $calendarid): array {
        global $DB;

        if (isset(self::$groups[$calendarid])) {
            return self::$groups[$calendarid];
        }

        $grouprecords = $DB->get_records(self::TABLE_GROUP, ['calendarid' => $calendarid], 'sortorder, id');
        $rulerecords = $DB->get_records(self::TABLE_RULE, ['calendarid' => $calendarid], 'sortorder, id');

        $groups = [];
        foreach ($grouprecords as $grouprecord) {
            $groups[(int) $grouprecord->id] = ['id' => (int) $grouprecord->id, 'rules' => []];
        }
        foreach ($rulerecords as $rule) {
            if (isset($groups[(int) $rule->groupid])) {
                $groups[(int) $rule->groupid]['rules'][] = $rule;
            }
        }

        // A block without conditions should not exist; if it does it is ignored, so it cannot open the calendar to all.
        $groups = array_values(array_filter($groups, function ($group) {
            return !empty($group['rules']);
        }));

        self::$groups[$calendarid] = $groups;

        return $groups;
    }

    /**
     * Tells whether the calendar has access rules.
     *
     * @param int $calendarid
     * @return bool
     */
    public static function has_rules(int $calendarid): bool {
        return !empty(self::get_groups($calendarid));
    }

    /**
     * Tells whether the user satisfies the access rules of the calendar.
     *
     * @param calendar $calendar
     * @param \stdClass $user user record (the full record is needed for profile fields, as in $USER)
     * @return bool
     */
    public static function user_can_access(calendar $calendar, \stdClass $user): bool {
        $groups = self::get_groups((int) $calendar->get('id'));

        if (empty($groups)) {
            return true;
        }

        foreach ($groups as $group) {
            if (self::group_matches($group, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preloads in bulk the data needed to check many users on a calendar (notification sending task).
     *
     * @param int $calendarid
     * @param int[] $userids
     * @return void
     */
    public static function prefetch(int $calendarid, array $userids): void {
        if (empty($userids)) {
            return;
        }

        $bytype = [];
        foreach (self::get_groups($calendarid) as $group) {
            foreach ($group['rules'] as $rule) {
                if (self::is_rule_valid($rule)) {
                    $bytype[$rule->ruletype][] = $rule;
                }
            }
        }

        foreach ($bytype as $type => $rules) {
            registry::get($type)->prefetch($rules, array_map('intval', $userids));
        }
    }

    /**
     * Tells whether the stored condition is valid. An unknown type is not valid.
     *
     * @param \stdClass $rule
     * @return bool
     */
    public static function is_rule_valid(\stdClass $rule): bool {
        $id = isset($rule->id) ? (int) $rule->id : 0;

        if ($id && isset(self::$validity[$id])) {
            return self::$validity[$id];
        }

        $type = registry::get((string) $rule->ruletype);
        $valid = $type !== null && $type->is_valid($rule);

        if ($id) {
            self::$validity[$id] = $valid;
        }

        return $valid;
    }

    /**
     * Human readable reason why a condition is not valid.
     *
     * @param \stdClass $rule
     * @return string
     */
    public static function get_invalid_reason(\stdClass $rule): string {
        $type = registry::get((string) $rule->ruletype);

        // A type that is no longer registered (for example added by a plugin that was uninstalled) has its own
        // message; otherwise the type explains the reason, reading the message from its own component.
        return $type ? $type->get_invalid_message($rule) : get_string('ruleinvalid_type', 'local_eventcalendar');
    }

    /**
     * Replaces all the rules of a calendar with the given ones, in a single transaction.
     *
     * Each condition must be acceptable for its type (valid subject, allowed operator, value when needed).
     * An invalid condition identical to an already saved one is kept as it is, so the manager can save other
     * changes without fixing it immediately. Empty blocks are discarded. The number of blocks and of conditions in
     * a block is limited (MAX_GROUPS, MAX_RULES_PER_GROUP), checked before anything is read or written.
     *
     * @param int $calendarid
     * @param array $groups [['rules' => [['ruletype', 'subject', 'comparison', 'value'], ...]], ...]
     * @return void
     * @throws \moodle_exception if a condition is not acceptable or the limits are exceeded
     */
    public static function save(int $calendarid, array $groups): void {
        global $DB, $USER;

        // The limits are checked on the request as it is, empty blocks included: a valid page never sends more.
        if (count($groups) > self::MAX_GROUPS) {
            throw new \moodle_exception('errortoomanyaccessrules', 'local_eventcalendar', '', self::limits());
        }
        foreach ($groups as $group) {
            if (count($group['rules'] ?? []) > self::MAX_RULES_PER_GROUP) {
                throw new \moodle_exception('errortoomanyaccessrules', 'local_eventcalendar', '', self::limits());
            }
        }

        // Signatures of the saved conditions: only these may stay saved when no longer valid.
        $existing = [];
        foreach ($DB->get_records(self::TABLE_RULE, ['calendarid' => $calendarid]) as $rule) {
            $existing[self::signature($rule)] = true;
        }

        // Normalise and check all conditions before touching the database.
        $clean = [];
        foreach ($groups as $group) {
            $rules = [];
            foreach ($group['rules'] ?? [] as $data) {
                $rule = (object) [
                    'ruletype' => trim((string) ($data['ruletype'] ?? '')),
                    'subject' => trim((string) ($data['subject'] ?? '')),
                    'comparison' => trim((string) ($data['comparison'] ?? '')),
                    'value' => trim((string) ($data['value'] ?? '')),
                ];

                if (!base::operator_needs_value($rule->comparison)) {
                    $rule->value = null;
                }

                $type = registry::get($rule->ruletype);
                $acceptable = $type !== null && $type->is_acceptable($rule);

                if (!$acceptable && !isset($existing[self::signature($rule)])) {
                    throw new \moodle_exception('errorinvalidaccessrule', 'local_eventcalendar');
                }

                $rules[] = $rule;
            }

            if (!empty($rules)) {
                $clean[] = $rules;
            }
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records(self::TABLE_RULE, ['calendarid' => $calendarid]);
        $DB->delete_records(self::TABLE_GROUP, ['calendarid' => $calendarid]);

        foreach ($clean as $groupindex => $rules) {
            $groupid = $DB->insert_record(self::TABLE_GROUP, (object) [
                'calendarid' => $calendarid,
                'sortorder' => $groupindex,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $USER->id,
            ]);

            foreach ($rules as $ruleindex => $rule) {
                $rule->groupid = $groupid;
                $rule->calendarid = $calendarid;
                $rule->sortorder = $ruleindex;
                $rule->timecreated = $now;
                $rule->timemodified = $now;
                $rule->usermodified = $USER->id;
                $DB->insert_record(self::TABLE_RULE, $rule);
            }
        }

        $transaction->allow_commit();

        self::reset_cache();
    }

    /**
     * Values of the limits for the error message.
     *
     * @return \stdClass groups, rules
     */
    protected static function limits(): \stdClass {
        return (object) ['groups' => self::MAX_GROUPS, 'rules' => self::MAX_RULES_PER_GROUP];
    }

    /**
     * Copies the rules of a calendar to another one, invalid conditions included (the copy must be faithful).
     *
     * @param int $fromcalendarid
     * @param int $tocalendarid
     * @return void
     */
    public static function copy(int $fromcalendarid, int $tocalendarid): void {
        global $DB, $USER;

        $now = time();

        foreach ($DB->get_records(self::TABLE_GROUP, ['calendarid' => $fromcalendarid], 'sortorder, id') as $group) {
            $newgroupid = $DB->insert_record(self::TABLE_GROUP, (object) [
                'calendarid' => $tocalendarid,
                'sortorder' => $group->sortorder,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $USER->id,
            ]);

            foreach ($DB->get_records(self::TABLE_RULE, ['groupid' => $group->id], 'sortorder, id') as $rule) {
                unset($rule->id);
                $rule->groupid = $newgroupid;
                $rule->calendarid = $tocalendarid;
                $rule->timecreated = $now;
                $rule->timemodified = $now;
                $rule->usermodified = $USER->id;
                $DB->insert_record(self::TABLE_RULE, $rule);
            }
        }

        self::reset_cache();
    }

    /**
     * Deletes all the rules of a calendar.
     *
     * @param int $calendarid
     * @return void
     */
    public static function delete_for_calendar(int $calendarid): void {
        global $DB;

        $DB->delete_records(self::TABLE_RULE, ['calendarid' => $calendarid]);
        $DB->delete_records(self::TABLE_GROUP, ['calendarid' => $calendarid]);

        self::reset_cache();
    }

    /**
     * Calendars with at least one invalid condition, with their count.
     *
     * @return array [calendarid => number of invalid conditions]
     */
    public static function get_invalid_counts(): array {
        global $DB;

        $counts = [];
        foreach ($DB->get_records(self::TABLE_RULE, null, 'calendarid, id') as $rule) {
            if (!self::is_rule_valid($rule)) {
                $counts[(int) $rule->calendarid] = ($counts[(int) $rule->calendarid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Type configuration for the rules page: all types, including unavailable ones (needed to show saved
     * conditions of that type), with an "available" flag.
     *
     * @return array
     */
    public static function get_ui_config(): array {
        $types = [];
        foreach (registry::get_all() as $type) {
            $types[] = $type->get_ui_config();
        }

        return self::to_plain_labels([
            'types' => $types,
            'novalueoperators' => base::NO_VALUE_OPERATORS,
            'selectclass' => bootstrap::select(),
        ]);
    }

    /**
     * Saved rules of a calendar in the page format, with the subject label (for the autocomplete initial
     * choice) and the reason for invalidity, if any.
     *
     * @param int $calendarid
     * @return array
     */
    public static function get_ui_state(int $calendarid): array {
        $state = [];

        foreach (self::get_groups($calendarid) as $group) {
            $rules = [];
            foreach ($group['rules'] as $rule) {
                $type = registry::get((string) $rule->ruletype);
                $valid = self::is_rule_valid($rule);

                $rules[] = [
                    'ruletype' => (string) $rule->ruletype,
                    'subject' => (string) $rule->subject,
                    'comparison' => (string) $rule->comparison,
                    'value' => (string) $rule->value,
                    'subjectlabel' => $type ? (string) $type->get_subject_label((string) $rule->subject) : '',
                    'invalid' => !$valid,
                    'invalidreason' => $valid ? '' : self::get_invalid_reason($rule),
                ];
            }
            $state[] = ['rules' => $rules];
        }

        return self::to_plain_labels($state);
    }

    /**
     * Turns the labels of the data for the rules page into plain text.
     *
     * The rule types return labels formatted for HTML (format_string() escapes & as &amp;amp;), which is right for
     * describe_rule() but not for the page: the JavaScript writes them as text (textContent, option labels),
     * so the entities would be shown as they are. The labels are decoded here, at the boundary with the
     * JavaScript, and stay safe because the JavaScript never inserts them as HTML. The results of the subject
     * search are not decoded: the autocomplete of Moodle shows them as HTML.
     *
     * @param array $data Configuration or state of the page, nested arrays.
     * @return array The same data with the 'label', 'subjectlabel', 'group' and 'invalidreason' values decoded.
     */
    protected static function to_plain_labels(array $data): array {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::to_plain_labels($value);
            } else if (is_string($value) && in_array($key, ['label', 'subjectlabel', 'group', 'invalidreason'], true)) {
                $data[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $data;
    }

    /**
     * Readable description of a condition, for the summary in manage.php.
     *
     * The value is shown only if the operator needs it; list fields show the option label, not the stored
     * code. Subject labels are already formatted (format_string), while operator and value are escaped with
     * s().
     *
     * @param \stdClass $rule
     * @return string safe HTML
     */
    public static function describe_rule(\stdClass $rule): string {
        $type = registry::get((string) $rule->ruletype);
        if (!$type) {
            return s(get_string('ruleinvalid_type', 'local_eventcalendar'));
        }

        $subject = (string) $rule->subject;
        $subjectlabel = $type->get_subject_label($subject);
        if ($subjectlabel === null) {
            $subjectlabel = s(get_string('accessinvalidrule', 'local_eventcalendar'));
        }

        $comparison = (string) $rule->comparison;
        $valuetype = $type->get_value_type($subject);
        $operators = $type->get_operators()[$valuetype] ?? [];
        $operatorlabel = s($operators[$comparison] ?? $comparison);

        $a = (object) [
            'type' => s($type->get_name()),
            'subject' => $subjectlabel,
            'operator' => $operatorlabel,
        ];

        if (!base::operator_needs_value($comparison)) {
            return get_string('accessruledescription', 'local_eventcalendar', $a);
        }

        $value = (string) $rule->value;
        if ($valuetype === base::VALUE_SELECT) {
            $options = $type->get_value_options($subject);
            $a->value = isset($options[$value]) ? $options[$value] : s(get_string('accessinvalidrule', 'local_eventcalendar'));
        } else {
            $a->value = s($value);
        }

        return get_string('accessruledescriptionvalue', 'local_eventcalendar', $a);
    }

    /**
     * Clears the cached data (after a save, or between calendars in a long process).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$groups = [];
        self::$validity = [];
    }

    /**
     * A block is satisfied when all its conditions are.
     *
     * @param array $group
     * @param \stdClass $user
     * @return bool
     */
    protected static function group_matches(array $group, \stdClass $user): bool {
        foreach ($group['rules'] as $rule) {
            if (!self::is_rule_valid($rule)) {
                return false;
            }

            if (!registry::get((string) $rule->ruletype)->matches($rule, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Signature of a condition (type, subject, operator, value), to recognise an already saved one.
     *
     * @param \stdClass $rule
     * @return string
     */
    protected static function signature(\stdClass $rule): string {
        return json_encode([
            (string) $rule->ruletype,
            (string) $rule->subject,
            (string) $rule->comparison,
            base::operator_needs_value((string) $rule->comparison) ? trim((string) $rule->value) : '',
        ]);
    }
}
