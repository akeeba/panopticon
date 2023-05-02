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
		Set up a CRON job
	</h3>
	<p class="lead text-center">
		Panopticon uses a lot of automation to make your life easier. For this, you need to set up a CRON job to run every minute.
	</p>
	<p class="lead text-center">
		Set up a CRON job using one of the methods below. Then, wait for up to a minute for this page to change.
	</p>
	<p class="small text-muted text-center">
		Take a breath. This is the last installation step. You're almost done.
	</p>

	<ul class="nav nav-tabs mt-4" id="instructionsTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button type="button" role="tab"
			        class="nav-link active" id="cliTab"
			        data-bs-toggle="tab" data-bs-target="#cliTabPane"
			        aria-controls="cliTabPane" aria-selected="true">
				CLI
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button type="button" role="tab"
			        class="nav-link" id="webTab"
			        data-bs-toggle="tab" data-bs-target="#webTabPane"
			        aria-controls="webTabPane">
				Web
			</button>
		</li>
	</ul>
	<div class="tab-content pb-2 mb-3 border-bottom border-2" id="instructionsContent">
		<div class="tab-pane show active px-2" id="cliTabPane" role="tabpanel" aria-labelledby="cliTab" tabindex="0">

			<div class="row">
				<div class="col-12 col-lg-8 order-1 order-lg-0 py-1">
					<h3>Instructions</h3>
					<p>
						Go to your hosting control panel. Create a new CRON job. Set it to run <strong>every minute</strong>. Use the following command line.
					</p>
					<p>
						<code><i>/path/to/php</i> <?= APATH_ROOT ?>/cli/panopticon.php task:run >/dev/null 2>&1</code>
					</p>
					<p>
						Replace <i>/path/to/php</i> with the path to the PHP <?= PHP_VERSION ?> CLI executable. Make sure it's the PHP CLI executable, <strong>not</strong> the PHP CGI/FastCGI executable.
					</p>
					<p class="small text-muted">
						If unsure what some or any of the above means, please ask your host.
					</p>

					<div class="alert alert-warning">
						<h4 class="alert-heading h6 fw-bold">
							<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
							Make sure the CRON job runs <em>every minute</em>.
						</h4>
						<p class="small">
							Running the CRON job less frequently results in outdated information and unreliability, including broken sites due to incomplete Joomla!&trade; core updates.
						</p>
					</div>

					<h4 class="h5 mb-2 pb-1 border-bottom">
						Problem? Meet solution!
					</h4>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							The CRON job does not run
						</summary>
						<p>
							Remove the <code>2>&1</code> from the CRON command line. When the CRON command executes again you will receive an email with an error (as long as your hosting account is configured to email you the output of CRON jobs). Using that, you can find out what is going on.
						</p>
						<p>
							If even then nothing seems to happen, please contact your host. Either your account is not receiving the output of the CRON jobs, or the CRON jobs are not executing at all (while unlikely, we've seen that happen a few times).
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							I get “Status: 403 Forbidden” running the CRON job
						</summary>
						<p>
							You are using the PHP-CGI/FastCGI executable. As noted above, you need to replace <em>/path/to/php</em> with the path to the <strong>PHP CLI</strong> executable.
						</p>
						<p>
							Please ask your host for the correct path. If your host gave you this path to begin with, escalate your support ticket with your host, asking to speak with a server engineer. It's trivial for them to give you that path.
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							I get “Akeeba Panopticon requires PHP <?= AKEEBA_PANOPTICON_MINPHP ?> or later.”
						</summary>
						<p>
							You are using the PHP CLI executable for the wrong PHP version in your CRON job.
						</p>
						<p>
							Most servers have multiple versions of PHP installed. You need to ask your host to give you the path to the PHP CLI executable for PHP <?= PHP_VERSION ?> and use that in your CRON job's command line.
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							The host only allows URLs in CRON
						</summary>
						<p>
							Click on the <strong>Web</strong> tab above.
						</p>
					</details>

					<details class="mb-3">
						<summary class="h6 fw-bold">
							The host does not have a CRON feature
						</summary>
						<p>
							We <strong>very strongly</strong> recommend that you use a different host.
						</p>
						<p>
							Panopticon is only useful when its automation features run consistently and reliably. This requires its CRON job executing every minute — <em>1440 times a day</em>. This can get expensive with a third party service; about $10 per day! You can instead spend as much money <em>per month</em> on a decent host which support real, command-line CRON jobs.
						</p>
						<p>
							If you have a cheaper way executing CRON jobs remotely (e.g. another server under your control), please click on the <strong>Web</strong> tab to find out how to do it.
						</p>
						<p>
							<strong>Do not “cheap out” on CRON job frequency!</strong> Running CRON jobs less frequently will make Panopticon unreliable and could even break your sites due to incomplete Joomla&trade; core updates.
						</p>
					</details>

				</div>
				<div class="col-12 col-lg-4 bg-light-subtle order-0 order-lg-1 py-1">
					<h3 class="mb-4">
						Is this right for me?
					</h3>
					<h4 class="text-success-emphasis">
						<span class="fa fa-plus-circle" aria-hidden="true"></span>
						Pros
					</h4>
					<ul>
						<li>More reliable</li>
						<li>Zero cost</li>
						<li>Faster</li>
					</ul>

					<h4 class="text-danger-emphasis">
						<span class="fa fa-minus-circle" aria-hidden="true"></span>
						Cons
					</h4>
					<ul>
						<li>Some hosts don't support it</li>
						<li>More involved setup</li>
					</ul>

					<h4 class="text-info">
						<span class="fa fa-bullseye" aria-hidden="true"></span>
						Target audience
					</h4>
					<ul>
						<li>Recommended method for most users</li>
						<li>Only supported method for dozens to hundreds of sites monitored</li>
					</ul>
				</div>
			</div>

		</div>

		<div class="tab-pane" id="webTabPane" role="tabpanel" aria-labelledby="webTab" tabindex="0">
			<div class="row">
				<div class="col-12 col-lg-8 order-1 order-lg-0 py-1">
					<h3>Instructions</h3>
					<p>
						If your host uses URL-based CRON jobs, or you're using a third party system which accepts a URL, use the following URL:
					</p>
					<p>
						<code><?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?></code>
					</p>
					<p>
						If you are setting up a regular CRON job, use one of the following command lines, depending on which command is installed on your system:
					</p>
					<p>
						<strong>wGET</strong> (most commercial hosts and Linux servers)
						<br/>
						<code>wget --no-check-certificate --max-redirect=20 "<?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?>" -O - >/dev/null 2>&1</code>
					</p>
					<p>
						<strong>cURL</strong> (macOS, some Linux servers)
						<br/>
						<code>curl -k -L "<?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?>" >/dev/null 2>&1</code>
					</p>
					<p>
						<strong>PowerShell</strong> (Windows)
						<br/>
						<code>Invoke-WebRequest -SkipCertificateCheck -URI <?= Uri::base() ?>index.php?view=cron&key=<?= $this->cronKey ?></code>
					</p>

					<div class="alert alert-warning">
						<h4 class="alert-heading h6 fw-bold">
							<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
							Make sure the CRON job runs <em>every minute</em>.
						</h4>
						<p class="small">
							Running the CRON job less frequently results in outdated information and unreliability, including broken sites due to incomplete Joomla!&trade; core updates.
						</p>
					</div>


					<h4 class="h5 mb-2 pb-1 border-bottom">
						Problem? Meet solution!
					</h4>

					<!-- TODO -->
					<details class="mb-3">
						<summary class="h6 fw-bold">
							Problem
						</summary>
						<p>
							Solution
						</p>
					</details>
				</div>

				<div class="col-12 col-lg-4 bg-light-subtle order-0 order-lg-1 py-1">
					<h3 class="mb-4">
						Is this right for me?
					</h3>
					<h4 class="text-success-emphasis">
						<span class="fa fa-plus-circle" aria-hidden="true"></span>
						Pros
					</h4>
					<ul>
						<li>Usually easier setup</li>
						<li>Works with hosts offering URL-only CRON</li>
						<li>Works with hosts lacking CRON support</li>
					</ul>

					<h4 class="text-danger-emphasis">
						<span class="fa fa-minus-circle" aria-hidden="true"></span>
						Cons
					</h4>
					<ul>
						<li>Less reliable</li>
						<li>Slower</li>
						<li>High cost (when used with a third party service)</li>
						<li>Typically unsuitable when monitoring several sites</li>
					</ul>

					<h4 class="text-info">
						<span class="fa fa-bullseye" aria-hidden="true"></span>
						Target audience
					</h4>
					<ul>
						<li>Servers with URL-only CRON jobs</li>
						<li>Ten or fewer monitored sites</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<h3>What next?</h3>
	<p>
		A few seconds to a minute after you set up your CRON job this page will change to a benchmark. Don't close this browser tab, don't change to a different browser tab / window or application, don't turn off or let your device sleep, don't feed your <em>mogwai</em> after midnight. Pretty straightforward, right?
	</p>
	<p>
		If you need to finish setting up your CRON job later — no worries! Close this browser tab. Next time you come back to your Panopticon installation it will take you to this page.
	</p>
	<p class="text-muted">
		If you're an expert user —or just looking around— you can skip this step and set up CRON jobs later.
		<br/>
		<a href="<?= $this->getContainer()->router->route('index.php?view=setup&task=skipcron') ?>" class="link-secondary">
			Skip CRON configuration (not recommended).
		</a>
	</p>
</div>

<!-- Benchmark -->
<div class="px-4 py-5 my-3 text-center d-none" id="benchmark">
	<h3 class="display-5 fw-bold text-primary">
		Benchmark in progress
	</h3>
	<p class="lead">
		Panopticon is measuring how long can a CRON task run
	</p>
	<p class="my-4">
		Please do not switch tabs, or windows, and make sure that your device does not turn off or go to sleep. The benchmark takes up to 3 minutes.
	</p>
	<div class="d-block my-5">
		<div class="progress w-75 mx-auto" role="progressbar"
		     aria-label="Benchmark progress, in seconds"
		     aria-valuenow="0" aria-valuemin="0" aria-valuemax="185">
			<div class="progress-bar progress-bar-striped progress-bar-animated text-dark fw-bold" id="progressFill" style="width: 25%">
				20s
			</div>
		</div>
	</div>
	<p class="text-muted small my-4">
		The progress bar updates every 5 seconds. The benchmark may finish before the progress bar fills up.
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
		Benchmark failed
	</h3>
	<div class="col-lg-9 mx-auto">
		<p class="lead mb-4">
			There was an error which prevented the benchmark from completing.
		</p>
		<p id="errorMessage" class="text-danger">
			This is an error message
		</p>
		<div class="d-grid gap-2 d-sm-flex justify-content-sm-center align-items-center">
			<a href="<?= Uri::current() ?>" role="button" class="btn btn-warning btn-lg px-4 gap-3 text-white">
				<span class="fa fa-refresh" aria-hidden="true"></span>
				Retry
			</a>

			<div>
				<a href="<?= $this->container->router->route('index.php?view=setup&task=database&layout=skipcron') ?>" role="button" class="btn btn-outline-danger btn-sm px-4 gap-3">
					<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
					Skip this step (not recommended)
				</a>
			</div>
		</div>
	</div>
</div>