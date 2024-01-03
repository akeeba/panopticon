<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

?>
<p class="h4">
	<?= $this->getLanguage()->text('PANOPTICON_SETUP_SUBTITLE_DATABASE') ?>
</p>

<p class="mt-2 mb-5 col-lg-6">
	<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_HEAD_TEXT') ?>
</p>

<form action="<?= $this->container->router->route('index.php?view=setup&task=installDatabase') ?>"
      name="dbForm" id="dbForm"
      method="post">

	<div class="row mb-3">
		<label for="driver" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DRIVER') ?>
		</label>
		<div class="col-sm-9">
			<?= $this->getContainer()->helper->setup->databaseTypesSelect($this->connectionParameters['driver']) ?>
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DRIVER_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="host-wrapper">
		<label for="host" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_HOST') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" class="form-control" id="host" name="host"
			       value="<?= $this->escape($this->connectionParameters['host']) ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_HOST_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="user-wrapper">
		<label for="user" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_USER') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" class="form-control" id="user" name="user"
			       value="<?= $this->escape($this->connectionParameters['user']) ?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_USER_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="pass-wrapper">
		<label for="pass" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_PASS') ?>
		</label>
		<div class="col-sm-9">
			<input type="password" class="form-control" id="pass" name="pass"
			       value="<?= $this->escape($this->connectionParameters['pass'])?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_PASS_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="name-wrapper">
		<label for="name" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_NAME') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="name" name="name" class="form-control"
			       value="<?= $this->escape($this->connectionParameters['name'])?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_NAME_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-wrapper">
		<label for="prefix" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_PREFIX') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="prefix" name="prefix" class="form-control"
			       value="<?= $this->escape($this->connectionParameters['prefix'])?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_PREFIX_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbencryption">
		<div class="col-sm-9 offset-sm-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch"
				       name="dbencryption" id="dbencryption" <?= ($this->connectionParameters['ssl']['enable'] ?? '') ? 'checked' : '' ?>
					value="1"
				>
				<label for="dbencryption">
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBENCRYPTION') ?>
				</label>
			</div>
		</div>
		<div class="form-text">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBENCRYPTION_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbsslcipher" <?= $this->showOn('dbencryption:1') ?>>
		<label for="dbsslcipher" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCIPHER') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="dbsslcipher" name="dbsslcipher" class="form-control"
			       placeholder="AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-CBC-SHA256:AES256-CBC-SHA384:DES-CBC3-SHA"
			       value="<?= $this->escape($this->connectionParameters['ssl']['cipher'] ?? '')?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCIPHER_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbsslca" <?= $this->showOn('dbencryption:1') ?>>
		<label for="dbsslca" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCA') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="dbsslca" name="dbsslca" class="form-control"
			       value="<?= $this->escape($this->connectionParameters['ssl']['ca'] ?? '')?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCA_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbsslkey" <?= $this->showOn('dbencryption:1') ?>>
		<label for="dbsslkey" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLKEY') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="dbsslkey" name="dbsslkey" class="form-control"
			       value=<?= $this->escape($this->connectionParameters['ssl']['key'] ?? '')?>>
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLKEY_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbsslcert" <?= $this->showOn('dbencryption:1') ?>>
		<label for="dbsslcert" class="col-sm-3 col-form-label">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCERT') ?>
		</label>
		<div class="col-sm-9">
			<input type="text" id="dbsslcert" name="dbsslcert" class="form-control"
			       value="<?= $this->escape($this->connectionParameters['ssl']['cert'] ?? '')?>">
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLCERT_HELP') ?>
		</div>
	</div>

	<div class="row mb-3" id="prefix-dbsslverifyservercert" <?= $this->showOn('dbencryption:1') ?>>
		<div class="col-sm-9 offset-sm-3">
			<div class="form-check form-switch">
				<input class="form-check-input" type="checkbox" role="switch"
				       name="dbsslverifyservercert" id="dbsslverifyservercert" <?= ($this->connectionParameters['ssl']['dbsslverifyservercert'] ?? '') ? 'checked' : '' ?>
				       value="1"
				>
				<label for="dbsslverifyservercert">
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLVERIFYSERVERCERT') ?>
				</label>
			</div>
		</div>
		<div class="form-text collapse">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_DATABASE_DBSSLVERIFYSERVERCERT_HELP') ?>
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