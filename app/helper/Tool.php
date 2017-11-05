<?php

namespace app\helper;

/**
 * 工具类，包含常用的工具函数
 * Class Tool
 * @package app\helper
 */
class Tool{

    /**
     * 获得客户端IP
     * @return null
     */
    static public function getIp(){
        $realIp = null;
        if(!isset($_SERVER)){
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $realIp = getenv('HTTP_X_FORWARDED_FOR');
            }elseif (getenv('HTTP_CLIENT_IP')) {
                $realIp = getenv('HTTP_CLIENT_IP');
            }else{
                $realIp = getenv('REMOTE_ADDR');
            }
            return $realIp;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
            foreach ($arr as $ip) {
                $ip = trim($ip);
                if ($ip != 'unknown') {
                    $realIp = $ip;
                    break;
                }
            }
        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realIp = $_SERVER['HTTP_CLIENT_IP'];
        }elseif(isset($_SERVER['REMOTE_ADDR'])){
            $realIp = $_SERVER['REMOTE_ADDR'];
        }
        return $realIp;
    }

    /**
     * 查询ip对应的地理位置
     * @param string $ip
     * @return mixed
     */
    static public function ipLocation($ip = ''){
        if($ip == '' || empty($ip)) $ip = self::getIp();
        $ipUrl="http://whois.pconline.com.cn/ip.jsp?ip=$ip";
        $ipLocation = self::getRequest($ipUrl);
        $ipLocation = iconv('GBK', 'UTF-8', trim($ipLocation));
        return $ipLocation;
    }

    /**
     * 得到浏览器标识
     * @return null|string
     */
    static public function userAgent(){
        if(!isset($_SERVER['HTTP_USER_AGENT'])){
            return null;
        }
        return $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * 过滤输入字符串，过滤html、sql查询语句、特殊字符
     * 支持字符串、一维数组各个字符串类型的元素过滤
     * @param $handle
     * @return array|string
     */
    static public function filter($handle)
    {
        if (is_array($handle)) {
            foreach ($handle as $key => $obj) {
                if (!is_string($obj)) {
                    continue;
                }
                $handle[$key] = addslashes(self::filterSql(strip_tags(trim($obj))));
            }
            return $handle;
        }
        return addslashes(self::filterSql(strip_tags(trim($handle))));
    }

    /**
     * 过滤sql
     * @param $str
     * @return mixed
     */
    static public function filterSql($str)
    {
        if (!is_string($str)) {
            return $str;
        }
        //将查询语句的首字母转换成unicode字符，特殊符号转换成空
        $search = array(
            "and",
            "execute",
            "update",
            "count",
            "select",
            "create",
            "delete",
            "insert",
            "where",
            "(",
            ")",
            "*",
            "=",
            ";"
        );
        $replace = array(
            "&#97;nd",
            "&#101;xecute",
            "&#117;pdate",
            "&#99;ount",
            "&#115;elect",
            "&#99;reate",
            "&#100;elete",
            "&#105;nsert",
            "&#119;here",
            "",
            "",
            "",
            "",
            ""
        );
        //不区分大小写
        return str_ireplace($search,$replace,$str);
    }

    /**
     * 自定义安全解码
     * @param string $string 待解码的字符串
     * @return string
     */
    static public function decode($string)
    {
        $data = strrev($string);
        $data = str_replace(array('-', '_'), array('+', '/'), $data);
        $mod4 = strlen($data) % 4;
        ($mod4) && $data .= substr('====', $mod4);
        return unserialize(base64_decode($data));
    }

    /**
     * 自定义安全编码
     * @param string $data 待编码的字符串
     * @return string
     */
    static public function encode($data) {
        return str_replace(array('+', '/', '='), array('-', '_', ''), strrev(base64_encode(serialize($data))));
    }

    /**
     * curl发送get请求，可访问https请求
     * @param string $url 请求的url
     * @param string $refer 请求的来源
     * @param int $timeout 超时时间
     * @return mixed
     */
    static public function getRequest($url,$refer = null,$timeout = 10)
    {
        $ssl = substr($url, 0, 8) == "https://" ? true : false;
        $curlObj = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_AUTOREFERER => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_TIMEOUT=>$timeout,//超时时间
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        ];
        if($refer){
            $options[CURLOPT_REFERER] = $refer;
        }
        if($ssl){
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            $options[CURLOPT_SSL_VERIFYPEER] = FALSE;
        }
        curl_setopt_array($curlObj, $options);
        $returnData = curl_exec($curlObj);
        if (curl_errno($curlObj)) {
            $returnData = curl_errno($curlObj);
        }
        curl_close($curlObj);
        return $returnData;
    }

    /**
     * @param string $url 请求的url地址
     * @param array $data 发送的数据
     * @param null|string $refer 来源设置
     * @param int $timeout 请求超时时间
     * @return mixed
     */
    static public function postRequest($url, $data, $refer = null, $timeout = 10)
    {
        $curlObj = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_AUTOREFERER => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            CURLOPT_TIMEOUT=>$timeout,//超时时间
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
        ];
        if($refer){
            $options[CURLOPT_REFERER] = $refer;
        }
        curl_setopt_array($curlObj, $options);
        $returnData = curl_exec($curlObj);
        if (curl_errno($curlObj)) {
            $returnData = curl_errno($curlObj);
        }
        curl_close($curlObj);
        return $returnData;
    }

    /**
     * 验证手机号码格式是否正确
     * @param string $str 手机号码字符串
     * @return bool
     */
    static public function isMobile($str){
        if(preg_match('/^1([3578][0-9]|4[57])\d{8}$/', $str)){
            return true;
        }
        return false;
    }

    /**
     * 验证当前用户是否在微信浏览器
     * @return bool
     */
    static public function wxVerify()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }
}
?>
