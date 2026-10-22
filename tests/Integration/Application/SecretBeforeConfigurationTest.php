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
use Awf\Registry\Registry;
use ReflectionMethod;
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

	private const TMP_SECRET_FILE = APATH_TMP . '/secret.php';

	private ?\stdClass $originalConfigData = null;

	private ?bool $originalConfigReadWrite = null;

	private mixed $originalPendingSecret;

	private bool $originalVolatile;

	private ?string $originalTmpSecretFile = null;

	private ?string $createdSegmentName = null;

	protected function setUp(): void
	{
		parent::setUp();

		// loadConfiguration() reloads the whole configuration, so keep a deep copy of it rather than just the secret.
		$configData                    = $this->configProperty('data')->getValue($this->container->appConfig);
		$this->originalConfigData      = unserialize(serialize($configData));
		$this->originalConfigReadWrite = $this->configProperty('isReadWrite')->getValue($this->container->appConfig);
		$this->originalPendingSecret   = $this->staticProperty('secret')->getValue();
		$this->originalVolatile        = $this->staticProperty('secretIsVolatile')->getValue();

		if (is_file(self::TMP_SECRET_FILE))
		{
			$this->originalTmpSecretFile = file_get_contents(self::TMP_SECRET_FILE);
		}
	}

	protected function tearDown(): void
	{
		$this->configProperty('data')->setValue($this->container->appConfig, $this->originalConfigData);
		$this->configProperty('isReadWrite')->setValue($this->container->appConfig, $this->originalConfigReadWrite);
		$this->staticProperty('secret')->setValue(null, $this->originalPendingSecret);
		$this->staticProperty('secretIsVolatile')->setValue(null, $this->originalVolatile);

		if ($this->originalTmpSecretFile !== null)
		{
			file_put_contents(self::TMP_SECRET_FILE, $this->originalTmpSecretFile);
		}
		elseif (is_file(self::TMP_SECRET_FILE))
		{
			unlink(self::TMP_SECRET_FILE);
		}

		if ($this->createdSegmentName !== null)
		{
			unset($_SESSION[$this->createdSegmentName]);
		}

		parent::tearDown();
	}

	public function testPendingSecretIsUsedBeforeConfigurationExists(): void
	{
		$this->staticProperty('secret')->setValue(null, self::PENDING_SECRET);
		$this->container->appConfig->set('secret', '');

		$this->callPrivate('adoptPendingSecret', $this->container->appConfig);

		$this->assertSame(
			self::PENDING_SECRET,
			(string) $this->container->appConfig->get('secret'),
			'Before config.php exists, the runtime configuration must use the secret from tmp/secret.php.'
		);

		// The segment the setup wizard uses must be the one config.php will select once written with this secret.
		$segment                  = (new SegmentProvider())($this->container);
		$this->createdSegmentName = $segment->getName();

		$this->assertSame(
			hash_hmac('md5', $this->container->application_name, self::PENDING_SECRET),
			$segment->getName()
		);
	}

	public function testVolatileSecretIsNotUsedBeforeConfigurationExists(): void
	{
		// tmp/secret.php could not be written, so every request generates a different secret. Using it would change
		// the session segment on every request during setup.
		$this->staticProperty('secret')->setValue(null, self::PENDING_SECRET);
		$this->staticProperty('secretIsVolatile')->setValue(null, true);
		$this->container->appConfig->set('secret', '');

		$this->callPrivate('adoptPendingSecret', $this->container->appConfig);

		$this->assertSame('', (string) $this->container->appConfig->get('secret'));
	}

	public function testTmpSecretFileIsRemovedOnceTheConfigurationFilesHaveASecret(): void
	{
		// Setup wrote the secret straight into config.php (here: .env.test has one), so tmp/secret.php is now unused.
		file_put_contents(self::TMP_SECRET_FILE, "<?php\ndefined('AKEEBA') or die;\n");
		$this->staticProperty('secret')->setValue(null, null);

		BootstrapUtilities::loadConfiguration();

		$this->assertFileDoesNotExist(self::TMP_SECRET_FILE);
	}

	public function testTmpSecretFileIsRedundantOnlyWhenTheConfigurationFilesHaveASecret(): void
	{
		$this->assertTrue($this->callPrivate('isSecretFileRedundant', new Registry(['secret' => 'ConfiguredSecret'])));

		// A container passing PANOPTICON_SECRET as a server environment variable: applySecret() cannot see it, so
		// tmp/secret.php is the one it reads. Deleting it would make applySecret() write a new one on every request.
		$this->assertFalse($this->callPrivate('isSecretFileRedundant', new Registry([])));

		// applySecret() trims the secret, so a blank one means it still relies on tmp/secret.php.
		$this->assertFalse($this->callPrivate('isSecretFileRedundant', new Registry(['secret' => "  \t"])));
	}

	private function callPrivate(string $method, mixed ...$args): mixed
	{
		return (new ReflectionMethod(BootstrapUtilities::class, $method))->invoke(null, ...$args);
	}

	private function staticProperty(string $name): ReflectionProperty
	{
		return new ReflectionProperty(BootstrapUtilities::class, $name);
	}

	private function configProperty(string $name): ReflectionProperty
	{
		return new ReflectionProperty($this->container->appConfig, $name);
	}
}
