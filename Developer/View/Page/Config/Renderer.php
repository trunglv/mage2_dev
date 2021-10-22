<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Betagento\Developer\View\Page\Config;

use Magento\Framework\App\Filesystem\DirectoryList;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\GroupedCollection;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Config\Metadata\MsApplicationTileImage;
use Magento\Framework\App\ObjectManager as ObjectManager;

/**
 * Page config Renderer model
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Renderer extends \Magento\Framework\View\Page\Config\Renderer
{
    
    

    /**
     * Render HTML tags referencing corresponding URLs
     *
     * @param \Magento\Framework\View\Asset\PropertyGroup $group
     * @return string
     */
    protected function renderAssetHtml(\Magento\Framework\View\Asset\PropertyGroup $group)
    {
        $time = time();
        /**
         * Because there are many for arguments of the parent contructor - So use Object Manager may be best option here
         */
        
        $om = ObjectManager::getInstance();

        $filesystem = $om->get('\Magento\Framework\Filesystem');
        
        $dir = $filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
        if (!$dir->isExist('enable_time_in_url.flag')) {
           
            return parent::renderAssetHtml($group);

        }

        $assets = $this->processMerge($group->getAll(), $group);
        $attributes = $this->getGroupAttributes($group);

        $result = '';
        $template = '';
        
        try {
            /** @var $asset \Magento\Framework\View\Asset\AssetInterface */
            foreach ($assets as $asset) {
                $template = $this->getAssetTemplate(
                    $group->getProperty(GroupedCollection::PROPERTY_CONTENT_TYPE),
                    $this->addDefaultAttributes($this->getAssetContentType($asset), $attributes)
                );
                $result .= sprintf($template, $asset->getUrl().'?t='.$time);
            }
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $result .= sprintf($template, $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']));
        }
        return $result;
    }

    
}
