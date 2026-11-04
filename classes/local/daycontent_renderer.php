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
 * Builds the content of a day from the fixed day content template.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

use local_eventcalendar\hook\day_cta;
use local_eventcalendar\persistent\calendar;
use local_eventcalendar\persistent\day;

/**
 * Builds the content of a day using the fixed template templates/daycontent/default.html.
 *
 * The placeholders {{header}}, {{content}}, {{ctabutton}} and {{footer}} are replaced with the values of the
 * given day and calendar. The template is read from file on each rendering.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daycontent_renderer {
    /**
     * Builds the complete HTML of a day content.
     *
     * @param day $day
     * @param calendar $calendar calendar the day belongs to
     * @param array $overrides raw values of the day form submitted to the live preview and not yet saved, passed
     *     to the day_cta hook; empty elsewhere
     * @return string HTML ready for display (already passed through format_text())
     */
    public static function render(day $day, calendar $calendar, array $overrides = []): string {
        global $CFG;

        $templatepath = $CFG->dirroot . '/local/eventcalendar/templates/daycontent/default.html';

        if (!is_file($templatepath)) {
            // Minimal fallback, safer than a fatal error on a preview page.
            return self::render_content_only($day);
        }

        $template = file_get_contents($templatepath);

        $replacements = [
            '{{header}}' => self::build_image_html($calendar->get_header_url()),
            '{{footer}}' => self::build_image_html($calendar->get_footer_url()),
            '{{content}}' => self::format_content($day),
            // Rest days have no call-to-action button, even if a value was entered before the rest day was set.
            '{{ctabutton}}' => calendar_generator::is_day_rest($calendar, $day) ? '' : self::build_cta($day, $calendar, $overrides),
        ];

        return strtr($template, $replacements);
    }

    /**
     * Formats the HTML content of a day.
     *
     * Plugin file placeholders (@@PLUGINFILE@@) saved by the editor are rewritten to real URLs before
     * format_text(), which does not do it. In the live preview the text already holds full draft URLs,
     * so the rewrite leaves it unchanged.
     *
     * @param day $day
     * @return string
     */
    private static function format_content(day $day): string {
        global $CFG;

        // The function is defined in lib/filelib.php, which Moodle does not always load (e.g. preview pages).
        require_once($CFG->libdir . '/filelib.php');

        $context = \context_system::instance();

        $text = file_rewrite_pluginfile_urls(
            $day->get('content') ?? '',
            'pluginfile.php',
            $context->id,
            'local_eventcalendar',
            'content',
            $day->get('id')
        );

        // The format_text() function turns every draftfile.php URL into brokenfile.php#, because a text read from the database
        // should never contain draft URLs. In the live preview of the day form the text is the one still in the
        // editor and it does hold draft URLs, which are valid for the current user. They are hidden behind a
        // marker for the duration of format_text() and restored afterwards, so the preview shows the images.
        // On saved content (placeholders already rewritten to pluginfile.php) the replacements do nothing.
        $draftprefix = $CFG->wwwroot . '/draftfile.php';
        $marker = '@@ECDRAFTFILE@@';
        $text = str_replace($draftprefix, $marker, $text);

        $formatted = format_text($text, $day->get('contentformat'), ['context' => $context]);

        return str_replace($marker, $draftprefix, $formatted);
    }

    /**
     * Builds a decorative image (alt empty and role presentation so screen readers skip it).
     *
     * @param \moodle_url|null $url
     * @return string complete img tag, or an empty string if $url is null
     */
    private static function build_image_html(?\moodle_url $url): string {
        if (!$url) {
            return '';
        }

        return \html_writer::empty_tag('img', [
            'src' => $url->out(false),
            'alt' => '',
            'role' => 'presentation',
            'style' => 'max-width:100%; height:auto; display:block; margin:0 auto 16px auto;',
        ]);
    }

    /**
     * Builds the call to action. This plugin has none: other plugins provide it through the day_cta hook.
     *
     * @param day $day
     * @param calendar $calendar
     * @param array $overrides values typed in the day form and not yet saved (live preview only)
     * @return string HTML of the call to action, or an empty string
     */
    private static function build_cta(day $day, calendar $calendar, array $overrides): string {
        $hook = new day_cta($day, $calendar, $overrides, '');
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return $hook->get_html();
    }

    /**
     * Minimal fallback used when the template file is missing: content only, without header, footer or CTA.
     *
     * @param day $day
     * @return string
     */
    private static function render_content_only(day $day): string {
        return self::format_content($day);
    }
}
