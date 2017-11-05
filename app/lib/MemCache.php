<?php
namespace app\lib;

/**
 * Memcache 操作类
 */

class MemCache
{
    /**
     * @var \Memcached || \Memcache
     */
    private $m;
    /**
     * @var string Memcache或Memcached
     */
    private $mType;
    /**
     * @var string 错误信息
     */
    private $error = '';

    /**
     * @var MemCache
     */
    private static $instance;

    /**
     * @var array 配置信息数组
     */
    private $config = array(
        'classType'=>'Memcached',
        'server' => array(
            'host' => '',
            'port' => 11211,
            'weight' => 1
        ),
        'expiration' => 7200,
        'prefix' => ''
    );

    /**
     * 单例模式
     * 获得操作类实例
     * @param $config
     * @return MemCache
     */
    public static function getInstance($config){
        if(null === self::$instance){
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 防止clone单例
     */
    private function __clone(){}

    /**
     * 初始化操作类
     * YKMemcached constructor.
     * @param array $config
     * @throws \Exception
     */
    private function __construct($config = array())
    {
        if (!extension_loaded('Memcached') && !extension_loaded('Memcache')) {
            throw new \Exception('Memcached、Memcache扩展不存在');
        }

        $this->config = array_merge($this->config, $config);
        if(!in_array($this->config['classType'],array('Memcache','Memcached'))){
            throw new \Exception('请选择需要调用的扩展类名称');
        }
        $mType = $this->config['classType'];
        $this->mType = $mType;
        if($mType == 'Memcached'){
            $this->m = new \Memcached();
        }else{
            $this->m = new \Memcache();
        }
        if (!$this->m) {
            throw new \Exception('memcache扩展类实例化失败');
        }
        $this->addServer($this->config['server']);
    }

    /**
     * @Name addServer
     * @param:none
     * @todu 连接memcache server
     * @return boolean
     **/
    public function addServer($server)
    {
        return $this->m->addServer($server['host'], $server['port'], $server['weight']);
    }

    /**
     * 添加缓存
     * @param string|null|array $key 键名称
     * @param string|null $value 要缓存的数据
     * @param int|null $expiration 缓存过期时间
     * @return bool
     */
    public function add($key = NULL, $value = NULL, $expiration = NULL)
    {
        $expiration = $expiration ? $expiration : $this->config['expiration'];
        if (is_array($key)) {
            $status = false;
            foreach ($key as $multi) {
                $status = $this->add($multi['key'], $multi['value'], $multi['expiration']);
            }
            return $status;
        } else {
            if($this->mType === 'Memcache'){
                return $this->m->add($key, $value, MEMCACHE_COMPRESSED, (int)$expiration);
            }
            return $this->m->add($key, $value, (int)$expiration);
        }
    }

    /**
     * 设置缓存，可修改指定缓存键的值
     * @param string|null|array $key 键名称
     * @param string|null $value 要缓存的数据
     * @param int|null $expiration 缓存过期时间
     * @return bool
     */
    public function set($key = NULL, $value = NULL, $expiration = NULL)
    {
        $expiration = $expiration ? $expiration : $this->config['expiration'];
        if (is_array($key)) {
            $status = false;
            foreach ($key as $multi) {
                $status = $this->set($multi['key'], $multi['value'], $multi['expiration']);
            }
            return $status;
        } else {
            if($this->mType === 'Memcache'){
                return $this->m->set($key, $value,MEMCACHE_COMPRESSED, (int)$expiration);
            }
            return $this->m->set($key, $value, (int)$expiration);
        }
    }

    /**
     * 根据键名称获取缓存的数据
     * @param null|array|string $key
     * @throws \Exception
     * @return bool|mixed|string
     */
    public function get($key = NULL)
    {
        if (is_null($key)) {
            throw new \Exception('key不能为空');
        }
        if (is_array($key)) {
            return $this->m->getMulti($key);
        } else {
            return $this->m->get($key);
        }
    }

    /**
     * 删除缓存
     * @param string|array $key 缓存键名称
     * @param null|int $expiration memcache服务器等待$expiration秒之后删除该缓存
     * @throws \Exception
     * @return bool
     */
    public function delete($key, $expiration = NULL)
    {
        if (is_null($key)) {
            throw new \Exception('key不能为空');
        }
        $expiration = $expiration ? $expiration : $this->config['expiration'];
        if (is_array($key)) {
            $status = false;
            foreach ($key as $multi) {
                $status = $this->delete($multi, $expiration);
            }
            return $status;
        } else {
            return $this->m->delete($key, (int)$expiration);
        }
    }

    /**
     * 将缓存的键和值替换
     * @param string|null|array $key 键名称
     * @param string|null $value 要缓存的数据
     * @param int|null $expiration 缓存过期时间
     * @return bool
     */
    public function replace($key = NULL, $value = NULL, $expiration = NULL)
    {
        $expiration = $expiration ? $expiration : $this->config['expiration'];
        if (is_array($key)) {
            $status = false;
            foreach ($key as $multi) {
                $status = $this->replace($multi['key'], $multi['value'], $multi['expiration']);
            }
            return $status;
        } else {
            if($this->mType === 'Memcache'){
                return $this->m->replace($key, $value, MEMCACHE_COMPRESSED, (int)$expiration);
            }
            return $this->m->replace($key, $value, (int)$expiration);
        }
    }

    /**
     * 递增
     * @param $key
     * @param int $value
     * @return bool|int
     */
    public function increment($key,$value = 1){
        return $this->m->increment($key,$value);
    }

    /**
     * 刷新缓存，将删除所有缓存数据
     * @return bool
     */
    public function flush()
    {
        return $this->m->flush();
    }
    
    /**
     * 获取服务器池中所有服务器的版本信息
     * @return bool
     **/
    public function get_version()
    {
        return 'Memcached v' . $this->m->getVersion();
    }


    /**
     * 获取服务器池的统计信息
     * @return bool
     **/
    public function get_stats()
    {
        return $this->m->getStats();
    }

    /**
     * 获取错误信息
     * @return bool
     **/
    public function getError()
    {
        return $this->error;
    }

    /**
     * 往缓存数据后面添加数据
     * @param null $key
     * @param null $value
     * @return bool
     */
    public function append($key = NULL, $value = NULL)
    {
        return $this->m->append($key, $value);
    }
}
?>
