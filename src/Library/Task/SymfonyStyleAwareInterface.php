<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Task;

defined('AKEEBA') || die;

use Symfony\Component\Console\Style\SymfonyStyle;

interface SymfonyStyleAwareInterface
{
	public function setSymfonyStyle(SymfonyStyle $ioStyle): void;
}