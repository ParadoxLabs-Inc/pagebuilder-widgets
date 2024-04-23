<?php
/**
 * Paradox Labs, Inc.
 * http://www.paradoxlabs.com
 * 717-431-3330
 *
 * Need help? Open a ticket in our support system:
 *  http://support.paradoxlabs.com
 *
 * @author      Ryan Hoerr <info@paradoxlabs.com>
 */

namespace ParadoxLabs\PageBuilderWidgets\Model\Catalog\View\Asset;

use Magento\Catalog\Model\View\Asset\VirtualCategoryImage;

/**
 * Class ImageFactory
 */
class ImageFactory extends \Magento\Catalog\Model\View\Asset\ImageFactory
{
    /**
     * @param array $data
     * @return \Magento\Catalog\Model\View\Asset\Image|\Magento\Catalog\Model\View\Asset\VirtualCategoryImage|mixed
     */
    public function create(array $data = [])
    {
        // If image path starts with catalog/category/, use a separate asset class
        if (isset($data['filePath'])
            && is_string($data['filePath'])
            && strpos($data['filePath'], 'catalog/category/') !== false) {
            return $this->_objectManager->create(
                VirtualCategoryImage::class,
                $data
            );
        }

        return parent::create($data);
    }
}
