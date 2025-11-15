<?php

/**
 * Billing/Delivery info descriptor.
 */
class StoreBillingInfo {
	/** @var int Must be either 0 or 1 */
	public $isCompany = 0;
	/** @var string */
	public $companyName = '';
	/** @var string */
	public $companyCode = '';
	/** @var string */
	public $companyVatCode = '';
	/** @var string */
	public $email = '';
	/** @var string */
	public $firstName = '';
	/** @var string */
	public $lastName = '';
	/** @var string */
	public $address1 = '';
	/** @var string */
	public $address2 = '';
	/** @var string */
	public $city = '';
	/** @var string */
	public $region = '';
	/** @var string */
	public $regionCode = '';
	/** @var string */
	public $postCode = '';
	/** @var string */
	public $countryCode = '';
	/** @var string */
	public $country = '';
	/** @var string */
	public $phone = '';
	
	/**
	 * Build data to be used for JSON serialization.
	 * @return array
	 */
	public function jsonSerialize() {
		return array(
			'isCompany' => $this->isCompany,
			'companyName' => $this->companyName,
			'companyCode' => $this->companyCode,
			'companyVatCode' => $this->companyVatCode,
			'email' => $this->email,
			'firstName' => $this->firstName,
			'lastName' => $this->lastName,
			'address1' => $this->address1,
			'address2' => $this->address2,
			'city' => $this->city,
			'region' => $this->region,
			'postCode' => $this->postCode,
			'countryCode' => $this->countryCode,
			'country' => $this->country,
			'phone' => $this->phone
		);
	}
	
	/**
	 * Build instance from JSON string or standard object.
	 * @param string|stdClass $json JSON string to parse.
	 * @return StoreBillingInfo
	 */
	public static function fromJson($json) {
		$data = is_object($json) ? $json : (is_string($json) ? json_decode($json) : (is_array($json) ? ((object) $json) : null));
		if (!is_object($data)) return null;
		$res = new self();
		foreach ($data AS $key => $value) { if (property_exists($res, $key)) $res->{$key} = $value; }
		return $res;
	}
	
}
