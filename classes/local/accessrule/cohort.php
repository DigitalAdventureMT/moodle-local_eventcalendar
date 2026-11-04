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
 * Rule condition: the user is (or is not) a member of a system cohort.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\accessrule;

/**
 * Rule condition: the user is (or is not) a member of a system cohort.
 *
 * Only system cohorts are offered, found by autocomplete search on name and idnumber. The subject is
 * the cohort id; the condition is not valid when the cohort is deleted or moved to a category.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort extends membership {
    /**
     * Returns the condition type handled by this class.
     *
     * @return string
     */
    public function get_type(): string {
        return 'cohort';
    }

    /**
     * The subject is the id of the cohort.
     *
     * @return bool
     */
    protected function subject_is_id(): bool {
        return true;
    }

    /**
     * Returns the control type used to choose the subject.
     *
     * @return string
     */
    public function get_subject_widget(): string {
        return self::WIDGET_AUTOCOMPLETE;
    }

    /**
     * Searches the selectable subjects matching the given text.
     *
     * @param string $query
     * @return array
     */
    public function search_subjects(string $query): array {
        global $DB;

        $params = ['contextid' => \context_system::instance()->id];
        $where = 'contextid = :contextid';

        if (trim($query) !== '') {
            $where .= ' AND (' . $DB->sql_like('name', ':q1', false, false)
                . ' OR ' . $DB->sql_like('idnumber', ':q2', false, false) . ')';
            $params['q1'] = '%' . $DB->sql_like_escape(trim($query)) . '%';
            $params['q2'] = $params['q1'];
        }

        $cohorts = $DB->get_records_select('cohort', $where, $params, 'name', 'id, name, idnumber', 0, self::SEARCH_LIMIT);

        $results = [];
        foreach ($cohorts as $cohort) {
            $results[] = ['value' => (string) $cohort->id, 'label' => $this->label($cohort)];
        }
        return $results;
    }

    /**
     * Returns the readable label of the subject of a condition.
     *
     * @param string $subject
     * @return string|null
     */
    public function get_subject_label(string $subject): ?string {
        global $DB;

        $cohort = $DB->get_record('cohort', ['id' => (int) $subject], 'id, name, idnumber');
        return $cohort ? $this->label($cohort) : null;
    }

    /**
     * Checks that the condition is still valid.
     *
     * @param \stdClass $rule
     * @return bool
     */
    public function is_valid(\stdClass $rule): bool {
        global $DB;

        return $DB->record_exists('cohort', [
            'id' => (int) $rule->subject,
            'contextid' => \context_system::instance()->id,
        ]);
    }

    /**
     * Returns, among the given users, those who belong to the subject.
     *
     * @param string $subject
     * @param int[] $userids
     * @return int[]
     */
    protected function fetch_members(string $subject, array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cohortid'] = (int) $subject;

        return $DB->get_fieldset_select('cohort_members', 'userid', "cohortid = :cohortid AND userid $insql", $params);
    }

    /**
     * Returns the cohort name and, if present, its idnumber.
     *
     * @param \stdClass $cohort
     * @return string
     */
    protected function label(\stdClass $cohort): string {
        $name = format_string($cohort->name, true, ['context' => \context_system::instance()]);
        return trim((string) $cohort->idnumber) !== '' ? $name . ' (' . s($cohort->idnumber) . ')' : $name;
    }
}
