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
 * Hook dispatched when the access rule types are registered.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\local\accessrule\base;

/**
 * Hook dispatched when the access rule types are registered.
 *
 * It is dispatched once per request by local\accessrule\registry, the first time a type is needed. The
 * callbacks add their types with add_type(): they are listed after those of the plugin, in the order they
 * were added. A type of the plugin cannot be replaced. The rules of a type that is no longer registered
 * (for example because the plugin that added it was uninstalled) are kept and are not satisfied. They do
 * not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add access rule types to the event calendars.')]
#[\core\attribute\tags('accessrule')]
final class access_rule_types {
    /** @var string[] Types added by the callbacks: identifier => class. */
    private array $types = [];

    /**
     * Creates the hook.
     *
     * @param string[] $existing Types of the plugin: identifier => class.
     */
    public function __construct(
        /** @var string[] Types of the plugin: identifier => class. */
        private readonly array $existing,
    ) {
    }

    /**
     * Adds an access rule type.
     *
     * @param string $type Identifier of the type, saved with the rules (letters, digits, underscore).
     * @param string $class Class of the type, a subclass of local\accessrule\base.
     * @throws \coding_exception If the identifier is not valid or already used, or the class is not a type.
     */
    public function add_type(string $type, string $class): void {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
            throw new \coding_exception('Invalid access rule type identifier: ' . $type);
        }
        if (isset($this->existing[$type]) || isset($this->types[$type])) {
            throw new \coding_exception('Access rule type already registered: ' . $type);
        }
        if (!class_exists($class) || !is_subclass_of($class, base::class)) {
            throw new \coding_exception('Access rule type class must extend ' . base::class . ': ' . $class);
        }

        $this->types[$type] = $class;
    }

    /**
     * Returns the types added by the callbacks.
     *
     * @return string[] identifier => class
     */
    public function get_types(): array {
        return $this->types;
    }
}
