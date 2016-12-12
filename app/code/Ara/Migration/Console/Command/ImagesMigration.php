<?php
/**
 * @package Ara_Migration
 * @version draft
 */
namespace Ara\Migration\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImagesMigration extends \Symfony\Component\Console\Command\Command
{
    const DB_HOST = '127.0.0.1';
    const DB_NAME = 'denta';
    const DB_USER = 'root';
    const DB_PASSWORD = '';

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        try {
            $state->getAreaCode();
        } catch(\Magento\Framework\Exception\LocalizedException $e) {
            $state->setAreaCode('adminhtml');
        }
        parent::__construct();
        $this->productRepository = $productRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migration:images');
        $this->setDescription('Migrate images');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $imageSourceUrl = 'http://denta.com.ua/uploads/shop/products/additional/';

        $res = $this->getSqlData($this->getQuerySql());

        foreach ($res as $item) {
            try {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->productRepository->getById($item['id']);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                continue;
            }
            $images = explode(',', $item['images']);
            foreach ($images as $image) {
                $imagePath = BP . '/pub/media/tmp/catalog/product/' . $image;
                if (!empty($image) && !file_exists($imagePath)) {
                    $this->downloadRemoteFileWithCurl(
                        $imageSourceUrl . $image,
                        $imagePath
                    );
                }

                if (!empty($image) && exif_imagetype($imagePath)) {
                    $product->addImageToMediaGallery(
                        'tmp/catalog/product/' . $image,
                        [],
                        false,
                        false
                    );
                    $output->writeln("<info>save image {$image} for product {$item['id']}</info>");
                }
            }
	    try {
                $product->save();
            } catch(\Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL;
            }
        }
        $output->writeln("<info>Images Migration has been finished</info>");
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
select spi.product_id as id,GROUP_CONCAT(spi.image_name) as images
from shop_product_images spi
group by spi.product_id;
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
}
