<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Awf\Uri\Uri;

/**
 * @var \Akeeba\Panopticon\View\Captive\Html $this
 * @var \Akeeba\Panopticon\Model\Captive     $model
 */

$shownMethods = [];

?>
<p>
    @lang('PANOPTICON_MFA_LBL_SELECT_INSTRUCTIONS')
</p>

<div class="d-flex flex-column gap-3 mb-3">
    @foreach ($this->records as $record)
			<?php
			if (
				!array_key_exists($record->method, $this->mfaMethods)
				&& ($record->method != 'backupcodes')
			) continue;

			$allowEntryBatching = isset($this->mfaMethods[$record->method])
				? $this->mfaMethods[$record->method]['allowEntryBatching'] : false;

			if ($this->allowEntryBatching)
			{
				if ($allowEntryBatching && in_array($record->method, $shownMethods)) continue;
				$shownMethods[] = $record->method;
			}

			$methodName = $this->getModel()->translateMethodName($record->method);
			$method = $this->mfaMethods[$record->method];
			?>
        <a href="@route('index.php?view=captive&record_id=' . $record->id)"
           class="d-flex flex-row gap-2 align-items-center link-underline link-offset-2 link-underline-opacity-0 link-underline-opacity-75-hover"
        >
            <img src="{{ Uri::root() . ($method->image ?: 'media/mfa/images/emergency.svg') }}"
                 alt="{{{ strip_tags($record->title) }}}"
				 class="img-fluid bg-light p-2 rounded-2" style="min-width: 3em; max-width: 7em" />

			<div class="flex-grow-1 d-flex flex-column">
				@if (!$this->allowEntryBatching || !$allowEntryBatching)
					@if ($record->method === 'backupcodes')
						<span class="fs-4 fw-bold text-body">
							{{ $methodName }}
						</span>
						<span class="text-muted small">
							@lang('PANOPTICON_MFA_LBL_BACKUPCODES_USE_WHEN_LOCKED_OUT')
						</span>
					@else
						<span class="fs-4 fw-bold text-body">
							{{ $record->title }}
						</span>
						<span class="text-muted small">
							{{ $methodName }}
						</span>
					@endif
				@else
					<span class="fs-4 fw-bold text-body">
				    	{{ $methodName }}
					</span>
				@endif
			</div>

        </a>
    @endforeach
</div>