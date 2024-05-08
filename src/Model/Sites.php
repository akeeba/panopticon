<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

use Awf\Text\LanguageAwareTrait;

defined('AKEEBA') || die;

/**
 * Sites Management Model
 *
 * @since  1.0.0
 */
class Sites extends Site
{
    public function batch(array $ids)
    {
        if (!$ids)
        {
            throw new \RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_BATCH_ERR_NO_IDS'));
        }
    }
}