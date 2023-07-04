<?php 

namespace MageInsight\ProductFeed\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\Stdlib\DateTime\DateTime;

abstract class GenerateFeed
{
    /**
     * @var Visibility
     */
    protected $visibility;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var StockHelper
     */
    protected $stockHelper;

    /**
     * @var DateTime
     */
    protected $dateTime;

    protected $directory;

    protected $attributes = [];

    /**
     * @param Visibility $visibility
     * @param CollectionFactory $collectionFactory
     * @param StockHelper $stockHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Visibility $visibility,
        CollectionFactory $collectionFactory,
        \Magento\Framework\Filesystem $filesystem,
        StockHelper $stockHelper,
        DateTime $dateTime,
        $attributes = []
    ) {
        $this->storeManager = $storeManager;
        $this->visibility = $visibility;
        $this->collectionFactory = $collectionFactory;
        $this->stockHelper = $stockHelper;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->attributes = $attributes;
        $this->dateTime = $dateTime;
    }

    /**
     * Method to generate CSV for all the websites at once.
     * 
     * return bool|void
     */
    public function generate() {
        $websites = $this->storeManager->getWebsites();
        if (empty($websites)) {
            return false;
        }

        foreach ($websites as $website) {
            $this->generateForWebsite($website->getId());
        }
    }

    abstract public function generateForWebsite($websiteId);

    abstract public function getEntity();

    /**
     * Get product Collection
     * @param int $storeId
     * @param bool $onlyVisible
     * @param bool $includeNotVisibleIndividually
     * 
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection $products
     */
    public function getProductCollectionQuery(
        $storeId,
        $onlyVisible = true,
        $includeNotVisibleIndividually = false
    ) {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products = $this->collectionFactory->create();

        $products = $products
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->distinct(true);

        if ($onlyVisible) {
            $products = $products->addAttributeToFilter('status', ['=' => Status::STATUS_ENABLED]);

            if ($includeNotVisibleIndividually === false) {
                $products = $products->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSiteIds()]);
            }

            $this->stockHelper->addInStockFilterToCollection($products);
        }
        $products = $products->addAttributeToSelect('status');
        if (!empty($this->attributes)) {
            $products = $products->addAttributeToSelect(array_keys($this->attributes));
        }

        $this->stockHelper->addStockStatusToProducts($products);

        return $products;
    }

    /**
     * Generate CSV for the array passed.
     * @param array $headers
     * @param array $rows
     * @param string $websiteCode
     * 
     * @return void
     */
    public function generateCsv($headers, $rows, $websiteCode) {
        $entity = $this->getEntity();
        $target = 'bloomreach/feeds/' . $entity . '/' . $websiteCode . '/';
        $this->directory->create($target);
        $fileName = $this->dateTime->timestamp() . '.csv';
        $stream = $this->directory->openFile($target . $fileName, 'w+');
        $stream->lock();

        $stream->writeCsv($headers);
        foreach ($rows as $row) {
            $stream->writeCsv($row);
        }
        $stream->flush();
    }
}