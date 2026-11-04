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
 * Hook dispatched when the list of icon sets is shown.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\local\hook_row_actions;
use local_eventcalendar\local\hook_toolbar_buttons;
use local_eventcalendar\persistent\iconset;

/**
 * Hook dispatched when the list of icon sets is shown.
 *
 * The page dispatches it before printing the list, also when the list is empty, so the callbacks can
 * contribute row actions and toolbar buttons. The items shown are available with
 * get_iconsets(). The HTML added by the callbacks must be already escaped. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add actions to the list of icon sets.')]
#[\core\attribute\tags('iconset', 'list')]
final class iconset_list {
    use hook_row_actions;
    use hook_toolbar_buttons;

    /**
     * Creates the hook.
     *
     * @param iconset[] $iconsets The icon sets shown in the list.
     */
    public function __construct(
        /** @var iconset[] The icon sets shown in the list. */
        private readonly array $iconsets,
    ) {
    }

    /**
     * Returns the icon sets shown in the list.
     *
     * @return iconset[]
     */
    public function get_iconsets(): array {
        return $this->iconsets;
    }
}
