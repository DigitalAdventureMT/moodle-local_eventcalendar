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
 * Access rule type used by the tests: it compares the department of the user record with the subject.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\tests\fixtures;

/**
 * Access rule type used by the tests: it compares the department of the user record with the subject.
 *
 * It stands for the rule types added by other plugins that read a column of the user record received by
 * matches(), for example a rule on a profile field: the notification task must give them the complete record.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class department_rule extends \local_eventcalendar\local\accessrule\base {
    /**
     * Returns the rule type handled by this class.
     *
     * @return string
     */
    public function get_type(): string {
        return 'department';
    }

    /**
     * Returns the type name, fixed for the tests.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Department';
    }

    /**
     * Returns the control used to choose the subject.
     *
     * @return string
     */
    public function get_subject_widget(): string {
        return self::WIDGET_SELECT;
    }

    /**
     * Returns the label of a subject: the subject itself.
     *
     * @param string $subject
     * @return string|null
     */
    public function get_subject_label(string $subject): ?string {
        return $subject;
    }

    /**
     * Returns the operators, by value type: only "is", without a value.
     *
     * @return array
     */
    public function get_operators(): array {
        return [self::VALUE_NONE => ['is' => 'is']];
    }

    /**
     * Every rule of this type is valid.
     *
     * @param \stdClass $rule
     * @return bool
     */
    public function is_valid(\stdClass $rule): bool {
        return true;
    }

    /**
     * Nothing to load: the value is in the user record.
     *
     * @param array $rules
     * @param array $userids
     */
    public function prefetch(array $rules, array $userids): void {
    }

    /**
     * Whether the department of the user is the subject of the rule. A record without the column does not match.
     *
     * @param \stdClass $rule
     * @param \stdClass $user
     * @return bool
     */
    public function matches(\stdClass $rule, \stdClass $user): bool {
        return isset($user->department) && $user->department === (string) $rule->subject;
    }
}
