<?php

namespace Dotsquares\Shipping\Model\Config\Source;

class Conditiontype implements \Magento\Framework\Option\ArrayInterface
{
    
    protected $carrierMatrixrate;

    public function __construct(\Dotsquares\Shipping\Model\Carrier\Condition $carrierMatrixrate)
    {
        $this->carrierMatrixrate = $carrierMatrixrate;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $arr = [];
        foreach ($this->carrierMatrixrate->getCode('condition_name') as $k => $v) {
            $arr[] = ['value' => $k, 'label' => $v];
        }
        return $arr;
    }
}
