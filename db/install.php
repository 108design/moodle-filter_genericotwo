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
 * filter genericotwo installation tasks
 *
 * @package    filter_genericotwo
 * @copyright  2026 Justin Hunt {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install the plugin.
 */
function xmldb_filter_genericotwo_install() {
    global $DB;

    // Templates registered here are DB rows (unlike filter_generico, which stores presets
    // as plugin config), so pre-installing one just means inserting its preset as a record.
    $forinstall = [
        'welcomeuser', 'accordian', 'accordianitem', 'tabs', 'tabitem', 'videolightbox',
        'pw-multiplayeraudio', 'pw-onceaudio', 'pw-poodllaudio', 'qrcode',
    ];

    foreach ($forinstall as $templatekey) {
        if ($DB->record_exists('filter_genericotwo_templates', ['templatekey' => $templatekey])) {
            continue;
        }

        $preset = \filter_genericotwo\presets::fetch_preset($templatekey);
        if (!$preset) {
            continue;
        }

        $record = new \stdClass();
        $record->templatekey = $preset->templatekey;
        $record->name = $preset->name ?? $templatekey;
        $record->version = $preset->version ?? '';
        $record->instructions = $preset->instructions ?? '';
        $record->instructionsformat = FORMAT_HTML;
        $record->content = $preset->content ?? '';
        $record->templateend = $preset->templateend ?? '';
        $record->importcss = $preset->importcss ?? '';
        $record->customcss = $preset->customcss ?? '';
        $record->jscontent = $preset->jscontent ?? '';
        $record->variabledefaults = $preset->variabledefaults ?? '';
        $record->dataset = $preset->dataset ?? '';
        $record->datasetvars = $preset->datasetvars ?? '';
        $record->allowedcontexts = $preset->allowedcontexts ?? '';
        $record->allowedcontextids = $preset->allowedcontextids ?? '';
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('filter_genericotwo_templates', $record);
    }
}
