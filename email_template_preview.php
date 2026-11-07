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
 * Structure preview of an email template, for the iframe of the template edit form.
 *
 * A bare page without Moodle header, footer or navigation: only the saved content of the template, with the
 * table borders forced visible (see \local_eventcalendar\local\email_template_preview).
 *
 * The content is not filtered, so the response carries a Content-Security-Policy sandbox without allow-scripts:
 * the browser runs no script and no event handler of the template, also when the page is opened directly in a
 * tab instead of the iframe. The page only reads, so it needs no sesskey. It requires the same capability as
 * the email template page.
 *
 * @package     local_eventcalendar
 * @copyright   2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_eventcalendar\local\email_template_preview;
use local_eventcalendar\persistent\email_template;

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/eventcalendar:managecalendars', $context);

// The page context and URL are set explicitly, as Moodle expects them also for a page printed without header.
$PAGE->set_context($context);
$PAGE->set_url(email_template_preview::get_url($id));

$template = email_template::get_record(['id' => $id]);
if (!$template) {
    throw new moodle_exception('errorinvalidemailtemplate', 'local_eventcalendar');
}

// The sandbox blocks every script of the page. allow-same-origin keeps the origin of the site, which is needed to
// load the images of the session and to let the edit form read the height of the page; without allow-scripts it
// gives the page no way to run code. The popup tokens let the links of the preview open in a new tab, outside
// the sandbox. The page is printed without $OUTPUT->header(), which sends X-Frame-Options for the other pages: it
// is sent here, so that another site cannot load the preview in a frame, unless the administrator allows it.
header('Content-Type: text/html; charset=utf-8');
header('Content-Security-Policy: sandbox ' . email_template_preview::SANDBOX);
if (empty($CFG->allowframembedding)) {
    header('X-Frame-Options: SAMEORIGIN');
}

echo email_template_preview::build_document((string) ($template->get('content') ?? ''));
