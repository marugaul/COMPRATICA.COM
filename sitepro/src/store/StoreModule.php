<?php

/**
 * Description of StoreModule
 */
class StoreModule {
	
	/** @var StoreNavigation */
	public static $storeNav;
	/** @var stdClass */
	public static $initData;
	/** @var SiteInfo */
	public static $siteInfo;
	private static $translations;
	/** @var string */
	public static $sessionKey;
	/** @var string */
	public static $storeAnchor;
	
	/**
	 * Translate store module variable.
	 * @param string $key
	 * @return string
	 */
	public static function __($key) {
		$langKey = (self::$storeNav && self::$storeNav->lang) ? self::$storeNav->lang : '-';
		return SiteModule::__($key, $langKey);
	}
	
	public static function init($data, SiteInfo $siteInfo) {
		@setlocale(LC_CTYPE, 'C.UTF-8');
		self::$initData = $data;
		self::$siteInfo = $siteInfo;
		self::$sessionKey = '__STORE_CART_DATA_'.$siteInfo->siteId.'__';
	}
	
	/**
	 * Get orders log file
	 * @return string
	 */
	public static function getLogFile() {
		return dirname(__FILE__).'/store.log';
	}

	public static function getNextInvoiceDocumentNumber($format) {
		if( empty($format) )
			return null;
		$fp = @fopen(dirname(__FILE__).'/invoice-series.json', "c+");
		if( !$fp )
			return null;
		@flock($fp, LOCK_EX);

		fseek($fp, SEEK_SET, 0);
		$content = "";
		while( !feof($fp) )
			$content .= fread($fp, 2048);
		$counters = $content ? json_decode($content, true) : array();

		$seriesId = trim($format);
		$resettableCounterKey = preg_replace_callback("#{(YYYY|YY|MM|DD)}#isu", function($mtc) {
			$key = strtoupper($mtc[1]);
			if( $key == "YYYY" )
				return date("Y");
			if( $key == "YY" )
				return date("y");
			if( $key == "MM" )
				return date("m");
			if( $key == "DD" )
				return date("d");
			return $mtc[0];
		}, $seriesId);

		$docNum = $resettableCounterKey;

		$seriesId = preg_replace("#{(R?N)(?::\\d+)?}#isu", "{\\1}", $seriesId);
		$seriesId = function_exists('mb_strtoupper') ? mb_strtoupper($seriesId, "utf-8") : strtoupper($seriesId);
		$resettableCounterKey = preg_replace("#{(R?N)(?::\\d+)?}#isu", "{\\1}", $resettableCounterKey);
		$resettableCounterKey = function_exists('mb_strtoupper') ? mb_strtoupper($resettableCounterKey, "utf-8") : strtoupper($resettableCounterKey);

		if( !isset($counters[$seriesId]) ) {
			$counters[$seriesId] = array(
				"n" => 0, // Absolute number for series.
				"rn" => 0, // Number that resets every time when date components in series are changed.
				"rck" => $resettableCounterKey // Date component values for current "rn" counter value.
			);
		}
		else if( $counters[$seriesId]["rck"] != $resettableCounterKey ) {
			$counters[$seriesId]["rn"] = 0;
			$counters[$seriesId]["rck"] = $resettableCounterKey;
		}

		$counters[$seriesId]["n"]++;
		$counters[$seriesId]["rn"]++;
		$curCounter = $counters[$seriesId];

		$docNum = preg_replace_callback("#{(R?N)(?::(\\d+))?}#isu", function($mtc) use($curCounter) {
			$index = (strtoupper($mtc[1]) == "RN") ? $curCounter["rn"] : $curCounter["n"];
			if( !empty($mtc[2]) )
				return sprintf("%0" . $mtc[2] . "d", $index);
			return (string)$index;
		}, $docNum);

		fseek($fp, SEEK_SET, 0);
		fwrite($fp, json_encode($counters));

		@flock($fp, LOCK_UN);
		fclose($fp);

		return $docNum;
	}

	/**
	 * Parse request to perform special actions
	 * @param SiteRequestInfo $requestInfo 
	 */
	public static function parseRequest($requestInfo) {
		$request = self::handleStoreNav($requestInfo);
		
		try {
			$out = StorePaymentApi::process($request, (!$requestInfo->page || $requestInfo->page['id'] == self::$siteInfo->homePageId));
		} catch (Exception $ex) {
			self::exitWithError($ex->getMessage());
		}
		
		return $out;
	}
	
	/**
	 * Resolve category by request.
	 * @param StoreNavigation $request
	 * @return \Profis\SitePro\controller\StoreDataCategory|null
	 */
	public static function resolveCategoryByRequest(StoreNavigation $request) {
		$categoryKey = (isset($request->args[0]) && $request->args[0]) ? $request->args[0] : null;
		if ($categoryKey != 'all') {
			$categories = StoreData::getCategories();
			for ($i = 0, $c = count($categories); $i < $c; $i++) {
				if ('store-cat-'.$categories[$i]->id != $categoryKey) continue;
				return $categories[$i];
			}
		}
		return null;
	}
	
	/**
	 * Resolve item by request.
	 * @param StoreNavigation $request
	 * @return \Profis\SitePro\controller\StoreDataItem|null
	 */
	public static function resolveItemByRequest(StoreNavigation $request) {
		$itemKey = end($request->args);
		$items = StoreData::getItems();
		for ($i = 0, $c = count($items); $i < $c; $i++) {
			if ('store-item-'.$items[$i]->id != $itemKey && (!$items[$i]->alias || $items[$i]->alias !== $itemKey)) continue;
			return $items[$i];
		}
		return null;
	}

	/**
	 * Build store request object.
	 * @param SiteRequestInfo $reqInf 
	 * @param array $thisPage page definition as key value pair array
	 * @return StoreNavigation
	 */
	private static function handleStoreNav($reqInf) {
		$forcedHome = false;
		if (!$reqInf->page) {
			foreach (self::$siteInfo->pages as $li) {
				if ($li['id'] != self::$siteInfo->homePageId) continue;
				$reqInf->page = $li;
				$forcedHome = true;
				break;
			}
		}
		
		self::$storeNav = new StoreNavigation();
		self::$storeNav->args = $reqInf->urlArgs;
		self::$storeNav->lang = $reqInf->lang;
		self::$storeNav->defLang = self::$siteInfo->defLang;
		self::$storeNav->baseLang = self::$siteInfo->baseLang;
		self::$storeNav->basePath = self::$siteInfo->baseDir;
		self::$storeNav->baseUrl = preg_replace('#^[^\:]+\:\/\/[^\/]+(?:\/|$)#', '/', self::$siteInfo->baseUrl);
		if (isset(self::$initData->defaultStorePageId)) {
			$defStorePageId = is_array(self::$initData->defaultStorePageId) ? (isset(self::$initData->defaultStorePageId[self::$storeNav->lang]) ? self::$initData->defaultStorePageId[self::$storeNav->lang] : self::$initData->defaultStorePageId[""]) : self::$initData->defaultStorePageId;
			foreach (self::$siteInfo->pages as $li) {
				if ($li['id'] != $defStorePageId) continue;
				self::$storeNav->defaultStorePageRoute = ($li['id'] != self::$siteInfo->homePageId) ? tr_($li['alias']) : '';
				break;
			}
		}
		self::$storeNav->pageId = isset($reqInf->page['id']) ? $reqInf->page['id'] : null;
		$defLang = self::$siteInfo->defLang ? self::$siteInfo->defLang : self::$siteInfo->baseLang;
		self::$storeNav->pageBaseUrl = self::$storeNav->baseUrl
				.(($reqInf->lang == $defLang || !$reqInf->lang) ? '' : ($reqInf->lang.'/'))
				.(($reqInf->page && $reqInf->page['id'] != self::$siteInfo->homePageId) ? (tr_($reqInf->page['alias']).'/') : '');
		$pageCtrls = (isset($reqInf->page['controllers']) && is_array($reqInf->page['controllers'])) ? $reqInf->page['controllers'] : array();
		$categoryKey = (isset($reqInf->urlArgs[0]) && $reqInf->urlArgs[0]) ? $reqInf->urlArgs[0] : null;
		if ($categoryKey == 'store-invoice') {
			$api = new StoreInvoiceApi();
			$api->process(self::$storeNav); // process finishes with exit()
		}
		if (in_array('store', $pageCtrls)) {
			// If current page is store page use it as default store page for current page.
			if( isset(self::$initData->storeElementsLangsAndPages[self::$storeNav->getCurrLang()]) && in_array($reqInf->page['id'], self::$initData->storeElementsLangsAndPages[self::$storeNav->getCurrLang()]) )
				self::$storeNav->defaultStorePageRoute = ($reqInf->page['id'] != self::$siteInfo->homePageId) ? tr_($reqInf->page['alias']) : '';
			if ($categoryKey == 'cart') {
				self::$storeNav->isCart = true;
				$api = new StoreCartApi();
				$api->process(self::$storeNav);
				return self::$storeNav;
			}
			$itemKey = (isset($reqInf->urlArgs[1]) && $reqInf->urlArgs[1]) ? $reqInf->urlArgs[1] : null;
			self::$storeNav->category = self::resolveCategoryByRequest(self::$storeNav);
			if (self::$storeNav->category) {
				$forcedHome = false;
			} else {
				$categoryKey = null;
				$itemKey = (isset($reqInf->urlArgs[0]) && $reqInf->urlArgs[0]) ? $reqInf->urlArgs[0] : null;
			}
			if (isset($_SERVER['HTTP_REFERER']) && preg_match('#\/store\-cat\-(\d+)#', $_SERVER['HTTP_REFERER'], $m)) {
				self::$storeNav->lastSelectedCategory = StoreData::getCategory($m[1]);
			}
			self::$storeNav->item = self::resolveItemByRequest(self::$storeNav);
			if (self::$storeNav->item) {
				if( !empty(self::$storeNav->item->seoTitle) )
					$reqInf->title = (string)tr_(self::$storeNav->item->seoTitle);
				if( !empty(self::$storeNav->item->seoDescription) )
					$reqInf->description = (string)tr_(self::$storeNav->item->seoDescription);
				if( !empty(self::$storeNav->item->seoKeywords) )
					$reqInf->keywords = (string)tr_(self::$storeNav->item->seoKeywords);
				if( !empty(self::$storeNav->item->image) ) {
					if( is_string(self::$storeNav->item->image) )
						$reqInf->image = self::$storeNav->item->image;
					else if( !empty(self::$storeNav->item->image->zoom) )
						$reqInf->image = self::$storeNav->item->image->zoom;
					else if( !empty(self::$storeNav->item->image->src) )
						$reqInf->image = self::$storeNav->item->image->src;
					else if( !empty(self::$storeNav->item->image->image) )
						$reqInf->image = reset(self::$storeNav->item->image->image);
					else if( !empty(self::$storeNav->item->image->thumb) )
						$reqInf->image = reset(self::$storeNav->item->image->thumb);
				}
				$forcedHome = false;
			}
			self::$storeNav->categoryKey = $categoryKey;
			self::$storeNav->itemKey = $itemKey;
		}
		if ($forcedHome) $reqInf->page = null;
		return self::$storeNav;
	}
	
	private static function exitWithError($error) {
		echo $error;
		exit();
	}
	
	/**
	 * Parse form object data string
	 * @param array $formDef form definition (associative array)
	 * @return stdClass
	 */
	private static function parseFormObject($formDef) {
		$obj = ((isset($formDef['object']) && $formDef['object']) ? json_decode($formDef['object']) : null);
		if (!$obj || !is_object($obj)) $obj = null;
		return $obj;
	}
	
	/**
	 * Render form object data
	 * @param array $formDef form definition (associative array)
	 * @param array $formData form data (input by user)
	 * @return string
	 */
	public static function renderFormObject($formDef, $formData) {
		$obj = self::parseFormObject($formDef);
		$objData = self::parseFormObject($formData);
		if (isset($obj->name) && $obj->name && $objData && isset($objData->items) && is_array($objData->items) && !empty($objData->items)) {
			$tpl = (isset($objData->name) && $objData->name && strpos($objData->name, '{{') !== false) ? $objData->name : $obj->name;
			
			$return = '<table cellspacing="5" cellpadding="0">';
			foreach ($objData->items as $item) {
				$val1 = preg_replace_callback('#\{\{\#([^\{\}\n]+)\}\}(.+)\{\{/\1\}\}#', function($m) use ($item) {
					return (isset($item->{$m[1]}) && $item->{$m[1]}) ? $m[2] : '';
				}, $tpl);
				$return .= '<tr><td><strong>'.preg_replace_callback('#\{\{([^\{\}\n]+)\}\}#', function($m) use ($item) {
					return isset($item->{$m[1]}) ? $item->{$m[1]} : $m[0];
				}, $val1)."</td></tr>\n";
			}
			$return .= '</table>&nbsp;<br/>';
			$return .= '<table cellspacing="5" cellpadding="0">';
			if (isset($objData->subTotalPrice) && $objData->subTotalPrice) {
				$return .= "<tr><td><strong>Subtotal: </strong></td><td>".$objData->subTotalPrice."</td></tr>\n";
			}
			if (isset($objData->taxPrice) && $objData->taxPrice) {
				$return .= "<tr><td><strong>Tax: </strong></td><td>".$objData->taxPrice."</td></tr>\n";
			}
			if (isset($objData->shippingPrice) && $objData->shippingPrice) {
				$return .= "<tr><td><strong>Shipping: </strong></td><td>".$objData->shippingPrice."</td></tr>\n";
			}
			if (isset($objData->totalPrice) && $objData->totalPrice) {
				$return .= "<tr><td><strong>Total: </strong></td><td>".$objData->totalPrice."</td></tr>\n";
			}
			$return .= '</table>&nbsp;<br/>';
			return $return;
		} else {
			return (isset($obj->name) && $obj->name) ? '<p><strong>'.htmlspecialchars($obj->name).'</strong></p>' : '';
		}
	}
	
	/**
	 * Log sent form as store order
	 * @param array $formDef form definition (associative array)
	 * @param array $formData form data (input by user)
	 * @param boolean $status mail send status
	 */
	public static function logForm($formDef, $formData, $status) {
		$buyerData = array();
		foreach ($formDef['fields'] as $idx => $field) {
			if (isset($field['type']) && $field['type'] == 'file') continue;
			$buyerData[tr_($field['name'])] = $formData[$idx];
		}
		$obj = self::parseFormObject($formDef);
		$objData = self::parseFormObject($formData);
		$order = null; $price = null;
		if ($objData) {
			if (isset($objData->items) && is_array($objData->items) && !empty($objData->items)) {
				foreach ($objData->items as $item) {
					$order[] = str_replace(
							array('{{name}}', '{{sku}}', '{{price}}', '{{qty}}'),
							array($item->name, $item->sku, $item->priceStr, $item->qty),
							$obj->name
						);
				}
			}
			$price = (isset($objData->totalPrice) && $objData->totalPrice) ? $objData->totalPrice : null;
		} else {
			$order = (isset($obj->name) && $obj->name) ? $obj->name : null;
			$price = (isset($obj->price) && $obj->price) ? $obj->price : null;
		}
		StoreModuleOrder::create()
				->setBuyer(StoreModuleBuyer::create($buyerData))
				->setItems((is_array($order) ? $order : ($order ? array($order) : array())))
				->setPrice($price)
				->setType('inquiry')
				->setState(StoreModuleOrder::STATE_COMPLETE)
				->setCompleteDateTime(date('Y-m-d H:i:s'))
				->save();
		
		if ($status) {
			StoreCartApi::clearStoreCart();
		}
	}
	
	/**
	 * Respond with JSON.
	 * @param mixed $data data to respond with.
	 */
	public static function respondWithJson($data) {
		if (session_id()) session_write_close();
		header('Content-Type: application/json; charset=utf-8', true);
		echo json_encode($data);
		exit();
	}

	/**
	 * Respond with PDF file.
	 * @param TCPDF $pdf Instance of PDF file to respond with.
	 * @param string $fileName Name of the file for PDF to be downloaded as.
	 */
	public static function respondWithPDF(TCPDF $pdf, $fileName) {
		if (session_id()) session_write_close();
		$pdf->Output($fileName, "I");
		exit();
	}
}
