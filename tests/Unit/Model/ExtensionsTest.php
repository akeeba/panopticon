<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Model;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Extensions;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for the version sorting and version filters of the Extensions model (gh discussion 1085).
 *
 * The model's public entry point reads the sites table, so these tests drive the private pure-PHP steps directly.
 */
class ExtensionsTest extends AbstractUnitTestCase
{
	private function makeModel(array $state = []): Extensions
	{
		$model = new Extensions(Factory::getContainer());
		$model->setIgnoreRequest(true);

		foreach ($state as $k => $v)
		{
			$model->setState($k, $v);
		}

		$names = new ReflectionProperty($model, 'siteNames');
		$names->setValue($model, [1 => 'Alpha', 2 => 'bravo', 3 => 'Charlie']);

		return $model;
	}

	private function ext(int $site, string $name, string $current, ?string $new = null): object
	{
		return (object) [
			'site_id' => $site,
			'name'    => $name,
			'type'    => 'component',
			'element' => strtolower($name),
			'folder'  => '',
			'client_id' => 1,
			'version' => (object) ['current' => $current, 'new' => $new],
		];
	}

	private function call(Extensions $model, string $method, array $args): mixed
	{
		return (new ReflectionMethod($model, $method))->invoke($model, ...$args);
	}

	private function sites(array $items): array
	{
		return array_map(fn($e) => $e->site_id, $items);
	}

	public function testNoSortKeepsNaturalOrder(): void
	{
		$items = [$this->ext(3, 'X', '1.0'), $this->ext(1, 'X', '2.0'), $this->ext(2, 'X', '1.5')];

		foreach ([[], ['filter_order' => 'bogus'], ['filter_order' => 'id']] as $state)
		{
			$out = $this->call($this->makeModel($state), 'sortExtensions', [$items]);

			$this->assertSame([3, 1, 2], $this->sites($out));
		}
	}

	public function testSortByVersionUsesVersionCompareNotStringCompare(): void
	{
		$items = [$this->ext(1, 'X', '5.0.9'), $this->ext(2, 'X', '5.0.10'), $this->ext(3, 'X', '5.0.2')];

		$asc  = $this->call($this->makeModel(['filter_order' => 'version', 'filter_order_Dir' => 'asc']), 'sortExtensions', [$items]);
		$desc = $this->call($this->makeModel(['filter_order' => 'version', 'filter_order_Dir' => 'DESC']), 'sortExtensions', [$items]);

		$this->assertSame([3, 1, 2], $this->sites($asc));
		$this->assertSame([2, 1, 3], $this->sites($desc));
	}

	public function testSortBySiteIsCaseInsensitiveAndTiesAreStable(): void
	{
		$items = [$this->ext(3, 'X', '1'), $this->ext(2, 'X', '1'), $this->ext(1, 'X', '1')];

		$asc = $this->call($this->makeModel(['filter_order' => 'site']), 'sortExtensions', [$items]);

		$this->assertSame([1, 2, 3], $this->sites($asc));
	}

	public function testInstalledVersionFilterAndDropdownOptions(): void
	{
		$items = [$this->ext(1, 'X', '5.0.53'), $this->ext(2, 'X', '5.0.5'), $this->ext(3, 'X', '5.0.53')];
		$model = $this->makeModel(['extension_name' => 'X', 'installed_version' => '5.0.5']);

		$out = $this->call($model, 'applyVersionFilters', [$items]);

		$this->assertSame([2], $this->sites($out), 'Exact match: 5.0.5 must not match 5.0.53');
		$this->assertSame(['5.0.53', '5.0.5'], $model->getInstalledVersions(), 'Options come from unfiltered rows, newest first');
	}

	public function testStaleInstalledVersionIsIgnored(): void
	{
		$items = [$this->ext(1, 'X', '1.0'), $this->ext(2, 'X', '2.0')];

		// No extension selected: the dropdown is empty, so a leftover session value must not filter.
		$out = $this->call($this->makeModel(['installed_version' => '1.0']), 'applyVersionFilters', [$items]);
		$this->assertCount(2, $out);

		// A version the selected extension doesn't have.
		$out = $this->call($this->makeModel(['extension_name' => 'X', 'installed_version' => '9.9']), 'applyVersionFilters', [$items]);
		$this->assertCount(2, $out);
	}

	public function testOutdatedFilter(): void
	{
		$items = [
			$this->ext(1, 'X', '1.0', '2.0'),
			$this->ext(2, 'X', '2.0', '2.0'),
			$this->ext(3, 'X', '2.0', null),
			$this->ext(4, 'X', '', '2.0'),
		];

		$out = $this->call($this->makeModel(['version_status' => 'outdated']), 'applyVersionFilters', [$items]);

		$this->assertSame([1], $this->sites($out));
	}

	public function testNotHighestFilterIsPerExtension(): void
	{
		$items = [
			$this->ext(1, 'X', '5.0.53'),
			$this->ext(2, 'X', '5.0.9'),
			$this->ext(3, 'X', '5.0.53'),
			$this->ext(1, 'Y', '1.0'),
			$this->ext(2, 'Y', '1.0'),
		];

		$out = $this->call($this->makeModel(['version_status' => 'not_highest']), 'applyVersionFilters', [$items]);

		$this->assertSame([2], $this->sites($out));
		$this->assertSame('X', $out[0]->name);
	}

	public function testEmptyStatesDoNotFilter(): void
	{
		$items = [$this->ext(1, 'X', '1.0', '2.0'), $this->ext(2, 'X', '2.0')];

		$out = $this->call($this->makeModel(['version_status' => '', 'installed_version' => '']), 'applyVersionFilters', [$items]);

		$this->assertCount(2, $out);
	}
}
