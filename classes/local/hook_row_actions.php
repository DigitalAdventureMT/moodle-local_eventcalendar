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
 * Row actions contributed by other plugins to a list page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Row actions contributed by other plugins to a list page.
 *
 * Used by the list hooks. An action is a fragment of HTML already built and escaped by the callback (usually
 * a link). The page shows the actions of each row after its own ones, keeping the delete action last.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait hook_row_actions {
    /** @var string[][] Actions indexed by item id. */
    private array $rowactions = [];

    /**
     * Adds an action to the row of an item.
     *
     * @param int $itemid Id of the item shown in the row.
     * @param string $html HTML of the action, already escaped.
     */
    public function add_row_action(int $itemid, string $html): void {
        $this->rowactions[$itemid][] = $html;
    }

    /**
     * Returns the actions added to the row of an item.
     *
     * @param int $itemid Id of the item shown in the row.
     * @return string[] HTML of the actions, in the order they were added.
     */
    public function get_row_actions(int $itemid): array {
        return $this->rowactions[$itemid] ?? [];
    }
}
