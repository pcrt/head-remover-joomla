# Verification of 7 November 2026

Runtime verification run with Docker 27.4.0 (linux/arm64) on the package `dist/plg_system_headremover-2.0.0.zip`, SHA-256 `79eef10463e17e8f95c498132ac1b4a3f5a1cc5639ec8052423d14f449b8ebef`. Command: `JEDCHECKER_ZIP=/path/to/com_jedchecker-2.4.4.zip ./tests/docker-test.sh` (result `ALL PASS`, exit code 0); the captured HTML files and logs are kept in `tests/results/`.

## Environment

| Component | Image / version |
| --- | --- |
| Joomla 5 | `joomla:5-php8.3-apache` → Joomla 5.4.10, PHP 8.3.36, Apache 2.4.69 |
| Joomla 6 | `joomla:6-php8.4-apache` → Joomla 6.2.0, PHP 8.4.27, Apache 2.4.69 |
| Database | `mariadb:11`, one database per site (`joomla5`, `joomla6`) |
| Lint | `php:8.1-cli` (PHP 8.1.34) and `php:8.4-cli` (PHP 8.4.27) |
| JED Checker | 2.4.4, source archive of the GitHub release (the release has no assets), packaged with `zip -r` |

## Result per check

| Check | Joomla 5.4.10 | Joomla 6.2.0 |
| --- | --- | --- |
| `php -l` on `src/` and `tests/` with PHP 8.1 and 8.4 | no errors | no errors |
| Static check `tests/static_check.py` (manifest, languages, subforms, forbidden APIs, licence, update server with SHA-256, reproducible build) | PASS | PASS |
| CLI installation `php cli/joomla.php extension:install --path=…zip` | `Extension installed successfully`, plugin disabled, update site registered | same |
| Plugin installed but disabled: home HTML identical to the previous one (CSRF token and media version masked) | identical | identical |
| Plugin enabled: removal by name (`hr.fixture.base`) and of its dependants, `bootstrap.*` family (`collapse.min.js`, `bootstrap.min.css`), by URL through WebAssetManager and through Document; unselected assets preserved (`core.min.js`, script options, `keep-*`) | PASS | PASS |
| Scripts and CSS added in the given order with `defer`/`module` and `media`; `javascript:` rejected | PASS | PASS |
| `generator` and `author` meta removed; custom `name`/`property`/`http-equiv` meta in order with escaping | PASS | PASS |
| Sanitised MOTD (`-->` and `<script>` neutralised) right after `<head>`, also on the 404 page | PASS | PASS |
| Backend excluded with `frontend_only=0` and `exclude_backend=1` (administrator login page unchanged) | PASS | PASS |
| Backend: login, plugin list with the name "System - Head Remover", edit form without untranslated keys and with 4 subforms, save through the real form applied on the frontend | PASS | PASS |
| Second installation of the same zip: `#__extensions` record unchanged (enabled flag and parameter hash), a single update site, identical HTML | PASS | PASS |
| Plugin disabled after the tests: HTML identical to the initial one | PASS | PASS |
| No PHP message in the container log (Apache/PHP) and no file in `administrator/logs` with `error_reporting = 'maximum'` | none | none |
| JED Checker 2.4.4, all 12 rules on the package | 0 errors, 0 warnings, 0 notices | 0 errors, 0 warnings, 0 notices (see note) |

Additional manual tests, with the same containers, not part of the pipeline: removal of `core` (`keepalive`, `messages`, `passwordview`, `bootstrap.collapse`, the script options block and the template/menu scripts that depend on it disappear too, without errors); parameters in the object format saved by the form (`{"scripts":{"scripts0":{…}}}`); backend processed with both safeguards disabled; RSS feed (`?format=feed`) untouched; gzip/deflate compression enabled in the global configuration: MOTD present on the home and on the 404, no errors.

## Notes

- JED Checker 2.4.4 is a Joomla 4/5 component and uses `Joomla\CMS\Filesystem\Folder`, removed in Joomla 6: it installs in Joomla 6, but its interface fails. `tests/jedchecker-run.php` runs the same rules from the CLI with an alias to `Joomla\Filesystem\*`; the results in Joomla 5 and 6 coincide.
- The first run of the checker reported the warning "The name of the plugin ('Head Remover') must comply with the JED naming conventions in the form '{Type} - {Extension Name}'": the translated name is now "System - Head Remover" (Italian "Sistema - Head Remover"). The JED listing stays "Head Remover".
- The JAMSS rule flagged the hexadecimal sequence `"\x1f\x8b"` used to detect an already compressed body: replaced by a check of the `Content-Encoding` header.
- The 404 page does not receive `onAfterRender` (the body is set by the ExceptionHandler): the MOTD is placed in `onBeforeRespond` as a fallback, after checking that the response is not already compressed.
- `InstallerHelper::unpack()` extracts the archive next to the file: in the pipeline the zip is copied to a writable folder of the container before `extension:install`. The official image enables opcache with `revalidate_freq=2`: the template fixture is applied before the first HTTP request.
- The 1.1.0 package (`head_remover`) was not modified.

## Repository and release

Repository `pcrt/head-remover-joomla` on GitHub, branch `main`, tag `v2.0.0`. The release of the same name carries `plg_system_headremover-2.0.0.zip` with the SHA-256 above, which `updateserver/extension.xml` references.
