<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

declare(strict_types=1);

namespace Akeeba\Panopticon\Tests\Unit\Helper;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Helper\LanguageTools;
use Akeeba\Panopticon\Tests\AbstractUnitTestCase;

/**
 * Tests for the INI ⇄ PO language file converter.
 *
 * Converting a translation's INI file to PO and back must give back the very same INI file. When the INI → PO step
 * forgot to undo the INI escaping of double quotes, every round trip added a level of backslashes: `\"` came back as
 * `\\"`, then `\\\"`. Those backslashes end up in the HTML, breaking links such as the MFA "Print these codes" one
 * (gh-1081).
 *
 * @since 2.4.0
 */
class LanguageToolsTest extends AbstractUnitTestCase
{
	/**
	 * Translation strings in the converter's canonical INI output format, in en-GB order. The keys must exist in
	 * en-GB.ini, as the INI → PO conversion only exports en-GB keys.
	 */
	private const INI_CONTENT = <<< 'INI'
PANOPTICON_SITES_LBL_AKEEBABACKUP_CANNOTCONNECT_TIP_2="Allez sur <a href=\"https://www.random.org/\">random.org</a> et cliquez sur « Enregistrer »."
PANOPTICON_SYSCONFIG_LBL_EMAILTEST_BODY="Ceci confirme l'envoi des e-mails depuis %s.\n\nVérifiez vos réglages DKIM."
PANOPTICON_MFA_LBL_BACKUPCODES_PRINT_PROMPT="<strong><a href=\"%s\">Imprimez ces codes</a></strong>. <span class=\"text-muted\">Chaque code ne peut être utilisé qu'une fois.</span>"

INI;

	private string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->tempDir = sys_get_temp_dir() . '/panopticon-langtools-' . bin2hex(random_bytes(6));
		mkdir($this->tempDir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->tempDir . '/*') ?: [] as $file)
		{
			unlink($file);
		}

		rmdir($this->tempDir);

		parent::tearDown();
	}

	public function testIniToPoToIniRoundTripIsLossless(): void
	{
		$iniFile = $this->tempDir . '/xx-XX.ini';

		file_put_contents($iniFile, self::INI_CONTENT);

		$this->roundTrip($iniFile);

		$this->assertSame(self::INI_CONTENT, file_get_contents($iniFile));
	}

	public function testRepeatedRoundTripsDoNotAddEscaping(): void
	{
		$iniFile = $this->tempDir . '/xx-XX.ini';

		file_put_contents($iniFile, self::INI_CONTENT);

		$this->roundTrip($iniFile);
		$this->roundTrip($iniFile);
		$this->roundTrip($iniFile);

		$this->assertSame(self::INI_CONTENT, file_get_contents($iniFile));
	}

	public function testPoFileEscapesDoubleQuotesOnce(): void
	{
		$iniFile = $this->tempDir . '/xx-XX.ini';

		file_put_contents($iniFile, self::INI_CONTENT);
		LanguageTools::convertToPo($iniFile);

		$po = file_get_contents($this->tempDir . '/xx-XX.po');

		$this->assertStringContainsString('msgstr "<strong><a href=\"%s\">Imprimez ces codes</a></strong>.', $po);
		$this->assertStringNotContainsString('\\\\\\"', $po);
	}

	private function roundTrip(string $iniFile): void
	{
		LanguageTools::convertToPo($iniFile);
		unlink($iniFile);
		LanguageTools::convertToIni(substr($iniFile, 0, -4) . '.po');
	}
}
