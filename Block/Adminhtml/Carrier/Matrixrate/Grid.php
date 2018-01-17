<?php

namespace Dotsquares\Shipping\Block\Adminhtml\Carrier\Matrixrate;

class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    protected $websiteId; 

	protected $conditionName;

	protected $matrixrate;

	protected $collectionFactory;

    
	public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Dotsquares\Shipping\Model\ResourceModel\Carrier\Matrixrate\CollectionFactory $collectionFactory,
        \Dotsquares\Shipping\Model\Carrier\Condition $matrixrate,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->matrixrate = $matrixrate;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Define grid properties
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('shippingMatrixrateGrid');
        $this->_exportPageSize = 10000;
    }

    /**
     * Set current website
     *
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $this->_storeManager->getWebsite($websiteId)->getId();
        return $this;
    }

    /**
     * Retrieve current website id
     *
     * @return int
     */
    public function getWebsiteId()
    {
        if ($this->websiteId === null) {
            $this->websiteId = $this->_storeManager->getWebsite()->getId();
        }
        return $this->websiteId;
    }

    /**
     * Set current website
     *
     * @param string $name
     * @return $this
     */
    public function setConditionName($name)
    {
        $this->conditionName = $name;
        return $this;
    }

    /**
     * Retrieve current website id
     *
     * @return int
     */
    public function getConditionName()
    {
        return $this->conditionName;
    }

    /**
     * Prepare shipping table rate collection
     *
     * @return \WebShopApps\MatrixRate\Block\Adminhtml\Carrier\Matrixrate\Grid
     */
    protected function _prepareCollection()
    {
        /** @var $collection \WebShopApps\MatrixRate\Model\ResourceModel\Carrier\Matrixrate\Collection */
        $collection = $this->collectionFactory->create();
        $collection->setConditionFilter($this->getConditionName())->setWebsiteFilter($this->getWebsiteId());

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare table columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     */
    protected function _prepareColumns()
    {
		$this->addColumn(
            'dest_country',
            ['header' => __('Country'), 'index' => 'dest_country', 'default' => '*']
        );

        $this->addColumn(
            'dest_region',
            ['header' => __('Region/State'), 'index' => 'dest_region', 'default' => '*']
        );
        $this->addColumn(
            'dest_city',
            ['header' => __('City'), 'index' => 'dest_city', 'default' => '*']
        );
        $this->addColumn(
            'dest_zip',
            ['header' => __('Zip/Postal Code From'), 'index' => 'dest_zip', 'default' => '*']
        );
        $this->addColumn(
            'dest_zip_to',
            ['header' => __('Zip/Postal Code To'), 'index' => 'dest_zip_to', 'default' => '*']
        );

        $label = $this->matrixrate->getCode('condition_name_short', $this->getConditionName());

        $this->addColumn(
            'condition_from_value',
            ['header' => $label.__('>'), 'index' => 'condition_from_value']
        );

        $this->addColumn(
            'condition_to_value',
            ['header' => $label.__('<='), 'index' => 'condition_to_value']
        );

        $this->addColumn('price', ['header' => __('Shipping Price'), 'index' => 'price']);

        $this->addColumn(
            'shipping_method',
            ['header' => __('Shipping Method'), 'index' => 'shipping_method']
        );
		return parent::_prepareColumns();
    }
}
