<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

?>
<p class="h4">
	<?= $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_SETUP') ?>
</p>

<p class="mt-2 mb-5 col-lg-6">
	<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_SETUP_HEAD_TEXT') ?>
</p>


<form action="<?= $this->container->router->route('index.php?view=setup&task=saveconfig') ?>"
      method="post" name="setupForm" id="setupForm">

	<div class="row mb-3">
		<label for="name" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_NAME') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" class="form-control" id="name" name="user_name"
			       value="<?= $this->params['user.name'] ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_NAME_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<label for="email" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_EMAIL') ?>
		</label>
		<div class="col-sm-9">
			<input type="email" class="form-control" id="email" name="user_email"
			       value="<?= $this->params['user.email'] ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_EMAIL_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<label for="username" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_USERNAME') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" class="form-control" id="username" name="user_username"
			       value="<?= $this->params['user.username'] ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_USERNAME_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<label for="password" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_PASSWORD') ?>
		</label>
		<div class="col-sm-9">
			<input type="password" class="form-control" id="password" name="user_password"
			       value="<?= $this->params['user.password'] ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_PASSWORD_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<label for="password2" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_PASSWORD2') ?>
		</label>
		<div class="col-sm-9">
			<input type="password" class="form-control" id="password2" name="user_password2"
			       value="<?= $this->params['user.password2'] ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_USER_PASSWORD2_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<label for="timezone" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_TIMEZONE') ?>
		</label>
		<div class="col-sm-9">
			<?= $this->getContainer()->helper->setup->timezoneSelect($this->params['timezone']) ?>
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_TIMEZONE_HELP') ?>
		</div>
	</div>

	<div class="row mb-3">
		<div class="col-sm-9 offset-sm-3">
			<button type="submit" id="dbFormSubmit"
			        class="btn btn-primary">
				<span class="fa fa-chevron-right" aria-hidden="true"></span>
				<?= $this->getLanguage()->text('PANOPTICON_BTN_NEXT') ?>
			</button>
		</div>
	</div>
</form>
