<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Symfony\Component\Console\Style\SymfonyStyle;

trait SymfonyStyleAwareTrait
{
	protected ?SymfonyStyle $ioStyle = null;

	public function setSymfonyStyle(SymfonyStyle $ioStyle): void
	{
		$this->ioStyle = $ioStyle;
	}
}