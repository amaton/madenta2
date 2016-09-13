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

class ConfigurableProductsMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    const DEFAULT_ATTRIBUTE_SET = 4;
    const PRODUCT_DETAILS_ATTRIBUTE_GROUP = 7;
    const ATTRIBUTE_MANUFACTURER = 'manufacturer';
    protected $attributes;

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
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var \Magento\CatalogInventory\Model\Stock\ItemFactory
     */
    private $stockItemFactory;
    /**
     * @var \Magento\ConfigurableProduct\Helper\Product\Options\Factory
     */
    private $optionsFactory;
    /**
     * @var \Magento\Indexer\Model\Processor
     */
    private $processor;
    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    private $eavSetup;
    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    private $cacheTypeList;
    /**
     * @var \Magento\Framework\App\Cache\Frontend\Pool
     */
    private $cacheFrontendPool;
    /**
     * @var \Magento\Eav\Model\Config
     */
    private $eavConfig;
    /**
     * @var \Magento\ConfigurableProduct\Api\LinkManagementInterface
     */
    private $linkManagement;

    /**
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Framework\App\State $state
     * @param \Ara\Migration\Helper\CustomAttribute $attributeHelper
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory
     * @param \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory
     * @param \Magento\Indexer\Model\Processor $processor
     * @param \Magento\Eav\Setup\EavSetup $eavSetup
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\ConfigurableProduct\Api\LinkManagementInterface $linkManagement
     * @throws \Magento\Framework\Exception\LocalizedException
     * @internal param \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItem
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Framework\App\State $state,
        \Ara\Migration\Helper\CustomAttribute $attributeHelper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory,
        \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory,
        \Magento\Indexer\Model\Processor $processor,
        \Magento\Eav\Setup\EavSetup $eavSetup,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\ConfigurableProduct\Api\LinkManagementInterface $linkManagement
    )
    {
        $this->productFactory = $productFactory;
        $this->productResource = $productResource;
        try {
            $state->getAreaCode();
        } catch(\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('adminhtml');
        }
        $this->attributeHelper = $attributeHelper;
        parent::__construct();
        $this->productRepository = $productRepository;
        $this->stockItemFactory = $stockItemFactory;
        $this->optionsFactory = $optionsFactory;
        $this->processor = $processor;
        $this->eavSetup = $eavSetup;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->eavConfig = $eavConfig;
        $this->linkManagement = $linkManagement;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:configurableProducts');
        $this->setDescription('Migrate products');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $imageSourceUrl = 'http://denta.com.ua/uploads/shop/products/large/';

       $res = $this->getSqlData($this->getQuerySql());

        foreach ($res as $item) {
            $product = $this->productFactory->create();
            $product->isObjectNew(true);

            try {
                /** @var \Magento\Catalog\Api\Data\ProductInterface $productToDelete */
                $productToDelete = $this->productRepository->getById($item['id']);
                $children = $this->linkManagement->getChildren($productToDelete->getSku());
                foreach ($children as $child) {
                    $this->productRepository->delete($child);
                }
                $this->productRepository->delete($productToDelete);
            } catch (\Exception $e) {
                // Nothing to remove
            }

            $imagePath = BP . '/pub/media/tmp/catalog/product/' . $item['image'];
            if (!empty($item['image']) && !file_exists($imagePath)) {
                $this->downloadRemoteFileWithCurl(
                    $imageSourceUrl . $item['image'],
                    $imagePath
                );
            }

            $product
                ->setTypeId(Configurable::TYPE_CODE)
                ->setId($item['id'])
                ->setAttributeSetId(self::DEFAULT_ATTRIBUTE_SET)
                ->setWebsiteIds([1])
                ->setName($item['name'])
                ->setSku($item['sku'])
                ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
                ->setStatus($item['status'])
                ->setPrice($item['price'])
                ->setDescription($item['description'])
                ->setShortDescription($item['short_description'])
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
                $product->addImageToMediaGallery(
                    'tmp/catalog/product/' . $item['image'],
                    ['thumbnail', 'small_image', 'image'],
                    false,
                    false
                );
            }
            $product->save();
        }
        foreach ($res as $item) {
            $attributeCode = $this->getAttributeCode($item['id']);
            if ($attributeCode == '0') {
                continue;
            }
            $attribute = $this->attributeHelper->getAttribute($attributeCode);
            $attributeValues = [];
            $associatedProductIds = [];

            /** @var AttributeOptionInterface[] $options */
            $options = $attribute->getOptions();
            array_shift($options); //remove the first option which is empty

            foreach ($options as $option) {
                $simpleProduct = $this->productFactory->create();
                $simpleProduct->isObjectNew(true);
                $simpleProduct->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
                    ->setAttributeSetId(self::DEFAULT_ATTRIBUTE_SET)
                    ->setWebsiteIds([1])
                    ->setName($item['name'] . '-' . $option->getLabel())
                    ->setSku($item['sku'] . '-' . $option->getLabel())
                    ->setPrice($item['price'])
                    ->setData($attributeCode, $option->getValue())
                    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE)
                    ->setStatus($item['status']);

                $simpleProduct->save();

                /** @var \Magento\CatalogInventory\Model\Stock\Item $stockItem */
                $stockItem = $this->stockItemFactory->create();
                $stockItem->load($simpleProduct->getId(), 'product_id');

                if (!$stockItem->getProductId()) {
                    $stockItem->setProductId($simpleProduct->getId());
                }
                $stockItem->setUseConfigManageStock(1);
                $stockItem->setQty($item['qty']);
                $stockItem->setIsQtyDecimal(0);
                $stockItem->setIsInStock(1);
                $stockItem->save();

                $attributeValues[] = [
                    'label' => 'test',
                    'attribute_id' => $attribute->getId(),
                    'value_index' => $option->getValue(),
                ];
                $associatedProductIds[] = $simpleProduct->getId();
            }


            $configurableAttributesData = [
                [
                    'attribute_id' => $attribute->getId(),
                    'code' => $attribute->getAttributeCode(),
                    'label' => $attribute->getStoreLabel(),
                    'position' => '0',
                    'values' => $attributeValues,
                ],
            ];

            $configurableOptions = $this->optionsFactory->create($configurableAttributesData);

            $product = $this->productFactory->create()->load($item['id']);
            $extensionConfigurableAttributes = $product->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
            $extensionConfigurableAttributes->setConfigurableProductLinks($associatedProductIds);

            $product->setExtensionAttributes($extensionConfigurableAttributes);
            $product->save();
        }
        $output->writeln("<info>Configurable Products Migration has been finished</info>");
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
-- spvi.name as variation_name,
 spv.price as price,
 sp.created as created_at,
 sp.updated as updated_at,
 sp.active as status,
 sp.url as url_key,
 spv.stock  as qty,
 spi.short_description as short_description,
 spi.full_description as description,
 spv.mainImage as image,
 group_concat(distinct spc.category_id) as category_ids,
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
(sp.name_main_variant is not null  or sp.add_group is not null)
 and spvi.name <> ''
 -- and sp.id > 395
group by
spv.product_id,
 spv.number,
 spi.name,
-- spvi.name,
 spv.price,
 sp.created,
 sp.updated,
 sp.active,
 sp.url,
 spv.stock,
 spi.short_description,
 spi.full_description,
-- spv.mainImage,
 sp.brand_id;
TAG;
    }

    /**
     * @return string
     */
    private function getOneAttributeQuerySql()
    {
        return <<<TAG
select
IFNULL(TRIM(t.name_main_variant), 'Цвет') as attribute_name,
 t.attribute_value, group_concat(t.id) as product_id,
 if(t.number <> '', group_concat(t.number), CONCAT(group_concat(t.id),'001')) as product_sku
 -- , group_concat(distinct t.category_id)
from
(
select
	spv.number,
	sp.id,
	sp.name_main_variant,
	count(*),
	group_concat(spvi.name order by spvi.id SEPARATOR '|') as attribute_value,
	group_concat(distinct sp.category_id) as category_id
from denta.shop_products sp
inner join denta.shop_product_variants spv on sp.id = spv.product_id
inner join  denta.shop_product_variants_i18n spvi on spv.id = spvi.id
where
	spvi.name <> ''
	and (sp.add_group is null or sp.add_group = 'a:0:{}')
group by sp.id, sp.name_main_variant
having count(*) > 1
order by spv.number desc
) t
group by t.name_main_variant, t.attribute_value
TAG;
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

    protected function getAttributeCode($productId)
    {
        if ($this->attributes === null) {
            $attributesRes = $this->getSqlData($this->getOneAttributeQuerySql());
            foreach ($attributesRes as $row) {
                $ids = explode(',', $row['product_id']);
                foreach($ids as $id) {
                    $attrString = strtr($this->attributeHelper->translit($row['attribute_name']), '/', '_');
                    $attrName =  implode('_', explode(' ', $attrString)) . '_' . $ids[0];
                    $this->attributes[$id] = $attrName;
                }
            }
        }
        return isset($this->attributes[$productId]) ? $this->attributes[$productId] : 0;
    }
}
