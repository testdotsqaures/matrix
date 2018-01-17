<?php

namespace Dotsquares\Shipping\Block\Adminhtml\System\Config;

class Import extends \Magento\Framework\Data\Form\Element\AbstractElement
{
    protected function _construct()
    {
        parent::_construct();
        $this->setType('file');
    }

    public function getElementHtml()
    {
        $html = '';

        $html .= '<input id="dot_time_condition" type="hidden" name="' . $this->getName() . '" value="' . time() . '" />';

        $html .= <<<EndHTML
        <script>
        require(['prototype'], function(){
        Event.observe($('carriers_dotsquares_condition_type'), 'change', checkConditionName.bind(this));
        function checkConditionName(event)
        {
            var conditionNameElement = Event.element(event);
            if (conditionNameElement && conditionNameElement.id) {
                $('dot_time_condition').value = '_' + conditionNameElement.value + '/' + Math.random();
            }
        }
        });
        </script>
EndHTML;

        $html .= parent::getElementHtml();

        return $html;
    }
}
