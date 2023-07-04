<?php

namespace MageInsight\ProductFeed\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use MageInsight\ProductFeed\Model\PriceFeed;

class GeneratePriceFeed extends Command
{
    const NAME = 'mageinsight:feed:price';
    const DESCRIPTION = 'This will generate the product feed based on the websiteId';
    const OPTION = 'website_id';
    private \Magento\Framework\App\State $state;

    private $priceFeed;

    public function __construct(
        \Magento\Framework\App\State $state,
        PriceFeed $priceFeed
    ) {
        parent::__construct();
        $this->state = $state;
        $this->priceFeed = $priceFeed;
    }

    protected function configure(): void
    {
        $this->setName(self::NAME);
        $this->setDescription(self::DESCRIPTION);
        $this->addOption(self::OPTION, null, InputOption::VALUE_REQUIRED, 'WebsiteId');

        parent::configure();
    }

    /**
     * Execute command to generate Product Feed
     * @param InputInterface $input
     * @param OutputInterface $output
     * 
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        $output->writeln('<info>-------------------------EXECUTION START-------------------------</info>');

        if ($websiteId = $input->getOption(self::OPTION)) {
            $response = $this->priceFeed->generateForWebsite($websiteId);
            if ($response === false) {
                $output->writeln('<error>Something went wrong while generating the price feed for website: `' . $websiteId . '`</error>');
            } else {
                $output->writeln('<info>Price feed is generated for the website: `' . $websiteId . '`</info>');
            }
        }
        $output->writeln('<info>-------------------------EXECUTION COMPLETE-------------------------</info>');
        return 0;
    }
}