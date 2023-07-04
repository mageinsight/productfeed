<?php

namespace MageInsight\ProductFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\App\ResourceConnection;

class AddFeedAttributeValue implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    private $resourceConnection;

    /** Add your attributes which you want to add in feed. */
    protected $attributes = [
        'name',
        'short_description',
        'color',
        'price',
        'special_price',
        'image',
        'small_image'
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ResourceConnection $resourceConnection
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->resourceConnection = $resourceConnection; 
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $connection = $this->resourceConnection->getConnection();
        try {
            if ($connection->tableColumnExists('catalog_eav_attribute', 'used_in_feed') === true) {
                foreach ($this->attributes as $attribute) {
                    $query = "UPDATE catalog_eav_attribute SET used_in_feed = 1 WHERE attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = '" . $attribute . "')";
                    $connection->query($query);
                }
            }
        } catch (\Exception $e) {
            $connection->rollBack();
        }
        
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        /**
         * This internal method, that means that some patches with time can change their names,
         */
        return [];
    }
}