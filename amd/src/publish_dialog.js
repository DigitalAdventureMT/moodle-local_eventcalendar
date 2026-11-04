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
 * Publishing dialogs of the calendar management page.
 *
 * A button with data-action="local-eventcalendar-publish-open" opens the dialog named in its
 * data-dialog attribute (a native dialog element, see the publish_dialog template).
 *
 * The options of the dialog are a carousel: up to three options are visible at a time (two or one on
 * smaller screens) and the previous and next buttons move between pages. The page shown is announced
 * to screen readers by a live status text. Hidden options use the hidden attribute, so they are also
 * removed from the keyboard and screen reader navigation.
 *
 * Copy buttons copy the code of their option, or the image of its preview (QR code), to the clipboard
 * and briefly show a confirmation, also announced through the status region of the dialog.
 *
 * @module     local_eventcalendar/publish_dialog
 * @copyright  2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** Time the confirmation stays visible, in milliseconds. */
const CONFIRMATION_DELAY = 1500;

/** Number of visible options by minimum screen width, from the largest. */
const PAGE_SIZES = [
    {query: '(min-width: 992px)', size: 3},
    {query: '(min-width: 768px)', size: 2},
];

/** Prefix of the actions handled by this module. */
const ACTION = 'local-eventcalendar-publish-';

/**
 * Number of options visible at a time on the current screen.
 *
 * @return {number}
 */
const getPageSize = () => {
    const match = PAGE_SIZES.find((entry) => window.matchMedia(entry.query).matches);
    return match ? match.size : 1;
};

/**
 * Shows the page of the carousel that starts at the given option.
 *
 * The start is moved back to the first option of its page, so that pages stay the same after a
 * resize. When all options fit in one page the navigation is hidden. When a navigation button
 * becomes disabled while it has the focus, the focus moves to the other button, so that it is not lost.
 *
 * @param {HTMLDialogElement} dialog
 * @param {number} start index of the first option to show
 */
const showPage = async(dialog, start) => {
    const slides = Array.from(dialog.querySelectorAll('[data-region="slide"]'));
    const previous = dialog.querySelector(`[data-action="${ACTION}previous"]`);
    const next = dialog.querySelector(`[data-action="${ACTION}next"]`);
    const status = dialog.querySelector('[data-region="carousel-status"]');
    const total = slides.length;
    const size = getPageSize();
    dialog.querySelector('[data-region="options"]').dataset.size = Math.min(size, total);

    if (total <= size) {
        slides.forEach((slide) => {
            slide.hidden = false;
        });
        previous.hidden = true;
        next.hidden = true;
        status.textContent = '';
        dialog.dataset.start = 0;
        return;
    }

    start = Math.max(0, Math.min(start, total - 1));
    start -= start % size;
    const last = Math.min(start + size, total);
    dialog.dataset.start = start;

    slides.forEach((slide, index) => {
        slide.hidden = index < start || index >= last;
    });

    // The focused element is read before disabling, because a disabled button loses the focus.
    const focused = document.activeElement;
    previous.hidden = false;
    next.hidden = false;
    previous.disabled = start === 0;
    next.disabled = last >= total;
    if (focused === previous && previous.disabled) {
        next.focus();
    } else if (focused === next && next.disabled) {
        previous.focus();
    }

    const page = {first: start + 1, last: last, total: total};
    status.textContent = await getString(last > start + 1 ? 'publishpage' : 'publishpagesingle', 'local_eventcalendar', page);
};

/**
 * Moves the carousel of a dialog by one page.
 *
 * @param {HTMLDialogElement} dialog
 * @param {number} direction -1 for the previous page, 1 for the next one
 */
const movePage = (dialog, direction) => {
    const start = parseInt(dialog.dataset.start || '0', 10);
    return showPage(dialog, start + direction * getPageSize());
};

/**
 * Copies a text to the clipboard.
 *
 * The Clipboard API needs a secure context (https or localhost): on other sites the text is copied
 * through a temporary text area and the older copy command.
 *
 * @param {string} text
 * @param {HTMLElement} container element where the temporary text area is added (the open dialog)
 */
const copyText = async(text, container) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.className = 'accesshide';
    container.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();
    if (!copied) {
        throw new Error('Copy command not available');
    }
};

/**
 * Converts an image data URI into a Blob, without network requests.
 *
 * @param {string} datauri
 * @return {Blob}
 */
const dataUriToBlob = (datauri) => {
    const [header, data] = datauri.split(',');
    const type = header.substring(5).split(';')[0];
    const binary = window.atob(data);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return new Blob([bytes], {type: type});
};

/**
 * Shows a message in the status region of the dialog and, for a while, as the text of the button.
 *
 * @param {HTMLButtonElement} button
 * @param {string} message
 */
const confirmAction = (button, message) => {
    const original = button.textContent;
    button.textContent = message;
    button.closest('dialog').querySelector('[data-region="status"]').textContent = message;
    setTimeout(() => {
        button.textContent = original;
    }, CONFIRMATION_DELAY);
};

/**
 * Copies the code of an option and shows the confirmation.
 *
 * @param {HTMLButtonElement} button
 */
const copySnippet = async(button) => {
    await copyText(button.dataset.code, button.closest('dialog'));
    confirmAction(button, await getString('linkcopied', 'local_eventcalendar'));
};

/**
 * Copies the preview image of an option (the QR code) and shows the confirmation.
 *
 * Images can be copied only with the asynchronous Clipboard API, available in secure contexts and
 * in recent browsers. The clipboard item is created before any wait, because some browsers accept
 * it only during the click. When the copy is not possible the user is invited to download the image.
 *
 * @param {HTMLButtonElement} button
 */
const copyImage = async(button) => {
    const image = button.closest('[data-region="slide"]').querySelector('img');
    const status = button.closest('dialog').querySelector('[data-region="status"]');

    if (!window.isSecureContext || !navigator.clipboard || !navigator.clipboard.write || !window.ClipboardItem) {
        status.textContent = await getString('copyimageunsupported', 'local_eventcalendar');
        return;
    }

    try {
        const blob = dataUriToBlob(image.src);
        await navigator.clipboard.write([new window.ClipboardItem({[blob.type]: blob})]);
    } catch (error) {
        status.textContent = await getString('copyimageunsupported', 'local_eventcalendar');
        return;
    }
    confirmAction(button, await getString('linkcopied', 'local_eventcalendar'));
};

/**
 * Initialises the module.
 */
export const init = () => {
    const dialogs = () => Array.from(document.querySelectorAll('dialog.local-eventcalendar-publish'));

    // First page of every dialog, then the same page again when the number of visible options changes.
    dialogs().forEach((dialog) => showPage(dialog, 0));
    PAGE_SIZES.forEach((entry) => {
        window.matchMedia(entry.query).addEventListener('change', () => {
            dialogs().forEach((dialog) => showPage(dialog, parseInt(dialog.dataset.start || '0', 10)));
        });
    });

    document.addEventListener('click', (e) => {
        const target = e.target;

        // A click on the backdrop of a publishing dialog (the dialog element itself) closes it.
        if (target.matches && target.matches('dialog.local-eventcalendar-publish')) {
            target.close();
            return;
        }

        const button = target.closest ? target.closest(`[data-action^="${ACTION}"]`) : null;
        if (!button) {
            return;
        }

        const dialog = button.closest('dialog');
        switch (button.dataset.action) {
            case ACTION + 'open': {
                const opened = document.getElementById(button.dataset.dialog);
                if (opened) {
                    showPage(opened, 0).catch(Notification.exception);
                    opened.showModal();
                }
                break;
            }
            case ACTION + 'close':
                dialog.close();
                break;
            case ACTION + 'previous':
                movePage(dialog, -1).catch(Notification.exception);
                break;
            case ACTION + 'next':
                movePage(dialog, 1).catch(Notification.exception);
                break;
            case ACTION + 'copy':
                copySnippet(button).catch(Notification.exception);
                break;
            case ACTION + 'copy-image':
                copyImage(button).catch(Notification.exception);
                break;
        }
    });
};
