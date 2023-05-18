<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Queue;


use Akeeba\Panopticon\Container;

defined('AKEEBA') || die;

class QueueFactory
{
	private $queues = [];

	public function __construct(private Container $container)
	{
	}

	public function makeQueue(string $queueIdentifier): QueueInterface
	{
		if (isset($this->queues[$queueIdentifier]))
		{
			return $this->queues[$queueIdentifier];
		}

		$this->queues[$queueIdentifier] = new MySQLQueue($queueIdentifier, $this->container->db);

		return $this->queues[$queueIdentifier];
	}
}