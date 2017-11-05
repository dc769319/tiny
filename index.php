<?php
/*定义编码格式*/
header("Content-type: text/html; charset=utf-8;");
/*设置时区，国内使用上海时区*/
ini_set('date.timezone', 'Asia/Shanghai');
/*当前目录为应用根目录*/
defined('BASE_PATH') or define('BASE_PATH', __DIR__);
/*定义应用目录*/
defined('APP_DIR') or define('APP_DIR', BASE_PATH . '/app');
/*定义是否开启DEBUG模式，开发环境下建议开启，生产环境下建议关闭*/
defined('DEBUG') or define('DEBUG', true);
/*加载应用类*/
require(APP_DIR . "/core/App.php");
/*加载主配置文件，启动应用*/
(new \app\core\App(APP_DIR . '/config/main.php'))->run();
?>