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
 * Base class for membership rule types.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\accessrule;

/**
 * Base class for membership rule types: the user has or does not have something (a role, a cohort,
 * an enrolment, a completion, a badge).
 *
 * Only two operators are stored, "is" and "isnot", without value; the displayed label comes from the
 * ruleop_{type}_is and ruleop_{type}_isnot strings of the component of the type (get_string_component()).
 *
 * Subclasses implement only fetch_members(). Results are cached per subject and user, filled in bulk by
 * prefetch() (notification task, many users) or on first need for a single user.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class membership extends base {
    /** @var array cache [subject][userid] => bool */
    protected $cache = [];

    /**
     * Returns which of the given users "have" the subject.
     *
     * @param string $subject subject already verified as valid
     * @param int[] $userids
     * @return int[] ids of the users who have it
     */
    abstract protected function fetch_members(string $subject, array $userids): array;

    /**
     * Returns the two operators, without value.
     *
     * @return array
     */
    public function get_operators(): array {
        $type = $this->get_type();

        return [
            self::VALUE_NONE => [
                'is' => get_string('ruleop_' . $type . '_is', $this->get_string_component()),
                'isnot' => get_string('ruleop_' . $type . '_isnot', $this->get_string_component()),
            ],
        ];
    }

    /**
     * Preloads membership of many users, for each subject used.
     *
     * @param \stdClass[] $rules
     * @param int[] $userids
     * @return void
     */
    public function prefetch(array $rules, array $userids): void {
        $subjects = array_unique(array_map(function ($rule) {
            return (string) $rule->subject;
        }, $rules));

        foreach ($subjects as $subject) {
            $this->load($subject, $userids);
        }
    }

    /**
     * Checks whether a user satisfies the rule.
     *
     * @param \stdClass $rule
     * @param \stdClass $user
     * @return bool
     */
    public function matches(\stdClass $rule, \stdClass $user): bool {
        $subject = (string) $rule->subject;
        $userid = (int) $user->id;

        if (!isset($this->cache[$subject][$userid])) {
            $this->load($subject, [$userid]);
        }

        $has = $this->cache[$subject][$userid];

        return $rule->comparison === 'isnot' ? !$has : $has;
    }

    /**
     * Loads membership into the cache for users not yet known, in chunks to respect SQL parameter limits.
     *
     * @param string $subject
     * @param int[] $userids
     * @return void
     */
    protected function load(string $subject, array $userids): void {
        $missing = [];
        foreach ($userids as $userid) {
            if (!isset($this->cache[$subject][(int) $userid])) {
                $missing[] = (int) $userid;
            }
        }

        foreach (array_chunk($missing, 1000) as $chunk) {
            $members = array_flip(array_map('intval', $this->fetch_members($subject, $chunk)));

            foreach ($chunk as $userid) {
                $this->cache[$subject][$userid] = isset($members[$userid]);
            }
        }
    }
}
