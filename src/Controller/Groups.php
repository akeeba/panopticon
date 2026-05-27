<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Controller;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Controller\Trait\ACLTrait;
use Awf\Mvc\DataController;

class Groups extends DataController
{
	use ACLTrait;

	public function execute($task)
	{
		$this->aclCheck($task);

		return parent::execute($task);
	}

	protected function onBeforeApplySave(array|object|null &$data)
	{
		$data = (array)$data;

		$permissions = array_keys($data['permissions'] ?? []);
		$this->getModel()->setPrivileges($permissions);
		unset($data['permissions']);

		// Handle the optional per-group API token limit (empty string → NULL).
		$rawLimit = isset($data['api_token_limit']) ? trim((string) $data['api_token_limit']) : '';

		if ($rawLimit === '')
		{
			$this->getModel()->api_token_limit = null;
		}
		else
		{
			$this->getModel()->api_token_limit = max(0, (int) $rawLimit);
		}

		unset($data['api_token_limit']);
	}
}