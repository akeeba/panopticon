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

	protected function onBeforeTableBody(): bool
	{
		// Groups map
		/** @var Groups $groupsModel */
		$groupsModel    = $this->getModel('groups');
		$this->groupMap = $groupsModel->getGroupMap();

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
