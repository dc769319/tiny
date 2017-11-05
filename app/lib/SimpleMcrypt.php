<?php
namespace app\lib;

/**
 * 对称加密类
 * Class SimpleMcrypt
 * @package app\lib
 */
class SimpleMcrypt{

    /**
     * 加密
     * @param string $code 要加密的字符串
     * @param string $key 对称加密使用的加密密钥
     * @return string 加密后的字符串
     */
    public static function encrypt($code,$key)
    {
        return self::safeEncode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $code, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }

    /**
     * 解密
     * @param string $code 要加密的字符串
     * @param string $key 对称加密时使用的加密密钥，必须与encode时使用的密钥一致
     * @return string 解密后的字符串
     */
    public static function decrypt($code,$key)
    {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), self::safeDecode($code), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }

    /**
     * 处理特殊字符
     * @param $string
     * @return mixed
     */
    private static function safeEncode($string) {
        $data = base64_encode($string);
        $data = str_replace(array('+','/','='),array('-','_',''),$data);
        return $data;
    }

    /**
     * 解析特殊字符
     * @param $string
     * @return mixed
     */
    private static function safeDecode($string) {
        $data = str_replace(array('-','_'),array('+','/'),$string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }
}
?>