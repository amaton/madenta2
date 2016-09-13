<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class CustomersMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    /**
     * @param \Magento\Framework\App\State $state
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\App\State $state
    )
    {
        try {
            $state->getAreaCode();
        } catch(\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('frontend');
        }
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:customers');
        $this->setDescription('Migrate customers');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $res = $this->getSqlData($this->getQuerySql());

        $output->writeln("<info>Customers Migration has been finished</info>");
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
        $statement = $dbh->exec($sql);
    }

    /**
     * @return string
     */
    private function getQuerySql()
    {
        return <<<TAG
DELETE  FROM  `magento`.`customer_group`
where `customer_group_id` in (4, 5, 6);


INSERT INTO `magento`.`customer_group`
(
	`customer_group_id`,
	`customer_group_code`
)
SELECT
	r.id as `customer_group_id`,
	r.name as `customer_group_code`
FROM denta.shop_rbac_roles r
where r.id in (4, 5, 6);



DELETE FROM `magento`.`customer_grid_flat`
WHERE entity_id in
(
	select
		u.id
	from denta.users u
	where u.role_id in (4, 5, 6) or u.role_id is null
);
DELETE FROM `magento`.`customer_address_entity`
WHERE entity_id in
(
	select
		u.id
	from denta.users u
	where u.role_id in (4, 5, 6) or u.role_id is null
);
DELETE FROM `magento`.`customer_entity`
WHERE entity_id in
(
	select
		u.id
	from denta.users u
	where u.role_id in (4, 5, 6) or u.role_id is null
);

INSERT INTO `magento`.`customer_entity`
(
	`entity_id`,
	`website_id`,
	`email`,
	`group_id`,
	`store_id`,
	`created_at`,
	`updated_at`,
	`is_active`,
	`disable_auto_group_change`,
	`firstname`,
	`password_hash`,
	`default_billing`,
	`default_shipping`
)
select
	u.id as `entity_id`,
    1 as `website_id`,
    u.email as `email`,
    IFNULL(u.role_id, 1) as `group_id`,
    1 as `store_id`,
    FROM_UNIXTIME(u.created) as `created_at`,
    FROM_UNIXTIME(u.created) as `updated_at`,
    1 as `is_active`,
	1 as `disable_auto_group_change`,
	u.username as `firstname`,
	password as `password_hash`,
	u.id as `default_billing`,
	u.id as `default_shipping`
from denta.users u
where u.role_id in (4, 5, 6) or u.role_id is null;

INSERT INTO `magento`.`customer_address_entity`
(
	`entity_id`,
	`parent_id`,
	`created_at`,
	`updated_at`,
	`is_active`,
	`city`,
	`country_id`,
	`firstname`,
	`lastname`,
	`street`,
	`telephone`
)
select
	u.id as `entity_id`,
	u.id as `parent_id`,
    FROM_UNIXTIME(u.created) as `created_at`,
    FROM_UNIXTIME(u.created) as `updated_at`,
	1 as `is_active`,
	u.address as `city`,
	'UA' as `country_id`,
	u.username as `firstname`,
    ' ' as `lastname`,
	' ' as `street`,
    u.phone as `telephone`
from denta.users u
where u.role_id in (4, 5, 6) or u.role_id is null;

INSERT INTO `magento`.`customer_grid_flat`
(
	`entity_id`,
	`name`,
	`email`,
	`group_id`,
	`created_at`,
	`website_id`,
	`shipping_full`,
	`billing_full`,
	`billing_firstname`,
	`billing_telephone`,
	`billing_country_id`,
	`billing_street`
)
select
	u.id as`entity_id`,
	u.username as `name`,
	u.email as `email`,
    IFNULL(u.role_id, 1) as `group_id`,
	FROM_UNIXTIME(u.created) as `created_at`,
	1 as `website_id`,
	u.address as `shipping_full`,
	u.address as `billing_full`,
	u.username as `billing_firstname`,
	u.phone as `billing_telephone`,
	'UA' as `billing_country_id`,
	u.address as `billing_street`
from denta.users u
where u.role_id in (4, 5, 6) or u.role_id is null;
TAG;
    }
}
