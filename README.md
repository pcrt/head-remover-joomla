# Head Remover

System plugin by Protocolli Creativi to control the assets and the metadata of the HTML head in Joomla 5 and 6. Licence **GPL-2.0-or-later**.

_Italiano: [README.it.md](README.it.md)_

## Requirements

The plugin code requires PHP 8.1 or later; the stricter requirements of the installed Joomla version still apply (Joomla 6 requires PHP 8.3 or later). It does not need jQuery or Bootstrap and does not use the Joomla 3 global APIs.

**Verification status as of 7 November 2026:** package built, static checks, `php -l` with PHP 8.1 and 8.4, functional tests in Docker on Joomla 5.4.10 (PHP 8.3) and Joomla 6.2.0 (PHP 8.4) with MariaDB 11, JED Checker 2.4.4 with no errors or warnings. Details and output in `TEST_REPORT.md`.

## Installation

1. Build with `./build.sh` (Python 3), or download `plg_system_headremover-2.0.0.zip` from the assets of the GitHub release.
2. In Joomla: System → Install → Extensions, upload the ZIP.
3. In System → Manage → Plugins, open **System - Head Remover** (in Italian **Sistema - Head Remover**), configure and enable it. The name follows the JED naming convention for plugins, `{Type} - {Name}`; the JED listing is simply called "Head Remover".
4. Order the plugin after the extensions that add assets; clear the cache and check the affected pages.

The plugin is a new installation, element `headremover`, group `system`. The update server points to the `updateserver/extension.xml` file on the `main` branch of this repository: it contains two equivalent updates for the 5 and 6 series, with the download from the GitHub release and the SHA-256 of the package, which Joomla verifies before installing. `build.sh` refreshes the SHA-256 at every build.

## Parameters

| Parameter | Behaviour |
| --- | --- |
| Frontend only | Enabled by default. Limits the action to the public site. |
| Always exclude the backend | Enabled by default. To act in the backend both restrictions must be disabled. CLI, API and non-HTML documents (feeds, JSON) stay excluded. |
| Asset names | One exact name per line: `jquery`, `core`, `keepalive`, `messages`. The `.*` suffix covers a family, for example `bootstrap.*`. Empty by default; acts on active scripts and styles. |
| URLs to remove | Repeatable rows with type script/style/both and a full URL or a fragment. Substring comparison, case-sensitive; empty rows are ignored. |
| Custom scripts | URL and mode: normal, defer, async or module. |
| Custom CSS | URL and media attribute (`all` by default). |
| Remove generator/author | Independent options, disabled by default. |
| Custom metadata | Ordered rows with `name`, `property` or `http-equiv` attribute, name and content. Values are HTML-encoded. |
| MOTD | Comment right after the opening `<head>`; delimiters are neutralised. |

**Disabling `core` can break modules, menus and other features.** The WebAssetManager would re-enable an asset required by a dependency: to make the removal effective the plugin also disables the active assets that require it, transitively (with `core`, for example, `keepalive`, `messages`, the script options and the Bootstrap components disappear). The same rule applies to removal by URL. Try it on a copy of the site before enabling removals.

Additions are registered in the WebAssetManager and each row depends on the previous one of the same type. The order of the tags is deterministic; `async` does not guarantee the execution order, and modules/defer follow the browser rules. There is no common execution order between CSS and JavaScript.

HTTP(S) URLs, protocol-relative URLs and local paths are accepted. Paths without a leading slash are relative to the Joomla root (also in a subfolder); paths with a leading slash are relative to the domain root. Local paths are emitted as absolute URLs of the current site. The plugin neither loads nor verifies the files on the server. Add existing paths and trusted sources; any CSP restrictions are still enforced by the browser.

Removal covers resources registered through the WebAssetManager or the public Document APIs, including those from templates and extensions. Tags written directly in the template, tags added with `addCustomTag`, later insertions by other plugins and resources loaded by the browser are not rewritten. It does not indiscriminately remove inline scripts.

Custom metadata is added in row order through the document's custom tags: Joomla emits them at the end of the head, after the scripts, where the template includes `<jdoc:include type="scripts" />`. They may repeat the same name (for example several `og:image`); identical rows are emitted once. For an `http-equiv` that must precede the scripts, such as `Content-Security-Policy`, use the server's HTTP header. To avoid duplicates with the template's metadata, coordinate them with the template.

Assets and metadata are processed in `onBeforeCompileHead`, with low priority. Only the placement of the already sanitised comment happens on the final HTML, in `onAfterRender` (before any gzip compression) and, for error pages such as the 404, in `onBeforeRespond`, because Joomla does not trigger `onAfterRender` after an exception. Keeping the charset meta at the beginning of the head is recommended; the comment introduces no executable content.

## Manual migration from 1.1.0

Version 1.1.0 uses the `head_remover` element: Joomla treats 2.0 as a different plugin. No parameter is imported automatically and no file of the old extension is modified.

1. Note down or export the parameters of the old plugin; back up the site.
2. Install 2.0 and leave it disabled at first.
3. Recreate the `additional_remover` rows in URLs to remove (JS → script, CSS → style).
4. Recreate the `additional_add` rows in Custom scripts/CSS, keeping the order; set the attributes if needed.
5. Copy `unsetgenerator` and `unsetauthor`; copy `additional_meta`: `name` → name, `value` → content, `httpeq` → http-equiv attribute.
6. Do not blindly copy the old Bootstrap 2, MooTools, caption, modal or HTML5 fallback switches: they refer to Joomla 3 files. Identify the assets actually present in Joomla 5/6 before choosing the removals. For jquery/core use the current WebAssetManager names.
7. Copy any MOTD configured externally (the PHP of the 1.1.0 examined does not emit it).
8. Disable the old version and enable the new one; check the HTML source, the browser console, modules and pages with interactive components. Do not keep both enabled.

## Build and tests

```sh
./build.sh
python3 tests/static_check.py
./tests/docker-test.sh
JEDCHECKER_ZIP=/path/to/com_jedchecker-2.4.4.zip ./tests/docker-test.sh
```

The package is reproducible: it contains only `src/`, with sorted names and fixed timestamps; the checksum is in `dist/*.sha256` and in `updateserver/extension.xml`.

`tests/docker-test.sh` requires a working Docker and network access to pull `php:8.1-cli`, `php:8.4-cli`, `joomla:5-php8.3-apache`, `joomla:6-php8.4-apache` and `mariadb:11`. It runs `php -l` with PHP 8.1 and 8.4, installs two temporary sites (5.4 and 6.2 series, verified version), injects into the Cassiopeia template a fixture with assets registered through the WebAssetManager and through Document, installs the plugin with `php cli/joomla.php extension:install`, enables it with test parameters and compares the HTML captured with curl: additions in order with attributes, removals by name, by family (`bootstrap.*`) and by URL including dependants, unselected assets preserved, generator/author removed, ordered metadata with escaping, sanitised MOTD right after `<head>` also on the 404 page, backend exclusion. In the backend it logs in, checks the plugin list and the edit form (tabs, translated labels, subforms) and saves the parameters through the real form, verifying their effect on the frontend. It then installs the same ZIP a second time (configuration and HTML unchanged), disables the plugin (HTML equal to the initial one) and checks that there are no PHP messages in the container logs and in `administrator/logs`. The HTML files and logs are kept in `tests/results/`. It removes only its own containers and network, also on error; no existing container is modified.

With `JEDCHECKER_ZIP` the script also installs JED Checker in each site and runs all its rules on the package through `tests/jedchecker-run.php`, failing on errors or warnings. The component package is obtained from the source archive of the release at https://github.com/joomla-extensions/jedchecker/releases (the release has no assets): `zip -r com_jedchecker-2.4.4.zip .` from the extracted folder. JED Checker 2.4.4 still uses `Joomla\CMS\Filesystem\*`, removed in Joomla 6: the component installs but its interface does not work in Joomla 6; the CLI runner works around it with an alias to `Joomla\Filesystem\*` and produces the same rule results as in Joomla 5.

## Technical references

Official APIs consulted: [WebAssetManager](https://api.joomla.org/cms-5/classes/Joomla-CMS-WebAsset-WebAssetManager.html), [HtmlDocument](https://api.joomla.org/cms-5/classes/Joomla-CMS-Document-HtmlDocument.html), [Joomla plugins source](https://github.com/joomla/joomla-cms/tree/6.2-dev/plugins/system).

## Licence

Copyright © 2026 Protocolli Creativi. Free software distributed under the GNU GPL version 2 or later (SPDX `GPL-2.0-or-later`); text of version 2 in [LICENSE](LICENSE). Not affiliated with the Joomla project; no project logos are included.
