<?php

namespace Dotsquares\Shipping\Model\Carrier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\State;
use Magento\Backend\App\Area\FrontNameResolver;


class Condition extends AbstractCarrier implements CarrierInterface
{
	
    protected $_code = 'dotsquares';

    protected $_isFixed = true;

    protected $defaultConditionName = 'package_weight';

    protected $conditionNames = [];

    protected $rateResultFactory;

    protected $resultMethodFactory;

    protected $matrixrateFactory;
	
	protected $appState;
    

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $resultMethodFactory,
        \Dotsquares\Shipping\Model\ResourceModel\Carrier\MatrixrateFactory $matrixrateFactory,
		State $appState,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->resultMethodFactory = $resultMethodFactory;
        $this->matrixrateFactory = $matrixrateFactory;
		$this->appState = $appState;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        foreach ($this->getCode('condition_name') as $k => $v) {
            $this->conditionNames[] = $k;
        }
    }

    
    public function collectRates(RateRequest $request)
    {
		/* if (!$this->isActive() || !$this->isAdmin()) {
            return false;
        } */
		
		if (!$this->isActive()) {
			return false;
		}
		
		if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getParentItem()) {
                    continue;
                }
                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getProduct()->isVirtual()) {
                            $request->setPackageValue($request->getPackageValue() - $child->getBaseRowTotal());
                        }
                    }
                } elseif ($item->getProduct()->isVirtual()) {
                    $request->setPackageValue($request->getPackageValue() - $item->getBaseRowTotal());
                }
            }
        }
		if (!$request->getConditionMRName()) {
            $conditionName = $this->getConfigData('condition_type');
			$request->setConditionMRName($conditionName ? $conditionName : $this->defaultConditionName);
        }

		$result = $this->rateResultFactory->create();
		
		if(!$this->isAdmin()){
			
			$rateArray = $this->getRate($request);
			$foundRates = false;
		
			foreach ($rateArray as $rate) {
				if (!empty($rate) && $rate['price'] >= 0) {
					
					$method = $this->resultMethodFactory->create();
	
					$method->setCarrier('dotsquares');
					$method->setCarrierTitle($this->getConfigData('title'));
	
					$method->setMethod('dotsquares' . $rate['id']);
					$method->setMethodTitle(__($rate['shipping_method']));
					
					$method->setPrice($rate['price']);
					$method->setCost($rate['cost']);
	
					$result->append($method);
					$foundRates = true;
				}
			}
	
			if (!$foundRates) {
				$error = $this->_rateErrorFactory->create(
					[
						'data' => [
							'carrier' => $this->_code,
							'carrier_title' => $this->getConfigData('title'),
							'error_message' => $this->getConfigData('errormsg'),
						],
					]
				);
				$result->append($error);
				
			}
		}else{
			if($this->getConfigData('adminfreeshipping')){
				$result = $this->rateResultFactory->create();
				/** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
				$method = $this->resultMethodFactory->create();
				$method->setCarrier('dotsquares');
				$method->setCarrierTitle($this->getConfigData('title'));
				$method->setMethod('dotsquares');
				$method->setMethodTitle($this->getConfigData('adminname'));
				$method->setPrice('0.00');
				$method->setCost('0.00');
				$result->append($method);
			}	
		}

        return $result;
    }

	
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
		return $this->matrixrateFactory->create()->getRate($request);
    }

    public function getCode($type, $code = '')
    {
		$codes = [
            'condition_name' => [
                'package_weight' => __('Weight vs. Destination'),
                'package_value' => __('Order Subtotal vs. Destination'),
                'package_qty' => __('# of Items vs. Destination'),
            ],
            'condition_name_short' => [
                'package_weight' => __('Weight'),
                'package_value' => __('Order Subtotal'),
                'package_qty' => __('# of Items'),
            ],
        ];
	
		if (!isset($codes[$type])) {
            throw new LocalizedException(__('Please correct Matrix Rate code type: %1.', $type));
        }

        if ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw new LocalizedException(__('Please correct Matrix Rate code for type %1: %2.', $type, $code));
        }

        return $codes[$type][$code];
    }
	
	public function getAllowedMethods()
	{
		return [$this->getCarrierCode() => __($this->getConfigData('title'))];
	}

	protected function isAdmin()
    {
        if ($this->appState->getAreaCode() === FrontNameResolver::AREA_CODE) {
            return true;
        }
        return false;
    }
}