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
 * Data source for core/form-autocomplete, used to search access rule subjects.
 *
 * The module exposes transport() to run the search and processResults() to convert
 * the results into value/label pairs. The rule type is read from the data-ruletype
 * attribute of the select element.
 *
 * @module     local_eventcalendar/access_rules_search
 * @copyright  2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Runs the search through the local_eventcalendar_search_rule_subjects web service.
 *
 * @param {string} selector Selector of the original select element
 * @param {string} query Text typed in the autocomplete
 * @param {Function} success Callback receiving the results
 * @param {Function} failure Callback invoked on error
 */
export const transport = (selector, query, success, failure) => {
    const select = document.querySelector(selector);

    Ajax.call([{
        methodname: 'local_eventcalendar_search_rule_subjects',
        args: {ruletype: select.dataset.ruletype, query: query},
    }])[0].then(success).catch(failure);
};

/**
 * Converts the results into the format expected by form-autocomplete.
 *
 * @param {string} selector Selector of the original select element
 * @param {Array} results Web service results
 * @returns {Array} List of {value, label}
 */
export const processResults = (selector, results) => {
    return results.map((result) => ({value: result.value, label: result.label}));
};
