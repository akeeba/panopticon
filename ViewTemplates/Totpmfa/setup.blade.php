<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Library\Task\Status;
use Awf\Html\Select;
use Awf\Registry\Registry;
use Awf\Html\Html as HtmlHelper;
use Awf\Text\Text;

defined('AKEEBA') || die;

/**
 * @var \Awf\Mvc\DataView\Html $this
 */

$svg = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $svg);
?>

<div class="card">
	<p class="h3 card-header">
		@lang('PANOPTICON_MFA_TOTP_QR_LBL_TITLE')
	</p>
	<div class="card-body d-flex flex-column flex-lg-row gap-3 gap-lg-0 align-items-start justify-content-center">
		{{ $svg }}
		<div>
			<p>
				@lang('PANOPTICON_MFA_TOTP_QR_LBL_INSTRUCTIONS')
			</p>
			<ul>
				<li>
					@lang('PANOPTICON_MFA_TOTP_QR_LBL_INSTRUCTIONS_OPT1')
				</li>
				<li>
					@sprintf('PANOPTICON_MFA_TOTP_QR_LBL_INSTRUCTIONS_OPT2', $uri)
				</li>
				<li>
					@lang('PANOPTICON_MFA_TOTP_QR_LBL_INSTRUCTIONS_OPT3')
					<code>{{ $secret }}</code>
				</li>
			</ul>
			<p>
				@lang('PANOPTICON_MFA_TOTP_QR_LBL_ENTER_CODE')
			</p>
			<p class="text-info small">
				<span class="fa fa-fw fa-info-circle" aria-hidden="true"></span>
				@lang('PANOPTICON_MFA_TOTP_QR_LBL_SOFTWARE')
			</p>
		</div>
	</div>
</div>
