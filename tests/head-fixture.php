<?php
/**
 * Runs in the isolated Cassiopeia template before head compilation.
 * @copyright (C) 2026 Protocolli Creativi
 * @license GPL-2.0-or-later
 */
\defined('_JEXEC') or die;

$this->setMetaData('author', 'Head Remover fixture author');
$this->addScript('/media/headremover-remove-legacy.js');
$this->addStyleSheet('/media/headremover-remove-legacy.css');
$this->addScript('/media/headremover-keep-legacy.js');
$wa = $this->getWebAssetManager();
$wa->registerAndUseScript('hr.fixture.base', '/media/headremover-base.js');
$wa->registerAndUseScript('hr.fixture.dependant', '/media/headremover-dependant.js', [], [], ['hr.fixture.base']);
$wa->registerAndUseScript('hr.fixture.url', '/media/headremover-remove-wam.js');
$wa->registerAndUseStyle('hr.fixture.style', '/media/headremover-remove-wam.css');
$wa->registerAndUseStyle('hr.fixture.keep', '/media/headremover-keep-wam.css');
