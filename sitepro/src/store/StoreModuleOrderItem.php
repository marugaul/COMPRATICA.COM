<?php

class StoreModuleOrderItem {
	/** @var StoreModuleOrder */
	private $order = null;

	/** @var string */
	public $name = "";
	/** @var string */
	public $sku = "";
	/** @var float */
	public $price = 0.0;
	/** @var int */
	public $quantity = 0;
	/** @var StoreModuleOrderItemCustomField[] */
	public $customFields = array();

	protected function __construct(StoreModuleOrder $order) {
		$this->order = $order;
	}

	/**
	 * @param StoreModuleOrder $order
	 * @param Profis\SitePro\controller\StoreDataItem $cartItem
	 * @return StoreModuleOrderItem
	 */
	public static function fromCartItem(StoreModuleOrder $order, $cartItem) {
		$item = new self($order);
		$item->name = $cartItem->name;
		$item->sku = $cartItem->sku;
		$item->price = $cartItem->price;
		$item->quantity = max(isset($cartItem->quantity) ? intval($cartItem->quantity) : 1, 1);
		$itemType = StoreData::getItemType($cartItem->itemType);
		foreach( $cartItem->customFields as $customField ) {
			$field = StoreData::getItemTypeField($itemType, $customField->fieldId);
			$fieldValue = StoreElement::stringifyFieldValue($customField, $field);
			$item->customFields[] = new StoreModuleOrderItemCustomField(tr_($field->name), $fieldValue);
		}
		return $item;
	}

	/**
	 * @param StoreModuleOrder $order
	 * @param stdClass|array $data
	 * @return StoreModuleOrderItem
	 */
	public static function fromJson(StoreModuleOrder $order, $data) {
		if( !is_object($data) )
			$data = (object)$data;
		$item = new self($order);
		$item->name = $data->name;
		$item->sku = $data->sku;
		$item->price = $data->price;
		$item->quantity = $data->quantity;
		$item->customFields = array();
		foreach( $data->customFields as $cfData )
			$item->customFields[] = StoreModuleOrderItemCustomField::fromJson($cfData);
		return $item;
	}

	public function jsonSerialize() {
		$customFields = array();
		foreach( $this->customFields as $field )
			$customFields[] = $field->jsonSerialize();
		return array(
			"name" => $this->name,
			"sku" => $this->sku,
			"price" => $this->price,
			"quantity" => $this->quantity,
			"customFields" => $customFields,
		);
	}

	public function getFormattedPrice() {
		return StoreData::formatPrice($this->price, $this->order->getPriceOptions(), $this->order->getCurrency());
	}

	public function __toString() {
		return trim(tr_($this->name))
			.' ('.StoreModule::__('SKU').": ".trim($this->sku).")"
			.' ('.StoreModule::__('Price').": ".$this->getFormattedPrice().")"
			.' ('.StoreModule::__('Qty').': '.$this->quantity.')';
	}
}