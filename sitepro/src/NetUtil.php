<?php

/**
 * Description of NetUtil
 */
class NetUtil {
	
	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_DELETE = 'DELETE';
	
	/** (type: boolean)*/
	const OPT_VERIFY_SSL = 'VERIFY_SSL';
	/** (type: boolean)*/
	const OPT_FOLLOW_LOCATION = 'FOLLOW_LOCATION';
	/** (type: string)*/
	const OPT_USER_AGENT = 'USER_AGENT';
	/** (type: boolean)*/
	const OPT_IGNORE_STATUS_CODE = 'IGNORE_STATUS_CODE';
	/** (type: int)*/
	const OPT_CONNECTION_TIMEOUT = 'CONNECTION_TIMEOUT';
	/** (type: int)*/
	const OPT_TIMEOUT = 'OPT_TIMEOUT';
	/** (type: string)*/
	const OPT_TO_FILE = 'TO_FILE';
	/** (type: resource)*/
	const OPT_TO_FILE_HANDLE = 'TO_FILE_HANDLE';
	/** (type: boolean)*/
	const OPT_PARAMS_AS_ARRAY = 'PARAMS_AS_ARRAY';
	/** (type: boolean)*/
	const OPT_GET_HEADERS = 'GET_HEADERS';
	/** (type: boolean)*/
	const OPT_NOBODY = 'NOBODY';
	/** (type: boolean)*/
	const OPT_ALLOW_CUSTOM_METHOD = 'ALLOW_CUSTOM_METHOD';
	/** (type: boolean)*/
	const OPT_RESOLVE_IPV4 = 'RESOLVE_IPV4';
	/** (type: int) */
	const OPT_AUTH_METHOD = 'AUTH_METHOD';
	/** (type: string) */
	const OPT_AUTH_USER = 'AUTH_USER';
	/** (type: string) */
	const OPT_AUTH_PASSWORD = 'AUTH_PASSWORD';

	const AUTH_METHOD_BASIC = CURLAUTH_BASIC;

	/**
	 * Make HTTP request to URL
	 * @param string $url URL to request
	 * @param array|string $params query of form parameters depending on method.
	 * @param string $method request method (one of METHOD_* constants)
	 * @param string[] $headers custom headers for request
	 * @param array $options key value pair array of additional options (available keys are OPT_* constants)
	 * @return stdClass return format { statuCode: ..., url: ..., headers: ..., body: ... }
	 * @throws ErrorException
	 */
	public static function request($url, $params = array(), $method = self::METHOD_GET, array $headers = array(), array $options = array()) {
		$finalUrl = ''.$url;
		$ch = curl_init();
		$opts = array();
		if ($method == self::METHOD_GET) {
			$opts[CURLOPT_HTTPGET] = 1;
			if (!empty($params)) {
				$urlInfo = parse_url($finalUrl);
				$prms = array();
				if (isset($urlInfo['query'])) parse_str($urlInfo['query'], $prms);
				$userpass = ((isset($urlInfo['user']) && $urlInfo['user']) ? (
						$urlInfo['user'].
						((isset($urlInfo['pass']) && $urlInfo['pass']) ? (':'.$urlInfo['pass']) : '')
					) : '');
				$finalUrl = ((isset($urlInfo['scheme']) && $urlInfo['scheme']) ? $urlInfo['scheme'] : 'http').'://'.
					($userpass ? ($userpass.'@') : '').
					((isset($urlInfo['host']) && $urlInfo['host']) ? $urlInfo['host'] : 'localhost').
					((isset($urlInfo['port']) && $urlInfo['port'] && $urlInfo['port'] != 80) ? (':'.$urlInfo['port']) : '').
					((isset($urlInfo['path']) && $urlInfo['path']) ? $urlInfo['path'] : '/').
					'?'.http_build_query(array_merge($prms, $params));
			}
		} else if ($method == self::METHOD_POST || $method == self::METHOD_PUT) {
			if ($method == self::METHOD_POST) $opts[CURLOPT_POST] = 1;
			if ($method == self::METHOD_PUT) $opts[CURLOPT_CUSTOMREQUEST] = $method;
			$opts[CURLOPT_POSTFIELDS] = (isset($options[self::OPT_PARAMS_AS_ARRAY]) && $options[self::OPT_PARAMS_AS_ARRAY])
					? $params : http_build_query($params);
		} else if (isset($options[self::OPT_ALLOW_CUSTOM_METHOD]) && $options[self::OPT_ALLOW_CUSTOM_METHOD]) {
			$opts[CURLOPT_CUSTOMREQUEST] = $method;
		} else {
			throw new ErrorException('Unrecognized method '.$method);
		}
		$getHeaders = isset($options[self::OPT_GET_HEADERS]) && $options[self::OPT_GET_HEADERS];
		$noBody = isset($options[self::OPT_NOBODY]) && $options[self::OPT_NOBODY];
		
		$opts[CURLOPT_URL] = str_replace(' ', '%20', $finalUrl);
		$opts[CURLOPT_RETURNTRANSFER] = true;
		if ($noBody) $opts[CURLOPT_NOBODY] = true;
		if ($getHeaders) $opts[CURLOPT_HEADER] = true;
		if (isset($options[self::OPT_TO_FILE_HANDLE]) && $options[self::OPT_TO_FILE_HANDLE] !== false) {
			$opts[CURLOPT_FILE] = $options[self::OPT_TO_FILE_HANDLE];
		} else if (isset($options[self::OPT_TO_FILE]) && $options[self::OPT_TO_FILE]) {
			if ($options[self::OPT_TO_FILE] == 'php://output') {
				// Note: using fopen on this stream fails with cURL.
				unset($opts[CURLOPT_RETURNTRANSFER]);
			} else if (($fileHandle = fopen($options[self::OPT_TO_FILE], 'wb')) !== false) {
				$opts[CURLOPT_FILE] = $fileHandle;
			}
		}
		if (isset($options[self::OPT_RESOLVE_IPV4]) && $options[self::OPT_RESOLVE_IPV4] && defined('CURLOPT_IPRESOLVE')) {
			$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		}
		$opts[CURLOPT_CONNECTTIMEOUT] = (isset($options[self::OPT_CONNECTION_TIMEOUT]) ? intval($options[self::OPT_CONNECTION_TIMEOUT]) : 300);
		if (isset($options[self::OPT_TIMEOUT])) $opts[CURLOPT_TIMEOUT] = intval($options[self::OPT_TIMEOUT]);
		$opts[CURLOPT_USERAGENT] = (isset($options[self::OPT_USER_AGENT]) ? $options[self::OPT_USER_AGENT] : 'Mozilla/5.0 (compatible; cURL)');
		$headerMap = array();
		foreach ($headers as $header) {
			list($headerName) = explode(': ', $header, 2);
			if ($headerName) $headerMap[strtolower(trim($headerName))] = 1;
		}
		$headerArr = array();
		if (!isset($headerMap['accept'])) $headerArr[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		if (!isset($headerMap['connection'])) $headerArr[] = 'Connection: Close';
		$opts[CURLOPT_HTTPHEADER] = array_merge($headerArr, $headers);
		$opts[CURLOPT_SSL_VERIFYPEER] = (isset($options[self::OPT_VERIFY_SSL]) ? ($options[self::OPT_VERIFY_SSL] ? true : false) : false);
		$opts[CURLOPT_SSL_VERIFYHOST] = (isset($options[self::OPT_VERIFY_SSL]) ? ($options[self::OPT_VERIFY_SSL] ? 2 : false) : false);
		if (isset($options[self::OPT_FOLLOW_LOCATION]) && $options[self::OPT_FOLLOW_LOCATION]) {
			$opts[CURLOPT_FOLLOWLOCATION] = true;
			$opts[CURLOPT_AUTOREFERER] = true;
			$opts[CURLOPT_MAXREDIRS] = 3;
		} else {
			$opts[CURLOPT_FOLLOWLOCATION] = false;
			$opts[CURLOPT_AUTOREFERER] = false;
		}
		if (isset($options[self::OPT_AUTH_METHOD]))
			$opts[CURLOPT_HTTPAUTH] = $options[self::OPT_AUTH_METHOD];
		if (isset($options[self::OPT_AUTH_USER]) || isset($options[self::OPT_AUTH_PASSWORD]))
			$opts[CURLOPT_USERPWD] = (isset($options[self::OPT_AUTH_USER]) ?$options[self::OPT_AUTH_USER] : "") . ":" . (isset($options[self::OPT_AUTH_PASSWORD]) ?$options[self::OPT_AUTH_PASSWORD] : "");

		curl_setopt_array($ch, $opts);
		$rdata = curl_exec($ch);
		if ($getHeaders) {
			if ($noBody) {
				$headersRaw = $rdata; $r = null;
			} else {
				$parts = explode("\r\n\r\n", $rdata, 2);
				if (count($parts) > 1) {
					$headersRaw = $parts[0];
					$r = $parts[1];
				} else {
					$headersRaw = '';
					$r = $parts[0];
				}
			}
			$h = array(); $headerFirst = true;
			$headersList = explode("\r\n", $headersRaw);
			foreach ($headersList as $headerRaw) {
				if (!$headerRaw) continue;
				if ($headerFirst) {
					$headerFirst = false;
					list($hProto, $hStatus) = explode(' ', $headerRaw, 3);
					$h['_proto_'] = $hProto;
					$h['_status_'] = intval($hStatus);
					continue;
				}
				list($headerKey, $headerVal) = explode(': ', $headerRaw, 2);
				if (!$headerKey) continue;
				$h[strtolower($headerKey)] = $headerVal;
			}
		} else {
			$h = null; $r = $rdata;
		}
		$errNo = curl_errno($ch);
		$errMsg = curl_error($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if (isset($fileHandle) && $fileHandle !== false) fclose($fileHandle);
		if ($errNo) throw new ErrorException($errMsg, $errNo);
		if (!isset($options[self::OPT_IGNORE_STATUS_CODE]) || !$options[self::OPT_IGNORE_STATUS_CODE]) {
			if ($status != 200) throw new ErrorException('Status code '.$status);
		}
		
		return (object) array('statusCode' => $status, 'url' => $finalUrl, 'headers' => $h, 'body' => $r);
	}
	
	/**
	 * Download file from URL and save to file.
	 * @param string $url URL to download from.
	 * @param string $targetPath path to save downloaded file.
	 * @param array $params query of form parameters depending on method.
	 * @param string $method request method (one of METHOD_* constants).
	 * @param string[] $headers custom headers for request.
	 * @param array $options key value pair array of additional options (available keys are OPT_* constants).
	 * @return stdClass return format { statuCode: ..., url: ..., headers: ..., body: ... }
	 * @throws ErrorException
	 */
	public static function download($url, $targetPath, array $params = array(), $method = self::METHOD_GET, array $headers = array(), array $options = array()) {
		if (!is_array($options)) $options = array();
		if ($targetPath) $options[self::OPT_TO_FILE] = $targetPath;
		return self::request($url, $params, $method, $headers, $options);
	}
	
	/**
	 * Get contents from URL.
	 * @param string $url URL to get contents from.
	 * @param array $params query of form parameters depending on method.
	 * @param string $method request method (one of METHOD_* constants).
	 * @param string[] $headers custom headers for request.
	 * @param array $options key value pair array of additional options (available keys are OPT_* constants).
	 * @return string|null contents as string or null on error.
	 */
	public static function getContents($url, array $params = array(), $method = self::METHOD_GET, array $headers = array(), array $options = array()) {
		try {
			$res = self::request($url, $params, $method, $headers, $options);
			if (isset($res->body)) return $res->body;
		} catch (ErrorException $ex) {}
		return null;
	}
	
	/**
	 * Get headers from URL.
	 * @param string $url URL to get headers from.
	 * @param array $params query of form parameters depending on method.
	 * @param string $method request method (one of METHOD_* constants).
	 * @param string[] $headers custom headers for request.
	 * @param array $options key value pair array of additional options (available keys are OPT_* constants).
	 * @return array|null headers as key value pair array or null on error.
	 */
	public static function getHeaders($url, array $params = array(), $method = self::METHOD_GET, array $headers = array(), array $options = array()) {
		try {
			if (!is_array($options)) $options = array();
			$options[self::OPT_GET_HEADERS] = true;
			$options[self::OPT_NOBODY] = true;
			$options[self::OPT_IGNORE_STATUS_CODE] = true;
			$res = self::request($url, $params, $method, $headers, $options);
			if (isset($res->headers)) return $res->headers;
		} catch (ErrorException $ex) {}
		return null;
	}
	
	/**
	 * Checks if IP is local (192.168.*.* or 10.*.*.* or 100.[64-127].*.* or 172.[16-32].*.* or fc00::/7 or fe80::/10
	 * @return boolean
	 */
	public static function isIpLocal($ip) {
		return preg_match('#^(?:192\.168\.|10\.|100\.(?:6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\.|172\.(?:1[6-9]|2[0-9]|3[01])\.|f[cd][0-9a-f]{2}:|fe[89ab][0-9a-f]:)#i', $ip);
	}
	
	/**
	 * Checks if IP is loopback (127.* or ::1)
	 * @return boolean
	 */
	public static function isIpLoopBack($ip) {
		return preg_match('#^(?:127\\..*|::1)$#', $ip);
	}
}
