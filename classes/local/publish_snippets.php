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
 * Ready-made HTML snippets to publish a calendar in pages, blocks and labels.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\publish_snippets as publish_snippets_hook;
use local_eventcalendar\persistent\calendar;

/**
 * Ready-made code to publish a calendar: HTML snippets for pages, blocks and labels (link, card, button).
 *
 * Other plugins add their options through the publish_snippets hook; the dialog shows every option the same
 * way, with HTML or text to copy and, if given, an image to copy or download and a link below the option.
 *
 * The HTML snippets are pasted by managers into the Moodle text editors, so they:
 * - use absolute URLs, valid on any page of the site;
 * - use only Bootstrap classes with the same name in Bootstrap 4 and 5 (Moodle 4.x and 5.x); the only
 *   inline style is the width of cards and banner, made of properties kept by the HTML cleaning of Moodle;
 * - never put block elements inside a link, which the HTML cleaning would break: the clickable card
 *   uses the Bootstrap stretched-link class instead.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_snippets {
    /** Maximum width of the card with banner, in pixels. */
    public const CARD_MAX_WIDTH = 550;

    /** Minimum width of the card with banner, in pixels, so that the name stays readable in narrow blocks. */
    public const CARD_MIN_WIDTH = 200;

    /**
     * Publishing options of a calendar, in display order.
     *
     * Every option has a preview (trusted HTML generated here) and a text to copy (HTML code). Other plugins
     * add their options, after these, through the publish_snippets hook.
     *
     * @param calendar $calendar
     * @return array list of options with key, title, description, preview, code, image, filename, link
     */
    public static function get_snippets(calendar $calendar): array {
        $url = (new \moodle_url('/local/eventcalendar/view.php', ['id' => $calendar->get('id')]))->out(false);
        // Raw name (plain text by definition), escaped once by the builders below.
        $name = (string) $calendar->get('name');
        $banner = $calendar->get_banner_url();
        $bannerurl = $banner ? $banner->out(false) : null;

        $options = [
            self::html_option('link', self::link($url, $name)),
            self::html_option('card', self::card($url, $name, $bannerurl)),
            self::html_option('button', self::button($url, $name)),
        ];

        $hook = new publish_snippets_hook($calendar, $options);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return $hook->get_snippets();
    }

    /**
     * Builds an option with its title and description strings.
     *
     * @param string $key option key, also used for the strings publish{key} and publish{key}desc
     * @param array $data preview, code, image, filename, link
     * @return array
     */
    protected static function option(string $key, array $data): array {
        return $data + [
            'key' => $key,
            'title' => get_string('publish' . $key, 'local_eventcalendar'),
            'description' => get_string('publish' . $key . 'desc', 'local_eventcalendar'),
            'code' => null,
            'image' => null,
            'filename' => null,
            'link' => null,
        ];
    }

    /**
     * Builds an option whose code is HTML, shown as it is in the preview.
     *
     * @param string $key option key
     * @param string $html snippet code
     * @return array
     */
    protected static function html_option(string $key, string $html): array {
        return self::option($key, ['preview' => $html, 'code' => $html]);
    }

    /**
     * Inline style that limits a card or banner to a third of the available width, between
     * CARD_MIN_WIDTH and CARD_MAX_WIDTH pixels.
     *
     * Public so that the options added through the publish_snippets hook can use the same width.
     *
     * @return string
     */
    public static function width_style(): string {
        return 'width: 33%; min-width: ' . self::CARD_MIN_WIDTH . 'px; max-width: ' . self::CARD_MAX_WIDTH . 'px;';
    }

    /**
     * Plain text link with the calendar name.
     *
     * @param string $url calendar page URL
     * @param string $name calendar name, not escaped
     * @return string
     */
    protected static function link(string $url, string $name): string {
        return '<a href="' . s($url) . '">' . s($name) . '</a>';
    }

    /**
     * Card with the banner above and the name below, clickable as a whole.
     *
     * The banner is decorative (empty alt): the accessible name of the link is the calendar name.
     * Without a banner the card shows only the name. The card, and so the banner, takes at most a third
     * of the available width and never more than CARD_MAX_WIDTH pixels; CARD_MIN_WIDTH keeps it readable
     * in narrow containers such as blocks.
     *
     * @param string $url calendar page URL
     * @param string $name calendar name, not escaped
     * @param string|null $bannerurl banner image URL, if any
     * @return string
     */
    protected static function card(string $url, string $name, ?string $bannerurl): string {
        $lines = ['<div class="card" style="' . self::width_style() . '">'];
        if ($bannerurl !== null) {
            $lines[] = '  <img src="' . s($bannerurl) . '" class="card-img-top" alt="">';
        }
        $lines[] = '  <div class="card-body text-center">';
        $lines[] = '    <a href="' . s($url) . '" class="stretched-link h5 card-title mb-0">' . s($name) . '</a>';
        $lines[] = '  </div>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    /**
     * Button with the calendar name, in the primary colour of the theme.
     *
     * @param string $url calendar page URL
     * @param string $name calendar name, not escaped
     * @return string
     */
    protected static function button(string $url, string $name): string {
        return '<a href="' . s($url) . '" class="btn btn-primary">' . s($name) . '</a>';
    }
}
