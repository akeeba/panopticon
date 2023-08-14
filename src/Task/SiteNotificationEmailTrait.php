<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Task;


defined('AKEEBA') || die;

trait SiteNotificationEmailTrait
{
	protected function getSiteNotificationEmails(?object $siteConfig): array
	{
		return array_map(
			function (string $item)
			{
				$item = trim($item);

				if (!str_contains($item, '<'))
				{
					return [$item, ''];
				}

				[$name, $email] = explode('<', $item, 2);
				$name  = trim($name);
				$email = trim(
					str_contains($email, '>')
						? substr($email, 0, strrpos($email, '>') - 1)
						: $email
				);

				return [$email, $name];
			},
			explode(',', $siteConfig?->config?->core_update?->email?->cc ?? "")
		);
	}
}