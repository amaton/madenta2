<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Model;


class CustomPrice
{
    const ATTRIBUTE_ORIGINAL_PRICE = 262;
    const ATTRIBUTE_VALUTA = 261;
    const ATTRIBUTE_PRICE = 77;

    /**
     * @var \Magento\Indexer\Model\Processor
     */
    private $processor;

    /**
     * @var \Magento\Framework\App\Cache\Manager
     */
    private $cacheManager;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $connection;

    /**
     * @param \Magento\Framework\App\ResourceConnection $connection
     * @param \Magento\Indexer\Model\Processor $processor
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $connection,
        \Magento\Indexer\Model\Processor $processor,
        \Magento\Framework\App\Cache\Manager $cacheManager
    )
    {
        $this->processor = $processor;
        $this->cacheManager = $cacheManager;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public  function updatePrice()
    {
        $this->connection->getConnection()->query($this->getQuerySql(
            [
                'attribute_code_price' => self::ATTRIBUTE_PRICE,
                'attribute_code_original_price' => self::ATTRIBUTE_ORIGINAL_PRICE,
                'attribute_code_valuta' => self::ATTRIBUTE_VALUTA
            ]
        ));
        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $this->processor->reindexAll();
    }

    /**
     * @return string
     */
    private function getQuerySql(array $data)
    {
        return <<<TAG
INSERT INTO `catalog_product_entity_decimal` (`attribute_id`, `store_id`, `entity_id`, `value`)
select * from (select
	{$data['attribute_code_price']} as attribute_id,
	0 as store_id,
	i.entity_id as entity_id,
	cast(v.value as DECIMAL(9,5))/
		(select rate from directory_currency_rate where currency_from = 'UAH' and currency_to = o.value)
	as value
from catalog_product_entity_varchar v
inner join catalog_product_entity_int i on v.entity_id = i.entity_id and i.attribute_id = {$data['attribute_code_valuta']}
inner join eav_attribute_option_value o on o.option_id = i.value and o.store_id = 0
where v.attribute_id = {$data['attribute_code_original_price']}
and i.attribute_id = {$data['attribute_code_valuta']})  as t
ON DUPLICATE KEY UPDATE value = t.value
TAG;
    }
}
