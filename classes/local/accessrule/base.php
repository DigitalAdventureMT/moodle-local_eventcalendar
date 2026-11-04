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
 * Base class of an access rule type for a calendar.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\accessrule;

/**
 * Base class of an access rule type for a calendar.
 *
 * Every saved rule (table local_eventcalendar_rule) has the same shape:
 * type, subject, operator (field "comparison") and value. Each subclass
 * defines what these mean, how they are shown in the rules page and how a
 * user is checked.
 *
 * The rules page (amd/src/access_rules.js) knows no types: it gets from
 * get_ui_config() how to choose the subject, the value type of each subject
 * and the operators allowed for each value type.
 *
 * A rule that refers to an element that no longer exists (is_valid() false)
 * stays saved but never matches, whatever the operator, so that deleting an
 * element never opens a restricted calendar. The caller (access_rules)
 * checks validity, not matches().
 *
 * Types can also come from other plugins (access_rule_types hook): a subclass
 * defined elsewhere returns its own component from get_string_component(), so
 * that its name and operator labels are read from its language file.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** No value to enter: the operator is enough. */
    public const VALUE_NONE = 'none';

    /** Free text value. */
    public const VALUE_TEXT = 'text';

    /** Value chosen from a list of options of the subject. */
    public const VALUE_SELECT = 'select';

    /** Date value, saved as YYYY-MM-DD. */
    public const VALUE_DATE = 'date';

    /** Subject chosen from a select with the full list. */
    public const WIDGET_SELECT = 'select';

    /** Subject chosen with a search (autocomplete, via web service). */
    public const WIDGET_AUTOCOMPLETE = 'autocomplete';

    /** Maximum number of search results. */
    public const SEARCH_LIMIT = 50;

    /**
     * Operators that need no value: the JavaScript hides the value field and
     * saving ignores it.
     */
    public const NO_VALUE_OPERATORS = ['is', 'isnot', 'empty', 'notempty', 'checked', 'notchecked'];

    /**
     * Returns the type identifier, as saved in the ruletype field.
     *
     * @return string
     */
    abstract public function get_type(): string;

    /**
     * Returns the component whose language file holds the strings of the type (ruletype_{type} and, for
     * membership types, ruleop_{type}_is and ruleop_{type}_isnot).
     *
     * @return string
     */
    protected function get_string_component(): string {
        return 'local_eventcalendar';
    }

    /**
     * Returns the type name shown in the first select.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('ruletype_' . $this->get_type(), $this->get_string_component());
    }

    /**
     * Tells whether the type can be offered for new rules. False when the
     * Moodle feature it relies on is disabled (for example badges). Saved
     * rules of an unavailable type stay visible but are not valid.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Returns how the subject is chosen: WIDGET_SELECT or WIDGET_AUTOCOMPLETE.
     *
     * @return string
     */
    abstract public function get_subject_widget(): string;

    /**
     * Returns the full list of subjects, for WIDGET_SELECT only. Each item has
     * value, label, group (group label or empty), valuetype and, for
     * VALUE_SELECT, options (list of value/label).
     *
     * @return array
     */
    public function get_subject_options(): array {
        return [];
    }

    /**
     * Searches subjects, for WIDGET_AUTOCOMPLETE only.
     *
     * @param string $query Search text (may be empty: first results).
     * @return array List of ['value' => string, 'label' => string].
     */
    public function search_subjects(string $query): array {
        return [];
    }

    /**
     * Returns the readable label of a saved subject.
     *
     * @param string $subject
     * @return string|null Null if the subject no longer exists.
     */
    abstract public function get_subject_label(string $subject): ?string;

    /**
     * Returns the value type of a subject (VALUE_* constants).
     *
     * @param string $subject
     * @return string
     */
    public function get_value_type(string $subject): string {
        return self::VALUE_NONE;
    }

    /**
     * Returns the allowed operators by value type: [valuetype => [operator => label]].
     *
     * @return array
     */
    abstract public function get_operators(): array;

    /**
     * Returns the value options of a VALUE_SELECT subject: [value => label].
     *
     * @param string $subject
     * @return array
     */
    public function get_value_options(string $subject): array {
        return [];
    }

    /**
     * Tells whether the saved rule is still valid, that is, whether the
     * element it refers to still exists.
     *
     * @param \stdClass $rule Record of local_eventcalendar_rule.
     * @return bool
     */
    abstract public function is_valid(\stdClass $rule): bool;

    /**
     * Returns why the rule is not valid, as a plugin string key. Call only
     * when is_valid() is false.
     *
     * @param \stdClass $rule
     * @return string
     */
    public function get_invalid_reason(\stdClass $rule): string {
        return 'ruleinvalid_missing';
    }

    /**
     * Returns the message that explains why the rule is not valid. Call only when is_valid() is false.
     *
     * The default reads the key returned by get_invalid_reason() from the language file of this plugin. A type
     * with reasons of its own overrides this method and reads them from its own component.
     *
     * @param \stdClass $rule
     * @return string
     */
    public function get_invalid_message(\stdClass $rule): string {
        return get_string($this->get_invalid_reason($rule), 'local_eventcalendar');
    }

    /**
     * Preloads in bulk the data needed to check many users, to avoid one
     * query per user (notification task).
     *
     * @param \stdClass[] $rules Valid rules of this type.
     * @param int[] $userids
     * @return void
     */
    abstract public function prefetch(array $rules, array $userids): void;

    /**
     * Tells whether the user satisfies the rule, which the caller has already
     * checked as valid.
     *
     * @param \stdClass $rule
     * @param \stdClass $user User record (at least id; the full record for standard profile fields).
     * @return bool
     */
    abstract public function matches(\stdClass $rule, \stdClass $user): bool;

    /**
     * Tells whether the subject of the conditions of this type is the id of a record (role, cohort, course...).
     *
     * Such subjects are accepted from the page only as positive integers (see is_acceptable()). Types whose
     * subject is a name, such as a profile field, keep the default.
     *
     * @return bool
     */
    protected function subject_is_id(): bool {
        return false;
    }

    /**
     * Tells whether a rule coming from the page is acceptable: valid subject,
     * operator allowed for its value type, and a value when the operator
     * needs one. VALUE_SELECT values are checked by is_valid().
     *
     * @param \stdClass $rule Fields ruletype, subject, comparison, value.
     * @return bool
     */
    public function is_acceptable(\stdClass $rule): bool {
        // A subject that is the id of a record must be written as a positive integer and nothing else: the
        // types cast it to int when they read it, so a text like "1 OR 1=1" would otherwise be accepted as id 1
        // and saved as it is.
        if ($this->subject_is_id() && !preg_match('/^[1-9][0-9]*$/', (string) $rule->subject)) {
            return false;
        }

        if (!$this->is_valid($rule)) {
            return false;
        }

        $valuetype = $this->get_value_type($rule->subject);
        $operators = $this->get_operators()[$valuetype] ?? [];

        if (!array_key_exists($rule->comparison, $operators)) {
            return false;
        }

        if (self::operator_needs_value($rule->comparison) && trim((string) $rule->value) === '') {
            return false;
        }

        if (
            $valuetype === self::VALUE_DATE && self::operator_needs_value($rule->comparison)
                && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $rule->value)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns the type configuration for the rules page (JavaScript).
     *
     * @return array
     */
    public function get_ui_config(): array {
        $operators = [];
        foreach ($this->get_operators() as $valuetype => $list) {
            $operators[$valuetype] = [];
            foreach ($list as $value => $label) {
                $operators[$valuetype][] = ['value' => $value, 'label' => $label];
            }
        }

        return [
            'type' => $this->get_type(),
            'name' => $this->get_name(),
            'available' => $this->is_available(),
            'widget' => $this->get_subject_widget(),
            'defaultvaluetype' => self::VALUE_NONE,
            'subjects' => $this->get_subject_widget() === self::WIDGET_SELECT ? $this->get_subject_options() : [],
            'operators' => $operators,
        ];
    }

    /**
     * Tells whether the operator needs a value.
     *
     * @param string $operator
     * @return bool
     */
    public static function operator_needs_value(string $operator): bool {
        return !in_array($operator, self::NO_VALUE_OPERATORS, true);
    }

    /**
     * Converts a [value => label] list to the format used by the JavaScript.
     *
     * @param array $options
     * @return array
     */
    protected static function to_option_list(array $options): array {
        $list = [];
        foreach ($options as $value => $label) {
            $list[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return $list;
    }

    /**
     * Searches courses by full or short name, shared by the types that choose
     * a course. The site front page is excluded.
     *
     * @param string $query
     * @return array
     */
    protected static function search_courses(string $query): array {
        global $DB;

        $params = ['siteid' => SITEID];
        $where = 'id <> :siteid';

        if (trim($query) !== '') {
            $where .= ' AND (' . $DB->sql_like('fullname', ':q1', false, false)
                . ' OR ' . $DB->sql_like('shortname', ':q2', false, false) . ')';
            $params['q1'] = '%' . $DB->sql_like_escape(trim($query)) . '%';
            $params['q2'] = $params['q1'];
        }

        $courses = $DB->get_records_select('course', $where, $params, 'fullname', 'id, fullname, shortname', 0, self::SEARCH_LIMIT);

        $results = [];
        foreach ($courses as $course) {
            $results[] = ['value' => (string) $course->id, 'label' => self::course_label($course)];
        }
        return $results;
    }

    /**
     * Returns a course label: full name and, in brackets, short name.
     *
     * @param \stdClass $course record with fullname and shortname
     * @return string
     */
    protected static function course_label(\stdClass $course): string {
        $context = \context_system::instance();
        return format_string($course->fullname, true, ['context' => $context])
            . ' (' . format_string($course->shortname, true, ['context' => $context]) . ')';
    }

    /**
     * Returns a course record from the subject, or null if it does not exist.
     *
     * @param string $subject
     * @return \stdClass|null
     */
    protected static function get_course(string $subject): ?\stdClass {
        global $DB;

        $courseid = (int) $subject;
        if ($courseid <= 0 || $courseid == SITEID) {
            return null;
        }

        return $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname, enablecompletion') ?: null;
    }
}
