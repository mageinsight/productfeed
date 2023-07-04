<?php 

namespace MageInsight\ProductFeed\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Exception\LocalizedException;

class PriceFeed extends GenerateFeed
{
    const PRODUCT_ENTITY_TYPE = 4;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    protected $directory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Visibility
     */
    protected $visibility;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Stock
     */
    protected $stockHelper;

    protected $customerGroups;

    /**
     * @param StoreManagerInterface $storeManager
     * @param DirectoryList $directoryList
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
        DateTime $dateTime
    ) {
        $this->storeManager = $storeManager;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->resourceConnection = $resourceConnection;

        parent::__construct($storeManager, $visibility, $collectionFactory, $filesystem, $stockHelper, $dateTime, []);
        $this->getCustomerGroups();
    }

    public function getEntity() {
        return 'price';
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
            $formattedData = [];
            foreach ($products as $product) {
                $prices = $product->getTierPrices();
                foreach ($prices as $price) {
                    $formattedData[] = [
                        'sku' => $product->getSku(),
                        'customer_group' => array_key_exists($price['customer_group_id'], $this->customerGroups) ? $this->customerGroups[$price['customer_group_id']]['customer_group_code'] : '',
                        'price' => $price['value']
                    ];
                }
            }
            $headers = ['sku', 'customer_group, price'];
            $this->generateCsv($headers, $formattedData, $website->getCode());
            return true;
        } catch (\Exception $e) {
            return false;
        } catch (LocalizedException $e) {
            return false;
        }
    }

    /**
     * get the list of all customer groups available in the system.
     */
    private function getCustomerGroups() {
        if (empty($this->customerGroups)) {
            $connection = $this->resourceConnection->getConnection();
            try {
                $query = "SELECT * FROM customer_group;";
                $this->customerGroups = $connection->fetchAssoc($query);
            } catch (\Exception $e) {
                $connection->rollBack();
                throw new LocalizedException(__($e->getMessage()));
            }
        }
    }
}