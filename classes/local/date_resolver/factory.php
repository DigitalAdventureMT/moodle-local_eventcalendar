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
 * Factory of the date resolution strategies.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\date_resolver;

/**
 * Factory of the date resolution strategies.
 *
 * Hides from callers which concrete class is instantiated for a given date
 * rule type.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factory {
    /**
     * Creates the right date resolution strategy.
     *
     * @param string $type One of 'fixed', 'computed', 'lookup_table'.
     * @param array $config Strategy parameters:
     *                       - fixed: ['month' => int, 'day' => int]
     *                       - computed: ['algorithm' => string] (default 'easter')
     *                       - lookup_table: ['templatekey' => string]
     * @return date_resolver_interface
     * @throws \coding_exception If required parameters are missing for the requested type.
     * @throws \moodle_exception If the date rule type is unknown.
     */
    public static function create(string $type, array $config = []): date_resolver_interface {
        switch ($type) {
            case 'fixed':
                if (!isset($config['month']) || !isset($config['day'])) {
                    throw new \coding_exception(
                        "The 'fixed' strategy requires the 'month' and 'day' parameters in \$config."
                    );
                }
                return new fixed((int) $config['month'], (int) $config['day']);

            case 'computed':
                $algorithm = $config['algorithm'] ?? 'easter';
                return new computed($algorithm);

            case 'lookup_table':
                if (!isset($config['templatekey'])) {
                    throw new \coding_exception(
                        "The 'lookup_table' strategy requires the 'templatekey' parameter in \$config."
                    );
                }
                return new lookup_table($config['templatekey']);

            default:
                throw new \moodle_exception('errorunknowndateruletype', 'local_eventcalendar', '', $type);
        }
    }
}
