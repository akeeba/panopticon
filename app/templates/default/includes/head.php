<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBA') || die;

use Awf\Text\Text;
use Awf\Uri\Uri;

/** @var Awf\Document\Document $this */
?>
<?php // Client-side options ?>
<script type="application/json" class="akeeba-script-options new"><?= json_encode($this->getScriptOptions(), (defined('AKEEBADEBUG') && AKEEBADEBUG && defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : false)) ?: '{}' ?></script>
<?php // Stylesheet files ?>
<?php foreach ($this->getStyles() as $url => $params): ?>
	<link rel="stylesheet" type="<?= $params['mime'] ?>" href="<?= $url ?>"<?= ($params['media']) ? " media=\"{$params['media']}\"" : '' ?><?= ($params['attribs'] ?? null) ? ' ' . \Awf\Utils\ArrayHelper::toString($params['attribs']) : '' ?>>
<?php endforeach ?>
<?php // Inline Stylesheets ?>
<?php foreach ($this->getStyleDeclarations() as $type => $content): ?>
	<style type="<?= $type ?>"><?= $content ?></style>
<?php endforeach ?>
<?php // Script files ?>
<?php foreach ($this->getScripts() as $url => $params): ?>
	<script type="<?= $params['mime'] ?>" src="<?= $url ?>"<?= ($params['defer'] ?? false) ? ' defer="defer"' : '' ?><?= ($params['async'] ?? false) ? ' async="async"' : '' ?>></script>
<?php endforeach ?>
<?php foreach ($this->getScriptDeclarations() as $type => $content): ?>
	<script type="<?= $type ?>"><?= $content ?></script>
<?php endforeach ?>
