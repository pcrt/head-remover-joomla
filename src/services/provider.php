<?php
/**
 * @package     HeadRemover
 * @copyright   (C) 2026 Protocolli Creativi
 * @license     GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Pcrt\Plugin\System\HeadRemover\Extension\HeadRemover;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): PluginInterface {
            $plugin = new HeadRemover(
                $container->get(DispatcherInterface::class),
                (array) PluginHelper::getPlugin('system', 'headremover')
            );
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
