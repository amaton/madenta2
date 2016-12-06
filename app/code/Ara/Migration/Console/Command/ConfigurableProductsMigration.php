<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Config\Definition\Exception\Exception;
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
     * @var \Magento\ConfigurableProduct\Api\LinkManagementInterface
     */
    private $linkManagement;
    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    private $cacheManager;

    /**
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Framework\App\State $state
     * @param \Ara\Migration\Helper\CustomAttribute $attributeHelper
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory
     * @param \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory
     * @param \Magento\Indexer\Model\Processor $processor
     * @param \Magento\ConfigurableProduct\Api\LinkManagementInterface $linkManagement
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @throws \Magento\Framework\Exception\LocalizedException
     * @internal param \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItem
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\State $state,
        \Ara\Migration\Helper\CustomAttribute $attributeHelper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory,
        \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory,
        \Magento\Indexer\Model\Processor $processor,
        \Magento\ConfigurableProduct\Api\LinkManagementInterface $linkManagement,
        \Magento\Framework\App\Cache\Manager $cacheManager
    )
    {
        $this->productFactory = $productFactory;
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
        $this->linkManagement = $linkManagement;
        $this->cacheManager = $cacheManager;
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

       $res = $this->getSqlData($this->getAllConfigurableProductsQuerySql());

        foreach ($res as $item) {
            $this->createConfigurableProduct($item);
            //only for items with one attribute
            $this->createVariations($item);
        }

        $this->attributes = null;
        $res = $this->getSqlData($this->getProductsWithTwoAttibutesQuerySql());
        foreach ($res as $item) {
            $this->createVariationsForTwoAttributesProduct($item);
        }

        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $this->processor->reindexAll();


        $output->writeln("<info>Configurable Products Migration has been finished</info>");
    }

    /**
     * @param $item
     * @return array
     */
    protected function createConfigurableProduct($item)
    {
        $imageSourceUrl = 'http://denta.com.ua/uploads/shop/products/large/';
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
        try {
            $product->save();
            echo ':';
        } catch (\Exception $e) {
            echo PHP_EOL . $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @param $item
     */
    protected function createVariations($item)
    {
        $attributeCode = $this->getAttributeCode($item['id']);
        if ($attributeCode == '0') {
            return;
        }
        try {
            $attribute = $this->attributeHelper->getAttribute($attributeCode);
        } catch (\Exception $e) {
            echo 'Attribute cannot be added to product id ' . $item['id'] . $e->getMessage();
            return;
        }
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

            try {
                $simpleProduct->save();
                echo '.';
            } catch (\Exception $e) {
                echo PHP_EOL . $e->getMessage() . PHP_EOL;
            }

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
        try {
            $product->save();
            echo ':';
        } catch (\Exception $e) {
            echo PHP_EOL . $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @param $item
     */
    protected function createVariationsForTwoAttributesProduct($item)
    {
        $attributeCodes = $this->getAttributeCodes($item['id']);
        if (empty($attributeCodes)) {
            return;
        }

        list($attributeCode1, $attributeCode2) = $attributeCodes;
        try {
            $attribute1 = $this->attributeHelper->getAttribute($attributeCode1);
            $attribute2 = $this->attributeHelper->getAttribute($attributeCode2);
        } catch (\Exception $e) {
            echo 'Attribute cannot be added to product id ' . $item['id'] . $e->getMessage();
            return;
        }
        $attribute1Values = [];
        $attribute2Values = [];
        $associatedProductIds = [];

        /** @var AttributeOptionInterface[] $options */
        $options1 = $attribute1->getOptions();
        array_shift($options1); //remove the first option which is empty

        /** @var AttributeOptionInterface[] $options */
        $options2 = $attribute2->getOptions();
        array_shift($options2); //remove the first option which is empty

        foreach ($options1 as $option1) {
            foreach ($options2 as $option2) {

                $simpleProduct = $this->productFactory->create();
                $simpleProduct->isObjectNew(true);
                $suffix = '-' . $option1->getLabel() . '-' . $option2->getLabel();
                $simpleProduct->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
                    ->setAttributeSetId(self::DEFAULT_ATTRIBUTE_SET)
                    ->setWebsiteIds([1])
                    ->setName($item['name'] . $suffix)
                    ->setSku($item['sku'] . $suffix)
                    ->setPrice($item['price'])
                    ->setData($attributeCode1, $option1->getValue())
                    ->setData($attributeCode2, $option2->getValue())
                    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE)
                    ->setStatus($item['status']);

                try {
                    $simpleProduct->save();
                    echo '.';
                } catch (\Exception $e) {
                    echo PHP_EOL . $e->getMessage() . PHP_EOL;
                }

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

                $attribute2Values[] = [
                    'label' => $option2->getLabel(),
                    'attribute_id' => $attribute2->getAttributeId(),
                    'value_index' => $option2->getValue(),
                ];
                $associatedProductIds[] = $simpleProduct->getId();
            }
            $attribute1Values[] = [
                'label' => $option1->getLabel(),
                'attribute_id' => $attribute1->getAttributeId(),
                'value_index' => $option1->getValue(),
            ];
        }

        $configurableAttributesData = [
            [
                'attribute_id' => $attribute1->getAttributeId(),
                'code' => $attribute1->getAttributeCode(),
                'label' => $attribute1->getDefaultFrontendLabel(),
                'position' => '0',
                'values' => $attribute1Values,
            ],
            [
                'attribute_id' => $attribute2->getAttributeId(),
                'code' => $attribute2->getAttributeCode(),
                'label' => $attribute2->getDefaultFrontendLabel(),
                'position' => '0',
                'values' => $attribute2Values,
            ],
        ];

        $configurableOptions = $this->optionsFactory->create($configurableAttributesData);

        $product = $this->productRepository->getById($item['id']);
        $extensionConfigurableAttributes = $product->getExtensionAttributes();
        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
        $extensionConfigurableAttributes->setConfigurableProductLinks($associatedProductIds);

        $product->setExtensionAttributes($extensionConfigurableAttributes);
        try {
            $this->productRepository->save($product);
        } catch (\Exception $e) {
            echo PHP_EOL . $e->getMessage() . PHP_EOL;
        }
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

    protected function getAttributeCodes($productId)
    {
        if ($this->attributes === null) {
            $attributesRes = $this->getSqlData($this->getFirstAttributeQuerySql());
            foreach ($attributesRes as $row) {
                $ids = explode(',', $row['product_id']);
                foreach($ids as $id) {
                    $attrString = strtr($this->attributeHelper->translit($row['attribute_name']), '/', '_');
                    $attrName =  implode('_', explode(' ', $attrString)) . '_' . $ids[0];
                    $this->attributes[$id][] = $attrName;
                }
            }
            $attributesRes = $this->getSqlData($this->getSecondAttributeQuerySql());
            foreach ($attributesRes as $row) {
                $ids = explode(',', $row['product_id']);
                if (isset($row['second_attribute'])) {
                    $secondAttribute = unserialize($row['second_attribute']);
                    if ($secondAttribute) {
                        $row = array_shift($secondAttribute);
                        foreach($ids as $id) {
                            $attrString = strtr($this->attributeHelper->translit($row['name']), '/', '_');
                            $attrName =  implode('_', explode(' ', $attrString)) . '_2_' . $ids[0];
                            $this->attributes[$id][] = $attrName;
                        }
                    }
                }
            }
        }
        return isset($this->attributes[$productId]) ? $this->attributes[$productId] : [];
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

    /**
     * @return string
     */
    private function getAllConfigurableProductsQuerySql()
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
  -- and sp.id = 395
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

    /**
     * @return string
     */
    private function getProductsWithTwoAttibutesQuerySql()
    {
        return <<<TAG
select
	sp.id,
	spi.name,
   spv.number as sku,
   spv.price as price,
   sp.created as created_at,
   sp.updated as updated_at,
   sp.active as status,
   sp.url as url_key,
   spv.stock  as qty
from denta.shop_products sp
join shop_products_i18n spi on sp.id = spi.id
inner join denta.shop_product_variants spv on sp.id = spv.product_id
inner join  denta.shop_product_variants_i18n spvi on spv.id = spvi.id
where
	spvi.name <> ''
	and (sp.add_group is not null and sp.add_group <> 'a:0:{}')
	  -- and sp.id = 395
group by sp.id, sp.name_main_variant, sp.add_group
having count(*) > 1
order by sp.id asc
TAG;
    }

    /**
     * @return string
     */
    private function getFirstAttributeQuerySql()
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
	and (sp.add_group is not null and sp.add_group <> 'a:0:{}')
group by sp.id, sp.name_main_variant
having count(*) > 1
order by spv.number desc
) t
group by t.name_main_variant, t.attribute_value
TAG;
    }

    /**
     * @return string
     */
    private function getSecondAttributeQuerySql()
    {
        return <<<TAG
select
IFNULL(TRIM(t.name_main_variant), 'Цвет') as attribute_name,
 t.attribute_value,
 t.add_group as second_attribute,
 group_concat(t.id) as product_id,
 if(t.number <> '', group_concat(t.number), CONCAT(group_concat(t.id),'001')) as product_sku
 -- , group_concat(distinct t.category_id)
from
(
select
	spv.number,
	sp.id,
	sp.name_main_variant,
	sp.add_group,
	count(*),
	group_concat(spvi.name order by spvi.id SEPARATOR '|') as attribute_value,
	 group_concat(distinct sp.category_id) as category_id
from denta.shop_products sp
inner join denta.shop_product_variants spv on sp.id = spv.product_id
inner join  denta.shop_product_variants_i18n spvi on spv.id = spvi.id
where
	spvi.name <> ''
	and (sp.add_group is not null and sp.add_group <> 'a:0:{}')
group by sp.id, sp.name_main_variant, sp.add_group
having count(*) > 1
order by spv.number desc
) t
group by t.name_main_variant, t.attribute_value, t.add_group
TAG;
    }
}
