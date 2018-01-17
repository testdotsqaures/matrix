<?php

namespace Dotsquares\Shipping\Model\ResourceModel\Carrier\Matrixrate;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $magento_countrytable;
    protected $magento_regionTable;
    protected function _construct()
    {
        $this->_init(
            'Dotsquares\Shipping\Model\Carrier\Condition',
            'Dotsquares\Shipping\Model\ResourceModel\Carrier\Matrixrate'
        );
        $this->magentocountryTable = $this->getTable('directory_country');
        $this->magentoregionTable = $this->getTable('directory_country_region');
    }

    public function _initSelect()
    {
        parent::_initSelect();

        $this->_select->joinLeft(
            ['country_table' => $this->magentocountryTable],
            'country_table.country_id = main_table.dest_country_id',
            ['dest_country' => 'iso3_code']
        )->joinLeft(
            ['region_table' => $this->magentoregionTable],
            'region_table.region_id = main_table.dest_region_id',
            ['dest_region' => 'code']
        );

        $this->addOrder('dest_country', self::SORT_ORDER_ASC);
        $this->addOrder('dest_region', self::SORT_ORDER_ASC);
        $this->addOrder('dest_zip', self::SORT_ORDER_ASC);
        $this->addOrder('condition_name', self::SORT_ORDER_ASC);
    }

    public function setWebsiteFilter($websiteId)
    {
        return $this->addFieldToFilter('website_id', $websiteId);
    }

    public function setConditionFilter($conditionName)
    {
        return $this->addFieldToFilter('condition_name', $conditionName);
    }

    public function setCountryFilter($countryId)
    {
        return $this->addFieldToFilter('dest_country_id', $countryId);
    }
}
