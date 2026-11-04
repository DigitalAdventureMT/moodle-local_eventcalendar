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
 * Web service saving all the access rules of a calendar ("Save rules" button of accessrules.php).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\external;

use local_eventcalendar\local\access_rules;
use local_eventcalendar\persistent\calendar;

/**
 * Web service saving all the access rules of a calendar ("Save rules" button of accessrules.php).
 *
 * It receives the whole structure (blocks and conditions) and replaces the saved one in a single transaction
 * (access_rules::save()). It returns the saved state in the page format, so the JavaScript can redraw it
 * (invalid conditions highlighted, empty blocks discarded).
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_access_rules extends \core_external\external_api {
    /**
     * Web service parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'calendarid' => new \core_external\external_value(PARAM_INT, 'Calendar id'),
            'groups' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'rules' => new \core_external\external_multiple_structure(
                        new \core_external\external_single_structure([
                            'ruletype' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Condition type'),
                            'subject' => new \core_external\external_value(PARAM_RAW, 'Subject'),
                            'comparison' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Comparison operator'),
                            'value' => new \core_external\external_value(PARAM_RAW, 'Value', VALUE_DEFAULT, ''),
                        ]),
                        'Group conditions (AND)',
                        VALUE_DEFAULT,
                        []
                    ),
                ]),
                'Condition groups (OR)',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Saves the rules, only for users who manage calendars.
     *
     * @param int $calendarid
     * @param array $groups
     * @return array
     */
    public static function execute(int $calendarid, array $groups = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'calendarid' => $calendarid,
            'groups' => $groups,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/eventcalendar:managecalendars', $context);

        if (!calendar::record_exists($params['calendarid'])) {
            throw new \moodle_exception('errorinvalidcalendarid', 'local_eventcalendar');
        }

        access_rules::save($params['calendarid'], $params['groups']);

        return ['groups' => access_rules::get_ui_state($params['calendarid'])];
    }

    /**
     * Structure of the values returned by the web service.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'groups' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'rules' => new \core_external\external_multiple_structure(
                        new \core_external\external_single_structure([
                            'ruletype' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Condition type'),
                            'subject' => new \core_external\external_value(PARAM_RAW, 'Subject'),
                            'comparison' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Comparison operator'),
                            'value' => new \core_external\external_value(PARAM_RAW, 'Value'),
                            'subjectlabel' => new \core_external\external_value(PARAM_RAW, 'Subject label'),
                            'invalid' => new \core_external\external_value(PARAM_BOOL, 'Invalid condition'),
                            'invalidreason' => new \core_external\external_value(PARAM_RAW, 'Reason why the condition is invalid'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
