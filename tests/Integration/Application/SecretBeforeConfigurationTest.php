<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\Application;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Application\BootstrapUtilities;
use Akeeba\Panopticon\Application\ContainerServices\SegmentProvider;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use ReflectionProperty;

/**
 * The installation secret must be the same before and after config.php is first written.
 *
 * The session segment's name is derived from the secret. During setup there is no config.php yet, so the secret only
 * lives in tmp/secret.php. If the runtime configuration does not use it, the segment name changes as soon as
 * config.php is written, and the administrator who was just created is logged out.
 *
 * @since 2.4.0
 */
class SecretBeforeConfigurationTest extends AbstractIntegrationTestCase
{
	private const PENDING_SECRET = 'PendingSecretFromTmpSecretPhpAbcdefghijklmnopqrstuvwxyz0123456789';

	private mixed $originalSecret;

	private mixed $originalPendingSecret;

	private mixed $originalVolatile = null;

	private ?string $originalTmpSecretFile = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalSecret        = $this->container->appConfig->get('secret');
		$this->originalPendingSecret = $this->pendingSecret()->getValue();

		if (property_exists(BootstrapUtilities::class, 'secretIsVolatile'))
		{
			$this->originalVolatile = $this->volatileFlag()->getValue();
		}

		$tmpSecretFile = APATH_TMP . '/secret.php';

		if (is_file($tmpSecretFile))
		{
			$this->originalTmpSecretFile = file_get_contents($tmpSecretFile);
		}
	}

	protected function tearDown(): void
	{
		$this->container->appConfig->set('secret', $this->originalSecret);
		$this->pendingSecret()->setValue(null, $this->originalPendingSecret);

		if ($this->originalVolatile !== null)
		{
			$this->volatileFlag()->setValue(null, $this->originalVolatile);
		}

		$tmpSecretFile = APATH_TMP . '/secret.php';

		if ($this->originalTmpSecretFile !== null)
		{
			file_put_contents($tmpSecretFile, $this->originalTmpSecretFile);
		}
		elseif (is_file($tmpSecretFile))
		{
			unlink($tmpSecretFile);
		}

		parent::tearDown();
	}

	public function testPendingSecretIsUsedBeforeConfigurationExists(): void
	{
		$this->pendingSecret()->setValue(null, self::PENDING_SECRET);
		$this->container->appConfig->set('secret', '');

		BootstrapUtilities::loadConfiguration(false);

		$this->assertSame(
			self::PENDING_SECRET,
			$this->container->appConfig->get('secret'),
			'Before config.php exists, the runtime configuration must use the secret from tmp/secret.php.'
		);

		// The segment the setup wizard uses must be the one config.php will select once written with this secret.
		$segment = (new SegmentProvider())($this->container);

		$this->assertSame(
			hash_hmac('md5', $this->container->application_name, self::PENDING_SECRET),
			$segment->getName()
		);
	}

	public function testVolatileSecretIsNotUsedBeforeConfigurationExists(): void
	{
		// tmp/secret.php could not be written, so every request generates a different secret. Using it would change
		// the session segment on every request during setup.
		$this->pendingSecret()->setValue(null, self::PENDING_SECRET);
		$this->volatileFlag()->setValue(null, true);
		$this->container->appConfig->set('secret', '');

		BootstrapUtilities::loadConfiguration(false);

		$this->assertSame('', (string) $this->container->appConfig->get('secret'));
	}

	public function testRedundantTmpSecretFileIsRemovedOnceTheConfigurationHasASecret(): void
	{
		// Setup wrote the secret straight into config.php, so tmp/secret.php was never transcribed and is now unused.
		file_put_contents(APATH_TMP . '/secret.php', "<?php\ndefined('AKEEBA') or die;\n");
		$this->pendingSecret()->setValue(null, null);

		BootstrapUtilities::loadConfiguration(true);

		$this->assertNotSame('', (string) $this->container->appConfig->get('secret'));
		$this->assertFileDoesNotExist(APATH_TMP . '/secret.php');
	}

	private function pendingSecret(): ReflectionProperty
	{
		return new ReflectionProperty(BootstrapUtilities::class, 'secret');
	}

	private function volatileFlag(): ReflectionProperty
	{
		return new ReflectionProperty(BootstrapUtilities::class, 'secretIsVolatile');
	}
}
