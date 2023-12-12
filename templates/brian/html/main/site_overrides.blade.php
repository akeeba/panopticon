<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\JoomlaVersion\JoomlaVersion;
use Akeeba\Panopticon\Library\Version\Version;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$jVersion            = $config->get('core.current.version');
$stability           = $config->get('core.current.stability');
$canUpgrade          = $config->get('core.canUpgrade');
$latestJoomlaVersion = $config->get('core.latest.version');
$lastError           = trim($config->get('core.lastErrorMessage') ?? '');
$jUpdateFailure      = !$config->get('core.extensionAvailable') || !$config->get('core.updateSiteAvailable');
$token               = $this->container->session->getCsrfToken()->getValue();
$returnUrl           = base64_encode(\Awf\Uri\Uri::getInstance()->toString());
$jVersionHelper      = new JoomlaVersion($this->getContainer())
?>

@if (($overridesChanged = $config->get('core.overridesChanged')) > 0)
<div class="d-flex flex-row gap-2">
    <div class="text-warning fw-bold" data-bs-toggle="tooltip" data-bs-placement="bottom"
        data-bs-title="@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N', $overridesChanged)">

        <span class="fa fa-arrows-to-eye" aria-hidden="true"></span>
        <span aria-hidden="true">{{ $overridesChanged ?? 0 }}</span>
        <span class="visually-hidden">@sprintf('PANOPTICON_SITE_LBL_TEMPLATE_OVERRIDES_CHANGED_N',
            $overridesChanged)</span>

    </div>
</div>
@endif