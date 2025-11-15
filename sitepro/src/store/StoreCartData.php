<?php

/**
 * Store cart descriptor.
 */
class StoreCartData {
	
	/** @var \Profis\SitePro\controller\StoreDataItem[] */
	public $items = array();
	/** @var StoreBillingInfo|null */
	public $billingInfo = null;
	/** @var StoreBillingInfo|null */
	public $deliveryInfo = null;
	/** @var int */
	public $shippingMethodId = 0;
	/** @var string */
	public $orderComment = '';
	/** @var bool */
	public $userAgreedToTerms = false;
	
	/**
	 * Build data to be used for JSON serialization.
	 * @return array
	 */
	public function jsonSerialize() {
		return array(
			'items' => $this->items,
			'billingInfo' => ($this->billingInfo ? $this->billingInfo->jsonSerialize() : null),
			'deliveryInfo' => ($this->deliveryInfo ? $this->deliveryInfo->jsonSerialize() : null),
			'shippingMethodId' => $this->shippingMethodId,
			'orderComment' => $this->orderComment,
			'userAgreedToTerms' => $this->userAgreedToTerms,
		);
	}
	
	/**
	 * Build instance from JSON string or standard object.
	 * @param string|stdClass $json JSON string to parse.
	 * @return StoreCartData
	 */
	public static function fromJson($json) {
		$data = is_object($json) ? $json : (is_string($json) ? json_decode($json) : (is_array($json) ? ((object) $json) : null));
		if (!$data || !is_object($data)) return null;
		$res = new self();
		if (isset($data->items) && is_array($data->items)) $res->items = $data->items;
		if (isset($data->billingInfo)) $res->billingInfo = StoreBillingInfo::fromJson($data->billingInfo);
		if (isset($data->deliveryInfo)) $res->deliveryInfo = StoreBillingInfo::fromJson($data->deliveryInfo);
		if (isset($data->shippingMethodId)) $res->shippingMethodId = intval($data->shippingMethodId);
		if (isset($data->orderComment)) $res->orderComment = (is_string($data->orderComment) ? $data->orderComment : '');
		if (isset($data->userAgreedToTerms)) $res->userAgreedToTerms = !!$data->userAgreedToTerms;
		return $res;
	}
	
}
