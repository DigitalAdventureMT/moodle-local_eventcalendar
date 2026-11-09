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
 * Structure preview of an email template, shown in an isolated frame.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventcalendar\local;

/**
 * Structure preview of an email template, shown in an isolated frame.
 *
 * The content of a template is raw HTML. In this plugin it comes only from the files of the plugin, but the
 * add-ons can let users change it or import it from a file made on another site, so it is treated as untrusted:
 * it is never printed in a Moodle page. The edit form shows it in an iframe whose page
 * (email_template_preview.php) is served with a Content-Security-Policy sandbox without allow-scripts, so no
 * script and no event handler of the template can run in the origin of the site.
 *
 * This class builds the address of that page and its document: the content of the template, unchanged, plus
 * a style that forces the borders of the tables visible, to show the structure of the layout. The style is
 * added inside the head of the template when it has one (templates are usually full documents), otherwise the
 * content is wrapped in a minimal document.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_template_preview {
    /** Style of the preview: visible table borders, which override border="0" of the templates. */
    private const STYLE = '<style>table, td, th { border: 1px solid #999 !important; }</style>';

    /** Sandbox of the frame and of the page: no scripts, same origin for the images, links open outside. */
    public const SANDBOX = 'allow-same-origin allow-popups allow-popups-to-escape-sandbox';

    /**
     * Returns the address of the preview page of a template.
     *
     * @param int $templateid
     * @return \moodle_url
     */
    public static function get_url(int $templateid): \moodle_url {
        return new \moodle_url('/local/eventcalendar/email_template_preview.php', ['id' => $templateid]);
    }

    /**
     * Builds the document of the preview from the content of a template.
     *
     * The content is not filtered: the sandbox of the page is what makes it harmless, and a filter would
     * remove the styles and part of the layout that the preview is meant to show.
     *
     * @param string $content HTML of the template.
     * @return string Complete HTML document.
     */
    public static function build_document(string $content): string {
        $headend = stripos($content, '</head>');
        if ($headend !== false) {
            return substr($content, 0, $headend) . self::STYLE . substr($content, $headend);
        }

        return '<!DOCTYPE html><html lang="' . s(str_replace('_', '-', current_language())) . '"><head>'
            . '<meta charset="utf-8">'
            . '<title>' . s(get_string('emailtplpreview', 'local_eventcalendar')) . '</title>'
            . self::STYLE
            . '</head><body>' . $content . '</body></html>';
    }
}
