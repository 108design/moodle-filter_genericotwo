# Project Context: Moodle Filter Plugin (filter_genericotwo)

## System Overview
"Generico Two" (G2) is a Moodle **text filter** plugin — a ground-up rewrite of the older `filter_generico` plugin. It filters content on Moodle pages: site admins register templates (HTML/Mustache + JS + CSS + optional SQL dataset) in the plugin, and when a tag like `{G2:type=youtube,videoid=AbCdEfGh}` appears in any Moodle text area, the filter replaces it at display time with content generated from the matching template.

Key changes in genericotwo versus the original generico:
- **Templates live in a DB table** (`filter_genericotwo_templates`), not in Moodle plugin settings/config as generico stored them.
- **Content is rendered with Mustache templates** — template bodies are Mustache strings rendered through Moodle's Mustache engine (see the renderer's string-loader swap), replacing generico's home-grown `@@variable@@` string substitution.
- New supporting features: an admin CRUD UI with live preview, presets, an AI helper for authoring, optional handling of legacy `{GENERICO:...}` tags, and a migration tool that imports templates from old generico's config.

## Environment & Tech Stack
- **Moodle target:** requires Moodle 4.5+ (`$plugin->requires = 2024100700`), developed against 5.1. PHP 8.0+.
- Read Moodle core APIs directly from the local Moodle checkout instead of guessing — never guess a core API signature. (See `CLAUDE.local.md`, gitignored, for this machine's checkout path and dev-environment commands.)
- **Frontend:** Moodle AMD modules (`amd/src/` → built to `amd/build/`), Mustache templates (`templates/`), plus *user-authored* Mustache/JS/CSS stored in the DB and rendered at runtime.
- **Related project:** `mod_minilesson` — same author/team, same conventions and build/server workflow; it has its own detailed CLAUDE.md.

## Architecture — how the filter works

### Key files
- `classes/text_filter.php` — the filter itself (`\filter_genericotwo\text_filter` extends `\core_filters\text_filter`, Moodle 4.5+ filter API). `filter.php` is just a `class_alias` shim for the old class name.
  - `filter()` finds `{G2:...}` tags (and `{GENERICO:...}` if the `handlelegacytags` config is on). The regex intentionally uses `[^}]*` so a match can never span two tags, and a tag alone inside a `<p>` consumes the wrapper `<p></p>` (so block-level output doesn't get injected inside a paragraph) — the bare tag is always in `$matches[1]` via a branch-reset group.
  - `filter_genericotwo_callback()` does the real work: parses tag props (`utils::fetch_filter_properties()`), adds `AUTOID`/`uniqid`, handles `passthrough=1` (show the tag literally), `viewcapability`/`hidecapability` checks, looks up the template by `templatekey`, resolves variables, runs the optional SQL dataset, then renders via the renderer.
  - **Start/end tag pairs:** a tag type ending in `_end` (e.g. `{G2:type=tabs_end}`) renders the template's `templateend` field with no JS/dataset — this is how wrapper templates (tabs, accordion) enclose other content.
  - `preview_filter()` / `preview_callback()` mirror the main path but take an unsaved template object from form data (used by the Fragment API preview) and skip DB lookup and capability checks.
- `classes/output/renderer.php` — `do_render()` swaps the Mustache engine's loader for a `Mustache_Loader_StringLoader` to render the *DB-stored* template string (and JS string) with the filter props as context, then restores the loader. JS output is wrapped via `templates/jsloader.mustache` and kicked off by `amd/src/loader.js` (a `window.filter_genericotwo` ready/queue mechanism, since filtered content can land after page JS init). Late CSS (when `$PAGE` head is already printed) is passed to `loader.js` for injection; otherwise CSS goes through `$PAGE->requires->css()`.
- `classes/utils.php` — `fetch_filter_properties()` parses the `{G2:key=value,...}` tag string into a props array.
- `css.php` — serves a template's custom CSS (`/filter/genericotwo/css.php?id=N&rev=timemodified` for cache busting).
- `lib.php` — only `filter_genericotwo_output_fragment_preview()`, the Fragment API callback for the template-form live preview (renders the `test1`/`test2` test strings against unsaved form data; inlines custom/imported CSS since the template may have no id yet).
- `templates.php` — admin CRUD page for templates (list/edit/delete, uses `classes/form/template_form.php`). `migrate.php` — admin tool to import templates from old `filter_generico` config. Both are registered as external admin pages in `settings.php` (note: `settings.php` deliberately sets `$settings = null` and builds its own admin category so the "Settings" link from Manage Filters lands correctly).
- `classes/presets.php` + `presets/*.txt` — bundled example templates. Each preset file is a **JSON object** (keys like `key`, `name`, `version`, `body`, `bodyend`, `script`, `defaults`, `style`...); `amd/src/presets.js` maps preset keys onto the form fields when the admin picks one. jlb224 (John) also contributes preset updates via PRs.
- `classes/external/fetch_aihelp.php` + `db/services.php` — AJAX web service `filter_genericotwo_fetch_aihelp` powering the AI helper in the template editor (prompt + current editor contents in, suggested code out; UI in `templates/aihelper_modal*.mustache`, toggled by the `enableaihelper` config).
- `amd/src/codemirror_init.js` — wires CodeMirror editors (via core `tiny_html/codemirror-lazy`) onto the template form textareas, plus the AI-helper UI. `amd/src/preview.js` — live preview via `core/fragment`.
- `amd/src/mediaparser.js`, `soundtouch.js`, `wavesurfer.js` — support/vendored libraries for media-player templates (wavesurfer and soundtouch are third-party code, `/* jshint ignore:start */`-style; don't reformat or "clean up" vendored files).

### Template variable resolution (order matters)
Filter props are built up by merging, later wins only if not already set:
1. Tag parameters (`{G2:type=x,foo=bar}` → `foo`), plus `AUTOID`/`uniqid`.
2. `{{URLPARAM:name}}` — from page URL via `optional_param(..., PARAM_TEXT)`.
3. `{{COURSE:field}}` — `$COURSE` fields, course custom fields, and `contextid`.
4. `{{USER:field}}` — `$USER` fields, profile fields, plus special `picurl`/`pic`.
5. `variabledefaults` (template-defined defaults, parsed with the same tag parser; `a|b|c` option lists collapse to the first option) — only fill gaps, never overwrite.
6. `DATASET` — if the template has a `dataset` SQL query, `datasetvars` (CSV, may reference `{{prop}}` placeholders) become the query's positional params; result rows land in `{{DATASET}}` for Mustache `{{#DATASET}}` loops. Variables are substituted only into `datasetvars`, never into the SQL body itself (so params go through `$DB` placeholder cleaning). Dataset errors are swallowed (returns `[]`).

The haystack scanned for `{{URLPARAM:/COURSE:/USER:}}` tokens is content + datasetvars + jscontent.

### Database
One table: `filter_genericotwo_templates` (see `db/install.xml`). Columns include `templatekey` (unique lookup key), `content`, `templateend`, `jscontent`, `customcss`, `importcss`, `variabledefaults`, `dataset`, `datasetvars`, `allowedcontexts`/`allowedcontextids` (CSV restriction lists, e.g. `course,mod_page` or context ids), `instructions`, `test1`/`test2` (saved preview strings), `version`, timestamps.

Plugin config (`get_config('filter_genericotwo')`): `enableaihelper`, `handlelegacytags`.

## Moodle conventions (shared with mod_minilesson)
- Use `\filter_genericotwo\constants::M_COMPONENT` instead of the literal component string where the class is already in scope.
- **DB API:** always `$DB` methods with placeholders; never interpolate values into SQL. (The template `dataset` feature is the one sanctioned raw-SQL spot — it's admin-authored and runs through `get_records_sql` with params.)
- **Strings:** all user-facing text via `get_string('key', 'filter_genericotwo')`, defined in `lang/en/filter_genericotwo.php`.
- **Security:** entry-point scripts (`templates.php`, `migrate.php`) require login + `moodle/site:config` + `require_sesskey()` on mutations; clean params with `optional_param()`/`required_param()` and strict PARAM types. Template authoring is site-admin-only by design — that's why storing raw JS/SQL in templates is acceptable.
- **Moodle coding style** (space indentation, no trailing whitespace, frankenstyle). Check with the project's codechecker command (see `CLAUDE.local.md`).
- **DB changes:** update `db/install.xml` *and* add a block in `db/upgrade.php`, then bump `$plugin->version` in `version.php` (read the current value first; format YYYYMMDDXX) and run the Moodle upgrade process (see `CLAUDE.local.md`).

## Build Commands
- **AMD build:** run grunt from the Moodle code root, scoped to this plugin (see `CLAUDE.local.md` for the exact command on this machine). Built files land in `amd/build/` (committed to git).
- **No SCSS pipeline here** (unlike minilesson): `styles.css` is hand-edited directly. Per-template CSS lives in the DB, not in files.
- After changing PHP that Moodle caches (lang strings, templates, JS), purge Moodle's caches (see `CLAUDE.local.md` for the command).

## Development Rules for Claude
- **Never guess Moodle core APIs.** If unsure about a core method signature (Form API, filter API, output/renderer API, etc.), read the relevant core file from the local Moodle checkout first (path in `CLAUDE.local.md`).
- **This is a Moodle text filter plugin** — follow standard Moodle coding conventions throughout (frankenstyle naming, `$DB` API with placeholders, `get_string()` for all user-facing text, capability checks + `optional_param`/`required_param` with strict PARAM types at entry points). See "Moodle conventions" above for specifics already established in this plugin.
- **Mirror the two callback paths.** A behaviour change in `filter_genericotwo_callback()` (`classes/text_filter.php`) almost always needs the same change in `preview_callback()`, or the admin preview will silently lie about what the live filter does.

## Gotchas
- The two callback paths (`filter_genericotwo_callback` and `preview_callback`) are near-duplicates — a behaviour change in one usually needs mirroring in the other, or the preview will lie.
- `AUTOID` must be set in both paths (a missing `AUTOID` in preview was a past bug, commit cbecd13).
- Filtered content can be rendered after the page head is done (AJAX, fragments, web services) — that's why CSS/JS have the late-injection fallback through `loader.js`; don't add `$PAGE->requires` calls that assume the head is open.
- Templates rendered via web services/mobile: `filter()`'s callback checks `$PAGE->url` against `/webservice/` — be careful touching `$PAGE` there, it may be unset (known harmless warning).
- Legacy `{GENERICO:...}` tags are only handled when the `handlelegacytags` config is on; keep the fast `strpos` pre-checks at the top of `filter()` — this filter runs on *every* piece of filtered text on the site, so the no-tag path must stay cheap.
- Preset `.txt` files are JSON — validate with `json_decode` semantics (parse failure makes the preset silently disappear from the picker).
