<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Awf\Uri\Uri;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

$js = <<< JS
window.addEventListener('DOMContentLoaded', () => {
   const div = document.getElementById('systemsGo');
   if (div) {
       div.classList.remove('d-none');
   }
   
   if (typeof bootstrap.Collapse !== 'function') {
       document.getElementById('brokenJavaScript').classList.remove('d-none');
   }
});

JS;
$this->getContainer()->application->getDocument()->addScriptDeclaration($js);
?>

<?php if($this->requiredMet): ?>
	<noscript>
		<div class="px-4 py-5 my-0 text-center">
			<div class="mx-auto mb-4">
			<span class="badge bg-danger rounded-5">
				<span class="far fa-times-circle display-5" aria-hidden="true"></span>
			</span>
			</div>

			<h3 class="display-5 fw-bold text-danger">
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_NOSCRIPT_HEAD') ?>
			</h3>
			<div class="col-lg-6 mx-auto">
				<p class="lead mb-4">
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_NOSCRIPT_EXPLAIN') ?>
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_CANNOT_CONTINUE') ?>
				</p>
				<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
					<a href="<?= Uri::current() ?>" role="button" class="btn btn-warning btn-lg px-4 gap-3">
						<span class="fa fa-refresh" aria-hidden="true"></span>
						<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_RETRY_PRECHECK') ?>
					</a>

					<div>
						<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=database') ?>" role="button" class="btn btn-outline-danger btn-sm px-4 gap-3">
							<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
							<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_IGNORE_AND_NEXT') ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</noscript>

	<div class="px-4 py-5 my-0 text-center d-none" id="brokenJavaScript">
		<div class="mx-auto mb-4">
			<span class="badge bg-danger rounded-5">
				<span class="far fa-times-circle display-5" aria-hidden="true"></span>
			</span>
		</div>

		<h3 class="display-5 fw-bold text-danger">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_BROKEN_JS_HEAD') ?>
		</h3>
		<div class="col-lg-6 mx-auto">
			<p class="lead mb-4">
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_BROKEN_JS_EXPLAIN') ?>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_CANNOT_CONTINUE') ?>
			</p>
			<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
				<a href="<?= Uri::current() ?>" role="button" class="btn btn-warning btn-lg px-4 gap-3">
					<span class="fa fa-refresh" aria-hidden="true"></span>
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_RETRY_PRECHECK') ?>
				</a>

				<div>
					<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=database') ?>" role="button" class="btn btn-outline-danger btn-sm px-4 gap-3">
						<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
						<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_IGNORE_AND_NEXT') ?>
					</a>
				</div>
			</div>
		</div>
	</div>


	<div class="px-4 py-5 my-0 text-center d-none" id="systemsGo">
		<div class="mx-auto mb-4">
			<span class="badge bg-success rounded-5">
				<span class="far fa-check-circle display-5" aria-hidden="true"></span>
			</span>
		</div>

		<h3 class="display-5 fw-bold text-success">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_HEAD_OK') ?>
		</h3>
		<div class="col-lg-6 mx-auto">
			<p class="lead mb-4">
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_REQUIREMENTS_OK') ?>
				<?php if(!$this->recommendedMet): ?>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_RECOMMENDED_IMPROVE') ?>
				<?php endif ?>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_YOU_MAY_CONTINUE') ?>
			</p>
			<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
				<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=database') ?>" role="button" class="btn btn-primary btn-lg px-4 gap-3">
					<span class="fa fa-chevron-circle-right" aria-hidden="true"></span>
					<?= $this->getLanguage()->text('PANOPTICON_BTN_NEXT') ?>
				</a>

				<?php if(!$this->recommendedMet): ?>
					<button type="button" class="btn btn-outline-info btn-sm"
					        data-bs-toggle="collapse" data-bs-target="#requirementsCheck" aria-expanded="false" aria-controls="requirementsCheck"
					>
						<span class="fa fa-question-circle" aria-hidden="true"></span>
						<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_BTN_SHOW_OPTIONAL_SETTINGS') ?>
					</button>
				<?php endif ?>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="px-4 py-5 my-0 text-center">
		<div class="mx-auto mb-4">
			<span class="badge bg-danger rounded-5">
				<span class="far fa-times-circle display-5" aria-hidden="true"></span>
			</span>
		</div>

		<h3 class="display-5 fw-bold text-danger">
			<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_HEAD_FAIL') ?>
		</h3>
		<div class="col-lg-6 mx-auto">
			<p class="lead mb-4">
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_REQUIREMENTS_FAIL') ?>
				<?php if(!$this->recommendedMet): ?>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_RECOMMENDED_FAIL') ?>
				<?php endif ?>
				<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_PRECHECK_CANNOT_CONTINUE') ?>
			</p>
			<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
				<a href="<?= Uri::current() ?>" role="button" class="btn btn-warning btn-lg px-4 gap-3">
					<span class="fa fa-refresh" aria-hidden="true"></span>
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_RETRY_PRECHECK') ?>
				</a>

				<div>
					<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=database') ?>" role="button" class="btn btn-outline-danger btn-sm px-4 gap-3">
						<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
						<?= $this->getLanguage()->text('PANOPTICON_SETUP_BTN_IGNORE_AND_NEXT') ?>
					</a>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<div class="<?= $this->requiredMet ? 'collapse' : '' ?>" id="requirementsCheck">
	<div class="d-flex flex-row gap-2">
		<?php if (!$this->requiredMet): ?>
			<div class="card w-100">
				<h3 class="card-header">
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_REQUIRED') ?>
				</h3>
				<div class="card-body">
					<table class="table table-striped">
						<tbody>
						<?php foreach($this->reqSettings as $option): ?>
							<tr>
								<th colspan="row">
									<?= $this->escape($option['label']) ?>
									<?php if ($option['notice'] ?? ''): ?>
										<div class="small text-muted">
											<?= $this->escape($option['notice']) ?>
										</div>
									<?php endif; ?>
								</th>
								<td>
						<span class="badge <?= $option['current'] ? 'bg-success' : 'bg-danger' ?>">
							<span class="fa <?= $option['current'] ? 'fa-check-circle' : 'fa-times-circle' ?>" aria-hidden="true"></span>
							<span class="visually-hidden">
								<?= $option['current'] ? $this->getLanguage()->text('AWF_YES') : $this->getLanguage()->text('AWF_NO') ?>
							</span>
						</span>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif ?>

		<?php if (!$this->recommendedMet): ?>
			<div class="card w-100">
				<h3 class="card-header">
					<?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_RECOMMENDED') ?>
				</h3>
				<div class="card-body">
					<table class="table table-striped">
						<thead>
						<tr>
							<th><?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_SETTING') ?></th>
							<th><?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_RECOMMENDED_VALUE') ?></th>
							<th><?= $this->getLanguage()->text('PANOPTICON_SETUP_LBL_CURRENT_SETTING') ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach($this->recommendedSettings as $option): ?>
							<tr>
								<th colspan="row">
									<?= $this->escape($option['label']) ?>
									<?php if ($option['notice'] ?? ''): ?>
										<div class="small text-muted">
											<?= $this->escape($option['notice']) ?>
										</div>
									<?php endif; ?>
								</th>
								<td>
							<span class="badge bg-secondary">
								<?= $option['recommended'] ? $this->getLanguage()->text('AWF_YES') : $this->getLanguage()->text('AWF_NO') ?>
							</span>
								</td>
								<td>
							<span class="badge <?= $option['current'] == $option['recommended'] ? 'bg-success' : 'bg-warning' ?>">
								<span class="fa <?= $option['current'] == $option['recommended'] ? 'fa-check-circle' : 'fa-times-circle' ?>" aria-hidden="true"></span>
								<?= $option['current'] ? $this->getLanguage()->text('AWF_YES') : $this->getLanguage()->text('AWF_NO') ?>
							</span>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>


