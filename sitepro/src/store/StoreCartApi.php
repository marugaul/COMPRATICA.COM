<?php

class StoreCartApi {
	const PHONE_FIELD_VISIBLE = true;
	const PHONE_FIELD_REQUIRED = true;

	protected function calcTotalsAction(StoreNavigation $request) {
		$data = $request->getBodyAsJson();
		if (!is_object($data) || !$data) $data = (object) array();
		
		$res = (object) array();
		
		$cartData = StoreData::getCartData();
		$tmpShippingMethodId = $cartData->shippingMethodId;
		if (isset($data->shippingMethodId)) {
			$cartData->shippingMethodId = intval($data->shippingMethodId);
		}
		
		self::calcTaxesAndShipping($res, $cartData);
		if ($tmpShippingMethodId != $cartData->shippingMethodId) StoreData::setCartData($cartData);
		
		StoreModule::respondWithJson($res);
	}
	
	protected function billingInfoAction(StoreNavigation $request) {
		$data = $request->getBodyAsJson();
		if (!is_object($data) || !$data) $data = (object) array();
		$useSame = isset($data->useSame) && $data->useSame;

		$res = (object) array(
			'billingInfoErrors' => array(),
			'deliveryInfoErrors' => array(),
			'generalErrors' => array()
		);
		$billingShippingRequired = StoreData::getBillingShippingRequired();
		$cartData = StoreData::getCartData();

		if ($billingShippingRequired) {
			$res->forceShowDeliveryInfo = false;
			if( StoreData::getTermsCheckboxEnabled() ) {
				if (isset($data->userAgreedToTerms))
					$cartData->userAgreedToTerms = $data->userAgreedToTerms;
				if( !$cartData->userAgreedToTerms )
					$res->generalErrors["userAgreedToTerms"] = StoreModule::__('You must agree to terms and conditions');
			}
			
			$cartData->billingInfo = StoreBillingInfo::fromJson($data->billingInfo);
			if ($useSame) {
				$cartData->deliveryInfo = clone $cartData->billingInfo;
			} else {
				$cartData->deliveryInfo = StoreBillingInfo::fromJson($data->deliveryInfo);
			}

			if (isset($data->billingInfo) && is_object($data->billingInfo) && $data->billingInfo) {
				$this->handleBillingInfo(
						$cartData->billingInfo,
						$res->billingInfoErrors,
						self::PHONE_FIELD_VISIBLE && self::PHONE_FIELD_REQUIRED, // Phone field MUST be required in billing info when it is required in shipping info
						false,
						$res->forceShowDeliveryInfo
					);
			}

			if (isset($data->deliveryInfo) && is_object($data->deliveryInfo) && $data->deliveryInfo) {
				$this->handleBillingInfo(
						$cartData->deliveryInfo,
						$res->deliveryInfoErrors,
						self::PHONE_FIELD_VISIBLE && self::PHONE_FIELD_REQUIRED,
						true,
						$res->forceShowDeliveryInfo
					);
			}
		}
		if (isset($data->orderComment)) {
			$cartData->orderComment = $data->orderComment;
		}

		if (empty($res->billingInfoErrors)) $res->billingInfoErrors = null;
		if (empty($res->deliveryInfoErrors)) $res->deliveryInfoErrors = null;
		if (empty($res->generalErrors)) $res->generalErrors = null;

		$res->billingInfo = $cartData->billingInfo ? $cartData->billingInfo->jsonSerialize() : null;
		$res->deliveryInfo = $cartData->deliveryInfo ? $cartData->deliveryInfo->jsonSerialize() : null;
		
		self::calcTaxesAndShipping($res, $cartData);
		StoreData::setCartData($cartData);
		
		StoreModule::respondWithJson($res);
	}
	
	public static function calcTaxesAndShipping(&$res, StoreCartData $cartData) {
		$billingShippingRequired = StoreData::getBillingShippingRequired();
		if ($cartData->billingInfo && $billingShippingRequired) {
			$taxRules = StoreData::getTaxRules($cartData->billingInfo);
		} else {
			$taxRules = array();
		}
		
		if ($cartData->deliveryInfo && $billingShippingRequired) {
			$shippingMethods = StoreData::getShippingMethods($cartData->deliveryInfo);
			// Note: make sure correct/existing shipping method is selected
			$tmpShippingMethodId = $cartData->shippingMethodId;
			$cartData->shippingMethodId = 0;
			foreach ($shippingMethods as $method) {
				if (!$cartData->shippingMethodId) $cartData->shippingMethodId = $method->id;
				if ($method->id != $tmpShippingMethodId) continue;
				$cartData->shippingMethodId = $method->id;
				break;
			}
		} else {
			$shippingMethods = array();
			$cartData->shippingMethodId = 0;
		}
		
		$po = StoreData::getPriceOptions();
		$dp = pow(10, (isset($po->decimalPlaces) && ($po->decimalPlaces >= 0)) ? intval($po->decimalPlaces) : 2);
		
		// calc subtotal
		$subTotalPrice = 0; $totalWeight = 0;
		foreach ($cartData->items as $item) {
			if (!isset($item->quantity) || $item->quantity < 1) $item->quantity = 1;
			$subTotalPrice += $item->quantity * $item->price;
			if (isset($item->weight)) $totalWeight += $item->quantity * $item->weight;
		}
		if ($subTotalPrice > 0) $subTotalPrice = round($subTotalPrice * $dp) / $dp;
		
		// calc shipping
		$shippingPrice = 0;
		$shippingMethod = null;
		if ($cartData->shippingMethodId) {
			foreach ($shippingMethods as $method) {
				if ($method->id != $cartData->shippingMethodId) continue;
				$shippingMethod = $method->name;
				if ($method->type == 0) { // FREE = 0
					$shippingPrice = 0;
				} else if ($method->type == 1) { // FLAT_RATE = 1
					$shippingPrice = isset($method->ranges[0]->value) ? $method->ranges[0]->value*1 : 0;
				} else if ($method->type == 2) { // BY_WEIGHT = 2
					foreach ($method->ranges as $range) {
						if (!isset($range->from) || !isset($range->to) || !isset($range->value)
								|| $range->from > $totalWeight || ($range->to < $totalWeight && $range->to)) continue;
						$shippingPrice = $range->value*1;
						break;
					}
				}
				else if ($method->type == 3 && isset($method->ranges) && is_array($method->ranges)) { // BY_SUBTOTAL = 3
					foreach ($method->ranges as $range) {
						if (!isset($range->from) || !isset($range->to) || !isset($range->value)
								|| $range->from > $subTotalPrice || ($range->to < $subTotalPrice && $range->to)) continue;
						$shippingPrice = $range->value*1;
						break;
					}
				}
				break;
			}
		}
		if ($shippingPrice > 0) $shippingPrice = round($shippingPrice * $dp) / $dp;
		
		// calc taxes
		$taxPrice = 0;
		$taxablePrice = $subTotalPrice + $shippingPrice;
		foreach ($taxRules as $rule) {
			foreach ($rule->rates as $rate) {
				$taxPrice += $taxablePrice * ($rate->rate / 100.0);
			}
		}
		if ($taxPrice > 0) $taxPrice = round($taxPrice * $dp) / $dp;
		
		// calc total
		$totalPrice = $subTotalPrice + $shippingPrice + $taxPrice;
		
		$res->shippingMethods = $shippingMethods;
		$res->shippingMethodId = $cartData->shippingMethodId;
		$res->subTotalPrice = $subTotalPrice;
		$res->shippingPrice = $shippingPrice;
		$res->shippingMethod = $shippingMethod;
		$res->taxPrice = $taxPrice;
		$res->totalWeight = $totalWeight;
		$res->totalPrice = $totalPrice;
	}

	/**
	 * @param StoreModuleOrder $order
	 * @param StoreCartData $cartData
	 * @return StoreModuleOrderItem[]
	 */
	public static function buildCartItemList(StoreModuleOrder $order, StoreCartData $cartData) {
		$items = array();
		foreach ($cartData->items as $item)
			$items[] = StoreModuleOrderItem::fromCartItem($order, $item);
		return $items;
	}
	
	protected function addAction(StoreNavigation $request) {
		$cartActionId = $request->getArg(2);
		$quantity = intval($request->getQueryParam("quantity", 1));
		if( $quantity >= 1 ) {
			$cartData = StoreData::getCartData();
			$items = StoreData::getItems();
			foreach ($items as $item) {
				if ($item->id != $cartActionId) continue;
				$found = false;
				foreach ($cartData->items as $cItem) {
					if ($cItem->id != $cartActionId) continue;
					$cItem->quantity = StoreData::cartItemQuantity($cItem) + $quantity;
					$found = true;
					break;
				}
				if (!$found) {
					// Note: reset quantity (comes from quantity in store) to one when adding new item.
					$item->quantity = $quantity;
					$cartData->items[] = $item;
				}
				break;
			}
			StoreData::setCartData($cartData);
		}
		StoreModule::respondWithJson(array('total' => StoreData::countCartItems()));
	}
	
	protected function updateAction(StoreNavigation $request) {
		$cartActionId = $request->getArg(2);
		$cartData = StoreData::getCartData();
		foreach ($cartData->items as $idx => $item) {
			if ($item->id != $cartActionId) continue;
			$quantityQs = $request->getArg(3);
			$cartData->items[$idx]->quantity = ($quantityQs ? intval($quantityQs) : null);
			break;
		}
		StoreData::setCartData($cartData);
		StoreModule::respondWithJson(array('total' => StoreData::countCartItems()));
	}
	
	protected function removeAction(StoreNavigation $request) {
		$cartActionId = $request->getArg(2);
		$cartData = StoreData::getCartData();
		foreach ($cartData->items as $idx => $item) {
			if ($item->id != $cartActionId) continue;
			array_splice($cartData->items, $idx, 1);
			break;
		}
		StoreData::setCartData($cartData);
		StoreModule::respondWithJson(array('total' => StoreData::countCartItems()));
	}
	
	protected function clearAction() {
		self::clearStoreCart();
		self::respondWithJson(array('total' => StoreData::countCartItems()));
	}
	
	public function process(StoreNavigation $request) {
		$cartAction = array_map('ucfirst', explode('-', strtolower(preg_replace('#[^a-zA-Z0-9\-]+#', '', $request->getArg(1)))));
		$cartAction[0] = strtolower($cartAction[0]);
		$method = implode('', $cartAction).'Action';
		if (method_exists($this, $method)) {
			call_user_func(array($this, $method), $request);
		}
	}
	
	private static function handleBillingInfo(StoreBillingInfo &$info, &$errors = array(), $needPhone = false, $validateCountryAndRegion = false, &$showDeliveryInfo = false) {
		if (!$info->email || !preg_match('#^[^ @]+@[^ @]+\.[^\ \.]+$#', $info->email)) {
			$errors['email'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Email'));
		}
		if (!$info->phone && $needPhone) {
			$errors['phone'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Phone Number'));
		}
		if( $info->isCompany ) {
			if (!$info->companyName) {
				$errors['companyName'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Company Name'));
			}
			/* if (!$info->companyCode) {
				$errors['companyCode'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Company Code'));
			} */
		}
		else {
			if (!$info->firstName) {
				$errors['firstName'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('First Name'));
			}
			if (!$info->lastName) {
				$errors['lastName'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Last Name'));
			}
		}
		if (!$info->address1) {
			$errors['address1'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Address'));
		}
		if (!$info->city) {
			$errors['city'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('City'));
		}
		/* if (!$info->postCode) {
			$errors['postCode'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Post Code'));
		} */

		/**
		 * @var StoreCountry|null $country
		 * @var StoreRegion|null $region
		 */
		list($country, $region) = StoreCountry::findCountryAndRegion($info->countryCode, $info->region);
		if (!$country) {
			$errors['country'] = sprintf(StoreModule::__("Field '%s' is required"), StoreModule::__('Country'));
		}
		else {
			$info->country = $country->name;
			if( !empty($country->regions) && !$region ) {
				$fieldLabel = ($country->code === "US") ? StoreModule::__('State / Province') : StoreModule::__('Region');
				$errors['region'] = sprintf(StoreModule::__("Field '%s' is required"), $fieldLabel);
			}
		}
		if( $region )
			$info->regionCode = $region->code;
		if( $validateCountryAndRegion && !isset($errors["country"]) && !isset($errors["region"]) ) {
			$allowed = StoreData::getAvailableShippingCountryAndRegionCodes();
			if( !empty($allowed) ) {
				if( !isset($allowed[$country->code]) ) {
					$errors['country'] = StoreModule::__("Delivery to specified destination is not supported");
					$showDeliveryInfo = true;
				}
				else if( !empty($allowed[$country->code]) && !in_array($region->code, $allowed[$country->code]) ) {
					$errors['region'] = StoreModule::__("Delivery to specified destination is not supported");
					$showDeliveryInfo = true;
				}
			}
		}
	}
	
	public static function clearStoreCart() {
		$cartData = StoreData::getCartData();
		$cartData->items = array();
		$cartData->orderComment = '';
		StoreData::setCartData($cartData);
	}
	
}
