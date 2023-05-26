<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\Setup as SetupHelper;
use Awf\Text\Text;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

$sessionPath = $this->getContainer()->session->getSavePath();

?>
<h3>
	<?= Text::_('PANOPTICON_SETUP_SESSION_LBL_WARNING_HEADER') ?>
</h3>
<p>
	<?= Text::sprintf('PANOPTICON_SETUP_SESSION_LBL_WARNING_BODY', $sessionPath) ?>
</p>

<form action="<?= $this->container->router->route('index.php?view=setup&task=savesession') ?>" method="post"
      role="form">
	<div class="row mb-3">
		<label for="fs_driver" class="col-sm-3 col-form-label">
			<?= Text::_('PANOPTICON_SETUP_LBL_FS_DRIVER_SESSION') ?>
		</label>
		<div class="col-sm-9">
			<?= SetupHelper::fsDriverSelect($this->params['fs.driver'], false) ?>
		</div>
	</div>

	<div id="ftp_options">
		<div class="row mb-3">
			<label for="fs_host" class="col-sm-3 col-form-label">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_HOST') ?>
			</label>
			<div class="col-sm-9">
				<input type="text" name="fs_host" id="fs_host" class="form-control" value="<?= $this->escape($this->params['fs.host']) ?>">
			</div>
			<div class="form-text">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_HOST_HELP') ?>
			</div>
		</div>

		<div class="row mb-3">
			<label for="fs_port" class="col-sm-3 col-form-label">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_PORT') ?>
			</label>
			<div class="col-sm-9">
				<input type="text" name="fs_port" id="fs_port" class="form-control" value="<?= $this->escape($this->params['fs.port']) ?>">
			</div>
			<div class="form-text">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_PORT_HELP') ?>
			</div>
		</div>

		<div class="row mb-3">
			<label for="fs_username" class="col-sm-3 col-form-label">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_USERNAME') ?>
			</label>
			<div class="col-sm-9">
				<input type="text" name="fs_username" id="fs_username" class="form-control"
				       value="<?= $this->escape($this->params['fs.username']) ?>">
			</div>
		</div>

		<div class="row mb-3">
			<label for="fs_password" class="col-sm-3 col-form-label">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_PASSWORD') ?>
			</label>
			<div class="col-sm-9">
				<input type="password" name="fs_password" id="fs_password" class="form-control"
				       value="<?= $this->escape($this->params['fs.password']) ?>">
			</div>
		</div>

		<div class="row mb-3">
			<label for="fs_directory" class="col-sm-3 col-form-label">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_DIRECTORY') ?>
			</label>
			<div class="col-sm-9">
				<input type="text" name="fs_directory" id="fs_directory" class="form-control"
				       value="<?= $this->escape($this->params['fs.directory']) ?>">
			</div>
			<div class="form-text">
				<?= Text::_('PANOPTICON_SETUP_LBL_FS_FTP_DIRECTORY_HELP') ?>
			</div>
		</div>
	</div>

	<div class="row mb-3">
		<div class="col-sm-9 offset-sm-3 d-flex flex-row gap-3 align-items-center">
			<button type="submit" id="setupFormSubmit" class="btn btn-primary">
				<span class="fa fa-chevron-circle-right" aria-hidden="true"></span>
				<?= Text::_('PANOPTICON_SETUP_BTN_MAKE_SESSION_FOLDER') ?>
			</button>

			<a href="<?= $this->container->router->route('index.php?view=setup&task=precheck') ?>"
			   class="btn btn-secondary btn-sm">
				<span class="fa fa-refresh" aria-hidden="true"></span>
				I fixed it myself; check again
			</a>
		</div>
	</div>
</form>