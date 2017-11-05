<?php
namespace app\core;

class App
{
    /**
     * 存储配置项的数组
     * @var array
     */
    public static $conf;

    /**
     * @var string 默认访问的控制器名称
     */
    private $defaultController;

    /**
     * @var string 默认访问的方法名称
     */
    private $defaultAction;

    /**
     * @var \app\core\Route 的引用
     */
    private $route;

    /**
     * @var array 核心文件路径映射表
     */
    public static $classMap;

    public function __construct($mainConfigPath)
    {
        //加载配置信息
        self::$conf = $this->loadConfig($mainConfigPath);
        //加载默认的路由配置信息
        $this->loadDefaultRoute();
        $this->route = new Route();
    }

    /**
     * 加载配置文件 保证只加载一次
     * @param string $mainConfigPath 主配置文件路径
     * @return array|mixed
     */
    private function loadConfig($mainConfigPath)
    {
        if (!empty(self::$conf)) {
            return self::$conf;
        } else {
            return include "{$mainConfigPath}";
        }
    }

    /**
     * 加载默认路由
     */
    private function loadDefaultRoute()
    {
        $this->defaultController = isset(self::$conf['app']['defaultController']) ? self::$conf['app']['defaultController'] : 'home';
        $this->defaultAction = isset(self::$conf['app']['defaultAction']) ? self::$conf['app']['defaultAction'] : 'index';
    }

    /**
     * 自动引入类文件
     * @param $class
     * @throws
     */
    public static function loadClass($class)
    {
        //优先使用核心文件路径映射表来获取文件路径
        if (isset(self::$classMap[$class])) {
            $classFile = self::$classMap[$class];
        } else {
            //如果核心文件路径映射表中找不到该文件的路径，则解析
            $class = str_replace('\\', '/', $class);
            $class = trim($class, '/');
            $classFile = BASE_PATH . DIRECTORY_SEPARATOR . $class . ".php";
        }
        if (!is_file($classFile)) {
            throw new \Exception('File ' . $classFile . ' not found');
        }
        include($classFile);
    }

    /**
     * 应用驱动
     */
    public function run()
    {
        //获从Url中得到，控制器段、方法段
        list($c, $a) = $this->route();
        //解析成对应的控制器类文件名称
        $controllerName = $this->parseController($c);
        //解析成对应的方法名称
        $actionName = $this->parseAction($a);
        //带完整命名空间的控制器
        $controllerClass = "app\\controller\\{$controllerName}";
        if (!class_exists($controllerClass)) {
            throw new \Exception('Class ' . $controllerClass . ' not found');
        }
        $app = new $controllerClass;
        if (!method_exists($app, $actionName)) {
            throw new \Exception('Method ' . $actionName . ' not found');
        }
        $app->$actionName();
    }

    /**
     * 解析控制器名称
     * 例如：url中的控制器部分为user-info，则解析出来的控制器名称为UserInfoController
     * @param $c
     * @return string
     */
    private function parseController($c)
    {
        $controllerDirt = explode('-', strtolower($c));
        $controllerDirt = array_map('ucfirst', $controllerDirt);
        //控制器名称后面添加Controller后缀
        return implode('', $controllerDirt) . 'Controller';
    }

    /**
     * 解析方法名称
     * 例如：url中的方法部分为get-info，则解析出来的方法名称为actionGetInfo
     * @param $a
     * @return string
     */
    private function parseAction($a)
    {
        $actionDirt = explode('-', strtolower($a));
        $actionDirt = array_map('ucfirst', $actionDirt);
        //添加action前缀
        return 'action' . implode('', $actionDirt);
    }

    /**
     * 以自定义格式显示错误
     * @param $exception \Exception
     */
    public static function handleException($exception)
    {
        //如果开启了debug则显示完整错误信息，否则只显示发生错误
        if (DEBUG) {
            //以特定格式输出错误信息
            $errorMsg = '<div style="font-size: 20px;color:#FF3030;font-weight:bold">An Error Occurred</div><div>' . $exception->getMessage() . '</div><div>Filename: <i>' . $exception->getFile()
                . '</i></div><div>Line Number: <i>' . $exception->getLine() . '</i></div>';
            echo $errorMsg;
        } else {
            echo '<div style="color: #636363;font-size: 20px;">An internal server error occurred.</div>';
        }
    }

    /**
     * 路由方法，负责从url中获得控制器、方法
     * @return array
     */
    private function route()
    {
        /*从URL中解析，请求指向的控制器、方法*/
        $routeStr = $this->route->parse();
        $routeDirt = (empty($routeStr)) ? [$this->defaultController, $this->defaultAction] : explode('/', $routeStr);
        $routeLen = sizeof($routeDirt);
        if ($routeLen < 2) {
            //控制器
            $controllerName = isset($routeDirt[0]) ? trim($routeDirt[0]) : $this->defaultController;
            //方法
            $actionName = $this->defaultAction;
        } else {
            //控制器
            $controllerName = isset($routeDirt[$routeLen - 2]) ? trim($routeDirt[$routeLen - 2]) : $this->defaultController;
            //方法
            $actionName = isset($routeDirt[$routeLen - 1]) ? trim($routeDirt[$routeLen - 1]) : $this->defaultAction;
        }
        return [$controllerName, $actionName];
    }
}

//加载核心文件路径映射表
App::$classMap = require __DIR__ . '/Classes.php';
//初始化不存在的类时，自动引入该类文件
spl_autoload_register(['\\app\\core\\App', 'loadClass'], true, true);
//注册顶层错误处理方法
set_exception_handler(['\\app\\core\\App', 'handleException']);
?>