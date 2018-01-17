<?php
namespace Dotsquares\Shipping\Model\Config\Backend;

use Magento\Framework\Model\AbstractModel;

class Matrixrate extends \Magento\Framework\App\Config\Value
{
    protected $matrixrateFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Dotsquares\Shipping\Model\ResourceModel\Carrier\MatrixrateFactory $matrixrateFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->matrixrateFactory = $matrixrateFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function afterSave()
    {
        $matrixRate = $this->matrixrateFactory->create();
        $matrixRate->uploadAndImport($this);
        return parent::afterSave();
    }
}
