<?php
namespace app\model;

use app\core\Model;

/**
 * 示例模型类
 * Users模型类对应于数据库中的users数据表，如果db.php配置文件中设置了tablePrefix，例如tablePrefix为test_，则对应表名为test_users
 * Class Users
 * @package app\model
 */
class Users extends Model{

    public function getOneUserInfo(){
        return $this->db->select(['id','username','password'])
            ->from($this->table)
            ->one();
    }
}
?>