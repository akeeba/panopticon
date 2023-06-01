<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 */
$config = new \Awf\Registry\Registry($this->item?->config ?? '{}');

?>
<div class="row mb-3">
	<div class="col-sm-9 offset-sm-3">
		<div class="form-check form-switch">
			<input class="form-check-input" type="checkbox" id="enabled" name="enabled"
			       {{ $this->item->enabled ? 'checked' : '' }}
			>
			<label class="form-check-label" for="enabled">
				@lang('PANOPTICON_LBL_TABLE_HEAD_ENABLED')
			</label>
		</div>
	</div>
</div>

<div class="alert alert-info col-sm-9 offset-sm-3">
	<h3 class="alert-heading fs-5 fw-semibold">
		<span class="fa fa-info-circle" aria-hidden="true"></span>
		@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_HEAD')
	</h3>
	<p>
		@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_BODY')
	</p>
</div>

<div class="row mb-3">
	<label for="url" class="col-sm-3 col-form-label">
		@lang('PANOPTICON_SITES_FIELD_URL')
	</label>
	<div class="col-sm-9">
		<input type="text" class="form-control" name="url" id="url"
		       value="{{{ $this->item->url ?? '' }}}" required
		>
	</div>
</div>

<div class="row mb-3">
	<label for="apiToken" class="col-sm-3 col-form-label">
		@lang('PANOPTICON_SITES_FIELD_TOKEN')
	</label>
	<div class="col-sm-9">
		<input type="text" class="form-control font-monospace" name="apiToken" id="url"
		       value="{{{ $config->get('config.apiKey') ?? '' }}}" required
		>
	</div>
</div>