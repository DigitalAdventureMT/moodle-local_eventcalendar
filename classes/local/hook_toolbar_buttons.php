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
 * Toolbar buttons contributed by other plugins to a list page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Toolbar buttons contributed by other plugins to a list page.
 *
 * Used by the list hooks. A button is a fragment of HTML already built and escaped by the callback (usually
 * a link with the classes of a button). The page shows them after its own buttons.
 *
 * Trusted HTML: the HTML added by the callbacks is printed as it is, without any filter, because it comes from
 * plugin code. The callbacks must escape every value they did not write themselves (names, texts entered by
 * users, data read from the database or from a request): s() or format_string() for text, format_text() for
 * rich text, html_writer for tags and attributes. An unescaped value would let whoever controls it inject
 * markup or scripts into the page of a manager.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait hook_toolbar_buttons {
    /** @var string[] Buttons in the order they were added. */
    private array $toolbarbuttons = [];

    /**
     * Adds a button to the toolbar above the list.
     *
     * @param string $html HTML of the button, printed as it is: every value not written by the callback must be escaped.
     */
    public function add_toolbar_button(string $html): void {
        $this->toolbarbuttons[] = $html;
    }

    /**
     * Returns the buttons added to the toolbar.
     *
     * @return string[] HTML of the buttons, in the order they were added.
     */
    public function get_toolbar_buttons(): array {
        return $this->toolbarbuttons;
    }
}
