<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\Data\AttributeOptionInterface;

class UpdatePriceMigration extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Ara\Migration\Model\CustomPrice
     */
    private $customPrice;

    /**
     * @param \Ara\Migration\Model\CustomPrice $customPrice
     * @param \Magento\Framework\App\State $state
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Ara\Migration\Model\CustomPrice $customPrice,
        \Magento\Framework\App\State $state
    )
    {
        try {
            $state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('adminhtml');
        }
        parent::__construct();
        $this->customPrice = $customPrice;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:updatePrice');
        $this->setDescription('update price');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->customPrice->updatePrice();
        $output->writeln("<info>Update price has been finished</info>");
    }

}
