<?php declare(strict_types=1);
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

namespace ParadoxLabs\PageBuilderWidgets\Model\Catalog\View\Media;

use Override;

class Config extends \Magento\Catalog\Model\Product\Media\Config
{
    protected const PATH = 'catalog/category';

    /**
     * Get filesystem directory path for category images relative to the media directory.
     *
     * @return string
     */
    #[Override]
    public function getBaseMediaPathAddition()
    {
        return self::PATH;
    }

    /**
     * Get web-based directory path for category images relative to the media directory.
     *
     * @return string
     */
    #[Override]
    public function getBaseMediaUrlAddition()
    {
        return self::PATH;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function getBaseMediaPath()
    {
        return '';
    }

    /**
     * Process file path.
     *
     * @param string $file
     * @return string
     */
    #[Override]
    protected function _prepareFile($file)
    {
        if (is_string($file)) {
            $file = str_replace(self::PATH . '/', '', $file);
        }

        return parent::_prepareFile($file);
    }
}
