<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Sites\Html $this
 * @var \Akeeba\Panopticon\Model\Site      $model
 */
$model     = $this->getModel();
$returnUrl = $this->input->getBase64('returnurl', '');

?>

<form action="@route('index.php?view=sites')" method="post" name="adminForm" id="adminForm">
    <p class="fs-3">
        <span>
            {{{ $model->name }}}
        </span>
        <span class="fa fa-chevron-right mx-2"></span>
        <span class="visually-hidden">,</span>
        <span class="text-secondary">
            {{{ $this->extension->name }}}
        </span>
    </p>

    <div class="row mt-3 mb-4">
        <label for="name" class="col-sm-3 col-form-label">
            @lang('PANOPTICON_SITES_LBL_DLKEY')
        </label>
        <div class="col-sm-9">
            <input type="text" class="form-control" name="dlkey" id="dlkey"
                   value="{{{ $this->extension->downloadkey->value ?? '' }}}" required
            >
        </div>
    </div>

    <input type="hidden" name="id" value="{{{ $model->getId() ?? 0 }}}">
    <input type="hidden" name="extension" value="{{{ $this->extension->extension_id ?? 0 }}}">
    <input type="hidden" name="token" value="@token()">
    <input type="hidden" name="task" id="task" value="savedlkey">
    @if (!empty($returnUrl))
        <input type="hidden" name="returnurl" value="{{{ $returnUrl }}}">
    @endif

</form>