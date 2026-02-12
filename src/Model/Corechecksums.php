<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

defined('AKEEBA') || die;

use Awf\Mvc\Model;

/**
 * Model for viewing core file integrity checksum results.
 *
 * Reads modified files list from the site configuration. No remote API calls needed.
 *
 * @since  1.3.0
 */
class Corechecksums extends Model
{
	private ?Site $site = null;

	public function setSite(Site $site): void
	{
		$this->site = $site;
	}

	/**
	 * Get the list of modified core files.
	 *
	 * @return  array
	 * @since   1.3.0
	 */
	public function getModifiedFiles(): array
	{
		if ($this->site === null)
		{
			return [];
		}

		return $this->site->coreChecksumsGetModifiedFiles();
	}

	/**
	 * Get the last check timestamp.
	 *
	 * @return  int|null
	 * @since   1.3.0
	 */
	public function getLastCheck(): ?int
	{
		if ($this->site === null)
		{
			return null;
		}

		return $this->site->coreChecksumsGetLastCheck();
	}

	/**
	 * Get the last check status.
	 *
	 * @return  bool|null
	 * @since   1.3.0
	 */
	public function getLastStatus(): ?bool
	{
		if ($this->site === null)
		{
			return null;
		}

		$status = $this->site->getConfig()->get('core.coreChecksums.lastStatus', null);

		return $status === null ? null : (bool) $status;
	}
}
