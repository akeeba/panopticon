<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Panopticon\Library\Queue;


use JsonSerializable;

defined('AKEEBA') || die;

class QueueItem implements JsonSerializable
{
	public function __construct(private mixed $data, private string $queueType = 'system', private ?int $siteId = null)
	{
		if (!is_scalar($this->data) && !is_object($this->data))
		{
			throw new \InvalidArgumentException(
				sprintf(
					'%s can only store scalars or objects', __CLASS__
				)
			);
		}

		if ($this->data instanceof self || $this->data instanceof QueueInterface)
		{
			throw new \InvalidArgumentException(
				sprintf(
					'%s cannot store recursive queues or queue items', __CLASS__
				)
			);
		}
	}

	public function getData(): mixed
	{
		return $this->data;
	}

	public function getQueueType(): string
	{
		return $this->queueType;
	}

	public function getSiteId(): ?int
	{
		return $this->siteId;
	}

	public static function fromJson(string $json): self
	{
		$temp      = json_decode($json, false);
		$data      = $temp?->data;
		$queueType = $temp->queueType ?? 'system';
		$siteId    = $temp?->siteId;
		$dataType  = $temp?->dataType;

		unset($temp);

		switch ($dataType)
		{
			case 'array':
				$data = json_decode(json_encode($data), true);
				break;

			default:
				if (class_exists($dataType) && is_callable([$dataType, 'fromJson']))
				{
					$data = call_user_func([$dataType, 'fromJson'], json_encode($data));
				}

				break;
		}

		return new self($data, $queueType, $siteId);
	}

	public function jsonSerialize(): array
	{
		$dataType = is_scalar($this->data) ? null : get_class($this->data);

		return [
			'data' => $this->data,
			'queueType' => $this->queueType,
			'siteId' => $this->siteId,
			'dataType' => $dataType
		];
	}
}