<?php

class StoreModuleOrder {
	
	const STATE_PENDING = 'pending';
	const STATE_COMPLETE = 'complete';
	const STATE_FAILED = 'failed';
	const STATE_REFUNDED = 'refunded';
	const STATE_CANCELLED = 'cancelled';

	const FILTER_ID = 'id';
	const FILTER_TRANSACTION_ID = 'transactionId';
	const FILTER_EXT_TRANSACTION_ID = 'extTransactionId';
	const FILTER_STATE = 'state';
	const FILTER_DATE_TIME_LTE = 'dateTimeLte';
	const FILTER_DATE_TIME_GTE = 'dateTimeGte';
	const FILTER_HASH = 'hash';

	private $id;
	private $transactionId;
	private $extTransactionId;
	private $gatewayId;
	private $lang;
	private $buyer;
	/** @var StoreModuleOrderItem[]|string[] */
	private $items;
	private $price;
	private $type;
	private $state;
	private $dateTime;
	private $completeDateTime;
	private $cancelDateTime;
	/** @var StoreBillingInfo|null */
	private $billingInfo;
	/** @var StoreBillingInfo|null */
	private $deliveryInfo;
	/** @var string */
	private $orderComment = '';
	/** @var float */
	private $taxAmount = 0;
	/** @var float */
	private $shippingAmount = 0;
	/** @var string */
	private $shippingDescription = null;
	/** @var StoreCurrency|null */
	private $currency = null;
	/** @var StorePriceOptions|null */
	private $priceOptions = null;
	/** @var array */
	private $customFields = array();
	/** @var string|null */
	private $invoiceDocumentNumber = null;

	private static $logLockFile = null;

	public static function create($transactionId = null) {
		return new self($transactionId);
	}
	
	public function __construct($transactionId = null) {
		$this->transactionId = $transactionId;
		$this->dateTime = date('Y-m-d H:i:s');
	}
	
	private function populate(array $f) {
		$this->id = isset($f['id']) ? $f['id'] : null;
		$this->transactionId = isset($f['transactionId']) ? $f['transactionId'] : (isset($f['tnx_id']) ? $f['tnx_id'] : null);
		$this->extTransactionId = isset($f['extTransactionId']) ? $f['extTransactionId'] : null;
		$this->gatewayId = isset($f['gatewayId']) ? $f['gatewayId'] : (isset($f['gateway_id']) ? $f['gateway_id'] : null);
		$this->lang = isset($f['lang']) ? $f['lang'] : null;
		$this->buyer = isset($f['buyer']) ? new StoreModuleBuyer($f['buyer']) : null;
		$this->price = isset($f['price']) ? $f['price'] : null;
		$this->type = isset($f['type']) ? $f['type'] : null;
		$this->state = isset($f['state']) ? $f['state'] : null;
		$this->dateTime = isset($f['dateTime']) ? $f['dateTime'] : (isset($f['time']) ? $f['time'] : null);
		$this->completeDateTime = isset($f['completeDateTime']) ? $f['completeDateTime'] : null;
		$this->cancelDateTime = isset($f['cancelDateTime']) ? $f['cancelDateTime'] : null;
		$this->billingInfo = isset($f['billingInfo']) ? StoreBillingInfo::fromJson($f['billingInfo']) : null;
		$this->deliveryInfo = isset($f['deliveryInfo']) ? StoreBillingInfo::fromJson($f['deliveryInfo']) : null;
		$this->orderComment = (isset($f['orderComment']) && is_string($f['orderComment'])) ? $f['orderComment'] : '';
		$this->taxAmount = (isset($f['taxAmount']) && is_numeric($f['taxAmount'])) ? floatval($f['taxAmount']) : 0;
		$this->shippingAmount = (isset($f['shippingAmount']) && is_numeric($f['shippingAmount'])) ? floatval($f['shippingAmount']) : 0;
		$this->shippingDescription = (isset($f['shippingDescription']) && $f['shippingDescription']) ? $f['shippingDescription'] : null;
		$this->currency = isset($f['currency']) ? StoreCurrency::fromJson($f['currency']) : null;
		$this->priceOptions = isset($f['priceOptions']) ? StorePriceOptions::fromJson($f['priceOptions']) : null;
		$this->customFields = (isset($f['customFields']) && is_array($f['customFields'])) ? $f['customFields'] : array();
		$this->invoiceDocumentNumber = isset($f['invoiceDocumentNumber']) ? $f['invoiceDocumentNumber'] : null;

		$this->items = array();
		$items = isset($f['items']) ? $f['items'] : (isset($f['order']) ? $f['order'] : null);
		foreach( $items as $item )
			$this->items[] = (is_object($item) || is_array($item)) ? StoreModuleOrderItem::fromJson($this, $item) : $item;
	}
	
	function getId() {
		return $this->id;
	}
	
	function getTransactionId() {
		return $this->transactionId;
	}

	function getExtTransactionId() {
		return $this->extTransactionId;
	}

	function getGatewayId() {
		return $this->gatewayId;
	}

	function getLang() {
		return $this->lang;
	}

	/** @return StoreModuleBuyer */
	function getBuyer() {
		return $this->buyer;
	}

	function getItems() {
		return $this->items;
	}

	function getPrice() {
		return $this->price;
	}

	function getType() {
		return $this->type;
	}
	
	function getState() {
		return $this->state;
	}

	function getDateTime() {
		return $this->dateTime;
	}

	function getCompleteDateTime() {
		return $this->completeDateTime;
	}

	function getCancelDateTime() {
		return $this->cancelDateTime;
	}

	/** @return StoreModuleOrder */
	function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setExtTransactionId($extTransactionId) {
		$this->extTransactionId = $extTransactionId;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setGatewayId($gatewayId) {
		$this->gatewayId = $gatewayId;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setLang($lang) {
		$this->lang = $lang;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setBuyer(StoreModuleBuyer $buyer = null) {
		$this->buyer = $buyer;
		return $this;
	}

	/**
	 * @param StoreModuleOrderItem[]|string[] $items
	 * @return StoreModuleOrder
	 */
	function setItems(array $items = array()) {
		$this->items = $items;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setPrice($price) {
		$this->price = $price;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setType($type) {
		$this->type = $type;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setState($state) {
		$this->state = $state;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setDateTime($dateTime) {
		$this->dateTime = $dateTime;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setCompleteDateTime($completeDateTime) {
		$this->completeDateTime = $completeDateTime;
		return $this;
	}

	/** @return StoreModuleOrder */
	function setCancelDateTime($cancelDateTime) {
		$this->cancelDateTime = $cancelDateTime;
		return $this;
	}

	/** @return StoreBillingInfo|null */
	public function getBillingInfo() {
		return $this->billingInfo;
	}

	/** @return StoreBillingInfo|null */
	public function getDeliveryInfo() {
		return $this->deliveryInfo;
	}

	/** @return StoreModuleOrder */
	public function setBillingInfo(StoreBillingInfo $billingInfo = null) {
		$this->billingInfo = $billingInfo;
		return $this;
	}

	/** @return StoreModuleOrder */
	public function setDeliveryInfo(StoreBillingInfo $deliveryInfo = null) {
		$this->deliveryInfo = $deliveryInfo;
		return $this;
	}
	
	/** @return float */
	public function getTaxAmount() {
		return $this->taxAmount;
	}

	/** @return float */
	public function getShippingAmount() {
		return $this->shippingAmount;
	}
	
	/** @return string|null */
	public function getShippingDescription() {
		return $this->shippingDescription;
	}

	/**
	 * @param float $taxAmount
	 * @return StoreModuleOrder
	 */
	public function setTaxAmount($taxAmount) {
		$this->taxAmount = $taxAmount;
		return $this;
	}

	/**
	 * @param float $shippingAmount
	 * @return StoreModuleOrder
	 */
	public function setShippingAmount($shippingAmount) {
		$this->shippingAmount = $shippingAmount;
		return $this;
	}
	
	/**
	 * @param string|null $shippingDescription
	 * @return $this
	 */
	public function setShippingDescription($shippingDescription) {
		$this->shippingDescription = $shippingDescription;
		return $this;
	}
	
	/** @return string */
	public function getOrderComment() {
		return $this->orderComment;
	}

	/**
	 * @param string $orderComment
	 * @return StoreModuleOrder
	 */
	public function setOrderComment($orderComment) {
		$this->orderComment = $orderComment;
		return $this;
	}
	
	/** @return StoreCurrency */
	public function getCurrency() {
		return $this->currency;
	}

	/** @return StorePriceOptions */
	public function getPriceOptions() {
		return $this->priceOptions;
	}

	/** @return StoreModuleOrder */
	public function setCurrency(StoreCurrency $currency = null) {
		$this->currency = $currency;
		return $this;
	}

	/** @return StoreModuleOrder */
	public function setPriceOptions(StorePriceOptions $priceOptions = null) {
		$this->priceOptions = $priceOptions;
		return $this;
	}
	
	public function getCustomFields() {
		return $this->customFields;
	}

	public function setCustomField($name, $value) {
		$this->customFields[$name] = $value;
	}

	public function getCustomField($name, $default = null) {
		if (isset($this->customFields[$name])) return $this->customFields[$name];
		return $default;
	}

	public function getInvoiceDocumentNumber() {
		return $this->invoiceDocumentNumber;
	}

	private static function getHashInternal($id, $transactionId, $dateTime) {
		return sha1("{$id}/{$transactionId}/{$dateTime}/C2s98&HfoAs87b0W(*mcvozpDe6rU-");
	}

	public function getHash() {
		return self::getHashInternal($this->id, $this->transactionId, $this->dateTime);
	}

	public function save() {
		self::lockLogFile(true);
		$listArr = self::readLogFile();
		if ($this->id) {
			foreach ($listArr as $idx => $liArr) {
				if (isset($liArr['id']) && $liArr['id'] == $this->id) {
					$listArr[$idx] = $this->jsonSerialize(); break;
				}
			}
		} else {
			$this->generateInvoiceDocumentNumber();
			$thisArr = $this->jsonSerialize();
			$newId = self::getNewId($listArr);
			$thisArr['id'] = $newId;
			$listArr[] = $thisArr;
			$this->id = $newId;
		}
		$result = (self::writeLogFile($listArr)) ? $this->id : null;
		self::unlockLogFile();
		return $result;
	}

	public function delete() {
		$deleted = false;
		if( $this->id ) {
			self::lockLogFile(true);
			$listArr = self::readLogFile();
			foreach ($listArr as $idx => $liArr) {
				if (isset($liArr['id']) && $liArr['id'] == $this->id) {
					array_splice($listArr, $idx, 1);
					$deleted = true;
					break;
				}
			}
			if( $deleted )
				$result = (self::writeLogFile($listArr)) ? $this->id : null;
			self::unlockLogFile();
		}
		return $deleted;
	}

	private static function getNewId(&$listArr = null) {
		if (!$listArr) $listArr = self::readLogFile();
		$max = 0;
		foreach ($listArr as $liArr) {
			if (is_numeric($liArr['id']) && (!$max || $max < intval($liArr['id']))) {
				$max = intval($liArr['id']);
			}
		}
		return (++$max);
	}
	
	/** @return StoreModuleOrder */
	public static function findByTransactionId($transactionId) {
		if (!$transactionId) return null;
		$list = self::findAll(array(self::FILTER_TRANSACTION_ID => $transactionId));
		return array_shift($list);
	}

	/** @return StoreModuleOrder */
	public static function findByExtTransactionId($extTransactionId) {
		if (!$extTransactionId) return null;
		$list = self::findAll(array(self::FILTER_EXT_TRANSACTION_ID => $extTransactionId));
		return array_shift($list);
	}

	/** @return StoreModuleOrder */
	public static function findByHash($hash) {
		if (!$hash) return null;
		$list = self::findAll(array(self::FILTER_HASH => $hash));
		return array_shift($list);
	}

	public static function findById($id) {
		if (!$id) return null;
		$list = self::findAll(array(self::FILTER_ID => $id));
		return array_shift($list);
	}

	/** @return StoreModuleOrder[] */
	public static function findAll(array $filter = array(), $limit = null) {
		$list = array();
		self::lockLogFile(true); // we have to lock for writing because readLogFile() calls fixLogFile(), which may actually write data to the file.
		$listArr = self::readLogFile();
		self::unlockLogFile();
		foreach ($listArr as $f) {
			if( $limit !== null ) {
				if( $limit <= 0 )
					break;
				$limit--;
			}
			if ($filter && is_array($filter)) {
				if (isset($filter[self::FILTER_ID]) && $filter[self::FILTER_ID]
						&& (!isset($f['id']) || $f['id'] != $filter[self::FILTER_ID])) continue;
				if (isset($filter[self::FILTER_TRANSACTION_ID]) && $filter[self::FILTER_TRANSACTION_ID]
						&& (!isset($f['transactionId']) || $f['transactionId'] != $filter[self::FILTER_TRANSACTION_ID])) continue;
				if (isset($filter[self::FILTER_EXT_TRANSACTION_ID]) && $filter[self::FILTER_EXT_TRANSACTION_ID]
						&& (!isset($f['extTransactionId']) || $f['extTransactionId'] != $filter[self::FILTER_EXT_TRANSACTION_ID])) continue;
				if (isset($filter[self::FILTER_HASH]) && $filter[self::FILTER_HASH]
						&& (!isset($f['id'], $f['transactionId'], $f['dateTime']) || self::getHashInternal($f['id'], $f['transactionId'], $f['dateTime']) != $filter[self::FILTER_HASH])) continue;
				if (isset($filter[self::FILTER_STATE]) && $filter[self::FILTER_STATE]
						&& (!isset($f['state']) || $f['state'] != $filter[self::FILTER_STATE])) continue;
				if (isset($filter[self::FILTER_DATE_TIME_LTE]) && $filter[self::FILTER_DATE_TIME_LTE]
						&& (!isset($f['dateTime']) || $f['dateTime'] > $filter[self::FILTER_DATE_TIME_LTE])) continue;
				if (isset($filter[self::FILTER_DATE_TIME_GTE]) && $filter[self::FILTER_DATE_TIME_GTE]
						&& (!isset($f['dateTime']) || $f['dateTime'] < $filter[self::FILTER_DATE_TIME_GTE])) continue;
			}
			$o = new self();
			$o->populate($f);
			$list[] = $o;
		}
		return $list;
	}
	
	private static function readLogFile() {
		try {
			self::fixLogFile();
			$itemsFile = StoreModule::getLogFile();
			if (is_file($itemsFile)) {
				$contents = '';
				if (($fh = @fopen($itemsFile, 'r')) !== false) {
					while (!feof($fh)) {
						$contents .= fread($fh, 2048);
					}
					fclose($fh);
				} else {
					/* file_put_contents(__DIR__.'/store_errors.log', print_r(array(
						'date' => date('Y-m-d H:i:s'),
						'method' => 'StoreModuleOrder::readLogFile',
						'function' => 'fopen'
					), true)."\n\n", FILE_APPEND); */
					throw new ErrorException('Error: Failed reading log file');
				}
				$parsed = json_decode($contents, true);
				if ($parsed === null) {
					/* file_put_contents(__DIR__.'/store_errors.log', print_r(array(
						'date' => date('Y-m-d H:i:s'),
						'method' => 'StoreModuleOrder::readLogFile',
						'function' => 'json_decode',
						'content' => $contents
					), true)."\n\n", FILE_APPEND); */
					throw new ErrorException('Error: Failed parsing orders log file');
				}
				return $parsed;
			}
		} catch (ErrorException $ex) {
			error_log($ex->getMessage());
		}
		return array();
	}
	
	private static function writeLogFile($arr) {
		try {
			$itemsFile = StoreModule::getLogFile();
			$json = json_encode($arr);
			if ($json === null || $json === false) {
				/* file_put_contents(__DIR__.'/store_errors.log', print_r(array(
					'date' => date('Y-m-d H:i:s'),
					'method' => 'StoreModuleOrder::writeLogFile',
					'function' => 'json_encode',
					'content' => print_r($arr, true)
				), true)."\n\n", FILE_APPEND); */
				throw new ErrorException('Error: Failed encoding orders log file');
			} else if (($fh = fopen($itemsFile, 'w')) !== false) {
				fwrite($fh, $json);
				fclose($fh);
				return true;
			} else {
				/* file_put_contents(__DIR__.'/store_errors.log', print_r(array(
					'date' => date('Y-m-d H:i:s'),
					'method' => 'StoreModuleOrder::writeLogFile',
					'function' => 'fopen',
					'content' => print_r($arr, true)
				), true)."\n\n", FILE_APPEND); */
				throw new ErrorException('Error: Failed writing log file');
			}
		} catch (ErrorException $ex) {
			error_log($ex->getMessage());
		}
		return false;
	}

	public function fromJson($data) {
		$this->populate($data);
	}
	
	public function jsonSerialize() {
		$items = array();
		foreach( $this->items as $k => $item )
			$items[$k] = ($item instanceof StoreModuleOrderItem) ? $item->jsonSerialize() : $item;
		return array(
			'id' => $this->id,
			'transactionId' => $this->transactionId,
			'extTransactionId' => $this->extTransactionId,
			'hash' => $this->getHash(),
			'invoiceDocumentNumber' => $this->invoiceDocumentNumber,
			'gatewayId' => $this->gatewayId,
			'lang' => $this->lang,
			'buyer' => ($this->buyer ? $this->buyer->jsonSerialize() : null),
			'items' => $items,
			'price' => $this->price,
			'type' => $this->type,
			'state' => $this->state,
			'dateTime' => $this->dateTime,
			'completeDateTime' => $this->completeDateTime,
			'cancelDateTime' => $this->cancelDateTime,
			'billingInfo' => ($this->billingInfo ? $this->billingInfo->jsonSerialize() : null),
			'deliveryInfo' => ($this->deliveryInfo ? $this->deliveryInfo->jsonSerialize() : null),
			'currency' => ($this->currency ? $this->currency->jsonSerialize() : null),
			'priceOptions' => ($this->priceOptions ? $this->priceOptions->jsonSerialize() : null),
			'orderComment' => $this->orderComment,
			'taxAmount' => $this->taxAmount,
			'shippingAmount' => $this->shippingAmount,
			'shippingDescription' => $this->shippingDescription,
			'customFields' => $this->customFields
		);
	}
	
	/**
	 * Update log file format to have new structure.
	 */
	private static function fixLogFile() {
		return;
		$itemsFile = StoreModule::getLogFile();
		if (!is_file($itemsFile)) return;
		$data = json_decode(file_get_contents($itemsFile), true);
		$fixNeeded = (isset($data['complete']) || isset($data['pending'])); // old file format
		if ($fixNeeded) {
			$listArr = array();
			if (isset($data['complete'])) {
				foreach ($data['complete'] as $itemData) {
					$item = new self();
					$item->populate($itemData);
					$item->setState(self::STATE_COMPLETE);
					$item->setCompleteDateTime($item->getDateTime());
					$itemArr = $item->jsonSerialize();
					$itemArr['id'] = sprintf("%08x", crc32(rand(1,999).'_'.microtime()));
					$listArr[] = $itemArr;
				}
			}
			if (isset($data['pending'])) {
				foreach ($data['pending'] as $itemData) {
					$item = new self();
					$item->populate($itemData);
					$item->setState(self::STATE_PENDING);
					$itemArr = $item->jsonSerialize();
					$itemArr['id'] = sprintf("%08x", crc32(rand(1,999).'_'.microtime()));
					$listArr[] = $itemArr;
				}
			}
		}
		self::unlockLogFile();
	}

	/**
	 * @param bool $forWriting
	 * @param bool $blocking
	 * @return bool|null Returns NULL if lock file could not be created or opened, TRUE if lock succeeded and FALSE if there was an error or locking did not block while $block parameter was set to FALSE.
	 */
	private static function lockLogFile($forWriting, $blocking = true) {
		if( self::$logLockFile === null )
			self::$logLockFile = @fopen(StoreModule::getLogFile() . ".lock", "c");
		if( !self::$logLockFile )
			return null;
		return @flock(self::$logLockFile, ($forWriting ? LOCK_EX : LOCK_SH) | ($blocking ? 0 : LOCK_NB));
	}

	private static function unlockLogFile() {
		if( !self::$logLockFile )
			return;
		@flock(self::$logLockFile, LOCK_UN);
	}

	public function generateInvoiceDocumentNumber() {
		foreach( $this->items as $item )
			if( !$item instanceof StoreModuleOrderItem )
				return null;
		return $this->invoiceDocumentNumber = StoreModule::getNextInvoiceDocumentNumber(StoreData::getInvoiceDocumentNumberFormat());
	}
}
