<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Queue;

defined('AKEEBA') || die;

use Countable;
use DateTime;

interface QueueInterface extends Countable
{
	public function push(QueueItem $item, int|DateTime|string|null $time = null): void;

	public function pop(): ?QueueItem;

	public function clear(array $conditions = []): void;

	public function countByCondition(array $conditions = []): int;
}