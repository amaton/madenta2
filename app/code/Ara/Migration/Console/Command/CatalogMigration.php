<?php
/**
 * @Author Arkadii Chyzhov
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'migration_db';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category
     */
    private $categoryResource;

    /**
     * @param \Magento\Catalog\Model\CategoryFactory $productFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category $productResource
     */
    public function __construct(
        \Magento\Catalog\Model\CategoryFactory $productFactory,
        \Magento\Catalog\Model\ResourceModel\Category $productResource
    )
    {
        $this->categoryFactory = $productFactory;
        $this->categoryResource = $productResource;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:catalog');
        $this->setDescription('Migrate');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $customAttr = ['description', 'meta_desc', 'meta_title', 'meta_keywords', 'url_key'];
        $res = $this->getMigrationData();
        foreach ($res as $item) {
            $parentId = $item['parent_id'] == 0 ? 2 : $item['parent_id'];
            $category = $this->categoryFactory->create();
            $category->isObjectNew(true);
            $parentCategory = $this->categoryFactory->create();
            $parentCategory->load($parentId);

            foreach ($item as $attr => $value) {
                if (in_array($attr, $customAttr)) {
                    $category->setCustomAttribute($attr, $value);
                } else {
                    $category->setData($attr, $value);
                }
            }
            $category->setData('attribute_set_id', 3);
            $category->setData('parent_id', $parentId);
            $category->setData('path', $parentCategory->getPath() . '/' . $item['entity_id']);
            $this->categoryResource->save($category);
            $output->write(".");
        }
        $output->writeln("<info>Catalog Migration has been finished</info>");
    }

    /**
     * @return array
     */
    private function getMigrationData()
    {
        $dsn = 'mysql:dbname=' . self::DB_NAME . ';host=' .self::DB_HOST . ';charset=UTF8';
        $user = self::DB_USER;
        $password = self::DB_PASSWORD;

        try {
            $dbh = new \PDO($dsn, $user, $password);
        } catch (\PDOException $e) {
            echo 'Cannot connect: ' . $e->getMessage();
        }
        $statement = $dbh->query($this->getQuerySql());
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return string
     */
    private function getQuerySql()
    {
        return <<<TAG
select
  c.id as entity_id,
  c.parent_id as parent_id,
  c.position as position,
  c.url as url_key,
  c.active as is_active,
  ci.name as name,
  ci.description as description,
  ci.meta_desc as meta_description,
  ci.meta_title as meta_title,
  ci.meta_keywords as meta_keywords
from denta.shop_category c
inner join denta.shop_category_i18n ci on c.id = ci.id and ci.locale = 'ru'
order by c.id
TAG;
    }
}
