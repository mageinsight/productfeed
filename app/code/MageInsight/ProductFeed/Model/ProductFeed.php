<?php 

namespace MageInsight\ProductFeed\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class ProductFeed extends GenerateFeed
{
    const PRODUCT_ENTITY_TYPE = 4;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    private $feedAttributes = [];

    private $additionalAttributes = [
        'sku',
        'stock',
        'manage_stock',
        'category_level1',
        'category_level2',
        'category_level3'
    ];

    private $categories = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param ResourceConnection $resourceConnection
     * @param Visibility $visibility
     * @param CollectionFactory $collectionFactory
     * @param StockHelper $stockHelper
     * @param DateTime $dateTime
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $filesystem,
        ResourceConnection $resourceConnection,
        Visibility $visibility,
        CollectionFactory $collectionFactory,
        StockHelper $stockHelper,
        DateTime $dateTime,
        StockRegistryInterface $stockRegistry
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->feedAttributes = $this->getFeedAttributes();
        $this->stockRegistry = $stockRegistry;
        parent::__construct($storeManager, $visibility, $collectionFactory, $filesystem, $stockHelper, $dateTime, $this->feedAttributes);

        $this->getCategoryDetails();
    }

    public function getEntity() {
        return 'product';
    }

    /**
     * This method basically genearets the feeds based on website.
     * This is created separately to give functionality to generate feed for single website through console command
     * @param int $websiteId
     * 
     * @return bool
     */
    public function generateForWebsite($websiteId) {
        try {
            $website = $this->storeManager->getWebsite($websiteId);
            $storeId = $website->getDefaultStore()->getId();
            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
            $products = $this->getProductCollectionQuery($storeId);
            if ($products->getSize() > 0) {
                $formattedData = $this->prepareProductData($products);
                $header = array_merge($this->additionalAttributes, array_keys($this->feedAttributes));
                $this->generateCsv($header, $formattedData, $website->getCode());
            }
            return true;
        } catch (\Exception $e) {
            return false;
        } catch (LocalizedException $e) {
            return false;
        }
    }

    /**
     * Get list of attributes needs to be added in feed.
     * 
     * @return array
     */
    public function getFeedAttributes() {
        if (empty($this->feedAttributes)) {
            $connection = $this->resourceConnection->getConnection();
            try {
                // Facing deployment issue for the column. So added check that this will be executed only if column exist.
                if ($connection->tableColumnExists('catalog_eav_attribute', 'used_in_feed') === true) {
                    $query = "SELECT ea.attribute_code, ea.frontend_input FROM eav_attribute AS ea
                        LEFT JOIN catalog_eav_attribute AS cea ON cea.attribute_id = ea.attribute_id
                        WHERE ea.entity_type_id = 4 AND cea.used_in_feed = 1;";
                    return $connection->fetchAssoc($query);
                }
            } catch (\Exception $e) {
                $connection->rollBack();
                throw new LocalizedException(__($e->getMessage()));
            }
        }
    }

    /**
     * Prepare the product's data in required format which can be converted into CSV.
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $products
     * 
     * @return array
     */
    public function prepareProductData($products)
    {
        $details = [];
        foreach ($products as $product) {
            $id = $product->getId();
            $details[$id]['sku'] = $product->getSku();
            $details[$id]['stock'] = $product->getIsSalable();
            $details[$id]['manage_stock'] = $this->getManageStock($product->getId());
            $this->getProductCategories($product, $details);
            foreach( $this->feedAttributes as $code => $attr) {
                if ($attr['frontend_input'] == 'select') {
                    $re = $product->getResource()->getAttribute($code);
                    if ($re->usesSource()) {
                        $details[$id][$code] = $re->getSource()->getOptionText($product->getData($code));
                    }
                } else {
                    $details[$id][$code] = $product->getData($code);
                }
            }
        }

        return $details;
    }

    /**
     * Get the list of product categories
     * 
     * @var $product
     * @var array $details
     */
    private function getProductCategories($product, &$details) {
        $productCategories = $product->getCategoryIds();
        $productId = $product->getId();
        $level1 = [];
        $level2 = [];
        $level3 = [];
        foreach ($productCategories as $productCategory) {
            if (array_key_exists($productCategory, $this->categories)) {
                if ($this->categories[$productCategory]['status'] == 0) {
                    continue;
                }

                $categories = explode('/', $this->categories[$productCategory]['path']);
                foreach ($categories as $category) {
                    if (array_key_exists($category, $this->categories)) {
                        $level1[] = $this->categories[$category]['level'] == 2 ? $this->categories[$category]['name'] : '';
                        $level2[] = $this->categories[$category]['level'] == 3 ? $this->categories[$category]['name'] : '';
                        $level3[] = $this->categories[$category]['level'] == 4 ? $this->categories[$category]['name'] : '';
                    }
                }
            }
        }
        $level1 = array_unique(array_filter($level1));
        $level2 = array_unique(array_filter($level2));
        $level3 = array_unique(array_filter($level3));
        $details[$productId]['category_level1'] = !empty($level1) ? implode(',', $level1) : '';
        $details[$productId]['category_level2'] = !empty($level2) ? implode(',', $level2) : '';
        $details[$productId]['category_level3'] = !empty($level3) ? implode(',', $level3) : '';
    }

    /**
     * Get all category details at once so that no need to call again and again.
     * 
     * @return void
     */
    private function getCategoryDetails() {
        if (empty($this->categories)) {
            $connection = $this->resourceConnection->getConnection();
            try {
                $query = "SELECT cce.entity_id, cce.parent_id, cce.path, cce.level, ccei.value as status, ccev.value as name FROM catalog_category_entity AS cce
                LEFT JOIN catalog_category_entity_varchar AS ccev ON ccev.row_id = cce.row_id AND ccev.attribute_id = 45 AND ccev.store_id = 0
                LEFT JOIN catalog_category_entity_int AS ccei ON ccei.row_id = cce.row_id AND ccei.attribute_id = 46 AND ccei.store_id = 0
                WHERE cce.path LIKE (SELECT CONCAT('1/',root_category_id,'/%') FROM store_group WHERE website_id = 1) AND cce.level != 1;";
                $this->categories = $connection->fetchAssoc($query);
            } catch (\Exception $e) {
                $connection->rollBack();
                throw new LocalizedException(__($e->getMessage()));
            }
        }
    }

    /**
     * get Product's manage_stock status.
     * @param int $productId
     * 
     * @return integer
     */
    private function getManageStock($productId) {
        $stock = $this->stockRegistry->getStockItem($productId);
        if (!empty($stock)) {
            return $stock['manage_stock'];
        }

        return 0;
    }
}