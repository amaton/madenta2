<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ManufacturerMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    const ATTRIBUTE_MANUFACTURER = 'manufacturer';

    /**
     * @var \Ara\Migration\Helper\CustomAttribute
     */
    private $attributeHelper;
    /**
     * @var \Magento\Indexer\Model\Processor
     */
    private $processor;
    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    private $cacheManager;
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param \Magento\Framework\App\State $state
     * @param \Ara\Migration\Helper\CustomAttribute $attributeHelper
     * @param \Magento\Indexer\Model\Processor $processor
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Ara\Migration\Helper\CustomAttribute $attributeHelper,
        \Magento\Indexer\Model\Processor $processor,
        \Magento\Framework\App\Cache\Manager $cacheManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        try {
            $state->getAreaCode();
        } catch(\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('frontend');
        }
        $this->attributeHelper = $attributeHelper;
        parent::__construct();
        $this->processor = $processor;
        $this->cacheManager = $cacheManager;
        $this->productRepository = $productRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:manufacturer');
        $this->setDescription('Migrate products');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->applyConfigurableToManufacturer();

        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $this->processor->reindexAll();

        $this->setManufacturerAtributeForConfigurableProducts();

        $output->writeln("<info>Manufacturer Migration has been finished</info>");
    }

    /**
     * @return array
     */
    private function getSqlData($sql)
    {
        $dsn = 'mysql:dbname=' . self::DB_NAME . ';host=' . self::DB_HOST . ';charset=UTF8';
        $user = self::DB_USER;
        $password = self::DB_PASSWORD;

        try {
            $dbh = new \PDO($dsn, $user, $password);
        } catch (\PDOException $e) {
            echo 'Cannot connect: ' . $e->getMessage();
        }
        $statement = $dbh->query($sql);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return string
     */
    private function getAllConfigurableProductsQuerySql()
    {
        return <<<TAG
select
 spv.product_id as id,
sbi.name as brand
from shop_product_variants spv
join shop_product_variants_i18n spvi on spv.id = spvi.id
join shop_products sp on sp.id = spv.product_id
join shop_brands_i18n sbi on sp.brand_id = sbi.id
where
(sp.name_main_variant is not null  or sp.add_group is not null)
 and spvi.name <> ''
group by
spv.product_id,
 spv.number,
 spv.price,
 sp.created,
 sp.updated,
 sp.active,
 sp.url,
 spv.stock,
 sp.brand_id;
TAG;
    }

    private function setManufacturerAtributeForConfigurableProducts()
    {
        $res = $this->getSqlData($this->getAllConfigurableProductsQuerySql());

        foreach ($res as $item) {
            $product = $this->productRepository->getById($item['id']);
            $product
                ->setCustomAttribute(
                    self::ATTRIBUTE_MANUFACTURER,
                    $this->attributeHelper->getOptionId(self::ATTRIBUTE_MANUFACTURER, $item['brand'])
                );
            try {
                $this->productRepository->save($product);
                echo '.';
            } catch(\Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL;
            }
        }
    }

    private function applyConfigurableToManufacturer()
    {
        $customAttribute = $this->attributeHelper->getAttribute(self::ATTRIBUTE_MANUFACTURER);
        $customAttribute->setApplyTo(array_merge($customAttribute->getApplyTo(), ['configurable']));
        $this->attributeHelper->saveAttribute($customAttribute);
    }
}
