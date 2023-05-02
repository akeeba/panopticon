<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;
use Awf\Uri\Uri;

defined('AKEEBA') || die;

/** @var \Akeeba\Panopticon\View\Setup\Html $this */

?>

<!-- Instructions -->
<div class="px-4 py-5 my-3" id="instructions">
	<h3 class="display-5 fw-bold text-center">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_HEAD') ?>
	</h3>
	<p class="lead text-center">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_SUBHEAD') ?>
	</p>
	<p class="lead text-center">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_SUBHEAD_CTA') ?>
	</p>
	<p class="small text-muted text-center">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_SUBHEAD_BREATHE') ?>
	</p>

	<ul class="nav nav-tabs mt-4" id="instructionsTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button type="button" role="tab"
			        class="nav-link active" id="cliTab"
			        data-bs-toggle="tab" data-bs-target="#cliTabPane"
			        aria-controls="cliTabPane" aria-selected="true">
				<?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_TABHEAD') ?>
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button type="button" role="tab"
			        class="nav-link" id="webTab"
			        data-bs-toggle="tab" data-bs-target="#webTabPane"
			        aria-controls="webTabPane">
				<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_TABHEAD') ?>
			</button>
		</li>
	</ul>
	<div class="tab-content pb-2 mb-3 border-bottom border-2" id="instructionsContent">
		<div class="tab-pane show active px-2" id="cliTabPane" role="tabpanel" aria-labelledby="cliTab" tabindex="0">

			<div class="row">
				<div class="col-12 col-lg-8 order-1 order-lg-0 py-1">
					<h3><?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_INSTRUCTIONS') ?></h3>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_CREATE_A_JOB') ?>
					</p>
					<p>
						<code><i>/path/to/php</i> <?= APATH_ROOT ?>/cli/panopticon.php task:run >/dev/null 2>&1</code>
					</p>
					<p>
						<?= Text::sprintf('PANOPTICON_SETUP_LBL_CRON_REPLACE_PHP_CLI', PHP_VERSION) ?>
					</p>
					<p class="small text-muted">
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_IF_UNSURE') ?>
					</p>

					<div class="alert alert-warning">
						<h4 class="alert-heading h6 fw-bold">
							<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_EVERY_MINUTE_ALERT_HEAD') ?>
						</h4>
						<p class="small">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_EVERY_MINUTE_ALERT_BODY') ?>
						</p>
					</div>

					<h4 class="h5 mb-2 pb-1 border-bottom">
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_TROUBLESHOOTING') ?>
					</h4>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NOT_RUN_HEAD') ?>
						</summary>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NOT_RUN_INFO_1') ?>
						</p>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NOT_RUN_INFO_2') ?>
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_FORBIDDEN_HEAD') ?>
						</summary>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_FORBIDDEN_INFO_1') ?>
						</p>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_FORBIDDEN_INFO_2') ?>
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							<?= Text::sprintf('PANOPTICON_SETUP_LBL_CRON_TROUBLE_WRONG_PHP_HEAD', AKEEBA_PANOPTICON_MINPHP) ?>
						</summary>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_WRONG_PHP_INFO_1') ?>
						</p>
						<p>
							<?= Text::sprintf('PANOPTICON_SETUP_LBL_CRON_TROUBLE_WRONG_PHP_INFO_2', PHP_VERSION) ?>
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_URL_CRON_HEAD') ?>
						</summary>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_URL_CRON_INFO') ?>
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NO_CRON_HEAD') ?>
						</summary>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NO_CRON_INFO_1') ?>
						</p>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NO_CRON_INFO_2') ?>
						</p>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NO_CRON_INFO_3') ?>
						</p>
						<p>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_TROUBLE_NO_CRON_INFO_4') ?>
						</p>
					</details>

				</div>
				<div class="col-12 col-lg-4 bg-light-subtle order-0 order-lg-1 py-1">
					<h3 class="mb-4">
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_IS_THIS_RIGHT_FOR_ME') ?>
					</h3>
					<h4 class="text-success-emphasis">
						<span class="fa fa-plus-circle" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_PROS') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_PROS_1') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_PROS_2') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_PROS_3')?></li>
					</ul>

					<h4 class="text-danger-emphasis">
						<span class="fa fa-minus-circle" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_CONS') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_CONS_1') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_CONS_2') ?></li>
					</ul>

					<h4 class="text-info">
						<span class="fa fa-bullseye" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_TARGET_AUDIENCE') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_TARGET_1')?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_CLI_TARGET_2')?></li>
					</ul>
				</div>
			</div>

		</div>

		<div class="tab-pane" id="webTabPane" role="tabpanel" aria-labelledby="webTab" tabindex="0">
			<div class="row">
				<div class="col-12 col-lg-8 order-1 order-lg-0 py-1">
					<h3><?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_INSTRUCTIONS') ?></h3>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_IF_URL_CRON') ?>
					</p>
					<p>
						<code><?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?></code>
					</p>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_IF_REGULAR_CRON') ?>
					</p>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_WGET') ?>
						<br/>
						<code>wget --no-check-certificate --max-redirect=20 "<?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?>" -O - >/dev/null 2>&1</code>
					</p>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_CURL') ?>
						<br/>
						<code>curl -k -L "<?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?>" >/dev/null 2>&1</code>
					</p>
					<p>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_POWERSHELL') ?>
						<br/>
						<code>Invoke-WebRequest -SkipCertificateCheck -URI <?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?></code>
					</p>

					<div class="alert alert-warning">
						<h4 class="alert-heading h6 fw-bold">
							<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_EVERY_MINUTE_ALERT_HEAD') ?>
						</h4>
						<p class="small">
							<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_EVERY_MINUTE_ALERT_BODY') ?>
						</p>
					</div>

					<!-- TODO -->
					<!--
					<h4 class="h5 mb-2 pb-1 border-bottom">
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_TROUBLESHOOTING') ?>
					</h4>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							Problem
						</summary>
						<p>
							Solution
						</p>
					</details>
					-->
				</div>

				<div class="col-12 col-lg-4 bg-light-subtle order-0 order-lg-1 py-1">
					<h3 class="mb-4">
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_IS_THIS_RIGHT_FOR_ME') ?>
					</h3>
					<h4 class="text-success-emphasis">
						<span class="fa fa-plus-circle" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_PROS') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_PROS_1') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_PROS_2') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_PROS_3') ?></li>
					</ul>

					<h4 class="text-danger-emphasis">
						<span class="fa fa-minus-circle" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_CONS') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_CONS_1') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_CONS_2') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_CONS_3') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_CONS_4') ?></li>
					</ul>

					<h4 class="text-info">
						<span class="fa fa-bullseye" aria-hidden="true"></span>
						<?= Text::_('PANOPTICON_SETUP_LBL_CRON_LBL_TARGET_AUDIENCE') ?>
					</h4>
					<ul>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_TARGET_1') ?></li>
						<li><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WEB_TARGET_2') ?></li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<h3><?= Text::_('PANOPTICON_SETUP_LBL_CRON_WHAT_NEXT') ?></h3>
	<p>
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WHAT_NEXT_BENCHMARK_INFO') ?>
	</p>
	<p>
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WHAT_NEXT_YOU_CAN_COME_BACK_LATER') ?>
	</p>
	<p class="text-muted">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WHAT_NEXT_EXPERT_USER') ?>
		<br/>
		<a href="<?= $this->getContainer()->router->route('index.php?view=setup&task=skipcron') ?>" class="link-secondary">
			<?= Text::_('PANOPTICON_SETUP_LBL_CRON_WHAT_NEXT_SKIP_CRON') ?>
		</a>
	</p>
</div>

<!-- Benchmark -->
<div class="px-4 py-5 my-3 text-center d-none" id="benchmark">
	<h3 class="display-5 fw-bold text-primary">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_IN_PROGRESS') ?>
	</h3>
	<p class="lead">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_WHAT_IS_THIS') ?>
	</p>
	<p class="my-4">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_DO_NOT_CLOSE') ?>
	</p>
	<div class="d-block my-5">
		<div class="progress w-75 mx-auto" role="progressbar"
		     aria-label="<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_PROGRESS_HINT') ?>"
		     aria-valuenow="0" aria-valuemin="0" aria-valuemax="185">
			<div class="progress-bar progress-bar-striped progress-bar-animated text-dark fw-bold" id="progressFill" style="width: 25%">
			</div>
		</div>
	</div>
	<p class="text-muted small my-4">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_UPDATES_EVERY') ?>
	</p>
</div>

<!-- Fail page-->
<div class="px-4 py-5 my-0 text-center d-none" id="error">
	<div class="mx-auto mb-4">
			<span class="badge bg-danger rounded-5">
				<span class="far fa-times-circle display-5" aria-hidden="true"></span>
			</span>
	</div>

	<h3 class="display-5 fw-bold text-danger">
		<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_FAILED_HEAD') ?>
	</h3>
	<div class="col-lg-9 mx-auto">
		<p class="lead mb-4">
			<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_FAILED_SUBHEAD') ?>
		</p>
		<p id="errorMessage" class="text-danger">
		</p>
		<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
			<a href="<?= Uri::current() ?>" role="button" class="btn btn-warning btn-lg px-4 gap-3 text-white">
				<span class="fa fa-refresh" aria-hidden="true"></span>
				<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_FAILED_BTN_RETRY') ?>
			</a>

			<div>
				<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=skipcron') ?>" role="button" class="btn btn-outline-danger btn-sm px-4 gap-3">
					<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
					<?= Text::_('PANOPTICON_SETUP_LBL_CRON_BENCHMARK_FAILED_SKIP') ?>
				</a>
			</div>
		</div>
	</div>
</div>