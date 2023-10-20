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
<h4>@lang('PANOPTICON_SITES_LBL_CONNECTOR_HEAD')</h4>

<div class="alert alert-info col-sm-9 offset-sm-3">
	<h3 class="alert-heading fs-5 fw-semibold">
		<span class="fa fa-info-circle" aria-hidden="true"></span>
		@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_HEAD')
	</h3>
	<p>
		@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_BODY')
	</p>
	<p>
		@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_DOWNLOAD_HERE')
	</p>
	<ul>
		<li>
			<a href="https://github.com/akeeba/panopticon-connector/releases" target="_blank">
				@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_DOWNLOAD_J4_LATER')
			</a>
		</li>
		<li>
			<a href="https://github.com/akeeba/panopticon_connector_j3/releases" target="_blank">
				@lang('PANOPTICON_SITES_LBL_INSTRUCTIONS_DOWNLOAD_J3')
			</a>
		</li>
	</ul>
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
		<input type="text" class="form-control font-monospace" name="apiToken" id="apiToken"
		       value="{{{ $config->get('config.apiKey') ?? '' }}}" required
		>
	</div>
</div>

<h4>@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_HEAD')</h4>

<div class="alert alert-info col-sm-9 offset-sm-3">
	<p>
		@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_INFO_1')
	</p>
	<p>
		@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_INFO_2')
	</p>
	<details>
		<summary>
			@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_HEAD')
		</summary>
		<p>
			@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_INFO_1')
		</p>
		<ul>
			<li>@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_INFO_2')</li>
			<li>@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_INFO_3')</li>
			<li>@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_INFO_4')</li>
		</ul>
		<p>
			@lang('PANOPTICON_SITES_LBL_ADMIN_PASSWORD_NEEDED_INFO_5')
		</p>
	</details>
</div>

{{-- I have to use greeklish and plain text fields to prevent bloody password managers from screwing up those fields. GARGH! --}}

<div class="row mb-3">
	<label for="diaxeiristis_onoma" class="col-sm-3 col-form-label">
		@lang('PANOPTICON_SITES_FIELD_ADMIN_USERNAME')
	</label>
	<div class="col-sm-9">
		<input type="text" class="form-control" name="config[config.diaxeiristis_onoma]" id="diaxeiristis_onoma"
			   value="{{{ $config->get('config.diaxeiristis_onoma', '') }}}" required autocomplete="off"
		>
	</div>
</div>

<div class="row mb-3">
	<label for="diaxeiristis_sunthimatiko" class="col-sm-3 col-form-label">
		@lang('PANOPTICON_SITES_FIELD_ADMIN_PASSWORD')
	</label>
	<div class="col-sm-9">
		<input type="text" class="form-control" name="config[config.diaxeiristis_sunthimatiko]" id="diaxeiristis_sunthimatiko"
			   value="{{{ $config->get('config.diaxeiristis_sunthimatiko', '') }}}" required autocomplete="off"
		>
	</div>
</div>
