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
 * Access rule based on a role assigned in the system context.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\accessrule;

/**
 * Access rule based on a role assigned in the system context.
 *
 * Only roles assignable at system level are offered. The authenticated user,
 * guest and not logged in roles are excluded: Moodle applies them implicitly,
 * without a real assignment, so a rule on them could not be checked.
 *
 * The subject is the role id. The rule is invalid when the role is deleted or
 * no longer assignable at system level.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role extends membership {
    /** @var array|null Allowed roles [id => record], computed once. */
    protected $allowedroles = null;

    /**
     * Returns the rule type handled by this class.
     *
     * @return string
     */
    public function get_type(): string {
        return 'role';
    }

    /**
     * The subject is the id of the role.
     *
     * @return bool
     */
    protected function subject_is_id(): bool {
        return true;
    }

    /**
     * Returns the widget used to choose the subject.
     *
     * @return string
     */
    public function get_subject_widget(): string {
        return self::WIDGET_SELECT;
    }

    /**
     * Returns the options of the subject select.
     *
     * @return array
     */
    public function get_subject_options(): array {
        $options = [];
        foreach ($this->get_allowed_roles() as $role) {
            $options[] = [
                'value' => (string) $role->id,
                'label' => role_get_name($role, \context_system::instance()),
                'group' => '',
                'valuetype' => self::VALUE_NONE,
                'options' => [],
            ];
        }
        return $options;
    }

    /**
     * Returns the readable label of a rule subject.
     *
     * @param string $subject
     * @return string|null
     */
    public function get_subject_label(string $subject): ?string {
        global $DB;

        $role = $DB->get_record('role', ['id' => (int) $subject]);
        return $role ? role_get_name($role, \context_system::instance()) : null;
    }

    /**
     * Checks that the rule is still valid.
     *
     * @param \stdClass $rule
     * @return bool
     */
    public function is_valid(\stdClass $rule): bool {
        return isset($this->get_allowed_roles()[(int) $rule->subject]);
    }

    /**
     * Returns the users, among the given ones, holding the role in the system context.
     *
     * @param string $subject
     * @param int[] $userids
     * @return int[]
     */
    protected function fetch_members(string $subject, array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['roleid'] = (int) $subject;
        $params['contextid'] = \context_system::instance()->id;

        return $DB->get_fieldset_select(
            'role_assignments',
            'DISTINCT userid',
            "roleid = :roleid AND contextid = :contextid AND userid $insql",
            $params
        );
    }

    /**
     * Returns the roles assignable at system level, except the implicit ones.
     *
     * @return array [id => record]
     */
    protected function get_allowed_roles(): array {
        global $CFG, $DB;

        if ($this->allowedroles !== null) {
            return $this->allowedroles;
        }

        $excluded = array_filter([
            (int) ($CFG->defaultuserroleid ?? 0),
            (int) ($CFG->guestroleid ?? 0),
            (int) ($CFG->notloggedinroleid ?? 0),
        ]);

        $roleids = array_diff(array_map('intval', get_roles_for_contextlevels(CONTEXT_SYSTEM)), $excluded);

        $this->allowedroles = empty($roleids) ? [] : $DB->get_records_list('role', 'id', array_values($roleids), 'sortorder');

        return $this->allowedroles;
    }
}
