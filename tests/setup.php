<?php
/**
 * Isolated Docker test setup; never installed in the distributed plugin.
 * @copyright (C) 2026 Protocolli Creativi
 * @license GPL-2.0-or-later
 */

const _JEXEC = 1;
define('JPATH_BASE', '/var/www/html');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
Factory::$application = $container->get(\Joomla\Console\Application::class);
$db = $container->get(DatabaseInterface::class);
$phase = $argv[1] ?? '';

if ($phase === 'install') {
    $expected = $argv[2] ?? '';
    $version = (new \Joomla\CMS\Version())->getShortVersion();

    if (!str_starts_with($version, $expected . '.')) {
        throw new RuntimeException('Expected Joomla ' . $expected . ', got ' . $version);
    }

    $zip = new ZipArchive();

    if ($zip->open('/package/plg_system_headremover-2.0.0.zip') !== true) {
        throw new RuntimeException('Cannot open plugin package');
    }

    mkdir('/tmp/headremover-install');
    $zip->extractTo('/tmp/headremover-install');
    $zip->close();

    if (!(new Installer())->install('/tmp/headremover-install')) {
        throw new RuntimeException('Plugin installation failed');
    }

    $template = JPATH_BASE . '/templates/cassiopeia/index.php';
    $source = file_get_contents($template);
    $source = preg_replace('/\?>/', "require '/tests/head-fixture.php';\n?>", $source, 1, $count);

    if ($count !== 1) {
        throw new RuntimeException('Cannot insert template fixture');
    }

    file_put_contents($template, $source);
    // Use a new database for each Joomla instance; this fixture lives only there.
    foreach (['added-first.js', 'added-second.js', 'added-first.css', 'added-second.css',
        'remove-legacy.js', 'remove-legacy.css', 'keep-legacy.js', 'base.js', 'dependant.js',
        'remove-wam.js', 'remove-wam.css', 'keep-wam.css'] as $file) {
        file_put_contents(JPATH_BASE . '/media/headremover-' . $file, '/* test fixture */');
    }

    echo 'Installed on Joomla ' . $version . "\n";
} elseif ($phase === 'enable' || $phase === 'backend') {
    $params = [
        'frontend_only' => $phase === 'backend' ? 0 : 1,
        'exclude_backend' => 1,
        'core_assets' => "hr.fixture.base\nbootstrap.*",
        'remove_urls' => [
            ['type' => 'both', 'url' => 'headremover-remove'],
            ['type' => 'both', 'url' => ''],
        ],
        'scripts' => [
            ['url' => '/media/headremover-added-first.js', 'mode' => 'defer'],
            ['url' => '/media/headremover-added-second.js', 'mode' => 'module'],
            ['url' => 'javascript:alert(1)', 'mode' => ''],
        ],
        'styles' => [
            ['url' => '/media/headremover-added-first.css', 'media' => 'screen'],
            ['url' => '/media/headremover-added-second.css', 'media' => 'print'],
        ],
        'remove_generator' => 1,
        'remove_author' => 1,
        'metas' => [
            ['kind' => 'name', 'name' => 'headremover-test', 'content' => 'test-ok'],
            ['kind' => 'property', 'name' => 'og:headremover', 'content' => 'property-ok'],
            ['kind' => 'http-equiv', 'name' => 'x-headremover', 'content' => 'equiv-ok'],
            ['kind' => 'name', 'name' => 'hr-escape', 'content' => '"><script>alert(1)</script>'],
        ],
        'motd' => 'HEADREMOVER-MOTD --> <script>bad</script>',
    ];
    $db->setQuery($db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . '=1')
        ->set($db->quoteName('params') . '=' . $db->quote(json_encode($params)))
        ->where($db->quoteName('type') . "='plugin'")
        ->where($db->quoteName('folder') . "='system'")
        ->where($db->quoteName('element') . "='headremover'"))->execute();
    echo "Plugin enabled with test parameters\n";
} else {
    throw new RuntimeException('Unknown phase');
}
