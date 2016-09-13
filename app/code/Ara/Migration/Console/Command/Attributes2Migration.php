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

class Attributes2Migration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    protected $attributes;

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
     * @param \Magento\Framework\App\State $state
     * @param \Ara\Migration\Helper\CustomAttribute $attributeHelper
     * @param \Magento\Indexer\Model\Processor $processor
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Ara\Migration\Helper\CustomAttribute $attributeHelper,
        \Magento\Indexer\Model\Processor $processor,
        \Magento\Framework\App\Cache\Manager $cacheManager
    )
    {
        try {
            $state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('adminhtml');
        }
        $this->attributeHelper = $attributeHelper;
        parent::__construct();
        $this->processor = $processor;
        $this->cacheManager = $cacheManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:attributes2');
        $this->setDescription('Migrate attributes');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->applyAttributes();

        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $this->processor->reindexAll();
        $output->writeln("<info>Attributes Migration has been finished</info>");
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
            $statement = $dbh->query($sql);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            echo 'Cannot connect: ' . $e->getMessage();
        }
    }

    /**
     * @return string
     */
    private function getOneAttributeQuerySql()
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

    /**
     * @param $attrName
     * @param $productId
     * @return string
     */
    protected function getSecondAttribute($item)
    {
        $attribute = [];
        $productId = explode(',', $item['product_id'])[0];
        if (isset($item['second_attribute'])) {
            $secondAttribute = unserialize($item['second_attribute']);
            if ($secondAttribute) {
                $row = array_shift($secondAttribute);
                $attrString = strtr($this->attributeHelper->translit($row['name']), '/', '_');
                $attrName =  implode('_', explode(' ', $attrString)) . '_' . $productId;
                $attribute['code'] = $attrName;
                $attribute['name'] = $row['name'];
                $attribute['options'] = explode(PHP_EOL, $row['value']);
            }
        }
        return $attribute;
    }

    /**
     * @return void
     */
    protected function applyAttributes()
    {
        $attrRes = $this->getSqlData($this->getOneAttributeQuerySql());
        foreach ($attrRes as $item) {

            $attribute = $this->getSecondAttribute($item);
            if (empty($attribute)) {
                continue;
            }
            try {
                $attr = $this->attributeHelper->getAttribute($attribute['code']);
                $this->attributeHelper->deleteAttribute($attr);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {

            }
            $this->attributeHelper->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                $attribute['code'],
                [
                    'type' => 'varchar',
                    'backend' => '',
                    'frontend' => '',
                    'label' => $attribute['name'],
                    'input' => 'select',
                    'class' => '',
                    'source' => '',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'unique' => false,
                    'group' => 'Product Details',
                    'option' => ['values' => $attribute['options']],
                    'apply_to' => 'simple, virtual'
                ]
            );
        }
    }
}
