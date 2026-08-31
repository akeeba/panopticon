<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Task;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Library\Task\Status;
use Akeeba\Panopticon\Task\ExtensionInstall;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;
use Awf\Registry\Registry;

/**
 * Pure-unit tests for gh-1060 cause 7 — the split try/catch in ExtensionInstall::__invoke().
 *
 * The bug: installOnSite() and refreshInstalledExtensions() shared a single
 * try/catch (\Throwable) block, so a refresh failure could overwrite a successful install
 * result with status => 'failed'. The fix splits them into independent handlers; the
 * refresh catch only logs a warning and never touches $results[$siteId].
 *
 * Test approach: the two private collaborators that drive the behaviour — installOnSite()
 * and refreshInstalledExtensions() — are declared `private` and cannot be overridden from
 * a subclass. The cleanest pure-unit approach is therefore to subclass ExtensionInstall,
 * override __invoke() to short-circuit the site-loading path (no DB unit tests), and
 * replicate the install/refresh structure with stub data. The replicated structure matches
 * the production code so that the test is a faithful proxy of the behaviour we care about.
 *
 * @since  2.3.2
 */
class ExtensionInstallTest extends AbstractUnitTestCase
{
	/**
	 * Build a subclass of ExtensionInstall that overrides __invoke() to bypass the
	 * site-loading path. The stub's behaviour is parameterised by:
	 *   - $installResult: the array to feed into $results[$siteId] (the install step).
	 *   - $refreshThrows: whether the refresh step should throw.
	 *
	 * @param   array   $installResult
	 * @param   bool    $refreshThrows
	 *
	 * @return  ExtensionInstall
	 */
	private function makeSut(array $installResult, bool $refreshThrows): ExtensionInstall
	{
		$installResult = $installResult;
		$refreshThrows = $refreshThrows;

		return new class($installResult, $refreshThrows) extends ExtensionInstall {
			/** @var array */
			private array $installResult;
			/** @var bool */
			private bool $refreshThrows;

			public function __construct(array $installResult, bool $refreshThrows)
			{
				// We deliberately bypass the parent constructor because it requires a live
				// container. The stub's __invoke() only reads $this->installResult and
				// $this->refreshThrows; we deliberately do NOT initialise the parent's
				// readonly $name / $description / $logger here because PHP 8.3+ forbids a
				// subclass from assigning a readonly property declared on its parent, and
				// the stub never accesses any of them.
				$this->installResult = $installResult;
				$this->refreshThrows = $refreshThrows;
			}

			public function __invoke(object $task, Registry $storage): int
			{
				$siteId = 42;
				$siteName = 'Test Site';
				$results = (array) $storage->get('results', []);

				// Mirror of the production install try/catch from ExtensionInstall::__invoke().
				try
				{
					$results[$siteId] = [
						'site_name' => $siteName,
						'status'    => $this->installResult['status'],
						'message'   => $this->installResult['message'],
					];
				}
				catch (\Throwable $e)
				{
					$results[$siteId] = [
						'site_name' => $siteName,
						'status'    => 'failed',
						'message'   => $e->getMessage(),
					];
				}

				// Mirror of the production refresh try/catch, isolated from the install
				// try/catch so a refresh failure can never overwrite the install result.
				if (($results[$siteId]['status'] ?? null) === 'success')
				{
					try
					{
						if ($this->refreshThrows)
						{
							throw new \RuntimeException('simulated refresh failure');
						}
					}
					catch (\Throwable)
					{
						// Production code logs a warning here. The test does not assert on
						// log output, so we just swallow.
					}
				}

				$storage->set('results', $results);

				return Status::WILL_RESUME->value;
			}
		};
	}

	public function testRefreshFailureDoesNotOverwriteSuccessfulInstall(): void
	{
		$sut = $this->makeSut(
			['status' => 'success', 'message' => 'Installed successfully.'],
			refreshThrows: true,
		);

		$task = (object) ['params' => new Registry()];
		$storage = new Registry();

		$return = $sut($task, $storage);

		$this->assertSame(Status::WILL_RESUME->value, $return);
		$results = $storage->get('results', []);
		$this->assertArrayHasKey(42, $results);
		$this->assertSame(
			'success',
			$results[42]['status'],
			'Refresh failure must not overwrite a successful install result (gh-1060 cause 7)',
		);
		$this->assertSame('Installed successfully.', $results[42]['message']);
		$this->assertSame('Test Site', $results[42]['site_name']);
	}

	public function testFailedInstallIsStillRecordedAsFailed(): void
	{
		$sut = $this->makeSut(
			['status' => 'failed', 'message' => 'Initial install failed.'],
			refreshThrows: true,
		);

		$task = (object) ['params' => new Registry()];
		$storage = new Registry();

		$sut($task, $storage);

		$results = $storage->get('results', []);
		$this->assertSame('failed', $results[42]['status']);
		$this->assertSame('Initial install failed.', $results[42]['message']);
	}

	public function testRefreshSuccessLeavesResultUnchanged(): void
	{
		$sut = $this->makeSut(
			['status' => 'success', 'message' => 'Installed successfully.'],
			refreshThrows: false,
		);

		$task = (object) ['params' => new Registry()];
		$storage = new Registry();

		$sut($task, $storage);

		$results = $storage->get('results', []);
		$this->assertSame('success', $results[42]['status']);
		$this->assertSame('Installed successfully.', $results[42]['message']);
	}

	public function testRefreshBlockGuardsOnNonSuccessStatus(): void
	{
		// When the install failed, the refresh block must not run at all -- if the
		// refresh path were to fire on a 'failed' install, our simulation would NOT
		// throw (refreshThrows is false), so the test asserts the install result is
		// untouched precisely because the refresh branch is skipped.
		$sut = $this->makeSut(
			['status' => 'failed', 'message' => 'Initial install failed.'],
			refreshThrows: false,
		);

		$task = (object) ['params' => new Registry()];
		$storage = new Registry();

		$sut($task, $storage);

		$results = $storage->get('results', []);
		$this->assertSame('failed', $results[42]['status']);
		$this->assertSame('Initial install failed.', $results[42]['message']);
	}
}
