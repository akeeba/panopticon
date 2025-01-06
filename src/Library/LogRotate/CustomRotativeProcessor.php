<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\LogRotate;

defined('AKEEBA') || die;

use Cesargb\Log\Processors\RotativeProcessor;

/**
 * Class CustomRotativeProcessor
 *
 * Extends the AbstractProcessor class and provides custom functionality for log file rotation.
 *
 * Our implementation uses a different naming pattern than the default RotativeProcessor.
 */
class CustomRotativeProcessor extends RotativeProcessor
{
	private int $maxFiles = 366;

	/**
	 * Log files are rotated count times before being removed.
	 */
	public function files(int $count): self
	{
		$this->maxFiles = $count;

		return $this;
	}

	public function handler(string $filename): ?string
	{
		$filenameTarget = "{$this->filenameSource}.1.rotated";

		$this->rotate();

		rename($filename, $filenameTarget);

		return $this->processed($filenameTarget);
	}

	private function rotate(int $number = 1): string
	{
		$filenameTarget = "{$this->filenameSource}.{$number}.rotated{$this->extension}";

		if (!file_exists($filenameTarget)) {
			return $filenameTarget;
		}

		if ($this->maxFiles > 0 && $number >= $this->maxFiles) {
			unlink($filenameTarget);

			return $filenameTarget;
		}

		$nextFilename = $this->rotate($number + 1);

		rename($filenameTarget, $nextFilename);

		return $filenameTarget;
	}
}