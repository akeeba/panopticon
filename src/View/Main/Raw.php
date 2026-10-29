<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\View\Main;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Model\Groups;
use Akeeba\Panopticon\Model\Site;
use Akeeba\Panopticon\View\Trait\AkeebaBackupTooOldTrait;
use Awf\Registry\Registry;
use Awf\Uri\Uri;

class Raw extends \Awf\Mvc\DataView\Raw
{
	use AkeebaBackupTooOldTrait;

	/**
	 * The groups currently used in sites.
	 *
	 * @var   array
	 * @since 1.0.5
	 */
	public array $groupMap = [];

	/**
	 * The badge colour of every group currently used in sites.
	 *
	 * @var   array
	 * @since 2.2.1
	 */
	public array $groupColours = [];

	/**
	 * The request URI's query before onBeforeTableBody() rewrote it; restored by onAfterTableBody().
	 *
	 * @var   array|null
	 * @since 2.5.0
	 */
	private ?array $tableBodyQuery = null;

	protected function onBeforeTableBody(): bool
	{
		// [bug #1042] JavaScript polls this endpoint every 30 s and inlines the
		// returned <tbody> into the main dashboard. Action links in the partial
		// (e.g. "Reload Joomla update information") capture Uri::getInstance()
		// for their `return` URL. If we let it point at this endpoint the user
		// lands back on the raw AJAX fragment after clicking the button.
		//
		// The Dispatcher has already populated Uri::getInstance()'s singleton
		// from $_SERVER['REQUEST_URI'], so we mutate the existing instance in
		// place. It must stay rewritten while the template renders, which
		// View::display() does AFTER this method returns; onAfterTableBody()
		// restores it so any post-render code still sees the real request URL.
		//
		// @see https://github.com/akeeba/panopticon/issues/1042
		// @see https://github.com/akeeba/panopticon/issues/1088
		$uri                  = Uri::getInstance();
		$this->tableBodyQuery = $uri->getQuery(true);

		self::rewriteUriForTableBodyFragment($uri);

		// Groups map
		/** @var Groups $groupsModel */
		$groupsModel        = $this->getModel('groups');
		$this->groupMap     = $groupsModel->getGroupMap();
		$this->groupColours = $groupsModel->getGroupColours();

		// Create the lists object
		$this->lists = new \stdClass();

		// Load the model
		/** @var Site $model */
		$model = $this->getModel();
		$model->setState('enabled', 1);

		// We want to persist the state in the session
		$model->savestate(1);

		// Ordering information
		$this->lists->order     = $model->getState('filter_order', 'name', 'cmd');
		$this->lists->order_Dir = $model->getState('filter_order_Dir', 'ASC', 'cmd');

		// Display limits
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('limit', 50, 'int');

		$model->setState('filter_order', $this->lists->order);
		$model->setState('filter_order_Dir', $this->lists->order_Dir);
		$model->setState('limitstart', $this->lists->limitStart);
		$model->setState('limit', $this->lists->limit);

		// Assign items to the view
		$this->items      = $model->get();
		$this->itemsCount = $model->count();

		// Set the layout
		$this->setLayout('default_tbody');

		return true;
	}

	protected function onAfterTableBody(): bool
	{
		// Undo the gh-1042 rewrite from onBeforeTableBody() now that the template has rendered,
		// putting the original query back exactly as it was, parameter order included.
		if ($this->tableBodyQuery !== null)
		{
			Uri::getInstance()->setQuery($this->tableBodyQuery);

			$this->tableBodyQuery = null;
		}

		return true;
	}

	/**
	 * Mutate a Uri instance so it looks like the main dashboard URL. Used by
	 * {@see self::onBeforeTableBody()} to fix the return URL captured by
	 * action links rendered inside the tableBody AJAX fragment (gh-1042).
	 *
	 * Strips the AJAX-specific query parameters (`task`, `format`,
	 * `_cacheBustingJunk`) and forces `view=main`. Other parameters (filter
	 * state, search, pagination, CSRF token) are preserved so the user lands
	 * back on the same filtered view after the action completes.
	 *
	 * The method is public and static so it can be unit-tested without
	 * touching the global Uri singleton.
	 *
	 * @param   Uri  $uri  The Uri instance to mutate in place.
	 *
	 * @return  void
	 * @since   2.3.1
	 */
	public static function rewriteUriForTableBodyFragment(Uri $uri): void
	{
		$uri->setVar('view', 'main');
		$uri->delVar('task');
		$uri->delVar('format');
		$uri->delVar('_cacheBustingJunk');
	}

	/**
	 * Retrieves the extensions from the given site configuration.
	 *
	 * @param   Registry  $config  The Registry object containing the site configuration.
	 *
	 * @return  array  An array of extensions.
	 * @since   1.0.6
	 */
	protected function getExtensions(Registry $config): array
	{
		return get_object_vars($config->get('extensions.list', new \stdClass()));
	}

	/**
	 * Retrieves the number of extensions that have updates available.
	 *
	 * @param   array  $extensions  An array of extension objects.
	 *
	 * @return  int  The number of extensions with available updates.
	 * @since   1.0.6
	 */
	protected function getNumberOfExtensionUpdates(array $extensions): int
	{
		return array_reduce(
			$extensions,
			function (int $carry, object $item): int {
				$current = $item?->version?->current;
				$new     = $item?->version?->new;

				if (empty($new))
				{
					return $carry;
				}

				return $carry + ((empty($current) || version_compare($current, $new, 'ge')) ? 0 : 1);
			},
			0
		);
	}

	/**
	 * Retrieves the number of extensions with missing Download Keys.
	 *
	 * @param   array  $extensions  An array of objects representing the extensions.
	 *
	 * @return  int The number of extensions with missing Download Keys.
	 * @since   1.0.6
	 */
	protected function getNumberOfKeyMissingExtensions(array $extensions): int
	{
		return array_reduce(
			$extensions,
			function (int $carry, object $item): int {
				$downloadkey = $item?->downloadkey ?? null;

				return $carry + (
					!$downloadkey?->supported || $downloadkey?->valid ? 0 : 1
					);
			},
			0
		);
	}

	/**
	 * Retrieves the last error message from the extensions update process.
	 *
	 * @param   Registry  $config  The site configuration registry object.
	 *
	 * @return  string  The last error message from the extensions update process. Returns an empty string if no errors
	 *                  occurred.
	 * @since   1.0.6
	 */
	protected function getLastExtensionsUpdateError(Registry $config): string
	{
		return trim($config->get('extensions.lastErrorMessage') ?? '');
	}
}
