<?php
namespace app\lib;

class Cookie
{
    /**
     * @var null|string 加密用的key
     */
    private $_cKey = "c#youku*";
    /**
     * @var Cookie 类实例
     */
    static private $_instance;

    /**
     * 防止clone单例
     */
    private function __clone()
    {
    }

    private function __construct($key = null)
    {
        $this->_cKey = (null === $key) ? $this->_cKey : $key;
    }

    /**
     * 单例模式，供外部获取类的实例，支持传入加密用的key
     * @param null $key
     * @return Cookie
     */
    public static function instance($key = null)
    {
        if (null === self::$_instance) {
            self::$_instance = new self($key);
        }
        return self::$_instance;
    }

    /**
     * 设置加密用的key
     * @param $key
     */
    public function setKey($key)
    {
        $this->_cKey = $key;
    }

    /**
     * 获取加密用的key
     * @return null|string
     */
    public function getKey()
    {
        return $this->_cKey;
    }

    /**
     * 简单的加密。对cookie键值进行加密
     * @param $str
     * @return string
     */
    private function authCode($str)
    {
        return substr(md5($str . $this->_cKey), 1, 10);
    }

    /**
     * 将数据写入cookie中
     * @param string $key cookie键
     * @param string|array|object $value cookie值
     * @param null $expire
     * @param string $path
     * @param null $domain
     * @param null $secure
     * @param bool $httponly
     */
    public function set($key, $value, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = true)
    {
        return setcookie($this->authCode($key), $value, null === $expire ? null : (time() + $expire), $path, $domain, $secure, $httponly);
    }

    /**
     * 从cookie中获取值
     * @param string $key cookie键
     * @return mixed|null
     */
    public function get($key)
    {
        if (!isset($_COOKIE[$this->authCode($key)])) return null;
        return $_COOKIE[$this->authCode($key)];
    }

    /**
     * 删除cookie
     * @param string $key cookie键
     * @return bool|null
     */
    public function remove($key)
    {
        return setcookie($this->authCode($key), null, -1);
    }
}