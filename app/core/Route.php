<?php
namespace app\core;

/**
 * 从url中解析路由字符串
 * Class Route
 * @package app\core
 */
class Route {

    /**
     * 供外部调用的方法，将路由解析的结果返回
     * @return string
     * @throws \Exception
     */
    public function parse(){
        //优先使用r参数，解析路由
        $route = $this->resolveRequestString();
        if(empty($route)){
            //如果url中没有r参数，则尝试获取pathInfo
            $route = $this->resolvePathInfo();
        }
        return $route;
    }

    /**
     * 获取url中r参数的值
     * @return string
     */
    private function resolveRequestString(){
        if (!isset($_GET['r']) || empty($_GET['r'])) {
            return '';
        }else{
            $rStr = trim($_GET['r'], ' \/');
            $questStr = urldecode($rStr);
            if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $questStr)
            ) {
                $questStr = utf8_encode($questStr);
            }
            return $questStr;
        }
    }

    /**
     * 获取pathinfo
     * @return string
     * @throws \Exception
     */
    private function resolvePathInfo()
    {
        $pathInfo = $this->resolveRequestUri();
        if (($pos = strpos($pathInfo, '?')) !== false) {
            //去掉queryString部分
            $pathInfo = substr($pathInfo, 0, $pos);
        }
        //解码url字符串
        $pathInfo = urldecode($pathInfo);
        //将url字符串转换为utf8编码
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $pathInfo)
        ) {
            $pathInfo = utf8_encode($pathInfo);
        }
        //获取url中，到入口文件名称前面的那一部分
        $scriptUrl = $this->getScriptUrl();
        //获取scriptUrl的父级url
        $baseUrl = $this->getBaseUrl();
        if (strpos($pathInfo, $scriptUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($scriptUrl));
        } elseif ($baseUrl === '' || strpos($pathInfo, $baseUrl) === 0) {
            $pathInfo = substr($pathInfo, strlen($baseUrl));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $scriptUrl) === 0) {
            $pathInfo = substr($_SERVER['PHP_SELF'], strlen($scriptUrl));
        } else {
            throw new \Exception('Unable to determine the path info of the current request.');
        }
        if (substr($pathInfo, 0, 1) === '/') {
            $pathInfo = substr($pathInfo, 1);
        }
        return (string) $pathInfo;
    }

    /**
     * 获取url中不包含入口文件名称的那一部分url
     * @return mixed
     * @throws \Exception
     */
    protected function getBaseUrl(){
        return rtrim(dirname($this->getScriptUrl()), '\\/');
    }

    /**
     * 获取url中，访问到php入口文件的那部分字符串
     * @return string
     * @throws \Exception
     */
    private function getScriptUrl()
    {
        $scriptFile = $this->getScriptFile();
        $scriptName = basename($scriptFile);
        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
            $scriptUrl = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $scriptName) {
            $scriptUrl = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
            $scriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/' . $scriptName)) !== false) {
            $scriptUrl = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $scriptName;
        } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($scriptFile, $_SERVER['DOCUMENT_ROOT']) === 0) {
            $scriptUrl = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $scriptFile));
        } else {
            throw new \Exception('Unable to determine the entry script URL.');
        }
        return $scriptUrl;
    }

    /**
     * 获取url中的入口文件名
     * @return mixed
     * @throws \Exception
     */
    public function getScriptFile()
    {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            return $_SERVER['SCRIPT_FILENAME'];
        } else {
            throw new \Exception('Unable to determine the entry script file path.');
        }
    }

    /**
     * 获取REQUEST_URI，兼容主流的http服务器
     * @return string
     * @throws \Exception
     */
    protected function resolveRequestUri()
    {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            throw new \Exception('Unable to determine the request URI.');
        }
        return $requestUri;
    }
}
?>