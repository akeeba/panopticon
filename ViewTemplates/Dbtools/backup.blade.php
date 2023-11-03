<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/**
 * @var \Akeeba\Panopticon\View\Dbtools\Html $this
 */
?>

<div class="card card-body my-5 mx-3 p-4 text-center">
    <div class="display-1 text-info my-5">
        <span class="fa fa-fw fa-spinner fa-spin-pulse" aria-hidden="true"></span>
    </div>
    <h3 class="display-2 text-body-emphasis">
        @lang('PANOPTICON_DBTOOLS_LBL_BACKUP_WAIT')
    </h3>
    <p class="text-body-secondary display-4">
        @lang('PANOPTICON_DBTOOLS_LBL_BACKUP_WAIT_SUB')
    </p>
    <p class="text-body-tertiary small mt-4">
        @lang('PANOPTICON_DBTOOLS_LBL_BACKUP_MAY_RELOAD')
    </p>
</div>

<form action="@route('index.php?view=dbtools&task=backup')" method="post"
      name="backupForm" id="backupForm">
    <input type="hidden" name="@token()" value="1">
</form>

<script type="text/javascript">
window.setTimeout(() => {
    document.forms['backupForm'].submit();
}, 500);
</script>