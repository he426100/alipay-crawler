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
    if ($separator == null) {
        $separator = app()->console ? "\n" : "<br/>";
    }

    if ($separator == "<br/>") {
        $log .= '<pre>';
    }
    if (is_array($var)) {
        $log .= print_r($var, true);
    } else {
        ob_start();
        var_dump($var);
        $log .= ob_get_clean();
    }
    if ($separator == "<br/>") {
        $log .= '</pre>';
    }

    if (!$return) {
        echo $log;
    }
    if ($exit) {
        exit();
    }

    return $log;
}
/**
 * http请求
 *
 * @param  string  $url    请求地址
 * @param  boolean|string|array $params 请求数据
 * @param  integer $ispost 0/1，是否post
 * @param  array  $header
 * @param  $verify 是否验证ssl
 * @return string|boolean          出错时返回false
 */
function http($url, $params = false, $ispost = 0, $header = [], $verify = false, $follow = true)
{
    $httpInfo = array();
    $ch = curl_init();
    if (!empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($follow === false) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    }
    //忽略ssl证书
    if ($verify === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if ($ispost) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        if (is_array($params)) {
            $params = http_build_query($params);//, null, '&', PHP_QUERY_RFC3986
        }
        if ($params) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }
    $response = curl_exec($ch);
    if ($response === false) {
        trace("cURL Error: " . curl_errno($ch) . ',' . curl_error($ch), \Psr\Log\LogLevel::ERROR);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        trace($httpInfo, \Psr\Log\LogLevel::ERROR);
        return false;
    }
    curl_close($ch);
    return $response;
}

/**
 * 快速记录日志
 *
 * @param mixed $message
 * @param string $level
 * @param array $context
 * @return Boolean Whether the record has been processed
 */
function trace($message, $level = \Psr\Log\LogLevel::DEBUG, array $context = array())
{
    if (!is_string($message)) {
        $message = var_export($message, true);
    }
    $logger = app()->resolve(\Psr\Log\LoggerInterface::class);
    return $logger->log($level, $message, $context);
}

/**
 * 生成验证码图片
 *
 * @param $location 验证码x,y轴坐标
 * @param $size 验证码的长宽
 */
function generateVcodeIMG($location, $size, $srcImg)
{
    $width = $size->getWidth();
    $height = $size->getHeight();
    $x = $location->getX();
    $y = $location->getY();
    
    $src = imagecreatefrompng($srcImg);
    $dst = imagecreatetruecolor($width, $height);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $width, $height, $width, $height);
    imagejpeg($dst, $srcImg);
    chmod($srcImg, 0777);
    imagedestroy($src);
    imagedestroy($dst);
}
