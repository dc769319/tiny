<?php
namespace app\controller;

use app\core\App;
use app\core\Controller;

class HomeController extends Controller{
    /**
     * 加载首页
     */
    public function actionIndex(){
        //加载视图文件
        $this->display('index');
    }
}
?>