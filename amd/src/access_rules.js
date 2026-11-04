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
 * Access rules page of a calendar.
 *
 * The page is built client side: groups of rules (cards, ORed together) containing rules (rows, ANDed
 * inside a group). State lives in the DOM and is sent whole to the local_eventcalendar_save_access_rules
 * web service, which returns the updated state used to redraw the page.
 *
 * The module does not know the rule types: they come from the configuration written by PHP
 * (access_rules::get_ui_config()). A row is built as a cascade: type, subject, operator, value.
 *
 * The change events use jQuery because the select behind Moodle's autocomplete (core/form-autocomplete)
 * triggers a jQuery event that native listeners do not receive.
 *
 * @module     local_eventcalendar/access_rules
 * @copyright  2026 Marco Tosatto <marco.tosatto@digitaladventure.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Autocomplete from 'core/form-autocomplete';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {get_string as getString, get_strings as getStrings} from 'core/str';

/** Keys of the strings used by the module (component local_eventcalendar). */
const STRING_KEYS = [
    'accessor',
    'accessand',
    'accesschoosetype',
    'accesschoosesubject',
    'accesschoosevalue',
    'accesssearchsubject',
    'accessnoselection',
    'accessinvalidrule',
    'accessincomplete',
    'accessincompleterule',
    'accesssaved',
    'accesslabeltype',
    'accesslabelsubject',
    'accesslabelcomparison',
    'accesslabelvalue',
];

/** Value types without a field to fill in. */
const NO_VALUE_TYPES = ['none', 'checkbox'];

/** @type {Object} loaded strings, by key */
const strings = {};

/** @type {Object|null} type configuration (access_rules::get_ui_config()) */
let config = null;

/** @type {HTMLElement|null} page container */
let root = null;

/** @type {number} calendar of the rules */
let calendarid = 0;

/** @type {boolean} unsaved changes present */
let dirty = false;

/** @type {number} counter for the ids of fields and messages */
let uniqueid = 0;

/**
 * Entry point, called from accessrules.php with js_call_amd().
 *
 * @param {string} rootid id of the page container
 */
export const init = async(rootid) => {
    root = document.getElementById(rootid);
    if (!root) {
        return;
    }

    // The page is drawn through several asynchronous steps (strings, templates, autocomplete fields). The
    // pending marker tells Moodle, and its automated tests, that the page is not ready until the last
    // rule has been drawn, including the messages of the conditions that are no longer valid.
    const pending = new Pending('local_eventcalendar/access_rules:init');
    try {
        calendarid = parseInt(root.dataset.calendarid, 10);
        config = JSON.parse(document.getElementById(rootid + '-config').textContent);
        const state = JSON.parse(document.getElementById(rootid + '-state').textContent);

        const loaded = await getStrings(STRING_KEYS.map((key) => ({key: key, component: 'local_eventcalendar'})));
        STRING_KEYS.forEach((key, index) => {
            strings[key] = loaded[index];
        });

        bindEvents();
        await renderState(state);
    } finally {
        pending.resolve();
    }
};

/**
 * Binds delegated listeners on the container and the browser warning for unsaved changes.
 */
const bindEvents = () => {
    root.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action]');
        if (!button || !root.contains(button)) {
            return;
        }

        const action = button.dataset.action;

        // After adding or removing an element the focus is moved to a sensible place, so that keyboard
        // and screen reader users do not lose their position in the page.
        if (action === 'add-group') {
            addGroup([]).then((group) => {
                refreshLabels();
                markDirty();
                group.querySelector('[data-action="add-rule"]').focus();
                return null;
            }).catch(Notification.exception);
        } else if (action === 'add-rule') {
            addRule(button.closest('[data-region="group"]'), null).then((rule) => {
                refreshLabels();
                markDirty();
                rule.querySelector('[data-field="ruletype"]').focus();
                return null;
            }).catch(Notification.exception);
        } else if (action === 'remove-group') {
            button.closest('[data-region="group"]').remove();
            refreshLabels();
            markDirty();
            root.querySelector('[data-action="add-group"]').focus();
        } else if (action === 'remove-rule') {
            const group = button.closest('[data-region="group"]');
            button.closest('[data-region="rule"]').remove();
            refreshLabels();
            markDirty();
            group.querySelector('[data-action="add-rule"]').focus();
        } else if (action === 'save') {
            save();
        }
    });

    $(root).on('change', '[data-field]', (e) => {
        const field = e.currentTarget.dataset.field;
        const rule = e.currentTarget.closest('[data-region="rule"]');

        clearMessage(rule);
        markDirty();

        if (field === 'ruletype') {
            buildSubject(rule, null).catch(Notification.exception);
        } else if (field === 'subject') {
            buildComparison(rule, null);
        } else if (field === 'comparison') {
            // The value already entered is kept when the operator changes.
            buildValue(rule, {value: getFieldValue(rule, 'value')});
        }
    });

    $(root).on('input', 'input[data-field="value"]', () => {
        markDirty();
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};

/**
 * Redraws all groups from a state (saved, or returned by the web service after saving).
 * @param {Array} state groups, each with its rules
 */
const renderState = async(state) => {
    root.querySelector('[data-region="groups"]').innerHTML = '';

    for (const group of state) {
        await addGroup(group.rules);
    }

    refreshLabels();
    dirty = false;
};

/**
 * Appends a group at the end, with the given rules.
 * @param {Array} rules rules of the group (empty for a new group)
 * @returns {Promise<HTMLElement>} the added group
 */
const addGroup = async(rules) => {
    const region = root.querySelector('[data-region="groups"]');
    const {html, js} = await Templates.renderForPromise('local_eventcalendar/access_group', {});
    Templates.appendNodeContents(region, html, js);

    const group = region.lastElementChild;
    for (const rule of rules) {
        await addRule(group, rule);
    }

    return group;
};

/**
 * Appends a rule at the end of a group.
 * @param {HTMLElement} group group element
 * @param {Object|null} data saved rule, or null for a new one
 * @returns {Promise<HTMLElement>} the added rule
 */
const addRule = async(group, data) => {
    const region = group.querySelector('[data-region="rules"]');
    const {html, js} = await Templates.renderForPromise('local_eventcalendar/access_rule', {});
    Templates.appendNodeContents(region, html, js);

    const rule = region.lastElementChild;
    await buildType(rule, data);

    if (data && data.invalid) {
        showMessage(rule, data.invalidreason);
    }

    return rule;
};

/**
 * First select: the rule type. Only available types are listed, plus the saved one if it is no longer
 * available: the rule stays visible and is flagged as invalid.
 * @param {HTMLElement} rule
 * @param {Object|null} data
 */
const buildType = async(rule, data) => {
    const region = rule.querySelector('[data-region="type"]');
    const select = createSelect('ruletype', strings.accesschoosetype);
    const current = data ? data.ruletype : '';

    config.types.forEach((type) => {
        if (type.available || type.type === current) {
            addOption(select, type.type, type.name);
        }
    });

    if (current && !getTypeConfig(current)) {
        addOption(select, current, strings.accessinvalidrule);
    }

    select.value = current;
    appendLabelled(region, select, strings.accesslabeltype);

    await buildSubject(rule, data);
};

/**
 * Second column: the subject, as a select (full list, with groups) or as an autocomplete (server search).
 * A saved subject that no longer exists is shown as unavailable and already selected.
 * @param {HTMLElement} rule
 * @param {Object|null} data
 */
const buildSubject = async(rule, data) => {
    const region = rule.querySelector('[data-region="subject"]');
    region.innerHTML = '';

    const type = getTypeConfig(getFieldValue(rule, 'ruletype'));
    if (!type) {
        buildComparison(rule, null);
        return;
    }

    const current = data ? data.subject : '';

    if (type.widget === 'select') {
        const select = createSelect('subject', strings.accesschoosesubject);
        const optgroups = {};

        type.subjects.forEach((subject) => {
            let parent = select;
            if (subject.group) {
                if (!optgroups[subject.group]) {
                    optgroups[subject.group] = document.createElement('optgroup');
                    optgroups[subject.group].label = subject.group;
                    select.appendChild(optgroups[subject.group]);
                }
                parent = optgroups[subject.group];
            }
            addOption(parent, subject.value, subject.label);
        });

        if (current && !type.subjects.some((subject) => subject.value === current)) {
            addOption(select, current, strings.accessinvalidrule);
        }

        select.value = current;
        appendLabelled(region, select, strings.accesslabelsubject);
    } else {
        const select = createSelect('subject', null);
        select.dataset.ruletype = type.type;

        if (current) {
            const label = (data.invalid || !data.subjectlabel) ? strings.accessinvalidrule : data.subjectlabel;
            addOption(select, current, label).selected = true;
        }

        appendLabelled(region, select, strings.accesslabelsubject);

        await Autocomplete.enhance(
            '#' + select.id,
            false,
            'local_eventcalendar/access_rules_search',
            strings.accesssearchsubject,
            false,
            true,
            strings.accessnoselection
        );
    }

    buildComparison(rule, data);
};

/**
 * Third column: the operator, based on the value type of the subject.
 * @param {HTMLElement} rule
 * @param {Object|null} data
 */
const buildComparison = (rule, data) => {
    const region = rule.querySelector('[data-region="comparison"]');
    region.innerHTML = '';

    const subject = getSubjectInfo(rule);
    if (!subject) {
        buildValue(rule, null);
        return;
    }

    const operators = subject.type.operators[subject.valuetype] || [];
    const select = createSelect('comparison', null);

    operators.forEach((operator) => {
        addOption(select, operator.value, operator.label);
    });

    if (data && operators.some((operator) => operator.value === data.comparison)) {
        select.value = data.comparison;
    }

    appendLabelled(region, select, strings.accesslabelcomparison);

    buildValue(rule, data);
};

/**
 * Fourth column: the value, only if the operator requires one (text, date or select of subject options).
 * A saved value no longer among the options is shown as unavailable.
 * @param {HTMLElement} rule
 * @param {Object|null} data
 */
const buildValue = (rule, data) => {
    const region = rule.querySelector('[data-region="value"]');
    region.innerHTML = '';

    const subject = getSubjectInfo(rule);
    const operator = getFieldValue(rule, 'comparison');

    if (!subject || NO_VALUE_TYPES.includes(subject.valuetype) || config.novalueoperators.includes(operator)) {
        return;
    }

    const current = data && data.value ? data.value : '';
    let element;

    if (subject.valuetype === 'select') {
        element = createSelect('value', strings.accesschoosevalue);
        subject.options.forEach((option) => {
            addOption(element, option.value, option.label);
        });
        if (current && !subject.options.some((option) => option.value === current)) {
            addOption(element, current, strings.accessinvalidrule);
        }
    } else {
        element = document.createElement('input');
        element.type = subject.valuetype === 'date' ? 'date' : 'text';
        element.className = 'form-control';
        element.dataset.field = 'value';
        element.id = nextId('value');
    }

    element.value = current;
    appendLabelled(region, element, strings.accesslabelvalue);
};

/**
 * Validates and saves all rules. Incomplete rules are flagged and block the save. The save buttons are
 * disabled during the request to avoid double submissions.
 */
const save = () => {
    const groups = [];
    let incomplete = false;

    root.querySelectorAll('[data-region="group"]').forEach((groupelement) => {
        const rules = [];

        groupelement.querySelectorAll('[data-region="rule"]').forEach((rule) => {
            const data = {
                ruletype: getFieldValue(rule, 'ruletype'),
                subject: getFieldValue(rule, 'subject'),
                comparison: getFieldValue(rule, 'comparison'),
                value: getFieldValue(rule, 'value'),
            };

            if (!isComplete(rule, data)) {
                incomplete = true;
                showMessage(rule, strings.accessincompleterule);
                return;
            }

            rules.push(data);
        });

        groups.push({rules: rules});
    });

    if (incomplete) {
        Notification.addNotification({message: strings.accessincomplete, type: 'error'});
        return;
    }

    const buttons = root.querySelectorAll('[data-action="save"]');
    buttons.forEach((button) => {
        button.disabled = true;
    });

    // As in init(), the page is not ready until the saved state has been drawn again.
    const pending = new Pending('local_eventcalendar/access_rules:save');
    Ajax.call([{
        methodname: 'local_eventcalendar_save_access_rules',
        args: {calendarid: calendarid, groups: groups},
    }])[0].then((result) => {
        return renderState(result.groups);
    }).then(() => {
        dirty = false;
        Notification.addNotification({message: strings.accesssaved, type: 'success'});
        return null;
    }).catch(Notification.exception).then(() => {
        buttons.forEach((button) => {
            button.disabled = false;
        });
        pending.resolve();
        return null;
    }).catch(Notification.exception);
};

/**
 * Checks that a rule has a type, a subject, an operator and, if the operator needs one, a value.
 *
 *
 * @param {HTMLElement} rule
 * @param {Object} data values read from the row
 * @returns {boolean}
 */
const isComplete = (rule, data) => {
    if (!data.ruletype || !data.subject || !data.comparison) {
        return false;
    }

    const subject = getSubjectInfo(rule);
    const needsvalue = subject && !NO_VALUE_TYPES.includes(subject.valuetype)
        && !config.novalueoperators.includes(data.comparison);

    return !needsvalue || data.value.trim() !== '';
};

/**
 * Rebuilds the separators between groups (OR) and between rules (AND).
 */
const refreshLabels = () => {
    root.querySelectorAll('[data-region="separator"]').forEach((element) => element.remove());

    root.querySelectorAll('[data-region="group"]').forEach((group, index) => {
        if (index > 0) {
            group.before(createSeparator(strings.accessor, 'my-2'));
        }
        setAccessibleName(group, 'accessgrouplabel', index + 1);

        group.querySelectorAll('[data-region="rule"]').forEach((rule, ruleindex) => {
            if (ruleindex > 0) {
                rule.before(createSeparator(strings.accessand, 'my-1 local-eventcalendar-ms'));
            }
            setAccessibleName(rule, 'accessrulelabel', ruleindex + 1);
        });
    });
};

/**
 * Sets the accessible name of a group or rule (role="group" in the templates), with its position.
 *
 * @param {HTMLElement} element
 * @param {string} key string with the position as parameter
 * @param {number} position
 */
const setAccessibleName = (element, key, position) => {
    getString(key, 'local_eventcalendar', position).then((name) => {
        element.setAttribute('aria-label', name);
        return null;
    }).catch(Notification.exception);
};

/**
 * Returns a new unique id for a field or message of the page.
 *
 * @param {string} name
 * @returns {string}
 */
const nextId = (name) => {
    uniqueid++;
    return 'local-eventcalendar-rule-' + name + '-' + uniqueid;
};

/**
 * Appends a field to a region with its label. The label is visually hidden (Moodle accesshide class):
 * the columns of a rule are self-explanatory on screen, but every field needs a name for screen readers.
 * The autocomplete of the subject also takes its name from this label.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} field element with an id
 * @param {string} text label text
 */
const appendLabelled = (region, field, text) => {
    const label = document.createElement('label');
    label.className = 'accesshide';
    label.htmlFor = field.id;
    label.textContent = text;
    region.appendChild(label);
    region.appendChild(field);
};

/**
 * Creates a separator between groups or rules, large and bold to make the logic evident.
 *
 * @param {string} text
 * @param {string} classes spacing classes
 * @returns {HTMLElement}
 */
const createSeparator = (text, classes) => {
    const element = document.createElement('div');
    element.className = 'text-uppercase text-muted local-eventcalendar-bold local-eventcalendar-separator ' + classes;
    element.dataset.region = 'separator';
    element.textContent = text;
    return element;
};

/**
 * Returns the type, value type and options of the subject chosen in a row.
 *
 * @param {HTMLElement} rule
 * @returns {Object|null} {type, valuetype, options}, or null if there is no subject
 */
const getSubjectInfo = (rule) => {
    const type = getTypeConfig(getFieldValue(rule, 'ruletype'));
    const value = getFieldValue(rule, 'subject');

    if (!type || !value) {
        return null;
    }

    if (type.widget === 'select') {
        const subject = type.subjects.find((item) => item.value === value);
        return {
            type: type,
            valuetype: subject ? subject.valuetype : type.defaultvaluetype,
            options: subject ? subject.options : [],
        };
    }

    return {type: type, valuetype: type.defaultvaluetype, options: []};
};

/**
 * Returns the configuration of a rule type.
 *
 * @param {string} name type identifier
 * @returns {Object|undefined}
 */
const getTypeConfig = (name) => config.types.find((type) => type.type === name);

/**
 * Returns the value of a field in a row ('' if the field is missing).
 *
 * @param {HTMLElement} rule
 * @param {string} field
 * @returns {string}
 */
const getFieldValue = (rule, field) => {
    const element = rule.querySelector('[data-field="' + field + '"]');
    return element ? element.value : '';
};

/**
 * Creates a select with the Bootstrap class of the site version and an optional empty placeholder option.
 *
 *
 * @param {string} field field name (data-field)
 * @param {string|null} placeholder
 * @returns {HTMLSelectElement}
 */
const createSelect = (field, placeholder) => {
    const select = document.createElement('select');
    select.className = config.selectclass + ' local-eventcalendar-autoselect';
    select.dataset.field = field;
    select.id = nextId(field);

    if (placeholder !== null) {
        addOption(select, '', placeholder);
    }

    return select;
};

/**
 * Appends an option to a select or optgroup.
 *
 * @param {HTMLElement} parent select or optgroup
 * @param {string} value
 * @param {string} label
 * @returns {HTMLOptionElement}
 */
const addOption = (parent, value, label) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    parent.appendChild(option);
    return option;
};

/**
 * Highlights a rule row (red border) and shows a message below it.
 *
 * @param {HTMLElement} rule
 * @param {string} message
 */
const showMessage = (rule, message) => {
    const region = rule.querySelector('[data-region="message"]');
    if (!region.id) {
        region.id = nextId('message');
    }
    rule.classList.add('border', 'border-danger');
    region.textContent = message;
    rule.setAttribute('aria-describedby', region.id);
    rule.querySelectorAll('[data-field]').forEach((field) => field.setAttribute('aria-invalid', 'true'));
};

/**
 * Removes the highlight and the message from a rule row.
 *
 * @param {HTMLElement} rule
 */
const clearMessage = (rule) => {
    rule.classList.remove('border', 'border-danger');
    rule.querySelector('[data-region="message"]').textContent = '';
    rule.removeAttribute('aria-describedby');
    rule.querySelectorAll('[data-field]').forEach((field) => field.removeAttribute('aria-invalid'));
};

/**
 * Marks the form as having unsaved changes.
 */
const markDirty = () => {
    dirty = true;
};
