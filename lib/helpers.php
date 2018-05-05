<?php

/**
 * get app instance
 *
 * @return \Lib\Framework\App
 */
function app($settings = [], $console = false)
{
	return \Lib\Framework\App::instance($settings, $console);
}


/**
 * @param string $url
 * @param null $showIndex
 * @param bool $includeBaseUrl
 *
 * @return string
 */
function url($url = '', $showIndex = null, $includeBaseUrl = true)
{
	return app()->url($url, $showIndex, $includeBaseUrl);
}


/**
 * @param string $url
 * @param null $showIndex
 * @param bool $includeBaseUrl
 *
 * @return string
 */
function baseUrl()
{
	return app()->url('', false);
}

/**
 * output a variable, array or object
 * 
 * @param string $var
 * @param boolean $exit
 * @param boolean $return
 * @param string $separator
 *
 * @return string
 */
function debug($var, $exit = false, $return = false, $separator = "<br/>")
{
	$log = "";
	if ($separator == null) $separator = app()->console ? "\n" : "<br/>";

	if ($separator == "<br/>") $log .= '<pre>';
	if (is_array($var)) {
		$log .= print_r($var, true);
	} else {
		ob_start();
		var_dump($var);
		$log .= ob_get_clean();
	}
	if ($separator == "<br/>") $log .= '</pre>';

	if (!$return) echo $log;
	if ($exit) exit();

	return $log;
}