<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Reports;


use Akeeba\Panopticon\View\Reports\Html;

defined('AKEEBA') || die;

class Raw extends Html
{
	protected function onBeforeMain(): bool
	{
		if (!is_string($this->viewOutput))
		{
			return parent::onBeforeMain();
		}

		$this->getContainer()->application->getDocument()->setMimeType('text/html');

		$title = $this->getContainer()->mvcFactory->makeTempModel('Sites')->findOrFail($this->getModel()->getState('site_id', null))->name;
		$style = $this->getModel('Mailtemplates')->getCommonCss();
		$this->viewOutput .= <<< HTML
<html>
<head>
<style>
$style
</style>
<title>$title</title>
</head>
<body>
HTML;

		return parent::onBeforeMain();
	}

	protected function onAfterMain(): bool
	{
		if (!is_string($this->viewOutput))
		{
			return true;
		}

		$this->viewOutput .= <<< HTML

</body>
</html>
HTML;
		return true;
	}
}