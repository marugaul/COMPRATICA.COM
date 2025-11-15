<?php

/**
 * Base element for store to be used by all other store elements.
 */
class StoreBaseElement {
	/** @var stdClass */
	protected $options;
	/** @var string */
	protected $viewPath;
	/** @var \Profis\SitePro\controller\StoreDataCurrency */
	protected $currency;
	protected $priceOptions;
	
	public function __construct($options) {
		$this->options = $options;
		$this->viewPath = dirname(__FILE__).'/view';
		$this->currency = StoreData::getCurrency();
		$this->priceOptions = StoreData::getPriceOptions();
	}

	/**
	 * Format price to price string.
	 * @param float $price price to format.
	 * @return string
	 */
	protected function formatPrice($price) {
		return StoreData::formatPrice($price, $this->priceOptions, $this->currency);
	}
	
	/**
	 * Escape PHP in content.
	 * @param string $content content to escape.
	 * @return string
	 */
	protected function noPhp($content) {
		if (!$content || !is_string($content)) return '';
		return str_replace(array('<?', '?>'), array('&lt;?', '?&gt;'), $content);
	}
	
	/**
	 * Get translated message.
	 * @param string $msg translation keyword.
	 * @return string
	 */
	protected function __($msg) {
		return StoreModule::__($msg);
	}
	
	/**
	 * Render template.
	 * @param string $templatePath path to template file.
	 * @param array $vars associative array with template variable values.
	 */
	protected function renderView($templatePath, $vars) {
		extract($vars);
		require $templatePath;
	}
	
	protected function getTemplateLnFile($request, $name) {
		if (is_file($this->viewPath."/{$name}_{$request->lang}.php")) {
			return $this->viewPath."/{$name}_{$request->lang}.php";
		} else if (is_file($this->viewPath."/{$name}.php")) {
			return $this->viewPath."/{$name}.php";
		}
		return null;
	}
	
}
