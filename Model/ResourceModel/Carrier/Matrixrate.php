<?php
namespace Dotsquares\Shipping\Model\ResourceModel\Carrier;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;

class Matrixrate extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected $importWebsiteId = 0;
    protected $importErrors = [];
    protected $importedRows = 0;
    protected $importUniqueHash = [];
    protected $importIso2Countries;
    protected $importIso3Countries;
    protected $importRegions;
    protected $importConditionName;
	protected $conditionFullNames = [];
    protected $coreConfig;
	protected $logger;
    protected $storeManager;
    protected $carrierMatrixrate;
    protected $countryCollectionFactory;
    protected $regionCollectionFactory;
    private $readFactory;
    protected $filesystem;

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $coreConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Dotsquares\Shipping\Model\Carrier\Condition $carrierMatrixrate,
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Filesystem $filesystem,
        $resourcePrefix = null
    ) {
        parent::__construct($context, $resourcePrefix);
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->carrierMatrixrate = $carrierMatrixrate;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->readFactory = $readFactory;
        $this->filesystem = $filesystem;
    }

    protected function _construct()
    {
        $this->_init('dotsquares_shipping', 'id');
    }

    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
		$adapter = $this->getConnection();
        $shippingData=[];
        $postcode = $request->getDestPostcode();
		$zipSearchString = " AND :postcode LIKE dest_zip ";

        for ($j=0; $j<8; $j++) {
			$select = $adapter->select()->from(
                $this->getMainTable()
            )->where(
                'website_id = :website_id'
            )->order(
                ['dest_country_id DESC', 'dest_region_id DESC', 'dest_zip DESC', 'condition_from_value DESC']
            );
            $zoneWhere='';
            $bind=[];
            switch ($j) {
                case 0: // country, region, city, postcode
                   $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = :region_id AND STRCMP(LOWER(dest_city),LOWER(:city))= 0 " .$zipSearchString;
				   /* $zoneWhere =  "dest_country_id = '0' AND dest_region_id = '0' AND dest_city ='*' AND dest_zip ='*'"; */
			    /* $zoneWhere =  "dest_country_id = '0' AND dest_region_id = '0' AND dest_city ='*' AND dest_zip ='*'"; */
                   $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':region_id' => (int)$request->getDestRegionId(),
                        ':city' => $request->getDestCity(),
                        ':postcode' => $request->getDestPostcode(),
                    ];
                    break;
                case 1: // country, region, no city, postcode
                    $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = :region_id AND dest_city='' "
                        .$zipSearchString;
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':region_id' => (int)$request->getDestRegionId(),
                        ':postcode' => $request->getDestPostcode(),
                    ];
                    break;
                case 2: // country, state, city, no postcode
                    $zoneWhere = "dest_country_id = :country_id AND dest_region_id = :region_id AND STRCMP(LOWER(dest_city),LOWER(:city))= 0 AND dest_zip ='*'";
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':region_id' => (int)$request->getDestRegionId(),
                        ':city' => $request->getDestCity(),
                    ];
                    break;
                case 3: //country, city, no region, no postcode
                    $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = '0' AND STRCMP(LOWER(dest_city),LOWER(:city))= 0 AND dest_zip ='*'";
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':city' => $request->getDestCity(),
                    ];
                    break;
                case 4: // country, postcode
                    $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = '0' AND dest_city ='*' "
                        .$zipSearchString;
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':postcode' => $request->getDestPostcode(),
                    ];
                    break;
                case 5: // country, region
                    $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = :region_id  AND dest_city ='*' AND dest_zip ='*'";
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                        ':region_id' => (int)$request->getDestRegionId(),
                    ];
                    break;
                case 6: // country
                    $zoneWhere =  "dest_country_id = :country_id AND dest_region_id = '0' AND dest_city ='*' AND dest_zip ='*'";
                    $bind = [
                        ':country_id' => $request->getDestCountryId(),
                    ];
                    break;
                case 7: // nothing
                    $zoneWhere =  "dest_country_id = '0' AND dest_region_id = '0' AND dest_city ='*' AND dest_zip ='*'";
                    break;
            }
			$select->where($zoneWhere);
			$bind[':website_id'] = (int)$request->getWebsiteId();
            $bind[':condition_name'] = $request->getConditionMRName();
			$bind[':condition_value'] = $request->getData($request->getConditionMRName());
			$select->where('condition_name = :condition_name');
            $select->where('condition_from_value < :condition_value');
            $select->where('condition_to_value >= :condition_value');

			$this->logger->debug('SQL Select: ', $select->getPart('where'));
            $this->logger->debug('Bindings: ', $bind);
			
            $results = $adapter->fetchAll($select, $bind);
			
			if (!empty($results)) {
                $this->logger->debug('SQL Results: ', $results);
                foreach ($results as $data) {
                    $shippingData[]=$data;
                }
                break;
            }
        }
		return $shippingData;
    }

    public function uploadAndImport(\Magento\Framework\DataObject $object)
    {
        $importFieldData = $object->getFieldsetDataValue('import');
        if (empty($importFieldData['tmp_name'])) {
            return $this;
        }

        $website = $this->storeManager->getWebsite($object->getScopeId());
        $csvFile = $importFieldData['tmp_name'];

        $this->importWebsiteId = (int)$website->getId();
        $this->importUniqueHash = [];
        $this->importErrors = [];
        $this->importedRows = 0;

        $tmpDirectory = ini_get('upload_tmp_dir')? $this->readFactory->create(ini_get('upload_tmp_dir'))
            : $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);
        $path = $tmpDirectory->getRelativePath($csvFile);
        $stream = $tmpDirectory->openFile($path);

        $headers = $stream->readCsv();
        if ($headers === false || count($headers) < 5) {
            $stream->close();
            throw new \Magento\Framework\Exception\LocalizedException(__('Please correct Matrix Rates File Format.'));
        }

        if ($object->getData('groups/dotsquares/fields/condition_type/inherit') == '1') {
            $conditionName = (string)$this->coreConfig->getValue('carriers/dotsquares/condition_type', 'default');
        } else {
            $conditionName = $object->getData('groups/dotsquares/fields/condition_type/value');
        }
		
        $this->importConditionName = $conditionName;

        $adapter = $this->getConnection();
        $adapter->beginTransaction();

        try {
            $rowNumber = 1;
            $importData = [];

            $this->_loadDirectoryCountries();
            $this->_loadDirectoryRegions();

            $condition = [
                'website_id = ?' => $this->importWebsiteId,
                'condition_name = ?' => $this->importConditionName,
            ];
            $adapter->delete($this->getMainTable(), $condition);

            while (false !== ($csvLine = $stream->readCsv())) {
                $rowNumber++;

                if (empty($csvLine)) {
                    continue;
                }

                $row = $this->_getImportRow($csvLine, $rowNumber);
				
				
                if ($row !== false) {
                    $importData[] = $row;
                }

                if (count($importData) == 5000) {
                    $this->_saveImportData($importData);
                    $importData = [];
                }
            }
			$this->_saveImportData($importData);
            $stream->close();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $adapter->rollback();
            $stream->close();
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        } catch (\Exception $e) {
            $adapter->rollback();
            $stream->close();
            $this->logger->critical($e);
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something went wrong while importing matrix rates.')
            );
        }

        $adapter->commit();

        if ($this->importErrors) {
            $error = __(
                'We couldn\'t import this file because of these errors: %1',
                implode(" \n", $this->importErrors)
            );
            throw new \Magento\Framework\Exception\LocalizedException($error);
        }

        return $this;
    }

    protected function _loadDirectoryCountries()
    {
        if ($this->importIso2Countries !== null && $this->importIso3Countries !== null) {
            return $this;
        }

        $this->importIso2Countries = [];
        $this->importIso3Countries = [];

        $collection = $this->countryCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->importIso2Countries[$row['iso2_code']] = $row['country_id'];
            $this->importIso3Countries[$row['iso3_code']] = $row['country_id'];
        }

        return $this;
    }

    protected function _loadDirectoryRegions()
    {
        if ($this->importRegions !== null) {
            return $this;
        }

        $this->importRegions = [];

        $collection = $this->regionCollectionFactory->create();
        foreach ($collection->getData() as $row) {
            $this->importRegions[$row['country_id']][$row['code']] = (int)$row['region_id'];
        }

        return $this;
    }

    protected function getConditionFullName($conditionName)
    {
        if (!isset($this->conditionFullNames[$conditionName])) {
            $name = $this->carrierMatrixrate->getCode('condition_name', $conditionName);
            $this->conditionFullNames[$conditionName] = $name;
        }

        return $this->conditionFullNames[$conditionName];
    }

    protected function _getImportRow($row, $rowNumber = 0)
    {
		
		if (count($row) < 9) {
            $this->importErrors[] =
                __('Please correct Matrix Rates format in Row #%1. Invalid Number of Rows', $rowNumber);
            return false;
        }

		
        foreach ($row as $k => $v) {
            $row[$k] = trim($v);
        }
		
        if (isset($this->importIso2Countries[$row[0]])) {
            $countryId = $this->importIso2Countries[$row[0]];
        } elseif (isset($this->importIso3Countries[$row[0]])) {
            $countryId = $this->importIso3Countries[$row[0]];
        } elseif ($row[0] == '*' || $row[0] == '') {
            $countryId = '0';
        } else {
            $this->importErrors[] = __('Please correct Country "%1" in Row #%2.', $row[0], $rowNumber);
            return false;
        }
		

        if ($countryId != '0' && isset($this->importRegions[$countryId][$row[1]])) {
            $regionId = $this->importRegions[$countryId][$row[1]];
        } elseif ($row[1] == '*' || $row[1] == '') {
            $regionId = 0;
        } else {
            $this->importErrors[] = __('Please correct Region/State "%1" in Row #%2.', $row[1], $rowNumber);
            return false;
        }

        // detect city
        if ($row[2] == '*' || $row[2] == '') {
            $city = '*';
        } else {
            $city = $row[2];
        }

        // detect zip code
        if ($row[3] == '*' || $row[3] == '') {
            $zipCode = '*';
        } else {
            $zipCode = $row[3];
        }

        //zip to
		
		if ($row[4] == '*' || $row[4] == '') {
            $zip_to = '*';
        } else {
            $zip_to = $row[4];
        }
		
		// validate condition from value
        $valueFrom = $row[5] == '*' ? -1 : $this->_parseDecimalValue($row[5]);
        if ($valueFrom === false) {
            $this->importErrors[] = __(
                'Please correct %1 From "%2" in Row #%3.',
                $this->getConditionFullName($this->importConditionName),
                $row[5],
                $rowNumber
            );
            return false;
        }
		
		$valueTo = $row[6] == '*' ? $row[6] :$this->_parseDecimalValue($row[6]);
		
		if ($valueTo === false) {
            $this->importErrors[] = __(
                'Please correct %1 To "%2" in Row #%3.',
                $this->getConditionFullName($this->importConditionName),
                $row[6],
                $rowNumber
            );
            return false;
        }

        // validate price
        $price = $this->_parseDecimalValue($row[7]);
        if ($price === false) {
            $this->importErrors[] = __('Please correct Shipping Price "%1" in Row #%2.', $row[7], $rowNumber);
            return false;
        }

        // validate shipping method
        if ($row[8] == '*' || $row[8] == '') {
            $this->importErrors[] = __('Please correct Shipping Method "%1" in Row #%2.', $row[8], $rowNumber);
            return false;
        } else {
            $shippingMethod = $row[8];
        }

        // protect from duplicate
        $hash = sprintf(
            "%s-%d-%s-%s-%F-%F-%s",
            $countryId,
            $city,
            $regionId,
            $zipCode,
            $valueFrom,
            $valueTo,
            $shippingMethod
        );
		
		if (isset($this->importUniqueHash[$hash])) {
            $this->importErrors[] = __(
                'Duplicate Row #%1 (Country "%2", Region/State "%3", City "%4", Zip from "%5", Zip to "%6", From Value "%7", To Value "%8", and Shipping Method "%9")',
                $rowNumber,
                $row[0],
                $row[1],
                $city,
                $zipCode,
                $zip_to,
                $valueFrom,
                $valueTo,
                $shippingMethod
            );
            return false;
        }
        $this->importUniqueHash[$hash] = true;
		
		return [
            $this->importWebsiteId,    
            $countryId,
            $regionId,
            $city,
            $zipCode,
            $zip_to,
            $this->importConditionName,
            $valueFrom,
            $valueTo,
            $price,
            $shippingMethod
        ];
    }

    protected function _saveImportData(array $data)
    {
		if (!empty($data)) {
            $columns = [
                'website_id',
                'dest_country_id',
                'dest_region_id',
                'dest_city',
                'dest_zip',
                'dest_zip_to',
                'condition_name',
                'condition_from_value',
                'condition_to_value',
                'price',
                'shipping_method',
            ];
            $this->getConnection()->insertArray($this->getMainTable(), $columns, $data);
            $this->importedRows += count($data);
        }

        return $this;
    }

    protected function _parseDecimalValue($value)
    {
        if (!is_numeric($value)) {
            return false;
        }
        $value = (double)sprintf('%.4F', $value);
        if ($value < 0.0000) {
            return false;
        }
        return $value;
    }
}
