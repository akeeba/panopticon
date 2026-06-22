<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mcp\Tool;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Enumerations\ApiScope;
use Akeeba\Panopticon\Library\Mcp\AbstractTool;
use Akeeba\Panopticon\Model\Sysconfig as SysconfigModel;

/**
 * MCP tool: read the non-sensitive system configuration.
 *
 * Mirrors `GET /api/v1/sysconfig` (Super User only). Sensitive keys (database password, secret, SMTP credentials, …)
 * are omitted entirely by the Sysconfig model — they are never returned.
 *
 * @since  2.2.0
 */
class GetSysconfig extends AbstractTool
{
	public function getName(): string
	{
		return 'get_sysconfig';
	}

	public function getDescription(): string
	{
		return 'Read the non-sensitive Panopticon system configuration as key/value pairs. Secrets such as database '
			. 'and SMTP credentials are never included. Super User only.';
	}

	public function getRequiredScope(): ?ApiScope
	{
		return ApiScope::SysconfigRead;
	}

	public function isSuperUserOnly(): bool
	{
		return true;
	}

	public function getInputSchema(): array
	{
		return [
			'type'       => 'object',
			'properties' => (object) [],
		];
	}

	public function __invoke(): array
	{
		$this->assertSuperUser();

		/** @var SysconfigModel $model */
		$model = $this->container->mvcFactory->makeTempModel('Sysconfig');

		return (array) $model->getNonSensitiveConfig();
	}
}
