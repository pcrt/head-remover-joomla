<?php
/**
 * Isolated Docker test helper; never installed in the distributed plugin.
 * Phases: version <expected>, fixture, params <frontend|backend|disabled>, check-installed <enabled>.
 *
 * @copyright (C) 2026 Protocolli Creativi
 * @license GPL-2.0-or-later
 */

const _JEXEC = 1;
define('JPATH_BASE', '/var/www/html');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

$db    = Factory::getContainer()->get(DatabaseInterface::class);
$phase = $argv[1] ?? '';

if ($phase === 'version') {
    $expected = $argv[2] ?? '';
    $version  = (new \Joomla\CMS\Version())->getShortVersion();

    if (!str_starts_with($version, $expected . '.')) {
        throw new RuntimeException('Expected Joomla ' . $expected . ', got ' . $version);
    }

    echo 'Joomla ' . $version . ' on PHP ' . PHP_VERSION . "\n";
} elseif ($phase === 'fixture') {
    $template = JPATH_BASE . '/templates/cassiopeia/index.php';
    $source   = file_get_contents($template);

    if (!str_contains($source, 'head-fixture.php')) {
        $source = preg_replace('/\?>/', "require '/tests/head-fixture.php';\n?>", $source, 1, $count);

        if ($count !== 1) {
            throw new RuntimeException('Cannot insert template fixture');
        }

        file_put_contents($template, $source);
    }

    foreach (['added-first.js', 'added-second.js', 'added-first.css', 'added-second.css',
        'remove-legacy.js', 'remove-legacy.css', 'keep-legacy.js', 'base.js', 'dependant.js',
        'remove-wam.js', 'remove-wam.css', 'keep-wam.css'] as $file) {
        file_put_contents(JPATH_BASE . '/media/headremover-' . $file, '/* test fixture */');
    }

    echo "Fixture installed\n";
} elseif ($phase === 'params') {
    $mode   = $argv[2] ?? 'frontend';
    $params = [
        'frontend_only'    => $mode === 'backend' ? 0 : 1,
        'exclude_backend'  => 1,
        'core_assets'      => "hr.fixture.base\nbootstrap.*",
        'remove_urls'      => [
            ['type' => 'both', 'url' => 'headremover-remove'],
            ['type' => 'both', 'url' => ''],
        ],
        'scripts'          => [
            ['url' => '/media/headremover-added-first.js', 'mode' => 'defer'],
            ['url' => 'media/headremover-added-second.js', 'mode' => 'module'],
            ['url' => 'javascript:alert(1)', 'mode' => ''],
        ],
        'styles'           => [
            ['url' => '/media/headremover-added-first.css', 'media' => 'screen'],
            ['url' => 'media/headremover-added-second.css', 'media' => 'print'],
        ],
        'remove_generator' => 1,
        'remove_author'    => 1,
        'metas'            => [
            ['kind' => 'name', 'name' => 'headremover-test', 'content' => 'test-ok'],
            ['kind' => 'property', 'name' => 'og:headremover', 'content' => 'property-ok'],
            ['kind' => 'http-equiv', 'name' => 'x-headremover', 'content' => 'equiv-ok'],
            ['kind' => 'name', 'name' => 'hr-escape', 'content' => '"><script>alert(1)</script>'],
        ],
        'motd'             => 'HEADREMOVER-MOTD --> <script>bad</script>',
    ];
    $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . '=' . ($mode === 'disabled' ? 0 : 1))
        ->where($db->quoteName('type') . "='plugin'")
        ->where($db->quoteName('folder') . "='system'")
        ->where($db->quoteName('element') . "='headremover'");

    if ($mode !== 'disabled') {
        $query->set($db->quoteName('params') . '=' . $db->quote(json_encode($params)));
    }

    $db->setQuery($query)->execute();
    echo 'Plugin ' . ($mode === 'disabled' ? 'disabled' : 'enabled with ' . $mode . ' parameters') . "\n";
} elseif ($phase === 'check-installed') {
    $expectedEnabled = (int) ($argv[2] ?? 0);
    $rows = $db->setQuery($db->getQuery(true)->select('extension_id, name, enabled, MD5(params) AS params_hash')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('type') . "='plugin'")
        ->where($db->quoteName('folder') . "='system'")
        ->where($db->quoteName('element') . "='headremover'"))->loadAssocList();

    if (count($rows) !== 1 || $rows[0]['name'] !== 'PLG_SYSTEM_HEADREMOVER' || (int) $rows[0]['enabled'] !== $expectedEnabled) {
        throw new RuntimeException('Unexpected extension rows: ' . json_encode($rows));
    }

    $sites = $db->setQuery($db->getQuery(true)->select('location')->from($db->quoteName('#__update_sites'))
        ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%head-remover-joomla%')))->loadColumn();

    if ($sites !== ['https://raw.githubusercontent.com/pcrt/head-remover-joomla/main/updateserver/extension.xml']) {
        throw new RuntimeException('Unexpected update sites: ' . json_encode($sites));
    }

    foreach (['headremover.xml', 'services/provider.php', 'src/Extension/HeadRemover.php', 'forms/metas.xml', 'LICENSE.txt'] as $file) {
        if (!is_file(JPATH_PLUGINS . '/system/headremover/' . $file)) {
            throw new RuntimeException('Missing installed file ' . $file);
        }
    }

    foreach (['plg_system_headremover.ini', 'plg_system_headremover.sys.ini'] as $file) {
        if (!is_file(JPATH_ADMINISTRATOR . '/language/en-GB/' . $file)) {
            throw new RuntimeException('Missing language file ' . $file);
        }
    }

    echo 'Installed: extension ' . $rows[0]['extension_id'] . ', enabled=' . $rows[0]['enabled'] . ', params=' . $rows[0]['params_hash'] . "\n";
} else {
    throw new RuntimeException('Unknown phase');
}
