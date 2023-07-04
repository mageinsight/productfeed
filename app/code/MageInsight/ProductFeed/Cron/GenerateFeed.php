<?php

namespace MageInsight\ProductFeed\Cron;

use MageInsight\ProductFeed\Model\ProductFeed;
use MageInsight\ProductFeed\Model\PriceFeed;

class GenerateFeed
{
    protected $productFeed;

    protected $priceFeed;

    public function __construct(
        ProductFeed $productFeed,
        PriceFeed $priceFeed
    ) {
        $this->productFeed = $productFeed;
        $this->priceFeed = $priceFeed;
    }

    public function generateProductFeed()
    {
        $this->productFeed->generate();
    }
    
    public function generatePriceFeed()
    {
        $this->priceFeed->generate();
    }
}
