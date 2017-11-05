<?php
namespace app\lib;

class Session
{

    /**
     * @var null|string 加密用的key
     */
    private $_seKey = "#youku*";
    /**
     * @var Session 类实例
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
        $this->_seKey = (null === $key) ? $this->_seKey : $key;
    }

    /**
     * 单例模式，供外部获取类的实例，支持传入加密用的key
     * @param null $key
     * @return Session
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
        $this->_seKey = $key;
    }

    /**
     * 获取加密用的key
     * @return null|string
     */
    public function getKey()
    {
        return $this->_seKey;
    }

    /**
     * 简单的加密。对session键值进行加密
     * @param $str
     * @return string
     */
    private function authCode($str)
    {
        return substr(md5($str . $this->_seKey), 2, 10);
    }

    /**
     * 确保session开启
     */
    private function ensureSessionStarted()
    {
        //如果未开启session，则开启session
        isset($_SESSION) || session_start();
    }

    /**
     * 将验证码数据写入session中
     * @param string $key session键
     * @param string|int $value session值
     */
    public function set($key, $value)
    {
        $this->ensureSessionStarted();
        $_SESSION[$this->authCode($key)] = $value;
    }

    /**
     * 从session中获取值
     * @param string $key session键
     * @return mixed|null
     */
    public function get($key)
    {
        //如果未开启session，则开启session
        $this->ensureSessionStarted();
        $keyHash = $this->authCode($key);
        if (!isset($_SESSION[$keyHash])) return null;
        return $_SESSION[$keyHash];
    }

    /**
     * 删除session
     * @param string $key session键
     * @return bool|null
     */
    public function remove($key)
    {
        $this->ensureSessionStarted();
        $keyHash = $this->authCode($key);
        if (!isset($_SESSION[$keyHash])) return null;
        unset($_SESSION[$keyHash]);
        return true;
    }

    /**
     * 删除所有session
     */
    public function removeAll(){
        session_destroy();
    }
}