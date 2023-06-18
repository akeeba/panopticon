<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Mfamethods\Html $this */

if ($this->record->method != 'backupcodes')
{
	throw new RuntimeException('These are not the droids you\'re looking for.', 403);
}

?>

<h3 class="mt-2 mb-4">
	@lang('PANOPTICON_MFA_LBL_BACKUPCODES')
</h3>

<div class="mt-3 mb-4 px-4">
	@lang('PANOPTICON_MFA_LBL_BACKUPCODES_INSTRUCTIONS')
</div>

<table class="table table-striped">
	@for ($i = 0; $i < (count($this->backupCodes) / 2); $i++)
		<tr>
			<td class="text-center">
				@if (!empty($this->backupCodes[2 * $i]))
					{{{ $this->backupCodes[2 * $i] }}}
				@endif
			</td>
			<td class="text-center">
				@if (!empty($this->backupCodes[1 + 2 * $i]))
					{{{ $this->backupCodes[1 + 2 * $i] }}}
				@endif
			</td>
		</tr>
	@endfor
</table>

<p class="form-text">
	@lang('PANOPTICON_MFA_LBL_BACKUPCODES_RESET_INFO')
</p>
