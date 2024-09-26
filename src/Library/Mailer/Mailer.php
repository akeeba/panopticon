<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Library\Mailer;

defined('AKEEBA') || die;

use Akeeba\Panopticon\Factory;
use Akeeba\Panopticon\Model\Mailtemplates;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Mailer\Mailer as AWFMailer;
use Awf\Uri\Uri;

class Mailer extends AWFMailer implements ContainerAwareInterface
{
	use ContainerAwareTrait;

	/**
	 * Allowed image file extensions to inline in sent emails
	 *
	 * @var   array
	 */
	private static $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];

	public function __construct($container = null)
	{
		$this->setContainer($container ?? Factory::getContainer());

		$config = $this->getContainer()->appConfig;

		// Prevent the core mailer from throwing PHP deprecated notices
		$config->set('mail.smtpauth', !$config->get('smtpauth') ? null : 1);
		$config->set('mail.smtpuser', $config->get('smtpuser'));
		$config->set('mail.smtppass', $config->get('smtppass'));
		$config->set('mail.smtphost', $config->get('smtphost', 'localhost'));
		$config->set('mail.smtpsecure', $config->get('smtpsecure', 'none'));
		$config->set('mail.smtpport', $config->get('smtpport', 25));
		$config->set('mail.mailfrom', $config->get('mailfrom'));
		$config->set('mail.fromname', $config->get('fromname'));
		$config->set('mail.mailer', $config->get('mailer'));

		parent::__construct($this->getContainer());

		unset($config['mail.smtpauth']);
		unset($config['mail.smtpuser']);
		unset($config['mail.smtppass']);
		unset($config['mail.smtphost']);
		unset($config['mail.smtpsecure']);
		unset($config['mail.smtpport']);
		unset($config['mail.mailfrom']);
		unset($config['mail.fromname']);
		unset($config['mail.mailer']);

		$smtpauth   = !$config->get('smtpauth') ? null : 1;
		$smtpuser   = $config->get('smtpuser');
		$smtppass   = $config->get('smtppass');
		$smtphost   = $config->get('smtphost', 'localhost');
		$smtpsecure = $config->get('smtpsecure', 'none');
		$smtpport   = $config->get('smtpport', 25);
		$mailfrom   = $config->get('mailfrom');
		$fromname   = $config->get('fromname');
		$mailer     = $config->get('mailer');

		$this->SetFrom($mailfrom, $fromname);

		switch ($mailer)
		{
			case 'smtp':
				$this->useSMTP($smtpauth, $smtphost, $smtpuser, $smtppass, $smtpsecure, $smtpport);
				break;

			case 'sendmail':
				$this->IsSendmail();
				break;

			default:
				$this->IsMail();
				break;
		}

		$this->clearReplyTos();
		$this->addReplyTo($config->get('mailfrom'), $config->get('fromname'));
		$this->XMailer = 'Panopticon ' . (defined('AKEEBA_PANOPTICON_VERSION') ? constant('AKEEBA_PANOPTICON_VERSION') : 'dev');
	}

	/**
	 * Attach and inline the referenced images in the email message
	 *
	 * @param   string  $templateText
	 * @param   self    $mailer
	 *
	 * @return  string
	 */
	private static function inlineImages(string $templateText, self $mailer): string
	{
		// RegEx patterns to detect images
		$patterns = [
			// srcset="**URL**" e.g. source tags
			'/srcset=\"?([^"]*)\"?/i',
			// src="**URL**" e.g. img tags
			'/src=\"?([^"]*)\"?/i',
			// url(**URL**) nad url("**URL**") i.e. inside CSS
			'/url\(\"?([^"\(\)]*)\"?\)/i',
		];

		// Cache of images so we don't inline them multiple times
		$foundImages = [];
		// Running counter of images, used to create the attachment IDs in the message
		$imageIndex = 0;

		// Run a RegEx search & replace for each pattern
		foreach ($patterns as $pattern)
		{
			// $matches[0]: the entire string matched by RegEx; $matches[1]: just the path / URL
			$templateText = preg_replace_callback($pattern, function (array $matches) use ($mailer, &$foundImages, &$imageIndex): string {
				// Abort if it's not a file type we can inline
				if (!self::isInlineableFileExtension($matches[1]))
				{
					return $matches[0];
				}

				// Try to get the local absolute filesystem path of the referenced media file
				$localPath = self::getLocalAbsolutePath(self::normalizeURL($matches[1]));

				// Abort if this was not a relative / absolute URL pointing to our own site
				if (empty($localPath))
				{
					return $matches[0];
				}

				// Abort if the referenced file does not exist
				if (!@file_exists($localPath) || !@is_file($localPath))
				{
					return $matches[0];
				}

				// Make sure the inlined image is cached; prevent inlining the same file multiple times
				if (!array_key_exists($localPath, $foundImages))
				{
					$imageIndex++;
					$mailer->AddEmbeddedImage($localPath, 'img' . $imageIndex, basename($localPath));
					$foundImages[$localPath] = $imageIndex;
				}

				return str_replace($matches[1], $toReplace = 'cid:img' . $foundImages[$localPath], $matches[0]);
			}, $templateText);
		}

		// Return the processed email content
		return $templateText;
	}

	/**
	 * Does this file / URL have an allowed image extension for inlining?
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  bool
	 */
	private static function isInlineableFileExtension(string $fileOrUri): bool
	{
		$dot = strrpos($fileOrUri, '.');

		if ($dot === false)
		{
			return false;
		}

		$extension = substr($fileOrUri, $dot + 1);

		return in_array(strtolower($extension), self::$allowedImageExtensions);
	}

	/**
	 * Return the path to the local file referenced by the URL, provided it's internal.
	 *
	 * @param   string  $url
	 *
	 * @return  string|null  The local file path. NULL if the URL is not internal.
	 */
	private static function getLocalAbsolutePath($url)
	{
		$base = rtrim(Uri::base(false, Factory::getContainer()), '/');

		if (!str_starts_with($url, $base))
		{
			return null;
		}

		return Factory::getContainer()->basePath . '/' . ltrim(substr($url, strlen($base) + 1), '/');
	}

	/**
	 * Normalizes an image relative or absolute URL as an absolute URL
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  string
	 */
	private static function normalizeURL($fileOrUri)
	{
		// Empty file / URIs are returned as-is (obvious screw up)
		if (empty($fileOrUri))
		{
			return $fileOrUri;
		}

		// Remove leading / trailing slashes
		$fileOrUri = trim($fileOrUri, '/');

		// HTTPS URLs are returned as-is
		if (str_starts_with($fileOrUri, 'https://'))
		{
			return $fileOrUri;
		}

		// HTTP URLs are returned upgraded to HTTPS
		if (str_starts_with($fileOrUri, 'http://'))
		{
			return 'https://' . substr($fileOrUri, 7);
		}

		// Normalize URLs with a partial schema as HTTPS
		if (str_starts_with($fileOrUri, '://'))
		{
			return 'https://' . substr($fileOrUri, 3);
		}

		// This is a file. We assume it's relative to the site's root
		return rtrim(Uri::base(false, Factory::getContainer()), '/') . '/' . $fileOrUri;
	}

	public function Send()
	{
		$config = $this->container->appConfig;

		if ($config->get('mail_online', false))
		{
			if (($this->Mailer == 'mail') && !function_exists('mail'))
			{
				throw new \RuntimeException(sprintf('%s::Send mail not enabled.', get_class($this)));
			}

			@$result = parent::Send();

			if (!$result)
			{
				throw new \RuntimeException(sprintf('%s::Send failed: "%s".', get_class($this), $this->ErrorInfo));
			}

			return $result;
		}
		else
		{
			$this->container->application->enqueueMessage(
				$this->getContainer()->language->text('AWF_MAIL_FUNCTION_OFFLINE')
			);

			return false;
		}
	}

	public function initialiseWithTemplate(string $type, string $language = 'en-GB', array $replacements = []): void
	{
		/** @var Mailtemplates $model */
		$model = $this->container->mvcFactory->makeModel('Mailtemplates');
		$model->where('type', values: strtolower($type));
		$model->where('language', 'in', [$language, '*']);
		$templates = $model->get(true);

		if ($templates->count() === 0)
		{
			return;
		}

		// Try to find an exact language match
		/** @var ?Mailtemplates $template */
		$template = $templates->reduce(
			fn(?Mailtemplates $carry, Mailtemplates $item): ?Mailtemplates =>
				$carry ?? ($item->language === $language ? $item : null)
		);

		// Fall back to the "All languages" match
		$template ??= $templates->reduce(
			fn(?Mailtemplates $carry, Mailtemplates $item): ?Mailtemplates =>
				$carry ?? ($item->language === '*' ? $item : null)
		);

		// Fall back to en-GB
		$template ??= $templates->reduce(
			fn(?Mailtemplates $carry, Mailtemplates $item): ?Mailtemplates =>
				$carry ?? ($item->language === 'en-GB' ? $item : null)
		);

		if (empty($template))
		{
			return;
		}

		$replacements = array_merge([
			'URL' => rtrim(Uri::base(false, $this->getContainer()), '/'),
		], $replacements);
		$replaceFrom  = array_map(
			fn(string $x) => '[' . strtoupper($x) . ']',
			array_keys($replacements)
		);
		$replaceTo    = array_values($replacements);

		$inlineImages = $this->container->appConfig->get('mail_inline_images', false);

		$subject = str_replace($replaceFrom, $replaceTo, $template->subject);
		$html    = str_replace($replaceFrom, $replaceTo, $template->html);

		if ($inlineImages)
		{
			$html = self::inlineImages($html, $this);
		}

		$plaintext  = str_replace($replaceFrom, $replaceTo, $template->plaintext);
		$css        = $template->getCommonCSS();
		$subjectAlt = htmlentities($subject);
		// This prevents SpamAssassin from killing our emails…
		$html = <<<HTML
<html>
<head>
<title>$subjectAlt</title>
<style>$css</style>
</head>
<body>$html</body>
</html>
HTML;

		$this->isHTML(true);
		$this->setSubject($subject);
		$this->setBody($html);
		$this->AltBody = $plaintext;
	}

}
