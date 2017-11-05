<?php
/**
 * 主配置文件
 */
return [
    'db' => require __DIR__ . '/db.php',
    //设置默认情况下访问的控制器名称和方法名称
    'app' => [
        'defaultController' => 'home',
        'defaultAction' => 'index'
    ],
    //添加自定义的配置项——memcached配置
    'memcached'=>[
        'classType'=>'Memcache',
        'server' => [
            'host' => '192.168.10.191',
            'port' => 11211,
            'weight' => 1
        ],
        'expiration' => 7200,
        'prefix' => ''
    ],
    //自定义配置项
    'params' => []
];
?>