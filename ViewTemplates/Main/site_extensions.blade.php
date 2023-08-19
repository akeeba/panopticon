<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Main\Html;

/**
 * @var Html                  $this
 * @var Site                  $item
 * @var Awf\Registry\Registry $config
 */

$extensions    = get_object_vars($config->get('extensions.list', new stdClass()));
$numUpdates    = array_reduce(
	$extensions,
	function (int $carry, object $item): int {
		$current = $item?->version?->current;
		$new     = $item?->version?->new;

		if (empty($new))
		{
			return $carry;
		}

		return $carry + ((empty($current) || version_compare($current, $new, 'ge')) ? 0 : 1);
	},
	0
);
$numKeyMissing = array_reduce(
	$extensions,
	fn(int $carry, object $item): int => $carry + ((!$item?->downloadkey?->supported || $item?->downloadkey?->valid) ? 0 : 1),
	0
);
?>

<div class="d-flex flex-row gap-2">
	<div>
		<a type="button" class="btn btn-outline-secondary btn-sm" role="button"
		   href="@route(sprintf('index.php?view=site&task=refreshExtensionsInformation&id=%d&return=%s&%s=1', $item->id, base64_encode(\Awf\Uri\Uri::getInstance()->toString()), $this->container->session->getCsrfToken()->getValue()))"
		   data-bs-toggle="tooltip" data-bs-placement="bottom"
		   data-bs-title="@sprintf('PANOPTICON_SITE_BTN_EXTENSIONS_RELOAD', $item->name)"
		>
			<span class="fa fa-refresh" aria-hidden="true"></span>
			<span class="visually-hidden">@sprintf('PANOPTICON_SITE_BTN_EXTENSIONS_RELOAD', $item->name)</span>
		</a>
	</div>
	<div class="d-flex flex-column flex-md-row gap-2">
		@if (empty($extensions))
			<span class="badge bg-secondary-subtle">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_UNKNOWN')</span>
		@else
			@if ($item->isExtensionsUpdateTaskStuck())
				<div>
					<div class="badge bg-light text-dark"
						 data-bs-toggle="tooltip" data-bs-placement="bottom"
						 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_EXT_STUCK_UPDATE')"
					>
						<span class="fa fa-bell" aria-hidden="true"></span>
						<span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_STUCK_UPDATE')</span>
					</div>
				</div>
			@elseif ($item->isExtensionsUpdateTaskScheduled())
				<div>
					<div class="badge bg-info-subtle text-info"
						 data-bs-toggle="tooltip" data-bs-placement="bottom"
						 data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_EXT_SCHEDULED_UPDATE')"
					>
						<span class="fa fa-clock" aria-hidden="true"></span>
						<span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_SCHEDULED_UPDATE')</span>
					</div>
				</div>
			@endif
			@if ($numUpdates)
				<div class="text-warning fw-bold"
					 data-bs-toggle="tooltip" data-bs-placement="bottom"
					 data-bs-title="@plural('PANOPTICON_MAIN_SITES_LBL_EXT_UPGRADE_N', $numUpdates)"
				>
					<span class="fa fa-box-open" aria-hidden="true"></span>
					<span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_UPGRADES_FOUND')</span>
					{{ $numUpdates }}
				</div>
			@elseif ($numKeyMissing === 0)
				<div class="text-body">
					<span class="fa fa-check-circle" aria-hidden="true"
						  data-bs-toggle="tooltip" data-bs-placement="bottom"
						  data-bs-title="@lang('PANOPTICON_MAIN_SITES_LBL_EXT_NO_UPGRADES')"
					></span>
					<span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_NO_UPGRADES')</span>
				</div>
			@endif

			@if ($numKeyMissing)
				<div>
					<div class="badge bg-danger"
						 data-bs-toggle="tooltip" data-bs-placement="bottom"
						 data-bs-title="@plural('PANOPTICON_MAIN_SITES_LBL_EXT_KEYS_MISSING_N', $numKeyMissing)"
					>
						<span class="fa fa-key" aria-hidden="true"></span>
						<span class="visually-hidden">@lang('PANOPTICON_MAIN_SITES_LBL_EXT_KEYS_MISSING')</span>
						{{ $numKeyMissing }}
					</div>
				</div>
			@endif
		@endif
	</div>
</div>
