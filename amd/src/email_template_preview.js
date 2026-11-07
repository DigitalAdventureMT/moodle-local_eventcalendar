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
 * Height of the structure preview of the email template form.
 *
 * The preview is an iframe (data-region="local-eventcalendar-emailpreview") whose page runs no script: its
 * sandbox has no allow-scripts. allow-same-origin keeps it on the origin of the site, so this module, running
 * in the form page, can read the height of its document and fit the frame to it, without inner scrollbars.
 *
 * The height is measured when the frame loads and again when the window is resized, since a narrower page
 * wraps the template on more lines. Before each measure the frame is shrunk, so that it can also get shorter.
 * If the document cannot be read (for example when the site is configured to serve it from another origin),
 * the frame keeps the height set in the stylesheet and its own scrollbar.
 *
 * @module     local_eventcalendar/email_template_preview
 * @copyright  2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Selector of the preview frame. */
const SELECTOR = 'iframe[data-region="local-eventcalendar-emailpreview"]';

/** Minimum height of the frame, in pixels, so that an empty template still shows a visible area. */
const MIN_HEIGHT = 100;

/** Delay before measuring again after a resize of the window, in milliseconds. */
const RESIZE_DELAY = 150;

/**
 * Returns the document of a frame, or null when it cannot be read.
 *
 * @param {HTMLIFrameElement} frame
 * @returns {Document|null}
 */
const getFrameDocument = (frame) => {
    try {
        return frame.contentDocument;
    } catch (error) {
        return null;
    }
};

/**
 * Fits the height of a frame to its document.
 *
 * @param {HTMLIFrameElement} frame
 */
const fitHeight = (frame) => {
    const framedoc = getFrameDocument(frame);
    if (!framedoc || !framedoc.documentElement) {
        return;
    }

    frame.style.height = MIN_HEIGHT + 'px';
    const contentheight = Math.max(
        framedoc.documentElement.scrollHeight,
        framedoc.body ? framedoc.body.scrollHeight : 0
    );
    frame.style.height = Math.max(MIN_HEIGHT, contentheight) + 'px';
};

/**
 * Sets up the height of the preview frames of the page.
 */
export const init = () => {
    const frames = Array.from(document.querySelectorAll(SELECTOR));
    if (!frames.length) {
        return;
    }

    frames.forEach((frame) => {
        frame.addEventListener('load', () => fitHeight(frame));
        // The frame may have finished loading before this module ran.
        const framedoc = getFrameDocument(frame);
        if (framedoc && framedoc.readyState === 'complete' && framedoc.URL !== 'about:blank') {
            fitHeight(frame);
        }
    });

    let timer = null;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(() => frames.forEach(fitHeight), RESIZE_DELAY);
    });
};
