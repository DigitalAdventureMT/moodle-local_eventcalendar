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
 * Web service that searches the subjects of an access rule, for the autocomplete lists of the rules page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\external;

use local_eventcalendar\local\accessrule\base;
use local_eventcalendar\local\accessrule\registry;

/**
 * Web service that searches the subjects of an access rule, for the autocomplete lists of the rules page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_rule_subjects extends \core_external\external_api {
    /**
     * Describes the web service parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'ruletype' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Condition type'),
            'query' => new \core_external\external_value(PARAM_TEXT, 'Search text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Runs the search, for calendar managers and autocomplete types only.
     *
     * @param string $ruletype
     * @param string $query
     * @return array
     */
    public static function execute(string $ruletype, string $query = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'ruletype' => $ruletype,
            'query' => $query,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/eventcalendar:managecalendars', $context);

        $type = registry::get($params['ruletype']);
        if (!$type || $type->get_subject_widget() !== base::WIDGET_AUTOCOMPLETE) {
            throw new \invalid_parameter_exception('ruletype');
        }

        return $type->search_subjects($params['query']);
    }

    /**
     * Describes the web service return value.
     *
     * @return \core_external\external_multiple_structure
     */
    public static function execute_returns(): \core_external\external_multiple_structure {
        return new \core_external\external_multiple_structure(
            new \core_external\external_single_structure([
                'value' => new \core_external\external_value(PARAM_RAW, 'Subject value to store'),
                'label' => new \core_external\external_value(PARAM_RAW, 'Formatted label'),
            ])
        );
    }
}
