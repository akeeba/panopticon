<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Model;

use Awf\Registry\Registry;
use Awf\Text\LanguageAwareTrait;

defined('AKEEBA') || die;

/**
 * Sites Management Model
 *
 * @since  1.0.0
 */
class Sites extends Site
{
    public function batch(array $ids, $data = []): void
    {
        if (!$ids)
        {
            throw new \RuntimeException($this->getLanguage()->text('PANOPTICON_SITES_BATCH_ERR_NO_IDS'));
        }

        // Apply the group to selected sites
        if (isset($data['groups']) && $data['groups'])
        {
            foreach ($ids as $id)
            {
                $this->find($id);
                $config = $this->getConfig() ?? new Registry();

                $groups = $config->get('config.groups', []);

                foreach ($data['groups'] as $group)
                {
                    $groups[] = $group;
                }

                $groups = array_unique($groups);

                $config->set('config.groups', $groups);

                $new_data['config'] = $config->toString();

                $this->save($new_data);
            }
        }
    }
}