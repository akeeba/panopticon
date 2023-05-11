<?php
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

@if (empty($extensions))
	<span class="badge bg-secondary-subtle">Unknown</span>
@else
	@if ($numUpdates)
		<div class="badge bg-warning"
			 data-bs-toggle="tooltip" data-bs-placement="bottom"
			 data-bs-title="There are %d extension updates"
		>
			<span class="fa fa-arrow-up-right-dots" aria-hidden="true"></span>
			<span class="visually-hidden">Updates found:</span>
			{{ $numUpdates }}
		</div>
	@elseif ($numKeyMissing === 0)
		<div class="text-body">
			<span class="fa fa-check-circle" aria-hidden="true"
				  data-bs-toggle="tooltip" data-bs-placement="bottom"
				  data-bs-title="All installed extensions are up-to-date"
			></span>
			<span class="visually-hidden">All installed extensions are up-to-date</span>
		</div>
	@endif

	@if ($numKeyMissing)
		<div class="badge bg-danger"
			 data-bs-toggle="tooltip" data-bs-placement="bottom"
			 data-bs-title="%d Download Keys are missing or invalid"
		>
			<span class="fa fa-key" aria-hidden="true"></span>
			{{ $numKeyMissing }}
		</div>
	@endif
@endif


