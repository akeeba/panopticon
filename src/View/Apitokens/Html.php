<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Apitokens;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Apitoken;
use Akeeba\Panopticon\View\Trait\CrudTasksTrait;
use Awf\Mvc\DataView\Html as BaseHtmlView;
use Awf\Uri\Uri;
use Awf\Utils\Template;

class Html extends BaseHtmlView
{
	use CrudTasksTrait;

	/** @var array Token rows for the current user */
	public array $tokens = [];

	/** @var string The API endpoint base URL */
	public string $apiUrl = '';

	protected function onBeforeMain(): bool
	{
		$this->addButton('back', ['url' => $this->container->router->route('index.php?view=main')]);
		$this->setTitle($this->getLanguage()->text('PANOPTICON_APITOKENS_TITLE'));

		$user = $this->container->userManager->getUser();

		/** @var Apitoken $model */
		$model        = $this->container->mvcFactory->makeTempModel('Apitoken');
		$this->tokens = $model->getTokensForUser($user->getId());

		// Compute the API endpoint URL
		$base = Uri::base();
		$this->apiUrl = rtrim($base, '/') . '/index.php/api';

		// Add JS
		Template::addJs('media://js/apitokens.js', $this->container->application);

		// Pass options to JavaScript
		$document = $this->container->application->getDocument();
		$document->addScriptOptions('panopticon.apitokens', [
			'createUrl'   => $this->container->router->route('index.php?view=apitokens&task=create&format=json'),
			'toggleUrl'   => $this->container->router->route('index.php?view=apitokens&task=toggle&format=json'),
			'removeUrl'   => $this->container->router->route('index.php?view=apitokens&task=remove&format=json'),
			'tokenUrl'    => $this->container->router->route('index.php?view=apitokens&task=getTokenValue&format=json'),
			'csrfToken'   => $this->container->session->getCsrfToken()->getValue(),
		]);

		return true;
	}
}
