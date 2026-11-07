<?php
/**
 * Runs the rules of an installed JED Checker component against a package, from the CLI.
 * Usage: HR_ZIP=/path/to/package.zip php /tests/jedchecker-run.php
 * Exit code 1 when JED Checker reports errors or warnings. Never installed in the plugin.
 *
 * @copyright (C) 2026 Protocolli Creativi
 * @license GPL-2.0-or-later
 */

// JED Checker 2.4.4 still uses Joomla\CMS\Filesystem\*, removed in Joomla 6: alias the framework classes.
spl_autoload_register(static function (string $class): void {
    $map = [
        'Joomla\CMS\Filesystem\Folder' => 'Joomla\Filesystem\Folder',
        'Joomla\CMS\Filesystem\File'   => 'Joomla\Filesystem\File',
        'Joomla\CMS\Filesystem\Path'   => 'Joomla\Filesystem\Path',
    ];

    if (isset($map[$class])) {
        class_alias($map[$class], $class);
    }
}, true, true);

const _JEXEC = 1;
define('JPATH_BASE', '/var/www/html');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_jedchecker');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
Factory::$application = $container->get(\Joomla\Console\Application::class);
Factory::getLanguage()->load('com_jedchecker', JPATH_ADMINISTRATOR, 'en-GB', true);

require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/report.php';
JLoader::discover('jedcheckerRules', JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');

$zip  = (string) getenv('HR_ZIP');
$dest = Factory::getApplication()->get('tmp_path') . '/jed_checker/unzipped/' . pathinfo($zip, PATHINFO_FILENAME) . '-' . uniqid();
mkdir($dest, 0755, true);
$archive = new ZipArchive();

if ($archive->open($zip) !== true) {
    throw new RuntimeException('Cannot open ' . $zip);
}

$archive->extractTo($dest);
$archive->close();

$manifest = simplexml_load_file(JPATH_COMPONENT_ADMINISTRATOR . '/jedchecker.xml');
echo 'JED Checker ' . $manifest->version . ' on Joomla ' . (new \Joomla\CMS\Version())->getShortVersion() . ' (PHP ' . PHP_VERSION . ")\n";
echo 'Package: ' . basename($zip) . "\n";
$totals = [];

foreach (glob(JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/*.php') as $file) {
    $rule  = basename($file, '.php');
    $class = 'jedcheckerRules' . ucfirst($rule);

    if (!class_exists($class)) {
        echo "SKIP $rule (class not found)\n";
        continue;
    }

    $police = new $class(['basedir' => Path::clean($dest)]);
    $police->check();
    $report = $police->get('report');
    $html   = $report->getHTML();

    foreach ((array) $report->get('issueLangTitles') as $level => $key) {
        $totals[$level] = ($totals[$level] ?? 0) + preg_match_all('/data-level="' . preg_quote(Text::_($key), '/') . '(?::|")/', $html);
    }

    $text = preg_replace('#<(?:/div|/p|/li|br\s*/?)>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace(["/[ \t]+/", "/\n[ \t]*/", "/\n{2,}/"], [' ', "\n", "\n"], trim($text));
    echo '=== ' . $rule . ' [' . $police->get('id') . '] ' . Text::_($police->get('title')) . "\n" . $text . "\n";
}

Folder::delete($dest);
echo 'TOTALS: ' . json_encode($totals) . "\n";
exit ((($totals['error'] ?? 0) + ($totals['warning'] ?? 0)) > 0 ? 1 : 0);
