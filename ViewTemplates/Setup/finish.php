<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */
?>

<div class="px-4 py-5 my-0 text-center">
	<div class="mx-auto mb-4">
			<span class="badge bg-success rounded-5 p-2">
				<span class="far fa-check-circle display-5" aria-hidden="true"></span>
			</span>
	</div>

	<h3 class="display-5 fw-bold text-success">
		<?= $this->getLanguage()->text($this->maxExec < 60 ? 'PANOPTICON_SETUP_FINISH_HEAD_WITH_WARNING' : 'PANOPTICON_SETUP_FINISH_HEAD') ?>
	</h3>
	<div class="col-lg-9 mx-auto">
		<p class="lead mb-4">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_SUBTITLE') ?>
		</p>
		<?php if ($this->maxExec < 30): ?>
		<div class="alert alert-danger text-start">
			<h4 class="alert-heading fs-5">
				<span class="fa fa-triangle-exclamation" aria-hidden="true"></span>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_TOOLOW_HEAD') ?>
			</h4>
			<p>
				<?= $this->getLanguage()->sprintf('PANOPTICON_SETUP_FINISH_MAXEXEC_TOOLOW_INFO', $this->maxExec) ?>
			</p>
			<p>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_TOOLOW_CAUTION') ?>
			</p>
			<p>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_TOOLOW_FIXIT') ?>
			</p>
		</div>
		<?php elseif ($this->maxExec < 60): ?>
		<div class="alert alert-warning text-start">
			<h4 class="alert-heading fs-5">
				<span class="fa fa-triangle-exclamation" aria-hidden="true"></span>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_LOW_HEAD') ?>
			</h4>
			<p>
				<?= $this->getLanguage()->sprintf('PANOPTICON_SETUP_FINISH_MAXEXEC_LOW_INFO', $this->maxExec) ?>
			</p>
			<p>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_LOW_CAUTION') ?>
			</p>
			<p>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_FINISH_MAXEXEC_LOW_FIXIT') ?>
			</p>
		</div>
		<?php endif ?>
		<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
			<a href="<?= $this->container->router->route('index.php?view=main') ?>" role="button" class="btn btn-primary btn-lg px-4 gap-3">
				<span class="fas fa-rocket me-2" aria-hidden="true"></span>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_START_USING') ?>
			</a>
		</div>
	</div>
</div>