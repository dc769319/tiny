<?php
namespace app\core;

class Controller
{

    public function __construct()
    {
        $this->init();
    }

    /**
     * 子类可用init方法执行初始化操作
     */
    public function init()
    {
    }

    /**
     * 加载视图文件
     * @param $view string 视图名
     * @param $data array 传入视图的参数
     * @throws \Exception
     */
    protected function display($view, $data = [])
    {
        //使用//开头的路径为强制加载模式，该模式下，按传入的路径加载视图文件。
        if(strncmp($view,"//",2) === 0){
            $view = trim(str_replace('//', '', $view), '\/\s');
            $viewPath = APP_DIR . '/view/' . $view . '.php';
        } else {
            $className = $this->trimControllerName(get_called_class());
            if (!$className) {
                throw new \Exception('视图文件路径错误');
            }
            $viewPath = APP_DIR . '/view/' . strtolower($className) . '/' . $view . '.php';
        }
        if (!file_exists($viewPath)) {
            throw new \Exception('视图文件不存在');
        }
        if (!empty($data) && is_array($data)) {
            extract($data, EXTR_OVERWRITE);
        }
        require($viewPath);
    }

    /**
     * 截取控制器对应的视图目录名
     * 根据控制器类名称，得到控制器方法加载视图文件的目录名称
     * 例如：控制器类名称为UserEditController对应的视图文件目录名称是user-edit
     * @param $className
     * @return bool
     */
    protected function trimControllerName($className)
    {
        if (!is_string($className)) {
            return false;
        }
        $dirDirt = explode('\\', $className);
        if (empty($dirDirt)) {
            return false;
        }
        //从命名空间字符串中，得到最后一段，也就是控制器类名
        $className = end($dirDirt);
        if (empty($className)) {
            return false;
        }
        $classNameTmp = lcfirst($className);
        //使用正则，从大写字母处截断，截成多个以大写字母开头的单词
        $viewDirTmp = preg_split('/(?=[A-Z])/', $classNameTmp);
        if (empty($viewDirTmp)) {
            return false;
        }
        array_pop($viewDirTmp);//删除数组的最后一个元素，也就是Controller后缀
        //所有单词全部转换成小写
        $viewDirTmp = array_map('strtolower', $viewDirTmp);
        //使用短线连接所有单词
        return implode('-', $viewDirTmp);
    }
}

?>