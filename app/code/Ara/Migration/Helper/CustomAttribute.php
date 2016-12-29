<?php
namespace Ara\Migration\Helper;

class CustomAttribute extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var array
     */
    protected $attributeValues;

    /**
     * @var \Magento\Catalog\Setup\CategorySetupFactory
     */
    protected $categorySetupFactory;

    /**
     * @var \Magento\Catalog\Setup\CategorySetup
     */
    protected $categorySetup;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\Source\TableFactory
     */
    protected $tableFactory;
    /**
     * @var \Magento\Eav\Model\Entity\TypeFactory
     */
    private $eavTypeFactory;
    /**
     * @var \Magento\Eav\Model\Entity\AttributeFactory
     */
    private $attributeFactory;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\GroupFactory
     */
    private $attributeGroupFactory;
    /**
     * @var \Magento\Eav\Api\AttributeManagementInterface
     */
    private $attributeManagement;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     * @param \Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory
     * @param \Magento\Eav\Model\Entity\Attribute\Source\TableFactory $tableFactory
     * @param \Magento\Eav\Model\Entity\TypeFactory $eavTypeFactory
     * @param \Magento\Eav\Model\Entity\AttributeFactory $attributeFactory
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
     * @param \Magento\Eav\Model\Entity\Attribute\GroupFactory $attributeGroupFactory
     * @param \Magento\Eav\Api\AttributeManagementInterface $attributeManagement
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory,
        \Magento\Eav\Model\Entity\Attribute\Source\TableFactory $tableFactory,
        \Magento\Eav\Model\Entity\TypeFactory $eavTypeFactory,
        \Magento\Eav\Model\Entity\AttributeFactory $attributeFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Eav\Model\Entity\Attribute\GroupFactory $attributeGroupFactory,
        \Magento\Eav\Api\AttributeManagementInterface $attributeManagement
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->categorySetupFactory = $categorySetupFactory;
        $this->tableFactory = $tableFactory;

        parent::__construct($context);
        $this->eavTypeFactory = $eavTypeFactory;
        $this->attributeFactory = $attributeFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->attributeGroupFactory = $attributeGroupFactory;
        $this->attributeManagement = $attributeManagement;
    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface
     */
    public function getAttribute($attributeCode)
    {
        return $this->attributeRepository->get($attributeCode);
    }

    /**
     * Delete attribute by code.
     *
     * @param string $attributeCode
     * @return bool
     */
    public function deleteAttribute($attributeCode)
    {
        return $this->attributeRepository->delete($attributeCode);
    }

    /**
     * Save attribute by code.
     *
     * @param string $attributeCode
     * @return bool
     */
    public function saveAttribute($attributeCode)
    {
        return $this->attributeRepository->save($attributeCode);
    }

    /**
     * @param string|integer $entityTypeId
     * @param string $code
     * @param array $attr
     * @return \Magento\Eav\Setup\EavSetup
     */
    public function addAttribute($entityTypeId, $code, array $att)
    {
        return $this->getCategorySetup()->addAttribute($entityTypeId, $code, $att);
    }

    /**
     * Find or create a matching attribute option
     *
     * @param string $attributeCode Attribute the option should exist in
     * @param string $label Label to find or add
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createOrGetId($attributeCode, $label)
    {
        if (strlen($label) < 1) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Label for %1 must not be empty.', $attributeCode)
            );
        }

        // Does it already exist?
        $optionId = $this->getOptionId($attributeCode, $label);

        if (!$optionId) {
            // If no, add it.
            $this->getCategorySetup()->addAttributeOption(
                [
                    'attribute_id'  => $this->getAttribute($attributeCode)->getAttributeId(),
                    'order'         => [0],
                    'value'         => [
                        [
                            0 => $label,
                            1 => $label// store_id => label
                        ],
                    ],
                ]
            );

            // Get the inserted ID. Should be returned from the installer, but it isn't.
            $optionId = $this->getOptionId($attributeCode, $label, true);
        }

        return $optionId;
    }

    /**
     * Find the ID of an option matching $label, if any.
     *
     * @param string $attributeCode Attribute code
     * @param string $label Label to find
     * @param bool $force If true, will fetch the options even if they're already cached.
     * @return int|false
     */
    public function getOptionId($attributeCode, $label, $force = false)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        // Build option array if necessary
        if ($force === true || !isset($this->attributeValues[ $attribute->getAttributeId() ])) {
            $this->attributeValues[ $attribute->getAttributeId() ] = [];

            // We have to generate a new sourceModel instance each time through to prevent it from
            // referencing its _options cache. No other way to get it to pick up newly-added values.

            /** @var \Magento\Eav\Model\Entity\Attribute\Source\Table $sourceModel */
            $sourceModel = $this->tableFactory->create();
            $sourceModel->setAttribute($attribute);

            foreach ($sourceModel->getAllOptions() as $option) {
                $this->attributeValues[ $attribute->getAttributeId() ][ $option['label'] ] = $option['value'];
            }
        }

        // Return option ID if exists
        if (isset($this->attributeValues[ $attribute->getAttributeId() ][ $label ])) {
            return $this->attributeValues[ $attribute->getAttributeId() ][ $label ];
        }

        // Return false if does not exist
        return false;
    }

    /**
     * Get category setup object; initialize if necessary.
     *
     * @return \Magento\Catalog\Setup\CategorySetup
     */
    protected function getCategorySetup()
    {
        if (is_null($this->categorySetup)) {
            $this->categorySetup = $this->categorySetupFactory->create();
        }

        return $this->categorySetup;
    }

    /**
     * @param string $attributeCode
     * @param string $attributeGroupCode
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addAttributeToAllAttributeSets($attributeCode, $attributeGroupCode)
    {
        /** @var \Magento\Eav\Model\Entity\Type $entityType */
        $entityType = $this->eavTypeFactory->create()->loadByCode('catalog_product');
        /** @var \Magento\Eav\Model\Entity\Attribute $attribute */
        $attribute = $this->attributeFactory->create()->loadByCode($entityType->getId(), $attributeCode);

        if (!$attribute->getId()) {
            return false;
        }

        /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $setCollection */
        $setCollection = $this->attributeSetFactory->create()->getCollection();
        $setCollection->addFieldToFilter('entity_type_id', $entityType->getId());

        /** @var \Magento\Eav\Model\Entity\Attribute\Set $attributeSet */
        foreach ($setCollection as $attributeSet) {
            /** @var \Magento\Eav\Model\Entity\Attribute\Group $group */
            $group = $this->attributeGroupFactory->create()->getCollection()
                ->addFieldToFilter('attribute_group_code', ['eq' => $attributeGroupCode])
                ->addFieldToFilter('attribute_set_id', ['eq' => $attributeSet->getId()])
                ->getFirstItem();

            $groupId = $group->getId() ?: $attributeSet->getDefaultGroupId();

            // Assign:
            $this->attributeManagement->assign(
                'catalog_product',
                $attributeSet->getId(),
                $groupId,
                $attributeCode,
                $attributeSet->getCollection()->count() * 10
            );
        }

        return true;
    }

    /**
     * @param string $attributeCode
     * @param string $attributeGroupCode
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addAttributeToAttributeSet($attributeCode, $attributeSetId, $attributeGroupCode)
    {
        /** @var \Magento\Eav\Model\Entity\Type $entityType */
        $entityType = $this->eavTypeFactory->create()->loadByCode('catalog_product');
        /** @var \Magento\Eav\Model\Entity\Attribute $attribute */
        $attribute = $this->attributeFactory->create()->loadByCode($entityType->getId(), $attributeCode);

        if (!$attribute->getId()) {
            return false;
        }

        $attributeSet = $this->attributeSetFactory->create()->load($attributeSetId);

        /** @var \Magento\Eav\Model\Entity\Attribute\Group $group */
        $group = $this->attributeGroupFactory->create()->getCollection()
            ->addFieldToFilter('attribute_group_code', ['eq' => $attributeGroupCode])
            ->addFieldToFilter('attribute_set_id', ['eq' => $attributeSet->getId()])
            ->getFirstItem();

        $groupId = $group->getId() ?: $attributeSet->getDefaultGroupId();

        // Assign:
        $this->attributeManagement->assign(
            'catalog_product',
            $attributeSet->getId(),
            $groupId,
            $attributeCode,
            $attributeSet->getCollection()->count() * 10
        );
        return true;
    }

    // Транслитерация строк.
    public  function translit($string)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        );
        return strtr($string, $converter);
    }

}