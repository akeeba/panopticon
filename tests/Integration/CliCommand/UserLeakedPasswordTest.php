<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Integration\CliCommand;

defined('AKEEBA') || die;

use Akeeba\Panopticon\CliCommand\UserAdd;
use Akeeba\Panopticon\CliCommand\UserCreate;
use Akeeba\Panopticon\CliCommand\UserSet;
use Akeeba\Panopticon\Library\Http\HttpFactory;
use Akeeba\Panopticon\Library\User\User;
use Akeeba\Panopticon\Tests\AbstractIntegrationTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The user CLI commands must report a password found in data breaches as a clean error, not an uncaught exception.
 *
 * User::setPassword() throws when the Have I Been Pwned check reports the password as leaked. The commands used to
 * call it outside any try/catch, so Symfony Console printed the raw exception block and the command's usage instead.
 *
 * The real leaked-password check runs, but against a stubbed HTTP client: the container's HTTP factory is replaced with
 * one whose clients answer every request with a Pwned Passwords range response listing the test password's hash. No
 * request leaves the machine.
 *
 * @since 2.4.0
 */
class UserLeakedPasswordTest extends AbstractIntegrationTestCase
{
	private const LEAKED_PASSWORD = 'Pnptc_Test_Leaked_Password_2026!';

	private mixed $originalHttpFactory = null;

	private mixed $originalHibp = null;

	private mixed $originalUserClass = null;

	/**
	 * URLs requested from the stubbed HTTP client.
	 *
	 * @var string[]
	 */
	private array $requestedUrls = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalHibp      = $this->container->appConfig->get('password_hibp', 1);
		$this->originalUserClass = $this->container->appConfig->get('user_class');

		$this->container->appConfig->set('user_class', User::class);

		$hash         = strtoupper(sha1(self::LEAKED_PASSWORD));
		$body         = "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n" . substr($hash, 5) . ":42\r\n";
		$requestedUrl = &$this->requestedUrls;
		$handler      = function (RequestInterface $request) use ($body, &$requestedUrl) {
			$requestedUrl[] = (string) $request->getUri();

			return Create::promiseFor(new Response(200, [], $body));
		};

		$this->originalHttpFactory = $this->container->httpFactory;
		unset($this->container['httpFactory']);
		$this->container['httpFactory'] = fn($c) => new class($c, $handler) extends HttpFactory {
			public function __construct($container, private $handler)
			{
				parent::__construct($container);
			}

			public function makeClient(
				?HandlerStack $stack = null,
				array $clientOptions = [],
				bool $cache = true,
				?int $cacheTTL = null,
				bool $singleton = true
			): Client
			{
				return new Client(['handler' => HandlerStack::create($this->handler)]);
			}
		};
	}

	protected function tearDown(): void
	{
		if ($this->originalHttpFactory !== null)
		{
			unset($this->container['httpFactory']);
			$this->container['httpFactory'] = $this->originalHttpFactory;
			$this->originalHttpFactory      = null;
		}

		$this->container->appConfig->set('password_hibp', $this->originalHibp);
		$this->container->appConfig->set('user_class', $this->originalUserClass);

		parent::tearDown();
	}

	public function testUserAddReportsLeakedPasswordAsAnError(): void
	{
		$username = 'testuser_' . bin2hex(random_bytes(4));

		[$exitCode, $display] = $this->runCommand(
			new UserAdd(),
			[
				'--username' => $username,
				'--password' => self::LEAKED_PASSWORD,
				'--email'    => $username . '@example.invalid',
			]
		);

		$this->assertLeakedPasswordError($exitCode, $display);
		$this->assertUserDoesNotExist($username);
	}

	public function testUserCreateReportsLeakedPasswordAsAnError(): void
	{
		$username = 'testuser_' . bin2hex(random_bytes(4));

		[$exitCode, $display] = $this->runCommand(
			new UserCreate(),
			[
				'--username' => $username,
				'--password' => self::LEAKED_PASSWORD,
				'--email'    => $username . '@example.invalid',
			]
		);

		$this->assertLeakedPasswordError($exitCode, $display);
		$this->assertUserDoesNotExist($username);
	}

	public function testUserSetReportsLeakedPasswordAsAnError(): void
	{
		// createUser() turns the check off to set its own password; turn it back on afterwards.
		$user = $this->createUser();
		$this->container->appConfig->set('password_hibp', 1);

		$id = (int) $user->getId();

		$this->assertGreaterThan(0, $id);

		$hashBefore = $this->getPasswordHash($id);

		[$exitCode, $display] = $this->runCommand(
			new UserSet(),
			[
				'id'         => (string) $id,
				'--password' => self::LEAKED_PASSWORD,
			]
		);

		$this->assertLeakedPasswordError($exitCode, $display);
		$this->assertSame($hashBefore, $this->getPasswordHash($id), 'The password must not change');
	}

	/**
	 * Run a command non-interactively and capture its exit code and output.
	 *
	 * @return  array{0: int, 1: string}
	 */
	private function runCommand(Command $command, array $input): array
	{
		$this->container->appConfig->set('password_hibp', 1);

		$application = new Application('Akeeba Panopticon CLI', 'test');
		$application->setAutoExit(false);
		$application->add($command);

		$tester   = new CommandTester($application->find($command->getName()));
		$exitCode = $tester->execute($input, ['interactive' => false]);

		return [$exitCode, $tester->getDisplay()];
	}

	private function assertLeakedPasswordError(int $exitCode, string $display): void
	{
		// SymfonyStyle wraps long messages over several indented lines.
		$flatDisplay = preg_replace('/\s+/', ' ', $display);
		$message     = $this->container->language->text('PANOPTICON_SYSERROR_LEAKED_PASSWORD');

		$this->assertNotEmpty($this->requestedUrls, 'The leaked-password check did not run');
		$this->assertStringStartsWith('https://api.pwnedpasswords.com/range/', $this->requestedUrls[0]);
		$this->assertNotSame('PANOPTICON_SYSERROR_LEAKED_PASSWORD', $message, 'The language string is not loaded');

		$this->assertSame(Command::FAILURE, $exitCode);
		$this->assertStringContainsString('[ERROR]', $display);
		$this->assertStringContainsString($message, $flatDisplay);
		$this->assertStringNotContainsString('Successfully saved user', $display);
	}

	private function assertUserDoesNotExist(string $username): void
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->qn('#__users'))
			->where($db->qn('username') . ' = ' . $db->q($username));

		$this->assertSame(0, (int) $db->setQuery($query)->loadResult(), 'The user must not have been saved');
	}

	private function getPasswordHash(int $id): string
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select($db->qn('password'))
			->from($db->qn('#__users'))
			->where($db->qn('id') . ' = ' . $id);

		return (string) $db->setQuery($query)->loadResult();
	}
}
