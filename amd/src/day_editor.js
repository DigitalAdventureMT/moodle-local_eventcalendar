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
 * Behaviour of the day edit form on the days page.
 *
 * - "Choose from pack" selects copy the chosen text into their target field.
 * - Preview and test buttons open their result in a modal window.
 * - The form fields are arranged in columns, following the layout passed by the page (day_form::get_layout()).
 *
 * @module     local_eventcalendar/day_editor
 * @copyright  2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

/** Name of the iframe used as form target by the preview buttons. */
const PREVIEW_FRAME = 'eventcalendar_preview_iframe';

/** Minimum height of the preview window, in pixels. */
const PREVIEW_MIN_HEIGHT = 160;

/**
 * Elements that never go into the columns: the hidden calendar id and the group of the action buttons, which
 * keeps the standard position of Moodle forms. Moodle gives a group the id "fgroup_id_{name}", a single
 * element "fitem_id_{name}", so both forms are listed.
 */
const OUTSIDE_COLUMNS = ['fitem_id_calendarid', 'fitem_id_buttonar', 'fgroup_id_buttonar'];

/**
 * Sets the text of a form field, also when the field is a TinyMCE or Atto editor.
 *
 * @param {string} name Field name
 * @param {string} text New content
 */
const setFieldText = (name, text) => {
    const id = 'id_' + name;
    const field = document.getElementById(id);
    if (!field) {
        return;
    }

    const tinyeditor = (window.tinymce && window.tinymce.get) ? window.tinymce.get(id) : null;
    if (tinyeditor) {
        tinyeditor.setContent(text);
        tinyeditor.save();
        return;
    }

    const attoeditable = document.getElementById(id + 'editable');
    if (attoeditable) {
        attoeditable.innerHTML = text;
    }
    field.value = text;
};

/**
 * Copies the text chosen in a pack select (data-pool and data-target attributes) into the target field.
 *
 * @param {HTMLSelectElement} select
 */
const applyPackChoice = (select) => {
    if (select.value === '') {
        return;
    }

    let pool;
    try {
        pool = JSON.parse(select.dataset.pool);
    } catch (error) {
        return;
    }
    if (pool[select.value] !== undefined) {
        setFieldText(select.dataset.target, pool[select.value]);
    }
};

/**
 * Creates the modal window whose iframe is the target of the preview and test buttons.
 *
 * The window is a modal dialog for assistive technologies: when it opens the focus moves to the close
 * button and stays inside the dialog, Escape closes it and the focus returns to the button that opened it.
 */
const createPreviewModal = async() => {
    const [closelabel, windowlabel] = await Promise.all([
        getString('closebuttontitle', 'core'),
        getString('previewwindow', 'local_eventcalendar'),
    ]);

    const overlay = document.createElement('div');
    overlay.className = 'local-eventcalendar-modal';

    const box = document.createElement('div');
    box.className = 'local-eventcalendar-modal-box';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', windowlabel);

    const closebutton = document.createElement('button');
    closebutton.type = 'button';
    closebutton.className = 'local-eventcalendar-modal-close';
    closebutton.setAttribute('aria-label', closelabel);
    closebutton.title = closelabel;

    const closeicon = document.createElement('span');
    closeicon.setAttribute('aria-hidden', 'true');
    closeicon.textContent = '✕';
    closebutton.appendChild(closeicon);

    // The preview shows HTML written by the editors and email templates that may come from an import,
    // so the frame runs no script: the sandbox has no allow-scripts. allow-same-origin keeps the session
    // for the images and lets the load handler below read the height of the page. preview.php sends the
    // same sandbox as a header, which also covers the page opened outside this frame.
    const iframe = document.createElement('iframe');
    iframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
    iframe.name = PREVIEW_FRAME;
    iframe.title = windowlabel;
    iframe.className = 'local-eventcalendar-modal-frame';
    iframe.style.height = PREVIEW_MIN_HEIGHT + 'px';

    let opener = null;

    // On each load the height is reset first, so that the window can also shrink, then fitted to the content.
    iframe.addEventListener('load', () => {
        try {
            const framedoc = iframe.contentDocument;
            if (!framedoc || !framedoc.documentElement) {
                return;
            }
            iframe.style.height = '1px';
            const contentheight = Math.max(
                framedoc.documentElement.scrollHeight,
                framedoc.body ? framedoc.body.scrollHeight : 0
            );
            const maxheight = Math.floor(window.innerHeight * 0.9);
            iframe.style.height = Math.max(PREVIEW_MIN_HEIGHT, Math.min(contentheight, maxheight)) + 'px';
        } catch (error) {
            iframe.style.height = '60vh';
        }
    });

    const isopen = () => overlay.style.display === 'flex';
    const close = () => {
        overlay.style.display = 'none';
        if (opener) {
            opener.focus();
            opener = null;
        }
    };
    closebutton.addEventListener('click', close);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            close();
        }
    });

    // Escape closes the dialog; Tab cycles between the close button and the iframe only.
    document.addEventListener('keydown', (e) => {
        if (!isopen()) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        } else if (e.key === 'Tab') {
            e.preventDefault();
            (document.activeElement === closebutton ? iframe : closebutton).focus();
        }
    });

    box.appendChild(closebutton);
    box.appendChild(iframe);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest ? e.target.closest('[data-eventcalendar-preview]') : null;
        if (trigger) {
            opener = trigger;
            iframe.style.height = PREVIEW_MIN_HEIGHT + 'px';
            overlay.style.display = 'flex';
            closebutton.focus();
        }
    });
};

/**
 * Arranges the edit form fields in columns.
 *
 * The layout comes from the page: one list of element names per column, where an array groups buttons on the
 * same row, plus the buttons moved without their wrapper. The fields are moved with JavaScript because the
 * .fitem elements are not always direct children of the form. appendChild() moves the existing nodes, keeping
 * their listeners and editors. Elements of the form not listed in the layout (for example added by another
 * plugin without a position) go to the end of the last column, so that nothing is left outside.
 *
 * @param {Object} layout {columns: Array, unwrapped: Array}
 */
const arrangeColumns = (layout) => {
    const editform = document.querySelector('.eventcalendar-day-edit form');
    const columns = (layout && Array.isArray(layout.columns)) ? layout.columns : [];
    const unwrapped = (layout && Array.isArray(layout.unwrapped)) ? layout.unwrapped : [];
    if (!editform || columns.length === 0) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'local-eventcalendar-dayform';

    // Moves only the input of a button into the target and drops its wrapper.
    const takeInput = (name, target) => {
        const fitem = document.getElementById('fitem_id_' + name);
        if (!fitem) {
            return;
        }
        const input = fitem.querySelector('input');
        if (input) {
            target.appendChild(input);
        }
        fitem.remove();
    };

    columns.forEach((fields) => {
        const column = document.createElement('div');
        column.className = 'local-eventcalendar-dayform-col';

        fields.forEach((entry) => {
            if (Array.isArray(entry)) {
                const row = document.createElement('div');
                row.className = 'local-eventcalendar-dayform-buttons';
                entry.forEach((name) => takeInput(name, row));
                column.appendChild(row);
                return;
            }

            if (unwrapped.includes(entry)) {
                takeInput(entry, column);
                return;
            }
            const fitem = document.getElementById('fitem_id_' + entry);
            if (fitem) {
                column.appendChild(fitem);
            }
        });

        wrapper.appendChild(column);
    });

    // Elements not listed in the layout: top level .fitem elements still outside the columns.
    const lastcolumn = wrapper.lastElementChild;
    editform.querySelectorAll('.fitem').forEach((fitem) => {
        const nested = fitem.parentElement && fitem.parentElement.closest('.fitem');
        if (!nested && !wrapper.contains(fitem) && !OUTSIDE_COLUMNS.includes(fitem.id)) {
            lastcolumn.appendChild(fitem);
        }
    });

    // The hidden calendarid field stays outside the columns.
    const calendarfitem = document.getElementById('fitem_id_calendarid');
    if (calendarfitem) {
        calendarfitem.style.display = 'none';
        editform.insertBefore(calendarfitem, editform.firstChild);
    }

    editform.insertBefore(wrapper, editform.firstChild);

    // Save and cancel buttons: full width row after the columns.
    const buttonfitem = document.getElementById('fitem_id_buttonar');
    if (buttonfitem) {
        buttonfitem.classList.add('local-eventcalendar-dayform-actions');
        wrapper.insertAdjacentElement('afterend', buttonfitem);
    }
};

/**
 * Initialises the module.
 *
 * @param {Object} layout Layout of the form: {columns: Array, unwrapped: Array}
 */
export const init = (layout) => {
    document.addEventListener('change', (e) => {
        const target = e.target;
        if (target.dataset && target.dataset.pool !== undefined && target.dataset.target !== undefined) {
            applyPackChoice(target);
        }
    });

    arrangeColumns(layout);

    createPreviewModal();
};
