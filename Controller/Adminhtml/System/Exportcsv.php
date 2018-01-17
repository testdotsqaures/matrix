<?php
namespace Dotsquares\Shipping\Controller\Adminhtml\System;

use Magento\Framework\App\ResponseInterface;
use Magento\Config\Controller\Adminhtml\System\ConfigSectionChecker;
use Magento\Framework\App\Filesystem\DirectoryList;

class Exportcsv extends \Magento\Config\Controller\Adminhtml\System\AbstractConfig
{
    protected $fileFactory;
	
	protected $storeManager;
	
	public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Config\Model\Config\Structure $configStructure,
        ConfigSectionChecker $sectionChecker,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->fileFactory = $fileFactory;
        parent::__construct($context, $configStructure, $sectionChecker);
    }

    public function execute()
    {
        $fileName = 'matrixrates.csv';
        $gridBlock = $this->_view->getLayout()->createBlock(
            'Dotsquares\Shipping\Block\Adminhtml\Carrier\Matrixrate\Grid'
        );
        $website = $this->storeManager->getWebsite($this->getRequest()->getParam('website'));
        if ($this->getRequest()->getParam('conditionName')) {
			$conditionName = $this->getRequest()->getParam('conditionName');
        } else {
            $conditionName = $website->getConfig('carriers/dotsquares/condition_type');
        }
        $gridBlock->setWebsiteId($website->getId())->setConditionName($conditionName);
        $content = $gridBlock->getCsvFile();
        return $this->fileFactory->create($fileName, $content, DirectoryList::VAR_DIR);
    }
}
