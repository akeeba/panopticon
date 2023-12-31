<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

function errorHandlerPrettyPrintArray (array $arr): void {
	if (empty($arr))
	{
		echo "<strong>(No data)</strong>";
	}

	?>
	<table class="table table-striped">
		<?php foreach ($arr as $k => $v): ?>
			<tr>
				<th scope="row" width="25%">
					<?= htmlentities($k, ENT_COMPAT) ?>
				</th>
				<td>
					<?php
					if (is_bool($v))
					{
						echo $v ? 'true' : 'false';
					}
					elseif (is_string($v) || is_numeric($v))
					{
						echo htmlentities($v, ENT_COMPAT);
					}
					else
					{
						try {
							$toArray = (array) $v;

							errorHandlerPrettyPrintArray($toArray);
						} catch (Throwable $e) {
							echo "<code>" . htmlentities(print_r($v, ENT_COMPAT)) . "</code>";
						}
					}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}
?>
<h3>System information</h3>
<table class="table table-striped">
	<tr>
		<td>Operating System (reported by PHP)</td>
		<td><?= PHP_OS ?></td>
	</tr>
	<tr>
		<td>PHP version (as reported <em>by your server</em>)</td>
		<td><?= PHP_VERSION ?></td>
	</tr>
	<tr>
		<td>PHP Built On</td>
		<td><?= htmlentities(php_uname()) ?></td>
	</tr>
	<tr>
		<td>PHP SAPI</td>
		<td><?= PHP_SAPI ?></td>
	</tr>
	<tr>
		<td>Server identity</td>
		<td><?= htmlentities(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : getenv('SERVER_SOFTWARE')) ?></td>
	</tr>
	<tr>
		<td>Browser identity</td>
		<td><?= htmlentities(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') ?></td>
	</tr>
	<tr>
		<td>Panopticon version and date</td>
		<td>
			<?= defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : 'Unknown' ?>
			&bull;
			<?= defined('AKEEBA_PANOPTICON_DATE') ? AKEEBA_PANOPTICON_DATE : 'Unknown' ?>
		</td>
	</tr>
	<?php
	try
	{
		$db = \Akeeba\Panopticon\Factory::getContainer()?->db;
	}
	catch (Throwable $e)
	{
		$db = null;
	}
	if (!is_null($db)):
		?>
		<tr>
			<td>Database driver name</td>
			<td><?= $db->name ?></td>
		</tr>
		<tr>
			<td>Database server version</td>
			<td><?= $db->getVersion() ?></td>
		</tr>
		<tr>
			<td>Database collation</td>
			<td><?= $db->getCollation() ?></td>
		</tr>
	<?php endif; ?>
	<tr>
		<td>PHP Memory limit</td>
		<td><?= function_exists('ini_get') ? htmlentities(ini_get('memory_limit')) : 'N/A' ?></td>
	</tr>
	<tr>
		<td>Peak Memory usage</td>
		<td><?= function_exists('memory_get_peak_usage') ? sprintf('%0.2fM', (memory_get_peak_usage() / 1024 / 1024)) : 'N/A' ?></td>
	</tr>
	<tr>
		<td>PHP Timeout (seconds)</td>
		<td><?= function_exists('ini_get') ? htmlentities(ini_get('max_execution_time')) : 'N/A' ?></td>
	</tr>
</table>

<h3>Request information</h3>
<h4>$_GET</h4>
<?php errorHandlerPrettyPrintArray($_GET) ?>
<h4>$_POST</h4>
<?php errorHandlerPrettyPrintArray($_POST) ?>
<h4>$_COOKIE</h4>
<?php errorHandlerPrettyPrintArray($_COOKIE) ?>
<h4>$_REQUEST</h4>
<?php errorHandlerPrettyPrintArray($_REQUEST) ?>

<h3>Session state</h3>
<?php
try
{
	$segment = \Akeeba\Panopticon\Factory::getContainer()?->segment;
}
catch (Throwable $e)
{
	$segment = null;
}
if ($segment !== null)
{
	$refObj  = new ReflectionObject($segment);
	$refProp = $refObj->getProperty('data');
	$refProp->setAccessible(true);
	errorHandlerPrettyPrintArray($refProp->getValue($segment));
}
else
{
	echo "(not accessible)";
}
?>

<?php
try
{
	$phpSettings = [
		'memory_limit'        => ini_get('memory_limit'),
		'upload_max_filesize' => ini_get('upload_max_filesize'),
		'post_max_size'       => ini_get('post_max_size'),
		'display_errors'      => ini_get('display_errors') == '1',
		'short_open_tag'      => ini_get('short_open_tag') == '1',
		'file_uploads'        => ini_get('file_uploads') == '1',
		'output_buffering'    => (int) ini_get('output_buffering') !== 0,
		'open_basedir'        => ini_get('open_basedir'),
		'session.save_path'   => ini_get('session.save_path'),
		'session.auto_start'  => ini_get('session.auto_start'),
		'disable_functions'   => ini_get('disable_functions'),
		'xml'                 => \extension_loaded('xml'),
		'zlib'                => \extension_loaded('zlib'),
		'zip'                 => \function_exists('zip_open') && \function_exists('zip_read'),
		'mbstring'            => \extension_loaded('mbstring'),
		'fileinfo'            => \extension_loaded('fileinfo'),
		'gd'                  => \extension_loaded('gd'),
		'iconv'               => \function_exists('iconv'),
		'intl'                => \function_exists('transliterator_transliterate'),
		'max_input_vars'      => ini_get('max_input_vars'),
	];
}
catch (Throwable $e)
{
	$phpSettings = [];
}

try
{
	$phpInfo = call_user_func(function () {
		ob_start();
		date_default_timezone_set('UTC');
		phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
		$phpInfo = ob_get_contents();
		ob_end_clean();
		preg_match_all('#<body[^>]*>(.*)</body>#siU', $phpInfo, $html);
		$html         = preg_replace('#<table[^>]*>#', '<table class="table">', $html[1][0]);
		$html         = preg_replace('#(\w),(\w)#', '\1, \2', $html);
		$html         = preg_replace('#<hr />#', '', $html);
		$html         = str_replace('<div class="text-center">', '', $html);
		$html         = preg_replace('#<tr class="h">(.*)</tr>#', '<thead><tr class="h">$1</tr></thead><tbody>', $html);
		$html         = str_replace('</table>', '</tbody></table>', $html);
		$html         = str_replace('</div>', '', $html);

		$html  = strip_tags($html, '<h2><th><td>');
		$html  = preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $html);
		$html  = preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $html);
		$t     = preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		$r     = [];
		$count = \count($t);
		$p1    = '<info>([^<]+)<\/info>';
		$p2    = '/' . $p1 . '\s*' . $p1 . '\s*' . $p1 . '/';
		$p3    = '/' . $p1 . '\s*' . $p1 . '/';

		for ($i = 1; $i < $count; $i++) {
			if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $t[$i], $matches)) {
				$name = trim($matches[1]);
				$vals = explode("\n", $t[$i + 1]);

				foreach ($vals as $val) {
					// 3cols
					if (preg_match($p2, $val, $matches)) {
						$r[$name][trim($matches[1])] = [trim($matches[2]), trim($matches[3]),];
					} elseif (preg_match($p3, $val, $matches)) {
						// 2cols
						$r[$name][trim($matches[1])] = trim($matches[2]);
					}
				}
			}
		}

		return $r;
	});
}
catch (Throwable $e)
{
	$phpInfo = [];
}
?>

<h3>PHP Settings</h3>
<?php errorHandlerPrettyPrintArray($phpSettings) ?>


<h3>Loaded PHP Extensions</h3>
<table class="table table-striped">
	<?php foreach ($phpInfo ?? [] as $section => $data):
		if ($section == 'Core')
		{
			continue;
		} ?>
		<tr>
			<th scope="row" width="25%"><?= htmlentities($section) ?></th>
			<td>
				<?php if (in_array($section, ['curl', 'openssl', 'ssh2', 'ftp', 'session', 'tokenizer'])): ?>
					<?php errorHandlerPrettyPrintArray($data) ?>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
