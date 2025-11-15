<?php

use Profis\SitePro\controller\StoreDataDestinationZone;

class StoreData {
	/** @var \Profis\SitePro\controller\StoreModuleSiteData */
	private static $data;
	/** @var \Profis\SitePro\controller\StoreDataCategory[] */
	private static $categoryIdx;
	/** @var \Profis\SitePro\controller\StoreDataItemType[] */
	private static $itemTypeIdx;
	/** @var \Profis\SitePro\controller\StoreDataItemFieldType[] */
	private static $itemFieldTypeIdx;

	private static function getDataFile() {
		return dirname(__FILE__).'/store.dat';
	}
	
	/** @return \Profis\SitePro\controller\StoreModuleData */
	private static function getData() {
		if (!self::$data) {
			$dataFile = self::getDataFile();
			if (is_file($dataFile)) {
				self::$data = json_decode(file_get_contents($dataFile));
			}
		}
		return self::$data;
	}
	
	public static function randomHash($len = 17, $onlyDigits = false) {
		return randomHash($len, $onlyDigits);
	}
	
	/** @return bool */
	public static function needToShowDates() {
		return (($data = self::getData()) && isset($data->showDates) && $data->showDates);
	}
	
	/** @return bool */
	public static function needToShowItemId() {
		return (($data = self::getData()) && isset($data->showItemId) && $data->showItemId);
	}
	
	/**
	 * Format price to price string.
	 * @param float $price price to format.
	 * @param StorePriceOptions  $priceOptions
	 * @param StoreCurrency|null $currency
	 * @return string
	 */
	public static function formatPrice($price, StorePriceOptions $priceOptions, StoreCurrency $currency = null) {
		return (($currency ? $currency->prefix : '')
				.number_format($price, intval($priceOptions->decimalPlaces), $priceOptions->decimalPoint, $priceOptions->thousandsSeparator)
				.($currency ? $currency->postfix : ''));
	}
	
	/**
	 * Get cart item quantity.
	 * @param \Profis\SitePro\controller\StoreDataItem|StoreModuleOrderItem $item
	 * @return int
	 */
	public static function cartItemQuantity($item) {
		if (!$item) return 0;
		return ((isset($item->quantity) && is_numeric($item->quantity) && intval($item->quantity) > 0) ? intval($item->quantity) : 1);
	}
	
	/**
	 * Get total cart item count.
	 * @return int
	 */
	public static function countCartItems() {
		$cartData = self::getCartData();
		$total = 0;
		foreach ($cartData->items as $item) {
			$total += self::cartItemQuantity($item);
		}
		return $total;
	}
	
	/** @return StoreCartData */
	public static function getCartData() {
		if (!session_id()) @session_start();
		$data = isset($_SESSION[StoreModule::$sessionKey])
				? StoreCartData::fromJson($_SESSION[StoreModule::$sessionKey])
				: null;
		if (!$data) $data = new StoreCartData();
		return $data;
	}
	
	/**
	 * @param StoreCartData $data
	 */
	public static function setCartData(StoreCartData $data) {
		if (!session_id()) @session_start();
		$_SESSION[StoreModule::$sessionKey] = json_encode($data->jsonSerialize());
	}
	
	public static function storeFilters($pageId, $filters) {
		if (!session_id()) @session_start();
		$_SESSION[StoreModule::$sessionKey.'_filters_'.$pageId] = $filters;
	}
	
	public static function loadFilters($pageId) {
		if (!session_id()) @session_start();
		$key = StoreModule::$sessionKey.'_filters_'.$pageId;
		$filters = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
		if (!is_array($filters)) $filters = array();
		return $filters;
	}
	
	/** @return \Profis\SitePro\controller\StoreDataTaxRule[] */
	public static function getTaxRules(StoreBillingInfo $billingInfo = null) {
		if (($data = self::getData()) && isset($data->taxRules) && is_array($data->taxRules)) {
			if ($billingInfo) {
				$zones = array_map(function($zone) { return $zone->id; }, self::getDestinationZones($billingInfo));
				$list = array();
				foreach ($data->taxRules as $li) {
					foreach ($li->rates as $rate) {
						if (!in_array($rate->destinationZoneId, $zones)) continue;
						$rule = clone $li;
						$rule->rates = array($rate);
						$list[] = $rule;
						break;
					}
				}
				return $list;
			}
			return $data->taxRules;
		}
		return array();
	}
	
	/** @return \Profis\SitePro\controller\StoreDataDestinationZone[] */
	public static function getDestinationZones(StoreBillingInfo $billingInfo = null) {
		if (($data = self::getData()) && isset($data->destinationZones) && is_array($data->destinationZones)) {
			if ($billingInfo) {
				$list = array();
				list($country, $region) = StoreCountry::findCountryAndRegion($billingInfo->countryCode, $billingInfo->region);
				if( $country ) {
					foreach ($data->destinationZones as $li) {
						if (in_array($billingInfo->countryCode, $li->countries)) {
							$regionRequired = !empty($country->regions);
							$regionFound = $regionRequired && $region && in_array($country->code . '-' . $region->code, $li->countries);
							if( $regionRequired && !$regionFound && $region ) {
								// We have to check if website creator has selected at least one region for this
								// country. If not treat it as if all regions are selected.
								$regionRequired = false;
								foreach( $li->countries as $code ) {
									if( preg_match("#^" . preg_quote($country->code) . "-#isu", $code) ) {
										$regionRequired = true;
										break;
									}
								}
							}
							if( !$regionRequired || $regionFound )
								$list[] = $li;
						}
					}
				}
				return $list;
			}
			return $data->destinationZones;
		}
		return array();
	}
	
	/** @return \Profis\SitePro\controller\StoreDataShippingMethod[] */
	public static function getShippingMethods(StoreBillingInfo $billingInfo = null) {
		if (($data = self::getData()) && isset($data->shippingMethods) && is_array($data->shippingMethods)) {
			if ($billingInfo) {
				$zones = array_map(function($zone) { return $zone->id; }, self::getDestinationZones($billingInfo));
				$list = array();
				foreach ($data->shippingMethods as $li) {
					if (in_array($li->destinationZoneId, $zones)) $list[] = $li;
				}
				return $list;
			}
			return $data->shippingMethods;
		}
		return array();
	}

	public static function getAvailableShippingCountryAndRegionCodes() {
		$codes = array();
		if (($data = self::getData()) && isset($data->shippingMethods, $data->destinationZones) && is_array($data->shippingMethods) && is_array($data->destinationZones) ) {
			/** @var StoreDataDestinationZone $dzIndex */
			$dzIndex = array();
			foreach( $data->destinationZones as $zone )
				$dzIndex[$zone->id] = $zone;
			$countryIndex = array();
			foreach( StoreCountry::buildList() as $country )
				$countryIndex[$country->code] = $country;
			foreach( $data->shippingMethods as $method ) {
				if( $method->destinationZoneId && isset($dzIndex[$method->destinationZoneId]) ) {
					$zone = $dzIndex[$method->destinationZoneId];
					$zoneCodes = array();
					foreach( $zone->countries as $code ) {
						if( preg_match("#^([a-z]{2})-(.+)$#isu", $code, $mtc) ) {
							$countryCode = $mtc[1];
							$regionCode = $mtc[2];
						}
						else {
							$countryCode = $code;
							$regionCode = null;
						}
						if( !isset($codes[$countryCode]) )
							$codes[$countryCode] = array();
						if( $regionCode !== null )
							$codes[$countryCode][] = $regionCode;
						if( !isset($zoneCodes[$countryCode]) )
							$zoneCodes[$countryCode] = array();
						if( $regionCode !== null )
							$zoneCodes[$countryCode][] = $regionCode;
					}
					foreach( $zoneCodes as $countryCode => $regionCodes ) {
						if( empty($regionCodes) && isset($countryIndex[$countryCode]) && !empty($countryIndex[$countryCode]->regions) ) {
							// Zone has no regions selected, but country is selected. This must be treated as if all regions are selected.
							foreach( $countryIndex[$countryCode]->regions as $region )
								$codes[$countryCode][] = $region->code;
						}
					}
					unset($zoneCodes);
				}
			}
			foreach( $codes as $countryCode => $regionCodes )
				$codes[$countryCode] = array_values(array_unique($regionCodes));
		}
		return $codes;
	}

	/** @return \Profis\SitePro\controller\StoreDataPaymentGateway[] */
	public static function getPaymentGateways() {
		if (($data = self::getData()) && isset($data->paymentGateways) && is_array($data->paymentGateways)) {
			return $data->paymentGateways;
		}
		return array();
	}
	
	/** @return bool */
	public static function getBillingShippingRequired() {
		if (($data = self::getData())) {
			$gateways = count(self::getPaymentGateways());
			return ($gateways > 0 && (!isset($data->billingShippingRequired) || $data->billingShippingRequired));
		}
		return false;
	}
	
	/** @return \Profis\SitePro\controller\StoreImageData */
	public static function getNoPhotoImage() {
		if (($data = self::getData()) && isset($data->noPhotoImage) && $data->noPhotoImage) {
			return $data->noPhotoImage;
		}
		return null;
	}
	
	/** @return StorePriceOptions */
	public static function getPriceOptions() {
		if (($data = self::getData()) && isset($data->priceOptions) && is_object($data->priceOptions)) {
			return StorePriceOptions::fromJson($data->priceOptions);
		}
		return new StorePriceOptions();
	}
	
	/** @return StoreCurrency */
	public static function getCurrency() {
		if (($data = self::getData()) && isset($data->currency) && is_object($data->currency)) {
			return StoreCurrency::fromJson($data->currency);
		}
		return new StoreCurrency();
	}
	
	/**
	 * Get category indent from depth in category tree.
	 * @param \Profis\SitePro\controller\StoreDataCategory $category
	 * @param int $lvl indent of parent item.
	 * @return int
	 */
	private static function getCategoryIndent($category, $lvl = 0) {
		if (!$category || !isset($category->parentId) || !$category->parentId) return $lvl;
		$parent = self::getCategory($category->parentId);
		if (!$parent) return $lvl;
		return self::getCategoryIndent($parent, $lvl + 1);
	}
	
	/**
	 * @param bool $indent return indented category list.
	 * @return \Profis\SitePro\controller\StoreDataCategory[]
	 */
	public static function getCategories($indent = false) {
		if (($data = self::getData()) && isset($data->categories) && is_array($data->categories)) {
			if ($indent) {
				for ($i = 0, $c = count($data->categories); $i < $c; $i++) {
					$data->categories[$i]->indent = self::getCategoryIndent($data->categories[$i]);
				}
			}
			return $data->categories;
		}
		return array();
	}

	/** @return \Profis\SitePro\controller\StoreDataItem[] */
	public static function getItems() {
		if (($data = self::getData()) && isset($data->items) && is_array($data->items)) {
			return $data->items;
		}
		return array();
	}
	
	/** @return \Profis\SitePro\controller\StoreDataItemType[] */
	public static function getItemTypes() {
		if (($data = self::getData()) && isset($data->itemTypes) && is_array($data->itemTypes)) {
			return $data->itemTypes;
		}
		return array();
	}
	
	/** @return \Profis\SitePro\controller\StoreDataItemFieldType[] */
	public static function getItemFieldTypes() {
		if (($data = self::getData()) && isset($data->itemFieldTypes) && is_array($data->itemFieldTypes)) {
			return $data->itemFieldTypes;
		}
		return array();
	}
	
	/** @return \Profis\SitePro\controller\StoreDataCategory */
	public static function getCategory($id) {
		if (!self::$categoryIdx) {
			self::$categoryIdx = array();
			$list = self::getCategories();
			for ($i = 0, $c = count($list); $i < $c; $i++) {
				self::$categoryIdx[$list[$i]->id] = $list[$i];
			}
		}
		return ($id && isset(self::$categoryIdx[$id])) ? self::$categoryIdx[$id] : null;
	}
	
	/** @return \Profis\SitePro\controller\StoreDataItemType */
	public static function getItemType($id) {
		if (!self::$itemTypeIdx) {
			self::$itemTypeIdx = array();
			$list = self::getItemTypes();
			for ($i = 0, $c = count($list); $i < $c; $i++) {
				self::$itemTypeIdx[$list[$i]->id] = $list[$i];
			}
		}
		return ($id && isset(self::$itemTypeIdx[$id])) ? self::$itemTypeIdx[$id] : null;
	}
	
	/**
	 * @param \Profis\SitePro\controller\StoreDataItemType $itemType
	 * @param int $id
	 * @return \Profis\SitePro\controller\StoreDataItemTypeField
	 */
	public static function getItemTypeField($itemType, $id) {
		if (!$itemType) return null;
		if (!isset($itemType->fieldsIdx)) {
			$itemType->fieldsIdx = array();
			for ($i = 0, $c = count($itemType->fields); $i < $c; $i++) {
				$itemType->fieldsIdx[$itemType->fields[$i]->id] = $itemType->fields[$i];
			}
		}
		return ($id && isset($itemType->fieldsIdx[$id])) ? $itemType->fieldsIdx[$id] : null;
	}
	
	/** @return \Profis\SitePro\controller\StoreDataItemFieldType */
	public static function getItemFieldType($id) {
		if (!self::$itemFieldTypeIdx) {
			self::$itemFieldTypeIdx = array();
			$list = self::getItemFieldTypes();
			for ($i = 0, $c = count($list); $i < $c; $i++) {
				self::$itemFieldTypeIdx[$list[$i]->id] = $list[$i];
			}
		}
		return ($id && isset(self::$itemFieldTypeIdx[$id])) ? self::$itemFieldTypeIdx[$id] : null;
	}

	/** @return string|string[]|null */
	public static function getCompanyInfo() {
		if (($data = self::getData()) && !empty($data->companyInfo)) {
			return $data->companyInfo;
		}
		return null;
	}

	/** @return string|string[]|null */
	public static function getInvoiceTitlePhrase() {
		if (($data = self::getData()) && isset($data->invoiceTitlePhrase) && !empty($data->invoiceTitlePhrase)) {
			return $data->invoiceTitlePhrase;
		}
		return StoreModule::__('Invoice');
	}

	/** @return string|null */
	public static function getInvoiceLogo() {
		if (($data = self::getData()) && isset($data->invoiceLogo) && !empty($data->invoiceLogo)) {
			return $data->invoiceLogo;
		}
		return null;
	}
	
	/** @return string|string[]|null */
	public static function getInvoiceTextBeginning() {
		if (($data = self::getData()) && isset($data->invoiceTextBeginning) && !empty($data->invoiceTextBeginning)) {
			return $data->invoiceTextBeginning;
		}
		return null;
	}
	
	/** @return string|string[]|null */
	public static function getInvoiceTextEnding() {
		if (($data = self::getData()) && isset($data->invoiceTextEnding) && !empty($data->invoiceTextEnding)) {
			return $data->invoiceTextEnding;
		}
		return null;
	}

	/** @return string|null */
	public static function getInvoiceDocumentNumberFormat() {
		if (($data = self::getData()) && !empty($data->invoiceDocumentNumberFormat)) {
			return $data->invoiceDocumentNumberFormat;
		}
		return null;
	}

	/** @return bool */
	public static function getTermsCheckboxEnabled() {
		if (($data = self::getData()) && property_exists($data, 'termsCheckboxEnabled')) {
			return !!$data->termsCheckboxEnabled;
		}
		return false;
	}

	/** @return string|string[]|null */
	public static function getTermsCheckboxText() {
		if (($data = self::getData()) && !empty($data->termsCheckboxText)) {
			return $data->termsCheckboxText;
		}
		return null;
	}

	/** @return float */
	public static function getMinOrderPrice() {
		if (($data = self::getData()) && !empty($data->minOrderPrice)) {
			return $data->minOrderPrice;
		}
		return 0;
	}
	
	/**
	 * @param int $categoryId
	 * @return Profis\SitePro\controller\StoreDataCategory[]
	 */
	public static function getCategoryItems($categoryId) {
		$catItems = array();
		$items = self::getItems();
		foreach ($items as $item) {
			if (in_array($categoryId, $item->categories)) {
				$catItems[] = $item;
			}
		}
		return $catItems;
	}
	
	/**
	 * Checks if category contains item.
	 * @param int $categoryId
	 * @param int $itemId
	 * @return bool
	 */
	public static function categoryHasItem($categoryId, $itemId) {
		$categories = self::getCategories();
		foreach ($categories as $category) {
			if ($category->id == $categoryId) {
				$items = self::getCategoryItems($categoryId);
				foreach ($items as $item) {
					if ($item->id == $itemId) {
						return true;
					}
				}
			}
		}
		return false;
	}
}
