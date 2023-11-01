<?php

namespace Akeeba\Panopticon\LocalDev;

defined('AKEEBA') || die;

use DirectoryIterator;

abstract class LocalLibraryLoader
{
	public function __construct(
		private readonly string $composerAutoloaderFile,
		private readonly string $namespace,
		private ?string $sourcesPath = null,
		private readonly ?string $constantName = null
	) {
		$this->sourcesPath ??= realpath(dirname($this->composerAutoloaderFile) . '/../src');
	}

	final public function __invoke()
	{
		if (!empty($this->constantName) && !defined($this->constantName) || !constant($this->constantName))
		{
			return;
		}

		$this->includeAutoloader();
		$this->preload($this->sourcesPath, $this->namespace);
	}

	private function includeAutoloader(): void
	{
		if (!file_exists($this->composerAutoloaderFile))
		{
			return;
		}

		/** @var \Composer\Autoload\ClassLoader $autoloader */
		$autoloader = require_once $this->composerAutoloaderFile;
		$autoloader->addPsr4(sprintf('\\%s\\', $this->namespace), $this->sourcesPath, true);
	}

	private function preload(string $path, string $namespace)
	{
		/** @var DirectoryIterator $file */
		foreach (new DirectoryIterator($path) as $file)
		{
			if ($file->isDot() || $file->isLink())
			{
				continue;
			}

			if ($file->isDir())
			{
				$this->preload($file->getPathname(), $namespace . '\\' . $file->getBasename());

				continue;
			}

			if ($file->getExtension() != 'php' || $file->getBasename() === 'Object.php' || $file->getBasename() === 'Autoloader.php')
			{
				continue;
			}

			class_exists($namespace . '\\' . $file->getBasename('.php'), true);
		}
	}

}