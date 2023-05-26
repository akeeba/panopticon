<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Library\Version;

/**
 * Panopticon version library
 *
 * Based on VersionParser by Sebastian Mordziol <s.mordziol@mistralys.eu>
 *
 * The original code carries the following copyright notice:
 * ====== ORIGINAL COPYRIGHT NOTICE START ======
 * MIT License
 *
 * Copyright (c) 2020 Mistralys
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * ====== ORIGINAL COPYRIGHT NOTICE END ======
 *
 * @since 1.0.0
 */
class Version
{
	const TAG_TYPE_NONE = 'none';

	const TAG_TYPE_DEV = 'dev';

	const TAG_TYPE_BETA = 'beta';

	const TAG_TYPE_ALPHA = 'alpha';

	const TAG_TYPE_RELEASE_CANDIDATE = 'rc';

	private static ?self $instance = null;

	private string $version;

	private string $tag = '';

	private array $parts = [];

	private string $tagType = self::TAG_TYPE_NONE;

	private int $tagNumber = 0;

	private string $branchName = '';

	private array $tagWeights = [
		self::TAG_TYPE_DEV               => 8,
		self::TAG_TYPE_ALPHA             => 6,
		self::TAG_TYPE_BETA              => 4,
		self::TAG_TYPE_RELEASE_CANDIDATE => 2,
		self::TAG_TYPE_NONE              => 0,
	];

	private function __construct(string $version = '')
	{
		$this->version = $version ?: AKEEBA_PANOPTICON_VERSION;

		$this->parse();
		$this->postParse();
	}

	public static function getInstance(): self
	{
		if (empty(self::$instance))
		{
			self::$instance = self::create(AKEEBA_PANOPTICON_VERSION);
		}

		return self::$instance;
	}

	public static function create(string $version): self
	{
		return new self($version);
	}

	public function major(): int
	{
		return $this->parts[0];
	}

	public function minor(): int
	{
		return $this->parts[1];
	}

	public function patch(): int
	{
		return $this->parts[2];
	}

	public function fullVersion(): string
	{
		$version = $this->version;

		if (!$this->hasTag())
		{
			return $version;
		}

		return $version . '-' . $this->tag();
	}

	public function shortVersion(bool $forceThreeParts = false): string
	{
		$keep = [];

		if ($forceThreeParts || $this->parts[2] > 0)
		{
			$keep = $this->parts;
		}
		else if ($this->parts[1] > 0)
		{
			$keep = [$this->parts[0], $this->parts[1]];
		}
		else
		{
			$keep = [$this->parts[0]];
		}

		return implode('.', $keep);
	}

	public function versionFamily(): string
	{
		return implode('.', [$this->major(), $this->minor()]);
	}

	public function tag(): string
	{
		return $this->tag;
	}

	public function hasTag(): bool
	{
		return !empty($this->tag);
	}

	public function tagType(): string
	{
		return $this->tagType;
	}

	public function tagNumber(): int
	{
		return $this->tagNumber;
	}

	public function isBeta(): bool
	{
		return $this->tagType() === self::TAG_TYPE_BETA;
	}

	public function isAlpha(): bool
	{
		return $this->tagType() === self::TAG_TYPE_ALPHA;
	}

	public function isRC(): bool
	{
		return $this->tagType() === self::TAG_TYPE_RELEASE_CANDIDATE;
	}

	public function isDev(): bool
	{
		return $this->tagType() === self::TAG_TYPE_DEV;
	}

	public function isStable(): bool
	{
		return $this->tagType() === self::TAG_TYPE_NONE;
	}

	public function isTesting(): bool
	{
		return !$this->isStable();
	}

	/**
	 * Whether a branch name is present in the version.
	 *
	 * @return bool
	 */
	public function hasBranch(): bool
	{
		return !empty($this->branchName);
	}

	public function branchName(): string
	{
		return $this->branchName;
	}

	private function parse(): void
	{
		$parts = explode('.', $this->extractTag());
		$parts = array_map('trim', $parts);

		while (count($parts) < 3)
		{
			$parts[] = 0;
		}

		for ($i = 0; $i < 3; $i++)
		{
			$this->parts[] = intval($parts[$i]);
		}
	}

	private function extractTag(): string
	{
		$version = $this->version;
		$version = str_replace('_', '-', $version);

		$hyphen = strpos($version, '-');

		if ($hyphen !== false)
		{
			$tag     = substr($version, $hyphen + 1);
			$version = substr($version, 0, $hyphen);
			$this->parseTag($tag);
		}

		return $version;
	}

	private function postParse(): void
	{
		$this->tag = $this->normalizeTag();
	}

	private function normalizeTag(): string
	{
		if ($this->tagType === self::TAG_TYPE_NONE)
		{
			return $this->branchName();
		}

		$tag = $this->tagType;

		if ($this->tagNumber > 1)
		{
			$tag .= $this->tagNumber;
		}

		if ($this->hasBranch())
		{
			$tag = $this->branchName() . '-' . $tag;
		}

		return $tag;
	}

	private function formatTagNumber(): string
	{
		$positions = 2 * 3;
		$weight    = $this->tagWeights[$this->tagType()];

		if ($weight > 0)
		{
			$number = sprintf('%0' . $weight . 'd', $this->tagNumber);
			$number = str_pad($number, $positions, '0', STR_PAD_RIGHT);

			$number = intval(str_repeat('9', $positions)) - intval($number);

			return '.' . $number;
		}

		return '';
	}

	private function parseTag(string $tag): void
	{
		$parts = explode('-', $tag);

		foreach ($parts as $part)
		{
			$this->parseTagPart($part);
		}

		if ($this->tagNumber === 0)
		{
			$this->tagNumber = 1;
		}

		if ($this->tagType === self::TAG_TYPE_NONE)
		{
			$this->tagNumber = 0;
		}
	}

	private function parseTagPart(string $part): void
	{
		if (is_numeric($part))
		{
			$this->tagNumber = intval($part);

			return;
		}

		$types = array_keys($this->tagWeights);
		$type  = '';
		$lower = strtolower($part);

		foreach ($types as $tagType)
		{
			if (strstr($lower, $tagType))
			{
				$type = $tagType;
				$part = str_replace($tagType, '', $lower);
			}
		}

		if (empty($type))
		{
			if (!empty($part))
			{
				$this->branchName = $part;
			}

			return;
		}

		$this->tagType = $type;

		if (is_numeric($part))
		{
			$this->tagNumber = intval($part);
		}
	}
}
