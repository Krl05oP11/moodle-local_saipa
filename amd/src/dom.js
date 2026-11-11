// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Small DOM helpers for the SAIPA dashboards (MMR-462 #15).
 *
 * The dashboards build their rows, badges and notices with these helpers instead of HTML strings:
 * every text goes in through textContent, so data from the web services is never parsed as markup.
 *
 * @module     local_saipa/dom
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    /**
     * Creates an element.
     *
     * @param {string} tag Tag name.
     * @param {string} [cls] CSS classes.
     * @param {(string|number|Node|Array)} [content] Text, a node, or a list of texts and nodes.
     * @param {Object} [attrs] Attributes to set (title, href, role, ...).
     * @returns {HTMLElement} The new element.
     */
    function el(tag, cls, content, attrs) {
        var node = document.createElement(tag);
        if (cls) { node.className = cls; }
        append(node, content);
        Object.keys(attrs || {}).forEach(function(name) {
            node.setAttribute(name, attrs[name]);
        });
        return node;
    }

    /**
     * Appends text and nodes to an element.
     *
     * @param {HTMLElement} node Parent element.
     * @param {(string|number|Node|Array|null)} content Text, a node, or a list of them; null is ignored.
     * @returns {HTMLElement} The parent element.
     */
    function append(node, content) {
        if (content === null || content === undefined || content === '') { return node; }
        (Array.isArray(content) ? content : [content]).forEach(function(part) {
            if (part === null || part === undefined || part === '') { return; }
            node.appendChild(part instanceof Node ? part : document.createTextNode(String(part)));
        });
        return node;
    }

    /**
     * Replaces the contents of an element.
     *
     * @param {HTMLElement} node Element to fill.
     * @param {(string|number|Node|Array|null)} [content] New contents; nothing empties the element.
     * @returns {HTMLElement} The element.
     */
    function replace(node, content) {
        if (!node) { return node; }
        node.textContent = '';
        return append(node, content);
    }

    /**
     * Joins texts and nodes with a separator, skipping empty parts.
     *
     * @param {Array} parts Texts and nodes.
     * @param {string} separator Text placed between parts.
     * @returns {Array} Parts and separators, ready for append().
     */
    function join(parts, separator) {
        var out = [];
        parts.filter(function(p) { return p !== null && p !== undefined && p !== ''; }).forEach(function(p, i) {
            if (i > 0) { out.push(separator); }
            out.push(p);
        });
        return out;
    }

    return {el: el, append: append, replace: replace, join: join};
});
