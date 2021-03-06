<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductsMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    const DEFAULT_ATTRIBUTE_SET = 4;
    const PRODUCT_DETAILS_ATTRIBUTE_GROUP = 7;
    const ATTRIBUTE_MANUFACTURER = 'manufacturer';

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    private $productResource;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Ara\Migration\Helper\CustomAttribute
     */
    private $attributeHelper;

    /**
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Framework\App\State $state
     * @param \Ara\Migration\Helper\CustomAttribute $attributeHelper
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\App\State $state,
        \Ara\Migration\Helper\CustomAttribute $attributeHelper
    )
    {
        $this->productFactory = $productFactory;
        $this->productResource = $productResource;
        try {
            $state->getAreaCode();
        } catch(\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('frontend');
        }
        $this->attributeHelper = $attributeHelper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:products');
        $this->setDescription('Migrate products');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $imageSourceUrl = 'http://denta.com.ua/uploads/shop/products/large/';
        $res = $this->getSqlData($this->getQuerySql());
        $brands = $this->getSqlData($this->getBrandsSql());
        foreach ($brands as $brand) {
            $this->attributeHelper->createOrGetId(self::ATTRIBUTE_MANUFACTURER, $brand['name']);
        }
        $this->attributeHelper->addAttributeToAllAttributeSets(
            self::ATTRIBUTE_MANUFACTURER, self::PRODUCT_DETAILS_ATTRIBUTE_GROUP
        );
        foreach ($res as $item) {
            if (!empty($item['image'])) {
                $this->downloadRemoteFileWithCurl(
                    $imageSourceUrl . $item['image'],
                    BP . '/pub/media/tmp/catalog/product/' . $item['image']
                );
            }
            $product = $this->productFactory->create();
            $product->isObjectNew(true);

            $product
                ->setId($item['id'])
                ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
                ->setAttributeSetId(self::DEFAULT_ATTRIBUTE_SET)
                ->setWebsiteIds([1])
                ->setName($item['name'])
                ->setSku($item['sku'])
                ->setPrice($item['price'])
                ->setDescription($item['description'])
                ->setShortDescription($item['short_description'])
                ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
                ->setStatus($item['status'])
                ->setCategoryIds(explode(',', $item['category_ids']))
                ->setStockData(
                    [
                        'use_config_manage_stock' => 1,
                        'qty' => $item['qty'],
                        'is_qty_decimal' => 0,
                        'is_in_stock' => 1
                    ]
                )
                ->setUrlKey($item['url_key'])
                ->setCreatedAt($item['created_at'])
                ->setUpdatedAt($item['updated_at'])
                ->setCustomAttribute(
                    self::ATTRIBUTE_MANUFACTURER,
                    $this->attributeHelper->getOptionId(self::ATTRIBUTE_MANUFACTURER, $item['brand'])
                );


            if (!empty($item['image'])) {
                try {
                    $product->addImageToMediaGallery(
                        'tmp/catalog/product/' . $item['image'],
                        ['thumbnail', 'small_image', 'image'],
                        false,
                        false
                    );
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
            try {
                $product->save();
                echo '.';
            } catch(\Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL;
            }
        }
        $output->writeln("<info>Products Migration has been finished</info>");
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
    private function getQuerySql()
    {
        return <<<TAG
select
 spv.product_id as id,
 spv.number as sku,
 spi.name as name,
 spv.price as price,
 sp.created as created_at,
 sp.updated as updated_at,
 sp.active as status,
 sp.url as url_key,
 spv.stock  as qty,
 spi.short_description as short_description,
 spi.full_description as description,
 spv.mainImage as image,
 group_concat(spc.category_id) as category_ids,
sbi.name as brand

-- spv.currency,
-- spv.price_in_main,

from shop_product_variants spv
join shop_product_variants_i18n spvi on spv.id = spvi.id
join shop_products sp on sp.id = spv.product_id
join shop_products_i18n spi on sp.id = spi.id
join shop_product_categories spc on sp.id = spc.product_id
join shop_brands_i18n sbi on sp.brand_id = sbi.id
where
-- spv.mainImage is not null
-- and
sp.name_main_variant is null
and sp.add_group is null
-- and spvi.name = ''
group by
spv.product_id,
 spv.number,
 spi.name,
 spv.price,
 sp.created,
 sp.updated,
 sp.active,
 sp.url,
 spv.stock,
 spi.short_description,
 spi.full_description,
 spv.mainImage,
 sp.brand_id;
TAG;
    }


    private function getBrandsSql()
    {
            return 'select name  from denta.shop_brands_i18n';
    }

    private function downloadRemoteFileWithCurl($file_url, $save_to)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_URL, $file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $file_content = curl_exec($ch);
        curl_close($ch);

        $downloaded_file = fopen($save_to, 'w+');
        fwrite($downloaded_file, $file_content);
        fclose($downloaded_file);

    }
}
