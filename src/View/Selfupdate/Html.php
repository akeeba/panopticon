<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Selfupdate;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\SelfUpdate\UpdateInformation;
use Akeeba\Panopticon\Library\SelfUpdate\VersionInformation;
use Akeeba\Panopticon\Model\Selfupdate;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	public bool $force = false;

	public ?UpdateInformation $updateInformation = null;

	public ?VersionInformation $latestversion = null;

	public $hasUpdate = false;

	public function onBeforeMain(): bool
	{
		/** @var Selfupdate $model */
		$model = $this->getModel();

		$this->updateInformation = $model->getUpdateInformation($this->force);
		$this->latestversion     = $model->getLatestVersion();
		$this->hasUpdate         = $model->hasUpdate();

		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=main')]);

		$this->setTitle($this->getLanguage()->text('PANOPTICON_SELFUPDATE_TITLE'));

		return true;
	}

	public function onBeforePreupdate(): bool
	{
		$document = $this->container->application->getDocument();
		$document->getMenu()->disableMenu();

		$js = <<< JS

setInterval(() => {
    const hourglass = document.getElementById('hourglass');
    
    if (hourglass.classList.contains('fa-hourglass-start')) {
        hourglass.classList.remove('fa-hourglass-start');
        hourglass.classList.add('fa-hourglass-half');
    } else if (hourglass.classList.contains('fa-hourglass-half')) {
        hourglass.classList.remove('fa-hourglass-half');
        hourglass.classList.add('fa-hourglass-end');
	} else if (hourglass.classList.contains('fa-hourglass-end') && !hourglass.classList.contains('fa-rotate-90')) {
        hourglass.classList.add('fa-rotate-90');
    } else {
        hourglass.classList.remove('fa-hourglass-end', 'fa-rotate-90');
        hourglass.classList.add('fa-hourglass-start');
	}
    
}, 420);

setTimeout(() => {
    window.location.reload();
}, 5000);

JS;
		$document->addScriptDeclaration($js);

		return true;
	}
}