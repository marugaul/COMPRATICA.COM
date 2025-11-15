<?php

use PHPMailer\PHPMailer\PHPMailer;

function getRequestUri($baseUrl) {
	$nh = preg_replace('#/\./#', '/', $baseUrl);
	$nh = preg_replace('#^http[s]*://[^/]+/#i', '/', $nh);
	$nh = preg_replace('#/[^/]+/\.\./#i', '/', $nh);
	if (isset($_SERVER['HTTP_X_REQUEST_URI'])) {
		$ru = trim(urldecode($_SERVER['HTTP_X_REQUEST_URI']));
	} else if (isset($_SERVER['REQUEST_URI'])) {
		$ru = trim(urldecode($_SERVER['REQUEST_URI']));
	} else {
		$ru = '/';
	}
	
	// Note: fix issue with MS IIS server.
	if ($ru == '/index.php' && !isset($_GET['route'])) $ru = '/';
	$ru = preg_replace('#'.preg_quote($nh).'#i', '', $ru, 1);
	list($ru) = explode('?', $ru, 2);
	return $ru;
}

function parse_uri(SiteInfo $siteInfo, SiteRequestInfo $requestInfo) {
	$ru = $requestInfo->requestUri;
	if (isset($_GET['route'])) {
		$ru = trim($_GET['route']);
	}
	$ru = preg_split('#[\ \t]*[/]+[\ \t]*#i', $ru, -1, PREG_SPLIT_NO_EMPTY);
	$ru = array_map('trim', $ru);
	
	$cusr = null;
	if (strpos(ini_get('disable_functions'), 'get_current_user') === false) {
		$cusr = get_current_user();
	}
	if ($cusr && !empty($ru) && ($ru[0] == ('~'.$cusr) || $ru[0] == ($cusr.'~'))) {
		array_shift($ru);
	}
	
	if (isset($ru[0]) && preg_match('#^[a-z]{2}-[A-Z]{2}$#', $ru[0])) {
		array_shift($ru);
	}
	
	if (!count($ru)) {
		foreach ($siteInfo->pages as $idx => $pi) {
			if ($siteInfo->homePageId == $pi['id']) return array($idx, $siteInfo->defLang, array(), null);
		}
		return array($siteInfo->homePageId, $siteInfo->defLang, array(), null);
	}
	
	$show_comments = false;
	
	if (false) {
		if ($ru[0] == 'news') {
			$pageIdx = getPageIndexById(isset($ru[1]) ? intval($ru[1]) : null, $siteInfo);
			$route = array_shift($ru);
			return array($pageIdx, $siteInfo->defLang, $ru, $route);
		}
		else if ($ru[0] == 'blog') {
			$pageIdx = getPageIndexById(isset($ru[1]) ? intval($ru[1]) : null, $siteInfo);
			$show_comments = true;
			$route = array_shift($ru);
			return array($pageIdx, $siteInfo->defLang, $ru, $route);
		}
	}
	
	$defLang = ($siteInfo->defLang ? $siteInfo->defLang : null);
	$lang = $defLang; $langArg = null;
	if ($siteInfo->langs && is_array($siteInfo->langs) && isset($siteInfo->langs[$ru[0]])) {
		$langArg = array_shift($ru);
		$lang = $langArg;
	}
	$ru_ = array_shift($ru);
	
	if (!$ru_) {
		foreach ($siteInfo->pages as $idx => $pi) {
			if ($siteInfo->homePageId == $pi['id']) {
				if ($langArg && $langArg == $defLang) {
					header('Location: '.$siteInfo->baseUrl . getPageUri($pi['id'], $defLang, $siteInfo), true, 301);
					exit();
				}
				return array($idx, $lang, $ru, null);
			}
		}
		return array($siteInfo->homePageId, $lang, $ru, null);
	}
	
	$ruBak = array_merge(array($ru_), $ru); $ruBakOther = array();
	while (!empty($ruBak)) {
		$ru_ = implode('/', $ruBak);
		foreach ($siteInfo->pages as $idx => $pi) {
			if (is_array($pi['alias'])) {
				if ($lang && isset($pi['alias'][$lang]) && $ru_ == $pi['alias'][$lang]) {
					if ($langArg && $langArg == $defLang) {
						header('Location: '.$siteInfo->baseUrl . getPageUri($pi['id'], $defLang, $siteInfo), true, 301);
						exit();
					}
					return array($idx, $lang, $ruBakOther, $ru_);
				}
			} else if ($ru_ == $pi['alias']) {
				return array($idx, $lang, $ruBakOther, $ru_);
			}
		}
		array_unshift($ruBakOther, array_pop($ruBak));
	}
	
	$hasMbstring = function_exists('mb_strtolower');
	foreach ($siteInfo->pages as $idx => $pi) {
		if (is_array($pi['alias'])) {
			if ($lang && isset($pi['alias'][$lang]) && ($lnAlias = $pi['alias'][$lang])) {
				if ($hasMbstring && mb_strtolower($ru_) == mb_strtolower($lnAlias) || !$hasMbstring && strtolower($ru_) == strtolower($lnAlias)) {
					header('Location: '.$siteInfo->baseUrl . getPageUri($pi['id'], $lang, $siteInfo), true, 301);
					exit();
				}
			}
		} else {
			if ($hasMbstring && mb_strtolower($ru_) == mb_strtolower($pi['alias']) || !$hasMbstring && strtolower($ru_) == strtolower($pi['alias'])) {
				header('Location: '.$siteInfo->baseUrl . getPageUri($pi['id'], $lang, $siteInfo), true, 301);
				exit();
			}
		}
	}
	
	foreach ($siteInfo->pages as $idx => $pi) {
		if ($ru_ == $pi['id']) {
			header('Location: '.$siteInfo->baseUrl . getPageUri($pi['id'], $lang, $siteInfo), true, 301);
			exit();
		}
	}
	
	return array(-1, $lang, array_merge(array($ru_), $ru), null);
}

function handleTrailingSlashRedirect(SiteInfo $siteInfo, SiteRequestInfo $requestInfo) {
	if (!$requestInfo->page) return;
	$ru = $requestInfo->requestUri;
	if ($ru && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET' && !file_get_contents('php://input')) {
		$qs = getQueryString();
		$hasTrailingSlash = (substr(ltrim($ru, '/'), -1) == '/');
		if ($siteInfo->useTrailingSlashes && !$hasTrailingSlash) {
			header('Location: '.$siteInfo->baseUrl . $ru.'/'.($qs ? '?'.$qs : ''), true, 301);
			exit();
		} else if (!$siteInfo->useTrailingSlashes && $hasTrailingSlash) {
			header('Location: '.$siteInfo->baseUrl . rtrim($ru, '/').($qs ? '?'.$qs : ''), true, 301);
			exit();
		}
	}
}

/** @param int $pageId */
function getPageIndexById($pageId, SiteInfo $siteInfo) {
	foreach ($siteInfo->pages as $id => $pi) {
		if ($pageId == $pi['id']) return $id;
	}
	return null;
}

function getQueryString() {
	$qs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
	if (!$qs) {
		$parts = explode('?', (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), 2);
		if (isset($parts[1]) && $parts[1]) {
			$qs = $parts[1];
		}
	}
	return $qs;
}

function getCurrUrl($cutQuery = false, $forceProto = null) {
	$currIsHttps = isHttps();
	$useHttps = $forceProto ? ($forceProto == 'https') : $currIsHttps;
	list($host) = explode(':', (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'), 2);
	$url = ($useHttps ? 'https' : 'http').'://'.$host;
	$port = getServerPort();
	if ($currIsHttps != $useHttps && $useHttps && SiteModule::$siteInfo->port) {
		$port = SiteModule::$siteInfo->port;
	}
	if ($port && $port != 80 && ($currIsHttps && $port != 443 || !$currIsHttps)) {
		$url .= ':'.$port;
	}
	$url .= '/';
	list($uri) = explode('?', (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''), 2);
	if (!$cutQuery) {
		$qs = getQueryString();
		$uri .= $qs ? '?'.$qs : '';
	}
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
		$hasTrailingSlash = (substr(ltrim($uri, '/'), -1) == '/');
		if ($uri != '/' && $hasTrailingSlash && !SiteModule::$siteInfo->useTrailingSlashes) {
			$uri = rtrim($uri, '/');
		} else if (!$hasTrailingSlash && SiteModule::$siteInfo->useTrailingSlashes) {
			$uri = $uri.'/';
		}
	}
	return $url . ltrim($uri, '/');
}

function getOrigin() {
	$baseUrl = getBaseUrl();
	$parts = parse_url($baseUrl);
	return $parts['scheme'].'://'.$parts['host'].
			((isset($parts['port']) && $parts['port']) ? ':'.$parts['port'] : '').'/';
			
}

function getBaseUrl() {
	if (SiteModule::$siteInfo->baseUrl != '/') {
		return SiteModule::$siteInfo->baseUrl;
	}
	$isHttps = isHttps();
	list($host) = explode(':', (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'), 2);
	$url = ($isHttps ? 'https' : 'http').'://'.$host;
	$port = SiteModule::$siteInfo->port;
	if (!$port && ($serverPort = getServerPort())) {
		$port = $serverPort;
	}
	if ($port && $port != 443 && $port != 80) {
		$url .= ':'.$port;
	}
	$url .= '/';
	if (!SiteModule::$siteInfo->modRewrite && SiteModule::$siteInfo->pathPrefix) {
		$url .= SiteModule::$siteInfo->pathPrefix.'/';
	}
	return $url;
}

function randomHash($len = 17, $onlyDigits = false) {
	$str = ''; $chars = '0123456789'.($onlyDigits ? '' : 'ABCDEFGHJKLMNOPQRSTUVWXZ');
	for ($i = 0; $i < $len; $i++) {
		$min = ($onlyDigits && $i == 0) ? 1 : 0; $max = strlen($chars) - 1;
		$str .= $chars[rand($min, $max)];
	}
	return $str;
}

function getPreferredLang() {
	return SiteModule::getPreferredLang();
}

function getPageUri($pageId, $lang, SiteInfo $siteInfo) {
	$uri = '';
	$isAnchor = false;
	foreach ($siteInfo->pages as $pi) {
		if ($pi['id'] != $pageId) continue;
		if (is_array($pi['alias'])) {
			$useLang = null;
			if ($lang && isset($pi['alias'][$lang])) {
				$useLang = $lang;
			} else if ($siteInfo->defLang && isset($pi['alias'][$siteInfo->defLang])) {
				$useLang = $siteInfo->defLang;
			}
			if ($useLang) {
				$isAnchor = (strpos($pi['alias'][$useLang], '#') !== false);
				$hasRoutePfx = (preg_match('#^\.\.\/\?route=#', $pi['alias'][$useLang]));
				$uri = '';
				if (!$siteInfo->modRewrite && !$hasRoutePfx)
					$uri .= '../?route=';
				if (!$isAnchor && $useLang != $siteInfo->defLang)
					$uri .= $useLang.'/';
				$uri .= $pi['alias'][$useLang];
				$uri = trim($uri, '/');
				if (!$isAnchor && $uri) {
					$uri .= '/';
				}
				break;
			}
		} else {
			$uri = (!$siteInfo->modRewrite && !preg_match('#^\.\.\/\?route=#', $pi['alias']) ? '../?route=' : '').
					$pi['alias'] . ($pi['alias'] ? '/' : '');
		}
	}
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
		$hasTrailingSlash = (substr(ltrim($uri, '/'), -1) == '/');
		if ($uri != '/' && $hasTrailingSlash && !SiteModule::$siteInfo->useTrailingSlashes) {
			$uri = rtrim($uri, '/');
		} else if (!$hasTrailingSlash && SiteModule::$siteInfo->useTrailingSlashes && !$isAnchor) {
			$uri = $uri.'/';
		}
	}
	return ltrim($uri, '/');
}

function handleComments($pageId, SiteInfo $siteInfo) {
	$post = $_POST;
	if (isset($post['postComment'])) {
		// message field is used as "Honney Pot" trap
		if ($pageId && isset($post['message']) && !$post['message']) {
			$file = dirname(__FILE__).'/'.$pageId.'.comments.dat';
			$dataStr = is_file($file) ? file_get_contents($file) : null;
			$data = $dataStr ? json_decode($dataStr) : array();
			if (trim($post['text'])) {
				$data[] = $comment = array(
					'date' => date('Y-m-d'),
					'time' => date('H:i'),
					'user' => ($post['name'] ? $post['name'] : 'anonymous'),
					'text' => substr($post['text'], 0, 200)
				);
				file_put_contents($file, json_encode($data));
				
				// post info to builder
				if (function_exists('curl_init')) {
					_http_get($siteInfo->commentsCallback, array(
						'key'	=> $siteInfo->userKey,
						'hash'	=> md5($siteInfo->userKey.$siteInfo->userHash),
						'id'	=> $pageId,
						'date'	=> base64_encode($comment['date']),
						'time'	=> base64_encode($comment['time']),
						'name'	=> base64_encode($comment['user']),
						'message'=> base64_encode($comment['text'])
					));
				}				
			}
		}
		list($ruA) = explode('?', (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
		list($ru) = explode('#', $ruA);
		header('Location: '.$ru.'#wb_comment_box');
		exit();
	}
}

function renderComments($pageId = null) {
	$comments = array();
	$dataFile = dirname(__FILE__).'/'.$pageId.'.comments.dat';
	$dataStr = is_file($dataFile) ? file_get_contents($dataFile) : null;
	$data = $dataStr ? json_decode($dataStr) : null;
	if ($data && is_array($data)) {
		$comments = array_reverse($data);
	}
	include dirname(__FILE__).'/comments.tpl.php';
}

function tr_($value, $ln = null, $default = null) {
	if (!$ln) $ln = SiteModule::$lang;
	if (!$ln) $ln = SiteModule::$siteInfo->defLang;
	if (!$ln) $ln = SiteModule::$siteInfo->baseLang;
	if (is_array($value)) {
		if ($ln && isset($value[$ln]) && $value[$ln] !== '') {
			return $value[$ln];
		} else {
			foreach ($value as $v) { return $v; }
		}
	} else if (is_object($value)) {
		if ($ln && isset($value->{$ln}) && $value->{$ln} !== '') {
			return $value->{$ln};
		} else {
			foreach ($value as $v) { return $v; }
		}
	}
	return ($value) ? $value : $default;
}

function popSessionOrGlobalVar($key) {
	if (!$key) return null;
	if (session_id() && isset($_SESSION[$key])) {
		$var = $_SESSION[$key];
		unset($_SESSION[$key]);
		return $var;
	} else {
		global $$key;
		if ($$key) return $$key;
	}
	return null;
}

function getServerPort() {
	$port = (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && is_numeric($_SERVER['HTTP_X_FORWARDED_PORT']))
		? intval($_SERVER['HTTP_X_FORWARDED_PORT'])
		: 0;
	if (!$port && isset($_SERVER['SERVER_PORT']) && is_numeric($_SERVER['SERVER_PORT'])) {
		$port = intval($_SERVER['SERVER_PORT']);
	}
	return $port;
}

function getRemoteAddr() {
	$remoteAddr = (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
	if ($remoteAddr != '127.0.0.1' && $remoteAddr != '::1') return $remoteAddr;
	
	$forwarded = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR']))
		? $_SERVER['HTTP_X_FORWARDED_FOR']
		: '';
	$ips = explode(',', trim($forwarded, " \t\r\n\0\x0B,"));
	return ($ip = trim($ips[count($ips) - 1])) ? $ip : $remoteAddr;
}

function isHttps() {
	return (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
			|| isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) && $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] == 'https'
			|| isset($_SERVER['HTTP_X_REMOTE_PROTO']) && $_SERVER['HTTP_X_REMOTE_PROTO'] == 'https'
			|| isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
			|| isset($_SERVER['HTTP_X_HTTPS']) && ($_SERVER['HTTP_X_HTTPS'] == 'on' || $_SERVER['HTTP_X_HTTPS'] == 1)
			|| isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443
			|| isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'
			|| isset($_SERVER['SERVER_PROTOCOL']) && preg_match('#https#i', $_SERVER['SERVER_PROTOCOL'])
			|| isset($_SERVER['HTTP_CF_VISITOR']) && preg_match('#https#i', $_SERVER['HTTP_CF_VISITOR'])
			|| isset($_SERVER['HTTP_SSL']) && $_SERVER['HTTP_SSL']);
}

function handleForms($page_id, SiteInfo $siteInfo) {
	global $post;
	$forms = $siteInfo->forms;
	// check to ensure that all parameters are ok as well as protect from bots
	// and hackers
	$post = $_POST;
	if (!isset($post['wb_form_id'])
		|| $post['message'] !== ''
		|| !isset($forms)
		|| !is_array($forms)
		|| !isset($page_id)
		|| !(isset($forms[$page_id]) || isset($forms['blog']) || isset($forms['store']))
		|| !(isset($forms[$page_id][$post['wb_form_id']]) || isset($forms['blog'][$post['wb_form_id']]) || isset($forms['store'][$post['wb_form_id']]))
		|| !(isset($forms[$page_id][$post['wb_form_id']]['fields']) || isset($forms['blog'][$post['wb_form_id']]['fields']) || isset($forms['store'][$post['wb_form_id']]['fields']))
		|| isset($post['forms'])
		|| isset($_GET['forms'])
	) return;

	$form = isset($forms[$page_id][$post['wb_form_id']])
		? $forms[$page_id][$post['wb_form_id']] : (isset($forms['store'][$post['wb_form_id']])
			? $forms['store'][$post['wb_form_id']] : (isset($forms['blog'][$post['wb_form_id']])
				? $forms['blog'][$post['wb_form_id']] : null));
	if (!$form) return;
	
	$apiPostUrl = (isset($form['postUrl']) && $form['postUrl']) ? $form['postUrl'] : null;
	$webhookUrl = (isset($form['webhookUrl']) && $form['webhookUrl']) ? $form['webhookUrl'] : null;
	$brandId = (isset($form['brandId']) && $form['brandId']) ? $form['brandId'] : null;
	
	try {
		global $wb_form_send_state, $wb_form_send_success, $wb_form_id, $formErrors, $wb_form_popup_mode, $wb_target_origin;
		
		$formErrors = new stdClass();
		$wb_form_send_state = false;
		$wb_form_send_success = false;
		$wb_form_id = $post['wb_form_id'];
		$wb_form_popup_mode = (isset($post['wb_popup_mode']) && $post['wb_popup_mode'] == 1);
		$wb_target_origin = getOrigin();
		
		$wb_form_sending_failed = SiteModule::__('Form sending failed');

		if (isset($form['recSiteKey']) && $form['recSiteKey'] && isset($form['recSecretKey']) && $form['recSecretKey']) {
			// reCAPTCHA is enabled
			$recResp = (isset($post['g-recaptcha-response']) ? $post['g-recaptcha-response'] : '');
			$remoteAddr = getRemoteAddr();
			$respStr = $recResp ? _http_get('https://www.google.com/recaptcha/api/siteverify', array(
				'secret' => $form['recSecretKey'],
				'response' => $recResp,
				'remoteip' => $remoteAddr ? $remoteAddr : null
			)) : null;
			$resp = $respStr ? json_decode($respStr) : null;
			if (!$resp || !isset($resp->success) || !$resp->success) {
				throw new ErrorException(SiteModule::__('Form was not sent, are you a robot?'));
			}
		}

		$maxFileSizeTotalMB = isset($form["maxFileSizeTotal"]) ? intval($form["maxFileSizeTotal"]) : 0;
		if( !$maxFileSizeTotalMB )
			$maxFileSizeTotalMB = 2;
		$maxFileSizeTotal = $maxFileSizeTotalMB * 1024 * 1024;
		$attachmentsDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . "forms_attachments";

		$fields = $form['fields'];
		$email_list = array_map('trim', preg_split('#[;,]#', $form['email'], -1, PREG_SPLIT_NO_EMPTY));
		$mail_to = array();
		foreach ($email_list as $eml) {
			if (($m = is_mail($eml))) { $mail_to[] = $m; }
		}
		$mail_from = reset($mail_to);
		$mail_from_name = null;

		$fileSizeTotal = 0;
		$data = Array();
		foreach($fields as $idx => $field) {
			$fieldName = "wb_input_$idx";
			$required = isset($field["required"]) ? $field["required"] : ($field["type"] != "file");
			if( $field["type"] === "file" ) {
				if( !isset($_FILES[$fieldName]) )
					continue;
				$err = null;
				foreach( $_FILES[$fieldName]["tmp_name"] as $fileIdx => $fileTmpName) {
					if( !$fileTmpName )
						continue;
					$fileName = $_FILES[$fieldName]["name"][$fileIdx];
					$fileSize = $_FILES[$fieldName]["size"][$fileIdx];
					$fileError = $_FILES[$fieldName]["error"][$fileIdx];
					if( $fileSize > $maxFileSizeTotal || $fileError == UPLOAD_ERR_INI_SIZE || $fileError == UPLOAD_ERR_FORM_SIZE ) {
						if( !$err )
							$err = "";
						$err .= sprintf(SiteModule::__('File %s is too big'), '"'.$fileName.'"')."\n";
					}
					else if( $fileError != 0 ) {
						if( !$err )
							$err = "";
						$err .= sprintf(SiteModule::__('File %s could not be uploaded for sending'), '"'.$fileName.'"')."\n";
					}
					else {
						$fileSizeTotal += $fileSize;
					}
				}
				if ($err) throw new ErrorException($err);
				if( $fileSizeTotal > $maxFileSizeTotal ) {
					throw new ErrorException(sprintf(SiteModule::__("Total size of attachments must not exceed %s MB"), $maxFileSizeTotalMB));
				}
				if( !$fileSizeTotal && $required )
					$formErrors->required[] = $fieldName;
			}
			else if( $field["type"] == "checkbox" ) {
				if (!isset($post[$fieldName])) {
					if( $required )
						$formErrors->required[] = $fieldName;
					$data[$idx] = false;
				}
				else
					$data[$idx] = true;
			}
			else {
				if (!isset($post[$fieldName])) {
					error_log("[Form error]: Field $fieldName is not present");
					throw new ErrorException($wb_form_sending_failed." (6): ".sprintf(SiteModule::__('Field %s is not present'), $fieldName));
				}
				$max_len = ($field["type"]=="textarea")?65536:1024; // 65 kilobytes max for textarea and 1024 for other
				$valueRaw = $post[$fieldName];
				if (empty($valueRaw) && strlen($valueRaw) == 0 && $required) {
					if (!isset($formErrors->required)) $formErrors->required = array();
					$formErrors->required[] = $fieldName;
					$data[$idx] = $value = "";
				}
				else {
					$value = (strlen($valueRaw) > 0) ? substr(htmlspecialchars($valueRaw), 0, $max_len) : htmlspecialchars($valueRaw);
					if ($field["type"] == "select") {
						$options = explode(";", html_entity_decode(tr_($field["options"])));
						$data[$idx] = trim($options[intval($value)]);
					} else
						$data[$idx] = $value;
				}
				if (($eml = is_mail($value))) $mail_from = $eml;
			}
		}

		if (isset($post['object']) && $post['object'])
			$data['object'] = $post['object'];
		
		$formErrors_t = (array) $formErrors;
		if (!empty($formErrors_t)) {
			throw new ErrorException($wb_form_sending_failed.' (7)');
		}
		
		if ($apiPostUrl || $webhookUrl) {
			$postData = array();
			foreach ($fields as $idx => $field) {
				$fieldName = "wb_input_$idx";
				if( $field["type"] === "file" ) {
					if( !isset($_FILES[$fieldName]) )
						continue;
					foreach( $_FILES[$fieldName]["tmp_name"] as $fileIdx => $fileTmpName) {
						if( !$fileTmpName )
							continue;
						$fileName = $_FILES[$fieldName]["name"][$fileIdx];
						$fileType = $_FILES[$fieldName]["type"][$fileIdx];
						if (class_exists('CURLFile')) {
							$postData["file_$fileIdx"] = new CURLFile($fileTmpName, $fileType, $fileName);
						} else {
							$postData["file_$fileIdx"] = '@'.$fileTmpName.';filename='.$fileName.';type='.$fileType;
						}
					}
				}
				else {
					$name = tr_($field["name"]);
					$value = $data[$idx];
					$postData[$name] = $value;
				}
			}
			if (!class_exists('NetUtil')) {
				$netUtilFile = __DIR__.'/src/NetUtil.php';
				if (is_file($netUtilFile)) require_once $netUtilFile;
			}
			if (!class_exists('NetUtil')) {
				throw new ErrorException($wb_form_sending_failed.' (9)');
			}
			if ($apiPostUrl) {
				try {
					$resp = NetUtil::request($apiPostUrl, $postData, NetUtil::METHOD_POST, array('Content-type: multipart/form-data'),
							array(NetUtil::OPT_PARAMS_AS_ARRAY => true, NetUtil::OPT_IGNORE_STATUS_CODE => true));
					$error = null;
				} catch (ErrorException $ex) {
					$resp = null;
					$error = $ex->getMessage();
				}
				if ($resp->statusCode == 200) {
					$wb_form_send_state = empty($form['sentMessage']) ? 'Form was sent.' : tr_($form['sentMessage']);
					$wb_form_send_success = true;
				} else {
					error_log('[Form sending error]: Failed to submit to URL: response code('.$resp->statusCode.')');
					throw new ErrorException($wb_form_sending_failed.' (8)'.($error ? ': '.$error : ''));
				}
			}
			if ($webhookUrl) {
				if ($brandId) $postData['_brandId_'] = $brandId;
				try {
					$resp = NetUtil::request($apiPostUrl, $postData, NetUtil::METHOD_POST, array('Content-type: multipart/form-data'),
							array(NetUtil::OPT_PARAMS_AS_ARRAY => true, NetUtil::OPT_IGNORE_STATUS_CODE => true));
				} catch (ErrorException $ex) {}
			}
		}
		
		if (!$apiPostUrl) {
			if (!$mail_from) $mail_from = reset($mail_to);

			if (empty($mail_to)) {
				error_log('[Form configuration error]: receiver not specified');
				throw new ErrorException($wb_form_sending_failed.' (5): '.SiteModule::__('Receiver not specified'));
			}

			requirePHPMailer();
			$mailer = new PHPMailer();
			$mailer->setLanguage(getPreferredLang());

			// cleanup old attachments that were not removed due to unknown reasons
			if( is_dir($attachmentsDir) ) {
				$dir = opendir($attachmentsDir);
				if( $dir ) {
					while( $f = readdir($dir) ) {
						if( $f == "." || $f == ".." || $f == ".htaccess" )
							continue;
						$fp = $attachmentsDir . DIRECTORY_SEPARATOR . $f;
						if( !is_file($fp) )
							continue;
						if( filemtime($fp) < time() - 86400 )
							unlink($fp);
					}
					closedir($dir);
				}
			}

			$movedFiles = array();
			foreach($fields as $idx => $field) {
				$fieldName = "wb_input_$idx";
				if( $field["type"] === "file" ) {
					if( !isset($_FILES[$fieldName]) )
						continue;
					if( !file_exists($attachmentsDir) ) {
						if( !mkdir($attachmentsDir, 0700) ) {
							error_log('[Form error]: Failed to create a directory for attachments');
							throw new ErrorException($wb_form_sending_failed.' (1): '.SiteModule::__('Failed to create a directory for attachments'));
						}
					}
					if( !is_dir($attachmentsDir) ) {
						error_log('[Form error]: Attachments inode on the server is not a directory');
						throw new ErrorException($wb_form_sending_failed.' (2): '.SiteModule::__('Attachments inode on the server is not a directory'));
					}
					foreach( $_FILES[$fieldName]["tmp_name"] as $fileIdx => $fileTmpName) {
						if( !$fileTmpName )
							continue;
						$fileName = $_FILES[$fieldName]["name"][$fileIdx];
						$tmpCopyName = $attachmentsDir . DIRECTORY_SEPARATOR . basename($fileTmpName);
						if( !move_uploaded_file($fileTmpName, $tmpCopyName) ) {
							foreach( $movedFiles as $tmpCopyName )
								unlink($tmpCopyName);
							error_log('[Form error]: Failed to move uploaded file to attachments directory');
							throw new ErrorException($wb_form_sending_failed.' (3): '.SiteModule::__('Failed to move uploaded file to attachments directory'));
						}
						$movedFiles[] = $tmpCopyName;
						$secureFileName = $fileName;
						$secureFileName = preg_replace("#[\\\\/<>\\?;:,=]+#isu", "_", $secureFileName);
						$secureFileName = preg_replace("#\\.\\.+#isu", ".", $secureFileName);
						$mailer->addAttachment($tmpCopyName, $secureFileName, "base64");
					}
				}
			}

			if (isset($form['smtpEnable']) && $form['smtpEnable']) {
				/* $mailer->Debugoutput = function($string) {
					file_put_contents(__DIR__.'/smtp-debug.log', $string."\n", FILE_APPEND);
				};
				$mailer->SMTPDebug = 2; */
				$mailer->isSMTP();
				$mailer->Host = ((isset($form['smtpHost']) && $form['smtpHost']) ? $form['smtpHost'] : 'localhost');
				$mailer->Port = ((isset($form['smtpPort']) && intval($form['smtpPort'])) ? intval($form['smtpPort']) : 25);
				$mailer->SMTPSecure = ((isset($form['smtpEncryption']) && $form['smtpEncryption']) ? $form['smtpEncryption'] : '');
				$mailer->SMTPAutoTLS = false;
				if (isset($form['smtpUsername']) && $form['smtpUsername'] && isset($form['smtpPassword']) && $form['smtpPassword']) {
					$mailer->SMTPAuth = true;
					$mailer->Username = ((isset($form['smtpUsername']) && $form['smtpUsername']) ? $form['smtpUsername'] : '');
					$mailer->Password = ((isset($form['smtpPassword']) && $form['smtpPassword']) ? $form['smtpPassword'] : '');
				}
				$mailer->SMTPOptions = array('ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				));
			}

			$style = "* { font: 12px Arial; }\nstrong { font-weight: bold; }";
			
			$toHasGmail = false;
			foreach ($mail_to as $eml) {
				if (strpos($eml, 'gmail.com') !== false) $toHasGmail = true;
				$mailer->AddAddress($eml);
			}

			$sender_email = (isset($form['emailFrom']) && $form['emailFrom']) ? trim($form['emailFrom']) : ('no-reply@'.$siteInfo->domain);
			$sender_name = $mail_from_name;
			if (preg_match('#^([^<]+|)<([^>]+)>$#', $sender_email, $m)) {
				if (trim($m[1])) $sender_name = trim($m[1]);
				$sender_email = trim($m[2]);
			} else if (preg_match('#^<([^>]+)>(.+|)$#', $sender_email, $m)) {
				if (trim($m[2])) $sender_name = trim($m[2]);
				$sender_email = trim($m[1]);
			}
			$mailer->SetFrom($sender_email, $sender_name);
			if (strpos($mail_from, 'gmail.com') === false || !$toHasGmail) {
				// do not add "Reply-To" header if both Receiver and ReplyTo are Gmail
				// form sending fails in such case.
				$mailer->addReplyTo($mail_from, $mail_from_name);
			}

			$mailer->CharSet = 'utf-8';
			$message = '';
			if (isset($form['object']) && $form['object']) {
				if (isset($form['objectRenderer']) && $form['objectRenderer'] && is_callable($form['objectRenderer'])) {
					$objectStr = call_user_func((strpos($form['objectRenderer'], '::') ? explode('::', $form['objectRenderer']) : $form['objectRenderer']), $form, $data);
				} else {
					$objectStr = '<p><strong>'.htmlspecialchars($form['object']).'</strong></p>';
				}
				if ($objectStr) $message .= $objectStr;
			}
			$message .= '<table cellspacing="5" cellpadding="0">';
			foreach ($fields as $idx => $field) {
				if ($field["type"] === "file")
					continue;
				$name = tr_($field["name"]);
				$value = $data[$idx];
				if ($field["type"] == "textarea")
					$message .= "<tr><td colspan=\"2\"><strong>$name: </strong></td></tr>\n<tr><td colspan=\"2\">" . nl2br($value) . "</td></tr>\n";
				else if ($field["type"] == "checkbox") {
					if( $value )
						$message .= "<tr><td colspan=\"2\"><strong>$name</strong></td></tr>\n";
				}
				else
					$message .= "<tr><td><strong>$name: </strong></td><td>" . nl2br($value) . "</td></tr>\n";
			}
			$message .= '</table>';

			$html =
	'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
	<html>
		<head>
			<title>' . $form["subject"] . '</title>
			<meta http-equiv=Content-Type content="text/html; charset=utf-8">
			' . ($style?"<style><!--\n$style\n--></style>\n\t\t":"") . '</head>
		<body>' . $message . '</body>
	</html>';
			$mailer->MsgHTML($html);
			$mailer->AltBody = strip_tags(str_replace("</tr>", "</tr>\n", $message));
			$mailer->Subject = $form["subject"];
			ob_start();
			$res = $mailer->Send();
			$wb_form_send_success = $res;
			ob_get_clean();
			if ($res) {
				$wb_form_send_state = empty($form['sentMessage']) ? 'Form was sent.' : tr_($form['sentMessage']);
			} else {
				if ($mailer->ErrorInfo) error_log('[Form sending error]: '.$mailer->ErrorInfo);
				throw new ErrorException($wb_form_sending_failed.' (4): '.$mailer->ErrorInfo);
			}
			if (isset($form['loggingHandler']) && $form['loggingHandler'] && is_callable($form['loggingHandler'])) {
				call_user_func((strpos($form['loggingHandler'], '::') ? explode('::', $form['loggingHandler']) : $form['loggingHandler']), $form, $data, $res);
			}
			foreach( $movedFiles as $tmpCopyName )
				unlink($tmpCopyName);
		}
	} catch (ErrorException $ex) {
		if (!$wb_form_send_state) {
			$wb_form_send_state = $ex->getMessage();
			$formErrors->any = true; // set values to fields back in case of error
		}
		$wb_form_send_success = false;
	}
	
	if (session_id()) {
		$_SESSION['post'] = $post;
		$_SESSION['formErrors'] = $formErrors;
		$_SESSION['wb_form_id'] = $wb_form_id;
		$_SESSION['wb_form_send_success'] = $wb_form_send_success;
		$_SESSION['wb_form_popup_mode'] = $wb_form_popup_mode;
		$_SESSION['wb_target_origin'] = $wb_target_origin;
		if ($wb_form_send_success && isset($form['redirectUrl']) && !is_null($form['redirectUrl'])) {
			$url = (preg_match('#^https?://#i', $form['redirectUrl']) ? '' : getBaseUrl()) . $form['redirectUrl'];
			if (isset($_GET['wbPopupMode']) && $_GET['wbPopupMode'] == 1) {
				echo '<script> window.parent.location.href = '.json_encode($url).'; </script>';
				exit();
			} else {
				header('Location: '.$url);
			}
		} else {
			$_SESSION['wb_form_send_state'] = $wb_form_send_state;
			header('Location: '.getCurrUrl());
		}
		exit();
	}
}

/**
 * Load PHPMailer library.
 * @param bool $noErrorException
 * @return bool true if PHPMailer loaded successfully, and false if not.
 * @throws ErrorException
 */
function requirePHPMailer($noErrorException = false) {
	if (version_compare(phpversion(), '5.5') < 0) {
		if (!$noErrorException) throw new ErrorException('Your PHP version is outdated for sending mails. Please update PHP version to 5.6 or higher.');
		return false;
	}
	if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
		require_once dirname(__FILE__).'/phpmailer/Exception.php';
		require_once dirname(__FILE__).'/phpmailer/OAuth.php';
		require_once dirname(__FILE__).'/phpmailer/PHPMailer.php';
		require_once dirname(__FILE__).'/phpmailer/POP3.php';
		require_once dirname(__FILE__).'/phpmailer/SMTP.php';

		// in PHP 7.4 validation of cyrilic email address does not work
		// due to unknown reason. So we just ignore PHPMailer validation.
		if (version_compare(phpversion(), '7.4') >= 0) {
			\PHPMailer\PHPMailer\PHPMailer::$validator = function($address) { return true; };
		}
	}
	return true;
}

function is_mail($mail) {
	if (preg_match("/^[0-9a-zA-ZА-Яа-я\.\-\_\w]+\@[0-9a-zA-ZА-Яа-я\.\-\_\w]+\.[0-9a-zA-ZА-Яа-я\.\-\_\w]+$/isu", trim($mail)))
		return trim($mail);
	return "";
}

function mini_text($text) {
	return trim(substr(strip_tags($text), 0, 100), " \n\r\t\0\x0B.").'...';
}

function _http_get($url, $post_vars = false) {
	$post_contents = '';
	if ($post_vars) {
		if (is_array($post_vars)) {
			foreach($post_vars as $key => $val) {
				$post_contents .= ($post_contents ? '&' : '').urlencode($key).'='.urlencode($val);
			}
		} else {
			$post_contents = $post_vars;
		}
	}

	$uinf = parse_url($url);
	$host = $uinf['host'];
	$path = $uinf['path'];
	$path .= (isset($uinf['query']) && $uinf['query']) ? ('?'.$uinf['query']) : '';
	$headers = array(
		($post_contents ? 'POST' : 'GET')." $path HTTP/1.1",
		"Host: $host",
	);
	if ($post_contents) {
		$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		$headers[] = 'Content-Length: '.strlen($post_contents);
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 600);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	if ($post_contents) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_contents);
	}

	$data = curl_exec($ch);
	if (curl_errno($ch)) {
		return false;
	}
	curl_close($ch);

	return $data;
}
