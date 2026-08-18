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
 * Loads CSS and runs queued JavaScript for content rendered by the filter after the page
 * head has already been output (e.g. AJAX, fragments, web services).
 *
 * @module     filter_genericotwo/loader
 * @copyright  2026 Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {
    return {
        init: function (cssurls) {

            // Inject CSS if provided
            if (cssurls && cssurls.length > 0) {
                cssurls.forEach(function (url) {
                    this.injectcss(url);
                }.bind(this));
            }

            // Run any queued JS scripts
            if (typeof window.filter_genericotwo !== 'undefined') {
                window.filter_genericotwo.ready = true;
                // Run all queued functions.
                for (var i = 0; i < window.filter_genericotwo.queue.length; i++) {
                    try {
                        window.filter_genericotwo.queue[i]();
                    } catch (e) {
                        // eslint-disable-next-line no-console
                        console.error('GenericoTwo script error:', e);
                    }
                }
                // Clear the queue, subsequent calls go straight to run().
                window.filter_genericotwo.queue = [];
            }
        },

        injectcss: function (csslink) {
            // Check if already exists to avoid duplicates
            if (document.querySelector('link[href="' + csslink + '"]')) {
                return;
            }
            var link = document.createElement("link");
            link.href = csslink;
            link.type = "text/css";
            link.rel = "stylesheet";
            document.getElementsByTagName("head")[0].appendChild(link);
        }
    };
});
