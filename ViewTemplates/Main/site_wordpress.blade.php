<?php

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\SoftwareVersions\WordPressVersion;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;
use Awf\Uri\Uri;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$wpVersion       = $config->get('core.current.version');
$stability       = $config->get('core.current.stability');
$canUpgrade      = $config->get('core.canUpgrade');
$latestWPVersion = $config->get('core.latest.version');
$lastError       = trim($config->get('core.lastErrorMessage') ?? '');
$wpRunState      = $item->getWordPressUpdateRunState();
$wpUpdateFailure = !$config->get('core.extensionAvailable', true)
                   || !$config->get('core.updateSiteAvailable', true);
$token           = $this->container->session->getCsrfToken()->getValue();
$returnUrl       = base64_encode(Uri::getInstance()->toString());
$wpVersionHelper = new WordPressVersion($this->getContainer());

// TODO
?>