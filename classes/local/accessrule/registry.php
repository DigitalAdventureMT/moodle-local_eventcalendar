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
 * Registry of the access rule types.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local\accessrule;

use local_eventcalendar\hook\access_rule_types;

/**
 * Registry of the access rule types.
 *
 * The types of the plugin are listed in TYPES (identifier => subclass of base).
 * Other plugins add theirs through the access_rule_types hook, dispatched once
 * per request the first time a type is needed: they come after those of the
 * plugin. The order is the order of the type select in the rules page. A type
 * that is no longer registered returns null, so its saved rules are kept and
 * treated as not satisfied.
 *
 * One instance per type is kept for the request, so the data each class
 * caches in memory is shared by all the checks.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** Registered types: identifier => class. */
    protected const TYPES = [
        'role' => role::class,
        'cohort' => cohort::class,
    ];

    /** @var base[] Instances already created. */
    protected static $instances = [];

    /** @var string[]|null All the registered types (identifier => class), built on first use. */
    protected static $types = null;

    /**
     * Returns all the registered types: those of the plugin followed by those added by the hook.
     *
     * @return string[] identifier => class
     */
    protected static function get_types(): array {
        if (self::$types === null) {
            $hook = new access_rule_types(self::TYPES);
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$types = self::TYPES + $hook->get_types();
        }

        return self::$types;
    }

    /**
     * Returns the instance of a type, or null if the type does not exist.
     *
     * @param string $type
     * @return base|null
     */
    public static function get(string $type): ?base {
        $types = self::get_types();
        if (!isset($types[$type])) {
            return null;
        }

        if (!isset(self::$instances[$type])) {
            $class = $types[$type];
            self::$instances[$type] = new $class();
        }

        return self::$instances[$type];
    }

    /**
     * Returns all the registered types, those of the plugin first.
     *
     * @return base[] [type => instance]
     */
    public static function get_all(): array {
        $all = [];
        foreach (array_keys(self::get_types()) as $type) {
            $all[$type] = self::get($type);
        }
        return $all;
    }

    /**
     * Clears the instances, their cached data and the list of the types, so the hook is dispatched again.
     * Useful for tests and long-running tasks.
     *
     * @return void
     */
    public static function reset(): void {
        self::$instances = [];
        self::$types = null;
    }
}
