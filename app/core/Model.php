<?php

namespace app\core;

use app\lib\SimpleDB;

class Model extends Bean
{
    /**
     * @var $db \app\lib\SimpleDB;
     */
    protected $db;

    /**
     * @var string 数据表名称
     */
    protected $table;

    public function __construct()
    {
        $this->db = $this->initDb();//初始化Mysql操作类
        $this->table = self::tableName();//模型类对应的数据表名称
    }

    /**
     * 根据模型类名称，计算对应的数据表名称
     * 例如：数据库中的user_info表，对应的模型类名称为UserInfo
     * @return bool
     */
    public static function tableName()
    {
        //得到调用该方法的类名（包含命名空间）
        $className = get_called_class();
        $dirDirt = explode('\\', $className);
        if (empty($dirDirt)) {
            return false;
        }
        //取得命名空间的最后一段，即模型类名称
        $className = end($dirDirt);
        if (empty($className)) {
            return false;
        }
        $classNameTmp = lcfirst($className);
        //以大写字母截断字符串，生成数组
        $viewDirTmp = preg_split('/(?=[A-Z])/', $classNameTmp);
        if (empty($viewDirTmp)) {
            return false;
        }
        //数组元素全部转化成小写
        $viewDirTmp = array_map('strtolower', $viewDirTmp);
        //表名称使用_连接
        return implode('_', $viewDirTmp);
    }

    /**
     * 单例模式初始化数据库操作类
     * @return \app\lib\SimpleDB
     */
    private function initDb()
    {
        $config = App::$conf['db'];
        return SimpleDB::getInstance($config);
    }
}

?>