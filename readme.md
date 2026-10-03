# Generico Two Filter

Generico Two (G2) is a Moodle filter that allows site administrators to define custom templates consisting of HTML, JavaScript, and CSS. These templates can be embedded into Moodle content using simple text tags. It is a ground-up rewrite of the original Generico filter: templates are stored in the database and rendered with Mustache, and the plugin ships with a library of ready-made presets, an admin CRUD UI with live preview, an optional AI helper for authoring, and a one-click migration tool for sites moving from the original Generico filter.

## Requirements

*   Moodle 4.5 or later (compatible with standard Moodle releases).
*   PHP 8.0 or later.

## Migrating from filter_generico

If the original `filter_generico` plugin is also installed and has templates configured, you can bring them into Generico Two without retyping anything:

1.  Go to `Site Administration > Plugins > Filters > Generico Two filter > Migrate legacy`.
2.  You'll see a table of every Generico template that hasn't already been migrated (templates whose key already exists in Generico Two are left out automatically, so the page is always safe to revisit).
3.  Tick the templates you want to bring across (or use "Select all"), then click "Migrate selected templates".

For each template, migration works one of two ways:

*   **If a bundled Generico Two preset shares the same template key** (shown with a "Generico Two preset available" note), the preset is used instead of your old config. This gets you the cleaned-up, Mustache-native version of that template rather than a raw copy.
*   **Otherwise**, your old template is copied across field-for-field, and along the way: legacy `@@variable@@` placeholders are converted to `{{variable}}` Mustache syntax, and old-style JS string concatenation like `'abc' + {{AUTOID}}` is rewritten to the Generico Two form (`'abc{{AUTOID}}'`). Any `Require JS` library your old template referenced is folded into the new template's JS wrapper.

Migration only copies data — it doesn't touch or disable `filter_generico`. Once you've checked the migrated templates render correctly, disable (or uninstall) the old Generico filter yourself via `Site Administration > Plugins > Filters > Manage filters`.

## Usage

1.  **Define Templates:** Go to `Site Administration > Plugins > Filters > Generico Two filter > Templates`.
2.  **Add a Template:** Click "Add template" (or start from a bundled preset — see below).
    *   **Template Key:** A unique identifier for your template (e.g., `youtube`).
    *   **Content:** The HTML structure of your template. Use mustache-style variables like `{{videoid}}`.
    *   **CSS Styles:** Use the `Custom CSS` or `Import CSS URL` fields to add styling.
    *   **JS Content:** Add JavaScript to control the template's behavior.
    *   **Variable Defaults:** Define default values for your variables, e.g., `width=500,height=300`.
3.  **Embed in Content:** Use the G2 tag in any Moodle text area (forum post, page, label, etc.):
    `{G2:type=templatekey,param1=value1,param2=value2}`

    Example: `{G2:type=youtube,videoid=AbCdEfGh}`

4.  **Wrapper templates (start/end pairs):** Some templates (tabs, accordions, and similar) are designed to wrap other content. Add a second tag with `_end` appended to the type to close the wrapper, e.g. `{G2:type=tabs}` ... your page content ... `{G2:type=tabs_end}`.
5.  **Show a tag literally:** Add `passthrough=1` to a tag (e.g. `{G2:type=youtube,videoid=abc,passthrough=1}`) to print the tag as-is instead of rendering it — handy for documentation or example content.
6.  **Restrict who sees a tag:** Add `viewcapability=some/capability:name` to only render for users who have that capability, or `hidecapability=some/capability:name` to hide it from users who do.

## Presets

Generico Two ships with a library of ready-made templates covering common needs — YouTube/Vimeo embeds, tabs, accordions, lightboxes, QR codes, audio players, and more. When adding a new template, use the preset dropdown (or drag a preset onto the "Bundle" drop area) to pre-fill the form with a working starting point, which you can then customise freely. A handful of the most commonly used presets (e.g. `welcomeuser`, `tabs`, `accordion`) are also installed automatically the first time the plugin is set up.

If a newer version of a preset you're using becomes available (e.g. after a plugin update), the Templates list will indicate an update is available for that template.

## AI Helper

The template editor includes an optional AI helper button that can generate or modify HTML/JS/CSS content for you from a plain-English instruction, using whichever AI provider is configured for your site. It's enabled by default and can be turned off via `Site Administration > Plugins > Filters > Generico Two filter > Enable AI helper`. It's only available to users who can already manage templates.

## Live Preview

While editing a template, use the "Test 1" / "Test 2" fields to save sample G2 tag strings, then use the Preview panel to see the rendered output update live as you edit the template's content, JS, and CSS — without needing to save the template or find a page to embed the tag in first.

## Variables and Properties

G2 supports dynamic variable substitution:

*   **Template Parameters:** Passed in the tag, e.g., `videoid` in `{G2:type=youtube,videoid=123}`.
*   **User Properties:** `{{USER:firstname}}`, `{{USER:email}}`, `{{USER:picurl}}`, etc.
*   **Course Properties:** `{{COURSE:fullname}}`, `{{COURSE:shortname}}`, `{{COURSE:id}}`.
*   **URL Parameters:** `{{URLPARAM:id}}` (fetches `id` from the page URL).
*   **Datasets:** Fetch rows from the database using an SQL query defined in the template settings; loop over the results in your template with `{{#DATASET}}...{{/DATASET}}`.

## Restricting where a template can be used

Each template can optionally be limited to specific Moodle contexts using the `Allowed contexts` field (e.g. `course,mod_page`) and/or the `Allowed context IDs` field (specific numeric context ids). If a tag using that template appears somewhere outside the allowed contexts, it won't render.

## Legacy {GENERICO:...} tags

If you still have content containing old-style `{GENERICO:type="xx"}` tags (rather than `{G2:...}`), you can have Generico Two process those too — enable `Site Administration > Plugins > Filters > Generico Two filter > Handle legacy tags`. When on, legacy tags are matched against your Generico Two templates the same way `{G2:...}` tags are, so you don't need to have every author update existing content after migrating.

## HTML Editor (tiny) plugin

There is a companion plugin for Moodle's TinyMCE HTML editor that supports both the original Generico and Generico Two tags. This makes it much easier to insert filter strings into an HTML area. You can see and get that at: [tiny_generico](https://marketplace.moodle.com/plugins/2858)

## Installation

1.  Download the `filter_genericotwo` plugin.
2.  Upload the folder `genericotwo` to your Moodle installation's `filter/` directory.
3.  Visit `Site Administration > Notifications` to trigger the installation.
4.  Enable the filter in `Site Administration > Plugins > Filters > Manage filters`.
5.  If you're moving from the original Generico filter, see "Migrating from filter_generico" above.

## Maintainer and origin

This 108design downstream version is maintained by Andreas Giesen
<andreas@108design.com>. Upstream: [Generico Two by Justin Hunt](https://github.com/justinhunt/moodle-filter_genericotwo).
Upstream authorship and copyright notices remain intact. The 108design release
adds plugin navigation and editor improvements and remains under GPL v3 or later.

## License

GNU General Public License version 3 or later. See [LICENSE.txt](LICENSE.txt) for the full terms.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
