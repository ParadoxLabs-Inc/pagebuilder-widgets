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

namespace ParadoxLabs\PageBuilderWidgets\Block\Category;

use Hyva\Theme\Service\CurrentTheme;
use Magento\Catalog\Block\Category\View;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\Widget\Html\Pager;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

/**
 * Catalog Products List widget block
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 */
class CategoriesList extends Template implements BlockInterface, IdentityInterface
{
    protected const DEFAULT_CATEGORIES_COUNT = 5;
    protected const DEFAULT_CATEGORIES_PER_PAGE = 5;

    /**
     * Instance of pager block
     *
     * @var Pager
     */
    protected $pager;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * Product collection factory
     *
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Block\Category\View
     */
    protected $categoryView;

    /**
     * Json Serializer Instance
     *
     * @var Json
     */
    protected $json;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @param Context $context
     * @param CollectionFactory $categoryCollectionFactory
     * @param HttpContext $httpContext
     * @param \Magento\Catalog\Block\Category\View $categoryView
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        CollectionFactory $categoryCollectionFactory,
        HttpContext $httpContext,
        View $categoryView,
        Json $json,
        Image $imageHelper,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->httpContext               = $httpContext;
        $this->json                      = $json;
        $this->categoryView              = $categoryView;
        $this->imageHelper               = $imageHelper;
        $this->registry                  = $registry;
    }

    /**
     * Internal constructor, that is called from real constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->addData([
            'cache_lifetime' => 86400,
            'cache_tags' => [
                Category::CACHE_TAG,
            ],
        ]);
    }

    /**
     * Get key pieces for caching block content
     *
     * @return array
     * @SuppressWarnings(PHPMD.RequestAwareBlockMethod)
     * @throws NoSuchEntityException
     */
    public function getCacheKeyInfo()
    {
        return [
            'CATALOG_CATEGORIES_LIST_WIDGET',
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP),
            $this->getCategoriesPerPage(),
            $this->getCategoriesCount(),
            $this->json->serialize($this->getRequest()->getParams()),
            $this->getTemplate(),
            $this->getTitle(),
            $this->getConditionOption(),
            $this->getCategoryId(),
            $this->getCategoryIds(),
            $this->getSortOrder(),
            $this->getShowName(),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _beforeToHtml()
    {
        $collection = $this->createCollection();

        /**
         * If sort_order is position, manually sort the collection to match the input order
         */
        $isDefinedCategories = !in_array(
            $this->getData('condition_option'),
            ['parent_category_id', 'current_category_childs'],
            true
        );

        if ($this->getSortOrder() === 'position' && $isDefinedCategories) {
            $this->applyManualPositionSorting($collection);
        }

        $this->setCategoryCollection($collection);

        return parent::_beforeToHtml();
    }

    /**
     * Prepare and return product collection
     *
     * @return Collection
     * @SuppressWarnings(PHPMD.RequestAwareBlockMethod)
     * @throws LocalizedException
     */
    public function createCollection()
    {
        /** @var $collection Collection */
        $collection = $this->categoryCollectionFactory->create();
        $collection->addIsActiveFilter();
        $collection->addAttributeToSelect('*');

        if ($this->getData('store_id') !== null) {
            $collection->setStoreId($this->getData('store_id'));
        }

        $sortOrder = explode('_', (string)$this->getSortOrder(), 2);
        $collection->addAttributeToSort(
            $sortOrder[0],
            (isset($sortOrder[1]) && $sortOrder[1] === 'descending') ? 'desc' : 'asc'
        );

        $collection->setPageSize($this->getPageSize())
                   ->setCurPage($this->getRequest()->getParam($this->getData('page_var_name'), 1));

        $this->applyCategoryFilters($collection);

        /**
         * Prevent retrieval of duplicate records. This may occur when multiselect product attribute matches
         * several allowed values from condition simultaneously
         */
        $collection->distinct(true);

        return $collection;
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function applyManualPositionSorting(Collection $collection): void
    {
        // Get the defined order of categories, with each ID in sequence
        $desiredOrder = explode(',', (string)$this->getData('category_ids'));
        $sortedItems  = [];

        // Get the loaded items and clear the collection
        $items = $collection->getItems();
        $collection->removeAllItems();

        // Loop over the defined order of IDs, and add each one back to the collection in sequence
        foreach ($desiredOrder as $itemId) {
            if (isset($items[ $itemId ])) {
                $collection->addItem($items[ $itemId ]);
            }
        }
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function applyCategoryFilters(Collection $collection): void
    {
        match ($this->getData('condition_option')) {
            'parent_category_id' => $this->applyCategoryFiltersParentId($collection),
            'current_category_childs' => $this->applyCategoryFiltersContext($collection),
            default => $this->applyCategoryFiltersEntityId($collection),
        };
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function applyCategoryFiltersEntityId(Collection $collection): void
    {
        if (empty($this->getData('category_ids'))) {
            throw new StateException(
                __('Please select one or more categories to display.')
            );
        }

        $collection->addAttributeToFilter(
            'entity_id',
            [
                'in' => explode(',', (string)$this->getData('category_ids')),
            ]
        );
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function applyCategoryFiltersParentId(Collection $collection): void
    {
        if (empty($this->getData('category_id'))) {
            throw new StateException(
                __('Please select a parent category to display.')
            );
        }

        $collection->addAttributeToFilter(
            'parent_id',
            $this->getData('category_id')
        );
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function applyCategoryFiltersContext(Collection $collection): void
    {
        $currentCategory = $this->registry->registry('current_category');
        if ($currentCategory instanceof Category) {
            $collection->addAttributeToFilter('parent_id', $currentCategory->getId());
        } else {
            // If we don't have a current_category in this context, fall back to the store's root category
            $collection->addAttributeToFilter(
                'parent_id',
                $this->_storeManager->getStore()->getRootCategoryId()
            );
        }

        // In context mode, only show nav-enabled categories
        $collection->addAttributeToFilter('include_in_menu', 1);
    }

    /**
     * Retrieve how many products should be displayed
     *
     * @return int
     */
    public function getCategoriesCount()
    {
        return $this->getData('categories_count')
            ?? $this->getData('categories_count')
            ?? self::DEFAULT_CATEGORIES_COUNT;
    }

    /**
     * Retrieve how many products should be displayed
     *
     * @return int
     */
    public function getCategoriesPerPage()
    {
        return $this->getData('categories_per_page')
            ?? self::DEFAULT_CATEGORIES_PER_PAGE;
    }

    /**
     * Return flag whether pager need to be shown or not
     *
     * @return bool
     */
    public function showPager()
    {
        if (!$this->hasData('show_pager')) {
            $this->setData('show_pager', false);
        }

        return (bool)$this->getData('show_pager');
    }

    /**
     * Retrieve how many products should be displayed on page
     *
     * @return int
     */
    protected function getPageSize()
    {
        return $this->showPager() ? $this->getCategoriesPerPage() : $this->getCategoriesCount();
    }

    /**
     * Render pagination HTML
     *
     * @return string
     * @throws LocalizedException
     */
    public function getPagerHtml()
    {
        if ($this->showPager() && $this->getCategoryCollection()->getSize() > $this->getCategoriesPerPage()) {
            if (!$this->pager) {
                $this->pager = $this->getLayout()->createBlock(
                    Pager::class,
                    $this->getWidgetPagerBlockName()
                );

                $this->pager->setUseContainer(true)
                            ->setShowAmounts(true)
                            ->setShowPerPage(false)
                            ->setPageVarName($this->getData('page_var_name') ?: 'p')
                            ->setLimit($this->getCategoriesPerPage())
                            ->setTotalLimit($this->getCategoriesCount())
                            ->setCollection($this->getCategoryCollection());
            }
            if ($this->pager instanceof AbstractBlock) {
                return $this->pager->toHtml();
            }
        }

        return '';
    }

    /**
     * Return identifiers for produced content
     *
     * @return array
     */
    public function getIdentities()
    {
        $identities = [];
        if ($this->getCategoryCollection()) {
            foreach ($this->getCategoryCollection() as $category) {
                if ($category instanceof IdentityInterface) {
                    $identities[] = $category->getIdentities();
                }
            }
        }
        $identities = array_merge([], ...$identities);

        return $identities ?: [Product::CACHE_TAG];
    }

    /**
     * Get value of widgets' title parameter
     *
     * @return mixed|string
     */
    public function getTitle()
    {
        return $this->getData('title');
    }

    /**
     * Get widget block name
     *
     * @return string
     */
    private function getWidgetPagerBlockName()
    {
        $pageName = $this->getData('page_var_name');
        $pagerBlockName = 'widget.category.list.pager';

        if (!$pageName) {
            return $pagerBlockName;
        }

        return $pagerBlockName . '.' . $pageName;
    }

    /**
     * @param \Magento\Catalog\Model\Category $category
     * @param string $attributeCode
     * @param array $options
     * @return \Magento\Catalog\Helper\Image
     */
    public function getImage(Category $category, string $attributeCode = 'image', array $options = [])
    {
        $prefix = strpos((string)$category->getData($attributeCode), 'catalog/category/') === false
            ? 'catalog/category/'
            : '';

        $image  = $this->imageHelper->init($category, 'product_base_image', $options);
        $image->setImageFile($prefix . str_replace('/media/', '', (string)$category->getData($attributeCode)));

        return $image;
    }

    /**
     * @return string
     */
    public function getCategorySuffix(): string
    {
        return (string)$this->_scopeConfig->getValue('catalog/seo/category_url_suffix');
    }

    /**
     * Get relevant path to template
     *
     * @return string
     */
    public function getTemplate()
    {
        $template = (string)parent::getTemplate();

        /**
         * If Hyva, inject Hyva path
         */
        if (class_exists(CurrentTheme::class)) {
            $currentTheme = ObjectManager::getInstance()->get(
                CurrentTheme::class
            );

            if ($currentTheme->isHyva() && strpos($template, 'hyva') === false) {
                $template = str_replace('::', '::hyva/', $template);
            }
        }

        return $template;
    }
}
