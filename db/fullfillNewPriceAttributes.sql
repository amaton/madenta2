INSERT INTO magento.`catalog_product_entity_varchar` (`attribute_id`, `store_id`, `entity_id`, `value`) 
select 262, 0, p.entity_id, spv.price_in_main
from denta.shop_product_variants spv
inner join  magento.catalog_product_entity p on SUBSTRING(p.sku, 1, IF(LOCATE('-',  p.sku) = '', LENGTH(p.sku), LOCATE('-',  p.sku)-1)) = spv.number
group by p.sku, spv.product_id, spv.number;

-- 995 EUR 6 (1001 - 6 = 995)
-- 996 USD 5
-- 997 UAH 4
INSERT INTO magento.`catalog_product_entity_int` (`attribute_id`, `store_id`, `entity_id`, `value`) 
select 261, 0, p.entity_id, 1001-spv.currency
from denta.shop_product_variants spv
inner join  magento.catalog_product_entity p on SUBSTRING(p.sku, 1, IF(LOCATE('-',  p.sku) = '', LENGTH(p.sku), LOCATE('-',  p.sku)-1)) = spv.number
group by p.sku, spv.product_id, spv.number;
