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
use Override;

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
     * @param Context $context
     * @param CollectionFactory $categoryCollectionFactory
     * @param HttpContext $httpContext
     * @param View $categoryView
     * @param Json $json
     * @param Image $imageHelper
     * @param Registry $registry
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        protected readonly CollectionFactory $categoryCollectionFactory,
        protected readonly \Magento\Framework\App\Http\Context $httpContext,
        protected readonly View $categoryView,
        protected readonly Json $json,
        protected readonly Image $imageHelper,
        protected readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Internal constructor, that is called from real constructor
     *
     * @return void
     */
    #[Override]
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
    #[Override]
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
    #[Override]
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
     * @param Collection $collection
     * @return void
     * @throws LocalizedException
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
     * @param Collection $collection
     * @return void
     * @throws LocalizedException
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
     * @param Collection $collection
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * @param Collection $collection
     * @return void
     * @throws LocalizedException
     * @throws StateException
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
     * @param Collection $collection
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * @param Category $category
     * @param string $attributeCode
     * @param array $options
     * @return Image
     */
    public function getImage(Category $category, string $attributeCode = 'image', array $options = [])
    {
        $prefix = !str_contains((string)$category->getData($attributeCode), 'catalog/category/')
            ? 'catalog/category/'
            : '';

        $image = $this->imageHelper->init($category, 'product_base_image', $options);
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
    #[Override]
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

            if ($currentTheme->isHyva() && !str_contains($template, 'hyva')) {
                $template = str_replace('::', '::hyva/', $template);
            }
        }

        return $template;
    }
}
