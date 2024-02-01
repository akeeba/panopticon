<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Helper\DarkModeEnum;
use Akeeba\Panopticon\Helper\DefaultTemplate as TemplateHelper;
use Akeeba\Panopticon\Library\Version\Version;
use Awf\Uri\Uri;
use Awf\Utils\Template;

/** @var Awf\Document\Document $this */

$text = $this->getLanguage();

[$langCode,] = explode('-', $this->getContainer()->language->getLangCode() ?: 'en-GB');
$user          = $this->container->userManager->getUser();
$darkMode      = TemplateHelper::getDarkMode();
$darkModeValue = match ($darkMode)
{
	DarkModeEnum::DARK => 'dark',
	DarkModeEnum::LIGHT => 'light',
	default => ''
};

$versionTag    = Version::create(AKEEBA_PANOPTICON_VERSION)->tagType();

TemplateHelper::applyDarkModeJavaScript();
TemplateHelper::applyFontSize();

$isBareDisplay = $this->getContainer()->input->getCmd('tmpl', '') === 'component';
$isMenuEnabled = $this->getMenu()->isEnabled('main');
$isDebug       = defined('AKEEBADEBUG') && AKEEBADEBUG;
$themeColor    = TemplateHelper::getThemeColour();
$importMap     = TemplateHelper::getImportMapAsJson();
$view          = $this->getContainer()->input->getCmd('view', 'main') ?: 'main';

?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $text->text('PANOPTICON_APP_TITLE') ?></title>

	<?php // See https://medium.com/swlh/are-you-using-svg-favicons-yet-a-guide-for-modern-browsers-836a6aace3df ?>
	<link rel="icon" href="<?= Template::parsePath('media://images/logo_colour.svg', app: Factory::getApplication()) ?>">
	<link rel="mask-icon" href="<?= Template::parsePath('media://images/logo_bw.svg', app: Factory::getApplication()) ?>" color="#000000">

	<?php // Client-side options ?>
	<script type="application/json" class="akeeba-script-options new"><?= json_encode($this->getScriptOptions(), $isDebug ? JSON_PRETTY_PRINT : 0) ?: '{}' ?></script>
	<?php // ECMAScript import map ?>
	<?php if (!empty($importMap)): ?>
	<script type="importmap"><?= $importMap ?></script>
	<?php endif ?>
	<?php // Stylesheet files ?>
	<?php foreach ($this->getStyles() as $url => $params): ?>
		<link rel="stylesheet" href="<?= $url ?>"<?= ($params['media']) ? " media=\"{$params['media']}\"" : '' ?><?= ($params['attribs'] ?? null) ? ' ' . \Awf\Utils\ArrayHelper::toString($params['attribs']) : '' ?>>
	<?php endforeach ?>
	<?php // Inline Stylesheets ?>
	<?php foreach ($this->getStyleDeclarations() as $type => $content): ?>
		<style><?= $content ?></style>
	<?php endforeach ?>
	<?php // Script files ?>
	<?php foreach ($this->getScripts() as $url => $params): ?>
		<script type="<?= $params['mime'] ?>" src="<?= $url ?>"<?= ($params['defer'] ?? false) ? ' defer="defer"' : '' ?><?= ($params['async'] ?? false) ? ' async="async"' : '' ?>></script>
	<?php endforeach ?>
	<?php foreach ($this->getScriptDeclarations() as $type => $content): ?>
		<script type="<?= $type ?>"><?= $content ?></script>
	<?php endforeach ?>

	<?php if ($darkModeValue): ?>
	<meta name="color-scheme" content="<?= $darkModeValue ?>">
	<?php endif ?>
	<?php if (!empty($themeColor)): ?>
	<meta name="theme-color" content="<?= $themeColor ?>">
	<?php endif; ?>
</head>
<body data-bs-theme="<?= $darkModeValue ?: '' ?>" class="panopticon-view-<?= $view ?>">

<?php // Top header ?>
<?php if (!$isBareDisplay): ?>
	<nav class="navbar navbar-expand-lg bg-primary border-bottom border-2 sticky-top container-xl navbar-dark pt-2 pb-1 px-2 d-print-none"
	     id="topNavbar">
		<h1>
			<?php if (!$isMenuEnabled): ?>
				<div class="navbar-brand ps-2 d-flex flex-row">
					<?= file_get_contents(Template::parsePath('media://images/logo_colour.svg', true, Factory::getApplication())) ?>
					<div>
						<?= $text->text('PANOPTICON_APP_TITLE_SHORT') ?>
						<?php if (in_array($versionTag, [
							Version::TAG_TYPE_DEV, Version::TAG_TYPE_ALPHA, Version::TAG_TYPE_BETA,
						])): ?>
							<sup>
								<span class="badge bg-danger"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php elseif ($versionTag === Version::TAG_TYPE_BETA): ?>
							<sup>
								<span class="badge text-bg-warning"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php elseif ($versionTag === Version::TAG_TYPE_RELEASE_CANDIDATE): ?>
							<sup>
								<span class="badge bg-secondary"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php endif ?>
					</div>
				</div>
			<?php else: ?>
				<a class="navbar-brand ps-2 d-flex flex-row"
				   href="<?= Uri::base() ?>">
					<?= file_get_contents(APATH_MEDIA . '/images/logo_colour.svg') ?>
					<div>
						<?= $text->text('PANOPTICON_APP_TITLE_SHORT') ?>
						<?php if (in_array($versionTag, [
							Version::TAG_TYPE_DEV, Version::TAG_TYPE_ALPHA, Version::TAG_TYPE_BETA,
						])): ?>
							<sup>
								<span class="badge bg-danger"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php elseif ($versionTag === Version::TAG_TYPE_BETA): ?>
							<sup>
								<span class="badge text-bg-warning"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php elseif ($versionTag === Version::TAG_TYPE_RELEASE_CANDIDATE): ?>
							<sup>
								<span class="badge bg-secondary"><?= ucfirst($versionTag) ?></span>
							</sup>
						<?php endif ?>
					</div>
				</a>
			<?php endif ?>

		</h1>
		<?php if ($isMenuEnabled && $user->getId()): ?>
			<button class="navbar-toggler" type="button"
			        data-bs-toggle="collapse" data-bs-target="#topNavbarMenu"
			        aria-controls="topNavbarMenu" aria-expanded="false"
			        aria-label="<?= $text->text('PANOPTICON_APP_LBL_TOGGLE_NAVIGATION') ?>">
				<span class="navbar-toggler-icon"></span>
			</button>
		<?php endif ?>

		<div class="collapse navbar-collapse" id="topNavbarMenu">
			<ul class="navbar-nav ms-auto mb-2 mb-lg-0">
				<?php if ($isMenuEnabled && $user->getId()): ?>
					<?= TemplateHelper::getRenderedMenuItem($this->getMenu()->getMenuItems('main'), onlyChildren: true) ?>
				<?php endif; ?>
			</ul>
		</div>
	</nav>
<?php endif ?>

<?php // Toolbar / page title ?>
<?php if (!empty($this->getToolbar()->getTitle()) || count($this->getToolbar()->getButtons())): ?>
	<section class="navbar container-xl bg-secondary py-3 px-2 d-print-none" id="toolbar"
	         aria-label="<?= $text->text('PANOPTICON_APP_LBL_TOOLBAR') ?>">
		<div class="ms-2 me-auto d-flex flex-row gap-2">
			<?= TemplateHelper::getRenderedToolbarButtons() ?>
		</div>
		 <?php if (!empty($this->getToolbar()->getTitle())): ?>
			<h2 class="navbar-text text-light ps-2 fs-5 py-0 my-0 me-2">
				<?= $this->getToolbar()->getTitle() ?>
			</h2>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php // Main Content ?>
<main class="container-xl py-2 min-vh-100">
	<?php // Messages ?>
	<?php if ($messages = TemplateHelper::getRenderedMessages()): ?>
		<section aria-label="<?= $text->text('PANOPTICON_APP_LBL_MESSAGES') ?>">
			<?= $messages ?>
		</section>
	<?php endif ?>
	<?= $this->getBuffer() ?>
</main>

<?php if (!$isBareDisplay): ?>
	<footer class="container-xl bg-dark text-light p-3 pb-3 text-light small sticky-sm-bottom d-print-none" data-bs-theme="dark">
		<?= $text->text('PANOPTICON_APP_TITLE') ?> <?= Version::create(AKEEBA_PANOPTICON_VERSION)->shortVersion(true) ?><?php if (Version::create(AKEEBA_PANOPTICON_VERSION)->hasTag()): ?><span class="text-muted small">.<?= Version::create(AKEEBA_PANOPTICON_VERSION)->tag() ?></span><?php endif; ?>
		<?php if ($isDebug): ?>
			<span class="text-body-tertiary">on</span>
			<span class="text-muted">PHP <?= PHP_VERSION ?>
				<span class="text-body-tertiary">at</span>
				<?= htmlentities($_SERVER['HTTP_HOST']) ?>
				<?php if ($_SERVER['HTTP_HOST'] != php_uname('n')): ?>
				<span class="text-body-tertiary">
					(<?= php_uname('n') ?>)
				</span>
				<?php endif ?>
			</span>
		<?php endif ?>
	</footer>
	<footer class="container-xl bg-dark text-light p-3 pt-1 text-light small d-print-none" data-bs-theme="dark">
		<div class="d-flex flex-column">
			<p class="mb-2">
				<?= $text->sprintf('PANOPTICON_APP_LBL_COPYRIGHT', date('Y')) ?>
			</p>
			<p class="mb-2">
				<?= $text->sprintf('PANOPTICON_APP_LBL_LICENSE', $text->text('PANOPTICON_APP_TITLE')) ?>
			</p>
				<div class="mt-0 mb-0 text-muted d-flex flex-row gap-2">
					<div>
						<span class="fab fa-github text-white" aria-hidden="true"></span>
						<a href="https://github.com/akeeba/panopticon" target="_blank">
							<?= $text->text('PANOPTICON_APP_LBL_SOURCE_CODE') ?>
						</a>
					</div>
					<div>
						<span class="fa fa-address-card" aria-hidden="true"></span>
						<a href="<?= $this->container->router->route('index.php?view=about') ?>">
							<?= $text->text('PANOPTICON_ABOUT_TITLE') ?>
						</a>
					</div>
					<?php if ($isDebug): ?>
					<div>
						<span class="fa fa-clock" title="<?= $text->text('PANOPTICON_APP_LBL_DEBUG_PAGE_CREATION_TIME') ?>"
						      aria-hidden="true"></span>
						<span
							class="visually-hidden"><?= $text->text('PANOPTICON_APP_LBL_DEBUG_PAGE_CREATION_TIME') ?></span>
						<?= sprintf('%0.3f', $this->getApplication()->getTimeElapsed()) ?> <abbr
							title="<?= $text->text('PANOPTICON_APP_LBL_DEBUG_SECONDS') ?>">s</abbr>
					</div>

					<div>
						<span class="fa fa-memory" title="<?= $text->text('PANOPTICON_APP_LBL_DEBUG_PEAK_MEM_USAGE') ?>"
						      aria-hidden="true"></span>
						<span class="visually-hidden"><?= $text->text('PANOPTICON_APP_LBL_DEBUG_PEAK_MEM_USAGE') ?></span>
						<?= sprintf('%0.1f', memory_get_peak_usage() / 1048576) ?> <abbr
							title="<?= $text->text('PANOPTICON_APP_LBL_DEBUG_MEGABYTES') ?>">MiB</abbr>
					</div>
					<?php endif; ?>
				</div>
				<div class="clearfix"></div>
		</div>
	</footer>

<?php endif ?>

</body>
</html>