<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\DBUtils;


use Akeeba\Panopticon\Factory;
use Awf\Database\Driver;
use Psr\Log\LoggerInterface;
use function React\Promise\all;

defined('AKEEBA') || die;

/**
 * Database Export for Akeeba Panopticon.
 *
 * Creates a SQL file with the contents of the base tables of the installation.
 *
 * @since 1.0.3
 */
class Export implements \JsonSerializable
{
	use FiniteStateTrait;
	use KnownTablesTrait;

	/**
	 * Finite state machine states
	 *
	 * @var    array
	 * @since  1.0.3
	 */
	protected const FSM_STATES = [
		'init',
		'preamble',
		'backup',
		'epilogue',
		'compress',
		'cleanup',
		'finish',
	];

	/**
	 * Maximum size of an individual INSERT command, in bytes
	 *
	 * @var    int
	 * @since  1.0.3
	 */
	private const MAX_PACKET = 524288;

	/**
	 * The stack of tables left to back up
	 *
	 * @var   array
	 * @since 1.0.3
	 */
	private array $tableStack = [];

	/**
	 * The current table being backed up.
	 *
	 * @var   string|null
	 * @since 1.0.3
	 */
	private ?string $currentTable = null;

	/**
	 * Batch size for the current table
	 *
	 * @var   int|null
	 * @since 1.0.3
	 */
	private ?int $batchSize = null;

	/**
	 * The current offset within the current table being backed up.
	 *
	 * @var   int|null
	 * @since 1.0.3
	 */
	private ?int $currentOffset = null;

	/**
	 * The currently buffered output
	 *
	 * @var   string|null
	 * @since 1.0.3
	 */
	private ?string $buffer = null;

	/**
	 * Should I compress the backup with Gzip?
	 *
	 * If NULL (default) it will use the global option. Otherwise, what the boolean value says.
	 *
	 * @var   bool|null
	 * @since 1.0.3
	 */
	private ?bool $compress = null;

	/**
	 * The file resource we are writing output into.
	 *
	 * @var   false|resource
	 * @since 1.0.3
	 */
	private $fp;

	/**
	 * PSR-3 logger object
	 *
	 * @var   LoggerInterface|null
	 * @since 1.0.3
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Public constructor.
	 *
	 * @param   string  $outputFilename  The file where we are going to be writing our output
	 *
	 * @since   1.0.3
	 */
	public function __construct(
		private string $outputFilename,
		private ?Driver $db = null
	)
	{
		$containingPath = dirname($this->outputFilename);

		if (!is_dir($containingPath))
		{
			mkdir($containingPath, 0755, true);
		}

		$this->db ??= Factory::getContainer()->db;
		$this->fp = @fopen($this->outputFilename, 'at');

		if ($this->fp === false)
		{
			throw new \RuntimeException(sprintf('Cannot open %s for writing.', $this->outputFilename));
		}
	}

	/**
	 * Create an instance from a JSON–serialised string representation.
	 *
	 * @param   string       $json  The JSON–serialised string representing an instance.
	 * @param   Driver|null  $db    The database object. NULL to use the application default.
	 *
	 * @return  static
	 * @throws \JsonException
	 * @since   1.0.3
	 */
	public static function fromJson(string $json, ?Driver $db = null): static
	{
		$o = json_decode($json, flags: JSON_THROW_ON_ERROR);

		$instance = new static($o->outputFilename, $db);

		$instance->fsmState      = $o->fsmState ?? null;
		$instance->tableStack    = $o->tableStack ?? [];
		$instance->currentTable  = $o->currentTable ?? null;
		$instance->currentOffset = $o->currentOffset ?? null;
		$instance->batchSize     = $o->batchSize ?? null;
		$instance->buffer        = $o->buffer ?? null;
		$instance->compress      = $o->compress ?? null;

		return $instance;
	}

	/**
	 * Returns an object which can be serialised with json_encode().
	 *
	 * @return  mixed
	 * @since   1.0.3
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): mixed
	{
		return (object) [
			'outputFilename' => $this->outputFilename,
			'fsmState'       => $this->fsmState,
			'tableStack'     => $this->tableStack,
			'currentTable'   => $this->currentTable,
			'currentOffset'  => $this->currentOffset,
			'batchSize'      => $this->batchSize,
			'buffer'         => $this->buffer,
			'compress'       => $this->compress,
		];
	}

	/**
	 * Execute a step of the backup process.
	 *
	 * @return  bool  True if we have more work to do
	 * @since   1.0.3
	 */
	public function execute(): bool
	{
		switch ($this->getCurrentState())
		{
			case 'init':
				$this->logger?->info(sprintf('Database export: starting backup to %s', $this->outputFilename));
				$this->initialise();
				break;

			case 'preamble':
				$this->logger?->info('Database export: writing preamble');
				$this->preamble();
				break;

			case 'backup':
				$this->logger?->info('Database export: stepping through the backup');
				$this->stepBackup();
				break;

			case 'epilogue':
				$this->logger?->info('Database export: writing epilogue');
				$this->epilogue();
				break;

			case 'compress':
				$this->logger?->info('Database export: compressing output');
				$this->compress();
				break;

			case 'cleanup':
				$this->cleanUp();
				break;

			case 'finish':
			default:
				$this->logger?->info('Database export: just finished');
				if (is_resource($this->fp))
				{
					@fclose($this->fp);

					$this->fp = null;
				}

				return false;
				break;
		}

		return true;
	}

	/**
	 * Set the PSR-3 logger object
	 *
	 * @param   LoggerInterface|null  $logger  The PSR-3 logger object
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public function setLogger(?LoggerInterface $logger): void
	{
		$this->logger = $logger;
	}

	/**
	 * Will I compress the backup with Gzip?
	 *
	 * @return  bool|null  NULL means use global setting.
	 * @since   1.0.3
	 */
	public function getCompress(): ?bool
	{
		return $this->compress;
	}

	/**
	 * Set the flag which controls compressing the backup with Gzip.
	 *
	 * @param   bool|null  $compress  NULL to use the global setting.
	 *
	 * @return  void
	 */
	public function setCompress(?bool $compress): void
	{
		$this->compress = $compress;
	}

	/**
	 * Initialise the backup process
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function initialise(): void
	{
		$this->tableStack    = static::$backupTables;
		$this->currentTable  = null;
		$this->currentOffset = null;
		$this->buffer        = null;

		@ftruncate($this->fp, 0);

		$version  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev';
		$dateTime = date('Y-m-d H:i:s T');
		$header   = <<< MySQL
-- Akeeba Panopticon $version
--
-- Base tables backup taken on $dateTime


MySQL;
		@fputs($this->fp, $header);

		$this->advanceState();
	}

	/**
	 * Writes the backup preamble.
	 *
	 * The preamble performs the following operations:
	 *
	 * - Lock exported tables for writing
	 * - Disable foreign key checks
	 * - Truncate the tables
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function preamble(): void
	{
		$preamble = "";

		$tables = array_map(
			fn($x) => sprintf('%s WRITE', $this->db->quoteName($this->db->replacePrefix($x))),
			array_keys(self::$backupTables)
		);

		$preamble .= "-- Locking tables for writing\n";
		$preamble .= 'LOCK TABLES ' . implode(',', $tables) . ';';
		$preamble .= "\n\n";

		$preamble .= "-- Disabling foreign key checks for efficiency\n";
		$preamble .= 'SET FOREIGN_KEY_CHECKS=0;';
		$preamble .= "\n\n";

		$preamble .= "-- Truncating tables before inserting new values\n";
		foreach (array_keys(self::$backupTables) as $tableName)
		{
			$preamble .= sprintf("TRUNCATE TABLE %s;\n", $this->db->quoteName($this->db->replacePrefix($tableName)));
		}
		$preamble .= "\n\n";

		fputs($this->fp, $preamble);

		$this->advanceState();
	}

	/**
	 * Step through the tables backup, processing exactly one table batch.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function stepBackup(): void
	{
		// Try to go to the next table. If there are no tables left, we're done.
		if (empty($this->currentTable))
		{
			$this->logger?->debug('Getting next table to back up');

			if (!empty($this->buffer))
			{
				$this->flushBuffer();
			}

			if (empty($this->tableStack))
			{
				$this->logger?->debug('No more tables to back up');
				$this->advanceState();

				return;
			}

			$tables     = array_keys($this->tableStack);
			$batchSizes = array_values($this->tableStack);

			$this->currentTable = array_shift($tables);
			$this->batchSize    = array_shift($batchSizes);
			$this->tableStack   = array_combine($tables, $batchSizes);
			/** @noinspection PhpFieldImmediatelyRewrittenInspection */
			$this->currentOffset = null;

			$this->logger?->debug(
				sprintf('Next table: %s -- batch size: %d rows', $this->currentTable, $this->batchSize)
			);

			$tableReal = $this->db->replacePrefix($this->currentTable);
			$miniPreamble = <<< MYSQL

-- Contents of the $tableReal table

MYSQL;
			fputs($this->fp, $miniPreamble);

		}

		// Starting backup on a new table?
		if ($this->currentOffset === null)
		{
			$this->flushBuffer();
			$this->currentOffset = 0;
		}

		// If the buffer is clean, initialise with an INSERT INTO for the current table
		if (empty($this->buffer))
		{
			$this->populateBufferWithInsertInto();
		}

		$this->logger?->debug(
			sprintf('Backing up from offset %d, up to %d rows', $this->currentOffset, $this->batchSize)
		);

		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName($this->currentTable))
			->setLimit($this->batchSize, $this->currentOffset);

		if (array_key_exists('id', $this->db->getTableColumns($this->currentTable)))
		{
			$query->order($this->db->quoteName('id') . ' ASC');
		}

		$rows = 0;

		foreach ($this->db->setQuery($query)->getIterator() as $row)
		{
			$rows++;
			$line         = sprintf("\t(%s)", implode(',', array_map([$this->db, 'quote'], (array) $row)));
			$lineLength   = mb_strlen($line, 'ASCII');
			$bufferLength = mb_strlen($this->buffer, 'ASCII');

			if ($lineLength + $bufferLength > self::MAX_PACKET)
			{
				$this->flushBuffer();
				$this->populateBufferWithInsertInto();
			}

			if (str_ends_with($this->buffer, ")"))
			{
				$this->buffer .= ",\n";
			}

			$this->buffer .= $line;
		}

		if ($rows === 0)
		{
			// No rows with an offset of 0; the table was empty.
			if ($this->currentOffset == 0)
			{
				$this->logger?->debug('The table was empty.');

				$this->buffer = null;

				$miniPreamble = <<< MYSQL
-- (empty table)

MYSQL;
				fputs($this->fp, $miniPreamble);

			}
			else
			{
				$this->logger?->debug('No more rows found; end of table backup.');
			}

			// We have reached the end of the table.
			$this->flushBuffer();
			$this->currentTable = null;
		}
		else
		{
			$this->logger?->debug(sprintf('Backed up %d rows.', $rows));

			// Increase the offset by the number of rows we output
			$this->currentOffset += $rows;
		}
	}

	/**
	 * Writes the backup epilogue.
	 *
	 * The epilogue performs the following operations:
	 *
	 * - Re-enable foreign key checks
	 * - Unlock the tables
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function epilogue(): void
	{
		$epilogue = "\n\n";

		$epilogue .= "-- Re-enabling foreign key checks\n";
		$epilogue .= 'SET FOREIGN_KEY_CHECKS=1;';
		$epilogue .= "\n\n";

		$epilogue .= "-- Unlocking tables for writing\n";
		$epilogue .= 'UNLOCK TABLES;';
		$epilogue .= "\n\n";

		fputs($this->fp, $epilogue);

		$this->advanceState();
	}

	/**
	 * Compress the backup file
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function compress(): void
	{
		// Has the user disabled Gzip-compressing backups?
		$shouldCompress = $this->compress ??
			Factory::getContainer()->appConfig->get('dbbackup_compress', true);

		if (!$shouldCompress)
		{
			$this->logger?->info('Compressing backups is disabled; skipping over');
			$this->advanceState();

			return;
		}

		// Try to compress the file.
		if (!$this->compressNative())
		{
			$this->compressPurePHP();
		}

		// Now, let's get out of here.
		$this->advanceState();
	}

	/**
	 * Automatically prune the oldest automatic database backup files
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function cleanUp(): void
	{
		$path = APATH_CACHE . '/db_backups';

		if (!is_dir($path) || !is_readable($path))
		{
			$this->logger?->notice(
				sprintf(
					'The database backup output folder %s does not exist or is not readable',
					$path
				)
			);

			$this->advanceState();

			return;
		}

		$allFiles = [];
		$di = new \DirectoryIterator($path);

		/** @var \DirectoryIterator $item */
		foreach ($di as $item)
		{
			if ($item->isDot() || !$item->isFile() || !in_array($item->getExtension(), ['sql', 'gz']))
			{
				continue;
			}

			if (str_ends_with($item->getBasename(), '.gz') && !str_ends_with($item->getBasename(), '.sql.gz'))
			{
				continue;
			}

			$allFiles[$item->getBasename()] = $item->getCTime();
		}

		$maxFiles = Factory::getContainer()->appConfig->get('dbbackup_maxfiles', 15);

		$this->logger?->debug(sprintf('Found %d database backup file(s)', count($allFiles)));

		if ($maxFiles < 1 || count($allFiles) < $maxFiles)
		{
			$this->logger?->debug(sprintf('No need to delete files (I am told to keep %d file(s))', $maxFiles));

			$this->advanceState();

			return;
		}

		asort($allFiles);

		$allFiles = array_slice($allFiles, 0, -$maxFiles);

		$this->logger?->debug(sprintf('I will delete %d old database backup file(s)', count($allFiles)));

		foreach ($allFiles as $fileName => $dummy)
		{
			$filePath = $path . '/' . $fileName;

			$this->logger?->debug(sprintf('Deleting old database backup file %s', $filePath));

			@unlink($filePath);
		}

		$this->advanceState();
	}

	/**
	 * Compress the SQL backup file with the native gzip command
	 *
	 * @return  bool
	 * @since   1.0.3
	 */
	private function compressNative(): bool
	{
		// Native Gzip is only support on UNIX–like systems (BSD, macOS, Solaris, Linux)
		if (in_array(strtolower(PHP_OS_FAMILY), ['windows', 'unknown']))
		{
			$this->logger?->debug('Cannot use native gzip executable: not a UNIX system');

			return false;
		}

		// We need to be able to run native commands
		if (!function_exists('exec'))
		{
			$this->logger?->debug('Cannot use native gzip executable: exec() is not available');

			return false;
		}

		// Does the Gzip command exist?
		exec('gzip -h 2>/dev/null', $out, $resultCode);

		if ($resultCode !== 0)
		{
			$this->logger?->debug('Cannot use native gzip executable: the gzip executable is not available');

			return false;
		}

		// Close the file
		if (is_resource($this->fp))
		{
			@fclose($this->fp);
		}
		$this->fp = null;

		// Compress the file
		$this->logger?->debug('Compressing backup with native gzip executable');

		$arg = escapeshellarg($this->outputFilename);
		exec('gzip -9 ' . $arg, $out, $resultCode);

		// Did we fail?
		if ($resultCode !== 0)
		{
			$this->logger?->notice('Compressing backup with native gzip executable failed');

			// Reopen file on failure
			$this->fp = @fopen($this->outputFilename, 'at');

			return false;
		}

		// All right! This was a success. Woo-hoo!
		return true;
	}

	/**
	 * Compress the SQL backup file with PHP's zlib extension
	 *
	 * This is not anywhere near as fast as the native utility because of the file management overhead in PHP, but it's
	 * good enough to work on Windows and subpar hosting in most cases.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function compressPurePHP(): void
	{
		if (is_resource($this->fp))
		{
			@fclose($this->fp);
		}

		if (!function_exists('gzopen') || !function_exists('gzputs'))
		{
			$this->logger?->warning(
				'Compressing backup with PHP zlib is not possible: the extension is not enabled, or its functions are disabled'
			);

			return;
		}

		$this->logger?->debug('Compressing backup with PHP zlib');

		$compressedFilename = $this->outputFilename . '.gz';
		$gzfp               = gzopen($compressedFilename, 'wb9');

		if ($gzfp === false)
		{
			return;
		}

		$this->fp = fopen($this->outputFilename, 'wb+');

		fseek($this->fp, 0);

		while (!feof($this->fp))
		{
			gzputs($gzfp, fread($this->fp, 20971520));
		}

		fclose($gzfp);
		fclose($this->fp);

		$this->logger?->debug('Finished compressing backup; deleting uncompressed file');

		@unlink($this->outputFilename);
	}

	/**
	 * Output the remaining buffer to the file.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function flushBuffer(): void
	{
		if (empty($this->buffer))
		{
			return;
		}

		if (!str_ends_with($this->buffer, ';'))
		{
			$this->buffer .= ";\n";
		}

		fputs($this->fp, $this->buffer);

		$this->buffer = null;
	}

	/**
	 * Populate the buffer with an INSERT INTO for the current table
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	private function populateBufferWithInsertInto(): void
	{
		$this->buffer = sprintf(
			"INSERT INTO %s VALUES \n",
			$this->db->quoteName($this->db->replacePrefix($this->currentTable)),
		);
	}
}