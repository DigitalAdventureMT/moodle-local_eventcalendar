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
 * Hook dispatched when the publishing options of a calendar are built.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\hook;

use local_eventcalendar\persistent\calendar;

/**
 * Hook dispatched when the publishing options of a calendar are built.
 *
 * It is dispatched by local\publish_snippets::get_snippets() after the options of the plugin, which are
 * available with get_snippets(). The callbacks add their own options with add_snippet(); they are shown
 * after those of the plugin, in the order they were added. They do not return a value.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add publishing options to the publish dialog of an event calendar.')]
#[\core\attribute\tags('calendar', 'publish')]
final class publish_snippets {
    /** @var array[] Options of the plugin followed by those added by the callbacks. */
    private array $snippets;

    /**
     * Creates the hook.
     *
     * @param calendar $calendar The calendar to publish.
     * @param array[] $snippets Options already built by the plugin.
     */
    public function __construct(
        /** @var calendar The calendar to publish. */
        private readonly calendar $calendar,
        array $snippets,
    ) {
        $this->snippets = $snippets;
    }

    /**
     * Returns the calendar to publish.
     *
     * @return calendar
     */
    public function get_calendar(): calendar {
        return $this->calendar;
    }

    /**
     * Returns the options, those of the plugin first.
     *
     * @return array[]
     */
    public function get_snippets(): array {
        return $this->snippets;
    }

    /**
     * Adds a publishing option.
     *
     * The option has the same structure as those of the plugin:
     *  - key: unique identifier of the option (required);
     *  - title, description: plain texts shown in the dialog, escaped by the template: do not escape them (required);
     *  - preview: trusted HTML shown as preview, printed as it is: every value not written by the callback must be
     *    escaped (required, it can be empty);
     *  - code: plain text to copy, escaped by the template (optional);
     *  - image: data URI of an image to copy, filename: name used to download it (optional);
     *  - link: ['url' => ..., 'label' => ...] shown under the option; plain texts, escaped by the template (optional).
     *
     * @param array $snippet The option.
     * @throws \coding_exception If a required key is missing or the key is already used.
     */
    public function add_snippet(array $snippet): void {
        foreach (['key', 'title', 'description', 'preview'] as $required) {
            if (!array_key_exists($required, $snippet)) {
                throw new \coding_exception('Publishing option without ' . $required);
            }
        }

        if (in_array($snippet['key'], array_column($this->snippets, 'key'), true)) {
            throw new \coding_exception('Publishing option already present: ' . $snippet['key']);
        }

        $this->snippets[] = $snippet + ['code' => null, 'image' => null, 'filename' => null, 'link' => null];
    }
}
