<?php
namespace app\lib;

/**
 * 数据库操作类
 * 使用PDO库操作数据库。借助prepare语句，有效防止sql注入攻击
 */
class SimpleDB
{
    /**
     * @var \PDO
     */
    private $_db;

    /**
     * @var string $_query 生成的查询语句
     */
    private $_query;

    /**
     * where查询条件的参数数组
     * @var array
     */
    private $_bindParams = [];

    /**
     * prepare中，对参数出现的次数进行统计
     * @var array
     */
    private $_paramTimes = [];

    /**
     * @var string 生成的sql语句
     */
    private $_tmpSql;

    /**
     * @var SimpleDB Db类的单例
     */
    private static $_instance;

    /**
     * @var array 配置信息
     */
    private $_config = [
        'host' => '',
        'user' => '',
        'pass' => '',
        'port' => '3306',
        'tablePrefix' => '',
        'db' => '',
        'charset' => 'utf8'
    ];

    /**
     * 单例模式
     * 外部调用获取操作类实例的方法
     * @param $config
     * @return SimpleDB
     */
    public static function getInstance($config)
    {
        if (null === self::$_instance) {
            self::$_instance = new self($config);
        }
        return self::$_instance;
    }

    /**
     * 防止clone单例
     */
    private function __clone()
    {
    }

    /**
     * 构造方法，需传入数据库配置信息
     * SimpleDb constructor.
     * @param $config
     * @throws \Exception
     */
    private function __construct($config)
    {
        $this->_config = array_merge($this->_config, $config);
        $dsn = 'mysql:dbname=' . $this->_config['db'] . ';host=' . $this->_config['host'];
        $this->_db = new \PDO($dsn, $this->_config['user'], $this->_config['password']);
        //设置utf8编码
        $this->_db->exec('SET NAMES ' . $this->_config['charset']);
        //设置错误可见，并在发生异常时抛出
        $this->_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * 得到原生的PDO对象
     * @return \PDO
     */
    public function getDb()
    {
        return $this->_db;
    }

    /**
     * 执行一条sql语句
     * @param $sql
     * @return int 影响行数
     */
    public function query($sql)
    {
        $this->_tmpSql = $sql;
        return $this->_db->exec($sql);
    }

    /**
     * 执行添加记录操作
     * @param $data array 要增加的数据，参数为数组。数组key为字段值，数组值为数据取值 格式:array('字段名' => 值);
     * @param $table string 数据表
     * @param  $replace boolean 是否替换
     * @throws \Exception
     * @return boolean
     */
    public function insert($table, $data, $replace = false)
    {
        if (empty($table)) {
            throw new \Exception('参数错误：待插入数据的表名不能为空');
        }
        if (!is_array($data) || count($data) < 1) {
            throw new \Exception('参数错误：插入的数据必须是一个不为空的数组');
        }
        //表名称
        $table = $this->trueTableName($table);
        $dataPre = [];
        //为键值添加:前缀
        foreach ($data as $key => $val) {
            $key = $this->addColon($key);
            $dataPre[$key] = $val;
        }
        //待插入数据数组中的键值组成的数组
        $fieldData = array_keys($data);
        //键值两边添加`
        $fieldData = array_map([$this, 'standardizeUDIField'], $fieldData);

        $field = implode(',', $fieldData);
        $holderData = array_keys($dataPre);
        $value = implode(',', $holderData);
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $sql = $cmd . ' ' . $table . ' (' . $field . ') VALUES (' . $value . ')';
        $this->_tmpSql = $sql;
        $stmt = $this->_db->prepare($sql);
        if (empty($stmt)) {
            throw new \Exception("数据库服务器执行prepare操作失败");
        }
        $cmdRes = $stmt->execute($dataPre);
        $stmt->closeCursor();
        if ($cmdRes === false) {
            throw new \Exception(implode(';', $stmt->errorInfo()));
        } else {
            return $this->_db->lastInsertId();
        }
    }

    /**
     * 执行批量添加记录操作
     * @param array $columns 待插入数据的字段名称
     * @param array $data 要增加的数据，二维数组。一个元素代表一条记录
     * 格式:array([0]=>array(值,值,值,值),[1]=>array(值,值,值,值),...);
     * @param string $table 数据表
     * @param boolean $replace 是否替换
     * @throws \Exception
     * @return boolean
     */
    public function batchInsert($table, $columns, $data, $replace = false)
    {
        if (empty($table)) {
            throw new \Exception('参数错误：表名不能为空');
        }
        if (!is_array($data) || count($data) < 1 || !isset($data[0])) {
            throw new \Exception('参数错误：待插入的数据必须是一个不为空的数组');
        }
        if (count($columns) !== count($data[0])) {
            throw new \Exception('参数错误：待插入数据的字段个数与待插入数据个数不一致');
        }
        //表名称
        $table = $this->trueTableName($table);
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        //为字段名称添加 `
        $nCols = array_map([$this, 'standardizeUDIField'], $columns);
        //sql语句
        $sql = $cmd . ' ' . $table . ' (' . implode(',', $nCols) . ') VALUES ';
        //生成和待插入值相同数量的别名（用于prepare，便于后面绑定参数值）
        for ($i = 0; $i < count($data); $i++) {
            //为字段名称添加:前缀，并添加序号。第一条记录的序号是0，第二条的序号是1。依次计算排列下去
            $newCols = array_map(function ($val) use ($i) {
                return $this->addColon($val, $i);
            }, $columns);
            //拼接待插入的value，一个括号内则是一条记录
            $sql .= '(' . implode(',', $newCols) . '), ';
        }
        $sql = trim($sql);
        //去除sql语句最后的 ,
        $sql = preg_match('/,$/', $sql) ? substr($sql, 0, -1) : $sql;
        $this->_tmpSql = $sql;
        //预处理sql语句
        $stmt = $this->_db->prepare($sql);
        if (empty($stmt)) {
            throw new \Exception("数据库服务器执行prepare操作失败");
        }
        $bindDict = [];
        //遍历待插入的值
        foreach ($data as $index => $dataVal) {
            foreach ($dataVal as $i => $val) {
                if (!isset($columns[$i])) {
                    continue;
                }
                //生成需要的绑定的参数名称
                $bindParamName = $this->addColon($columns[$i], $index);
                $bindDict[$bindParamName] = $val;
            }
        }
        $cmdRes = $stmt->execute($bindDict);
        $rows = $stmt->rowCount();
        $stmt->closeCursor();
        if ($cmdRes === false) {
            throw new \Exception(implode(';', $stmt->errorInfo()));
        } else {
            return $rows;
        }
    }

    /**
     * 执行更新记录操作
     * @param $table string 数据表
     * @param $data string|array 要更新的数据内容，参数可以为数组也可以为字符串。
     * @param $where string|array 更新数据时的条件
     * @throws \Exception
     * @return boolean
     */
    public function update($table, $data, $where = '')
    {
        if (empty($data) || empty($table)) {
            throw new \Exception('参数错误：待更新的表名、数据不能为空');
        }
        //需要更新的内容，$data支持数组和字符串
        list($field, $updateBindParams) = $this->parseUpdateFields($data);
        //表名称
        $table = $this->trueTableName($table);
        //支持数组和字符串形式的where条件参数
        list($whereStr, $whereBindParam) = $this->parseWhere($where);
        //初始化参数出现次数统计数组
        unset($this->_paramTimes);
        //拼接update查询语句
        $sql = 'UPDATE ' . $table . ' SET ' . $field . ' ' . $whereStr;
        $this->_tmpSql = $sql;
        //预处理查询语句
        $stmt = $this->_db->prepare($sql);
        if (empty($stmt)) {
            throw new \Exception("数据库服务器执行prepare操作失败");
        }
        //绑定的参数数组（参数名=>参数值）
        $bindParams = array_merge($updateBindParams, $whereBindParam);
        //执行查询语句，并将参数值绑定到对应的参数名称上
        $stmt->execute($bindParams);
        //影响行数
        $rows = $stmt->rowCount();
        //关闭游标，供下一个prepare语句使用
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * 删除记录
     * @param string $table 表名称
     * @param string|array $where where查询条件
     * @return int 影响行数
     * @throws \Exception
     */
    public function delete($table, $where)
    {
        if (empty($table)) {
            throw new \Exception('参数错误：表名不能为空');
        }
        $table = $this->trueTableName($table);
        list($whereStr, $bindParams) = $this->parseWhere($where);
        //初始化参数出现次数统计数组
        unset($this->_paramTimes);
        //拼接update查询语句
        $sql = 'DELETE FROM ' . $table . ' ' . $whereStr;
        $this->_tmpSql = $sql;
        //预处理查询语句
        $stmt = $this->_db->prepare($sql);
        if (empty($stmt)) {
            throw new \Exception("数据库服务器执行prepare操作失败");
        }
        //执行查询语句，并将参数值绑定到对应的参数名称上
        $stmt->execute($bindParams);
        //影响行数
        $rows = $stmt->rowCount();
        //关闭游标，供下一个prepare语句使用
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * 得到真实的表名称
     * @param $table
     * @return mixed|string
     */
    private function trueTableName($table)
    {
        //确定外部传入的表名称是否已经包含表前缀
        if (strpos($table, $this->_config['tablePrefix']) !== false) {
            return $this->addSpecialChar($table);
        } else {
            $table = ($this->_config['tablePrefix']) ? $this->_config['tablePrefix'] . $table : $table;
            return $this->addSpecialChar($table);
        }
    }


    /**
     * 解析update需要更新的参数
     * @param $data
     * @return array
     * @throws \Exception
     */
    private function parseUpdateFields($data)
    {
        if (!is_array($data) && !is_string($data)) {
            throw new \Exception('参数错误：更新的数据不可用');
        }
        //绑定的参数数组（参数名=>参数值）
        $bindParams = [];
        //需要更新的内容，$data支持数组和字符串
        if (is_array($data)) {
            $fields = [];//需要更新的内容，数组
            foreach ($data as $k => $v) {
                //判断是否有 'column1'=>'+=1'形式的元素
                switch (substr($v, 0, 2)) {
                    case '+=':
                        //截取要递增的值
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $k = $this->standardizeUDIField($k);
                            $fields[] = $k . '=' . $k . '+' . $v;
                        } else {
                            continue;
                        }
                        break;
                    case '-=':
                        //截取要递减的值
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $k = $this->standardizeUDIField($k);
                            $fields[] = $k . '=' . $k . '-' . $v;
                        } else {
                            continue;
                        }
                        break;
                    default:
                        $nk = $this->generateParamName($k);
                        $fields[] = $this->standardizeUDIField($k) . '=' . $nk;
                        $bindParams[$nk] = $v;
                }
            }
            $field = implode(',', $fields);
        } else {
            //字符串形式
            $field = $data;
        }
        return [$field, $bindParams];
    }

    /**
     * 为where子句添加WHERE关键字
     * @param $where
     * @return array
     * @throws \Exception
     */
    private function parseWhere($where)
    {
        list($whereSubStr, $bindParams) = $this->subWhereStatement($where);
        return ['WHERE ' . $whereSubStr, $bindParams];
    }

    /**
     * 解析where查询条件参数
     * 为数组时数组key为数据表字段名，数组值为字段值
     * 例: array('name'=>'phpcms','password'=>'123456')
     * 数组可使用array('name'=>'+=1', 'base'=>'-=1');程序会自动解析为`name` = `name` + 1, `base` = `base` - 1
     * 为字符串时，例：`name`='phpcms',`hits`=`hits`+1 。
     * @param string|array $where
     * @return array
     * @throws \Exception
     */
    private function subWhereStatement($where)
    {
        if (!is_string($where) && !is_array($where)) {
            throw new \Exception('参数错误：where条件不可用');
        }
        //解析where条件时，需要绑定的参数数组
        $bindParams = [];
        //支持数组和字符串形式的where条件参数
        if (is_string($where)) {
            $whereStr = "{$where}";
        } else {
            //判断条件拼接符（and/or）
            $symbol = 'AND';
            //如果WHERE条件参数数组的第一个元素是and或者or，则设置条件拼接符
            if (isset($where[0]) && is_string($where[0]) && (strtoupper($where[0]) === 'AND' || strtoupper($where[0]) === 'OR')) {
                $symbol = strtoupper($where[0]);
                //移除条件拼接符
                array_shift($where);
            }
            $whereDirt = [];//where参数数组
            //$keyOrderNumList = [];//where条件中可能多次出现同一个字段名。该数组用于统计每一个字段名出现的次数
            foreach ($where as $key => $val) {
                if (is_array($val)) {
                    //where条件元素值，如果是数组，则必须长度为3，第一个元素是操作符，第二个元素是字段名，第三个元素是字段的值
                    if (count($val) !== 3) {
                        continue;
                    }
                    //将操作符转换为大写
                    $upVal0 = strtoupper($val[0]);
                    if (!in_array($upVal0, ['=', '>', '<', '>=', '<=', '<>', '!=', 'IN', 'NOT IN'])) {
                        continue;
                    }
                    if (in_array($upVal0, ['=', '>', '<', '>=', '<=', '<>', '!='])) {
                        $nVal1 = $this->generateParamName($val[1]);
                        //where条件的子查询丢入数组中
                        $whereDirt[] = $this->standardizeField($val[1]) . $val[0] . $nVal1;
                        //设置绑定的参数和值
                        $bindParams[$nVal1] = $val[2];
                    } elseif (in_array($upVal0, ['IN', 'NOT IN'])) {
                        //支持in、not in查询
                        if (!is_array($val[2])) {
                            continue;
                        }
                        //为字符串的添加双引号
                        $val[2] = array_map([$this, 'addStrSign'], $val[2]);
                        $whereDirt[] = $this->standardizeField($val[1]) . ' ' . $upVal0 . ' ' . '(' . implode(',', $val[2]) . ')';
                    }
                } else {
                    //如果键是字符串，值是非数组类型
                    if (!is_string($key)) {
                        continue;
                    }
                    $nKey = $this->generateParamName($key);
                    //将where子查询添加到数组中
                    $whereDirt[] = $this->standardizeField($key) . ' = ' . $nKey;
                    $bindParams[$nKey] = $val;
                }
            }
            //拼接where查询条件
            $whereStr = '(' . implode(' ' . $symbol . ' ', $whereDirt) . ')';
        }
        return [$whereStr, $bindParams];
    }

    /**
     * 生成prepare语句中的参数名称。对于未在之前的操作中出现的参数名，前面加:，后面加上0。
     * 对于已经出现过的参数名，则通过统计序号。前面加:，后面加序号
     * @param string $rawName 字段名
     * @return string
     */
    private function generateParamName($rawName)
    {
        if (isset($this->_paramTimes[$rawName])) {
            //生成参数名时，加上出现的次数序号。没有则默认是0
            $paramName = $this->addColon($rawName, $this->_paramTimes[$rawName]);
            //将计数加1
            $this->_paramTimes[$rawName] = intval($this->_paramTimes[$rawName]) + 1;
        } else {
            $paramName = $this->addColon($rawName);
            //将字段名计数设置为1
            $this->_paramTimes[$rawName] = 1;
        }
        return $paramName;
    }

    /**
     * 对字段两边加反引号，以保证数据库安全
     * @param $value string 数组值
     * @return mixed|string
     */
    private function addSpecialChar($value)
    {
        return '`' . trim($value, " \t\n\r\0\x0B`") . '`';
    }

    /**
     * 确保更新、插入、删除操作的字段名称符合标准。为数据库名添加`，为表名添加前缀和`
     * @param $val
     * @return mixed|string
     */
    private function standardizeUDIField($val)
    {
        $joinDict = explode('.', $val);
        $len = count($joinDict);
        switch ($len) {
            case 1:
                //为字段名添加`
                return $this->addSpecialChar($val);
                break;
            case 2:
                //为表名称添加`和前缀，为字段名添加`
                return $this->trueTableName($joinDict[0]) . '.' . $this->addSpecialChar($joinDict[1]);
                break;
            case 3:
                //为数据库名添加`，为表名称添加`和前缀，为字段名添加`
                return $this->addSpecialChar($joinDict[0]) . '.' . $this->trueTableName($joinDict[1]) . '.' . $this->addSpecialChar($joinDict[2]);
                break;
            default:
                return $val;
                break;
        }
    }

    /**
     * 生成prepare语句中的参数名称
     * @param string $val 字段名称
     * @param int $suffix
     * @return string
     */
    private function addColon($val, $suffix = 0)
    {
        //如果给定的字段名称中有.号（例如：数据库.表名.字段名），则将其换成空
        $val = str_replace(['.', '`'], ['', ''], $val);
        return ':' . trim($val) . $suffix;
    }

    /**
     * 为字符串型的数据，添加双引号。可作为array_map的回调函数
     * @param $value
     * @return string
     */
    private function addStrSign($value)
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        } else {
            return $value;
        }
    }

    /**
     * @param string|array $fields 选择的字段
     * @throws \Exception
     * @return $this
     */
    public function select($fields)
    {
        if (!is_array($fields) && !is_string($fields)) {
            throw new \Exception('参数错误：字段名称参数不可用');
        }
        if (is_array($fields)) {
            $fields = $this->fieldAlias($fields);
            $fieldStr = implode(', ', $fields);
        } else {
            $fieldStr = $fields;
        }
        $this->_query['field'] = "SELECT {$fieldStr}";
        return $this;
    }

    /**
     * 为字段名设置别名。如果数组的键是字符串则添加AS关键字
     * @param $map
     * @return array
     */
    private function fieldAlias($map)
    {
        $aliasMap = [];
        foreach ($map as $key => $val) {
            if (is_string($key)) {
                $aliasMap[] = $this->standardizeField($key) . ' AS ' . $val;
            } else {
                $aliasMap[] = $this->standardizeField($val);
            }
        }
        return $aliasMap;
    }

    /**
     * 为表名设置别名
     * @param $table
     * @return array
     */
    private function tableAlias($table)
    {
        $aliasMap = [];
        foreach ($table as $key => $val) {
            if (is_string($key)) {
                $aliasMap[] = $this->standardizeTable($key) . ' AS ' . $val;
            } else {
                $aliasMap[] = $this->standardizeTable($val);
            }
        }
        return $aliasMap;
    }

    /**
     * 查询的表名
     * @param string|array $table 要查询的表名可以带别名，例如table as tab
     * @return $this
     */
    public function from($table)
    {
        if (is_string($table)) {
            //为表名添加表前缀
            $table = $this->trueTableName($table);
        } elseif (is_array($table)) {
            $table = $this->tableAlias($table);
            $table = implode(', ', $table);
        }
        $this->_query['table'] = "FROM {$table}";
        return $this;
    }

    /**
     *
     * 条件语句
     * @param string|array $where sql条件语句不需加where关键字
     * @return $this
     * @throws \Exception
     */
    public function where($where = '')
    {
        list($whereStr, $bindParams) = $this->parseWhere($where);
        $this->_bindParams = array_merge($this->_bindParams, $bindParams);
        $this->_query['where'] = $whereStr;
        return $this;
    }

    /**
     * 添加where条件，中间连接词为AND
     * @param string $where
     * @return $this|bool
     * @throws \Exception
     */
    public function andWhere($where = '')
    {
        list($whereStr, $bindParams) = $this->subWhereStatement($where);
        $this->_bindParams = array_merge($this->_bindParams, $bindParams);
        $this->_query['andWhere'][] = $whereStr;
        return $this;
    }

    /**
     * 添加where条件，中间连接词为OR
     * @param string $where
     * @return $this|bool
     * @throws \Exception
     */
    public function orWhere($where = '')
    {
        list($whereStr, $bindParams) = $this->subWhereStatement($where);
        $this->_bindParams = array_merge($this->_bindParams, $bindParams);
        $this->_query['orWhere'][] = $whereStr;
        return $this;
    }

    /**
     * 排序
     * @param string|array $order
     * @throws \Exception
     * @return $this
     */
    public function orderBy($order)
    {
        if (!is_array($order) && !is_string($order)) {
            throw new \Exception('参数错误：order by参数不可用');
        }
        if (is_string($order) && $order) {
            $this->_query['orderBy'] = "ORDER BY {$order}";
        } elseif (is_array($order) && !empty($order)) {
            $orderDirt = [];
            foreach ($order as $column => $orderSign) {
                $orderDirt[] = $this->addSpecialChar($column) . ' ' . $orderSign;
            }
            $orderStr = implode(',', $orderDirt);
            $this->_query['orderBy'] = "ORDER BY {$orderStr}";
        }
        return $this;
    }

    /**
     * 按某字段分组
     * @param string $field 分组的字符按名称
     * @throws \Exception
     * @return $this|bool
     */
    public function groupBy($field)
    {
        if (!is_string($field)) {
            throw new \Exception('参数错误：group by参数不可用');
        }
        $this->_query['groupBy'] = "GROUP BY {$this->addSpecialChar($field)}";
        return $this;
    }

    /**
     * 联合查询（可反复调用该方法，关联多张表）
     * @param string $table 要关联的表名称
     * @param array $on 要关联的字段
     * @param string $type 关联的类型
     * @throws \Exception
     * @return $this
     */
    public function join($table, $on, $type = 'LEFT JOIN')
    {
        if (!is_string($table)) {
            throw new \Exception('参数错误：要关联的表名必须是一个字符串');
        }
        if (!is_array($on) || empty($on)) {
            throw new \Exception('参数错误：on语句参数不能为空');
        }
        $table = $this->trueTableName($table);
        $joinFrom = array_keys($on);
        $joinTo = array_values($on);
        $joinStr = strtoupper($type) . ' ' . $table . ' ON ' . $this->standardizeField($joinFrom[0]) . '=' . $this->standardizeField($joinTo[0]);
        $this->_query['join'][] = $joinStr;
        return $this;
    }

    /**
     * 确保字段名符合标准。
     * @param $val
     * @return mixed|string
     */
    private function standardizeField($val)
    {
        if (strpos($val, '.') !== false) {
            return $val;
        } else {
            return $this->addSpecialChar($val);
        }
    }

    /**
     * 确保表名符合标准。为数据库名添加`，为表名添加前缀和`
     * @param $val
     * @return mixed|string
     */
    private function standardizeTable($val)
    {
        $joinDict = explode('.', $val);
        $len = count($joinDict);
        if ($len === 1) {
            //为表名添加前缀和`
            return $this->trueTableName($val);
        } elseif ($len === 2) {
            return $this->addSpecialChar($joinDict[0]) . '.' . $this->trueTableName($joinDict[1]);
        } else {
            return $val;
        }
    }

    /**
     * 关联表（右联）
     * @param string $table 要关联的表名称
     * @param array $on 要关联的字段
     * @throws \Exception
     * @return $this
     */
    public function rightJoin($table, $on)
    {
        return $this->join($table, $on, 'RIGHT JOIN');
    }

    /**
     * 关联表（左联）
     * @param string $table 要关联的表名称
     * @param array $on 要关联的字段
     * @throws \Exception
     * @return $this
     */
    public function leftJoin($table, $on)
    {
        return $this->join($table, $on, 'LEFT JOIN');
    }

    /**
     * 关联表（内联）
     * @param string $table 要关联的表名称
     * @param array $on 要关联的字段
     * @throws \Exception
     * @return $this
     */
    public function innerJoin($table, $on)
    {
        return $this->join($table, $on, 'INNER JOIN');
    }

    /**
     * 获取条数
     * @param int $limit 格式0,5
     * @param int $offset
     * @throws \Exception
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        if (!is_int($limit)) {
            throw new \Exception('参数错误：limit参数不可用');
        }
        if ($limit) {
            $this->_query['limit'] = "LIMIT {$offset},{$limit}";
        }
        return $this;
    }

    /**
     * prepare语句，执行语句，并绑定参数
     * @return \PDOStatement
     * @throws \Exception
     */
    private function queryPrepare()
    {
        $sql = $this->buildSql();
        //预处理查询语句
        $stmt = $this->_db->prepare($sql);
        if (empty($stmt)) {
            throw new \Exception($this->_db->errorInfo());
        }
        //执行查询语句，并将参数值绑定到对应的参数名称上
        $stmt->execute($this->_bindParams);
        //重置为空数组
        $this->_bindParams = [];
        return $stmt;
    }

    /**
     * 计算结果集总数
     * @return int
     * @throws \Exception
     */
    public function count()
    {
        $stmt = $this->queryPrepare();
        $rows = $stmt->rowCount();
        //关闭游标，供下一个prepare语句使用
        $stmt->closeCursor();
        return $rows;
    }

    /**
     * 得到结果集中的第一条记录
     * @return array
     * @throws \Exception
     */
    public function one()
    {
        $stmt = $this->queryPrepare();
        //结果集数组
        $oneRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        //关闭游标，供下一个prepare语句使用
        $stmt->closeCursor();
        return $oneRow;
    }

    /**
     * 得到结果集中的所有记录
     * @return array
     * @throws \Exception
     */
    public function all()
    {
        $stmt = $this->queryPrepare();
        //结果集数组
        $resRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        //关闭游标，供下一个prepare语句使用
        $stmt->closeCursor();
        return $resRows;
    }

    /**
     * 构造sql语句
     * @return string 返回sql语句
     */
    public function buildSql()
    {
        //默认查询所有字段
        $fields = (isset($this->_query['field']) && !empty($this->_query['field'])) ? $this->_query['field'] : 'SELECT *';
        $sql = $fields . ' ' . $this->_query['table'];
        //拼接join子句
        if (isset($this->_query['join']) && !empty($this->_query['join'])) {
            foreach ($this->_query['join'] as $join) {
                $sql .= " {$join}";
            };
        }
        //拼接where子句
        if (isset($this->_query['where']) && !empty($this->_query['where'])) {
            $sql .= ' ' . $this->_query['where'];
        }
        if (isset($this->_query['andWhere']) && !empty($this->_query['andWhere'])) {
            foreach ($this->_query['andWhere'] as $andWhere) {
                if (empty($andWhere)) {
                    continue;
                }
                $sql .= ' AND ' . $andWhere;
            }
        }
        if (isset($this->_query['orWhere']) && !empty($this->_query['orWhere'])) {
            foreach ($this->_query['orWhere'] as $orWhere) {
                if (empty($orWhere)) {
                    continue;
                }
                $sql .= ' OR ' . $orWhere;
            }
        }
        //拼接group by子句
        if (isset($this->_query['groupBy']) && !empty($this->_query['groupBy'])) {
            $sql .= ' ' . $this->_query['groupBy'];
        }
        //拼接order by子句
        if (isset($this->_query['orderBy']) && !empty($this->_query['orderBy'])) {
            $sql .= ' ' . $this->_query['orderBy'];
        }
        //拼接limit子句
        if (isset($this->_query['limit']) && !empty($this->_query['limit'])) {
            $sql .= ' ' . $this->_query['limit'];
        }
        $this->_query = null;
        //销毁参数出现次数统计数组
        $this->_paramTimes = [];
        $this->_tmpSql = $sql;
        return $sql;
    }

    /**
     * 获得最后执行的一条sql语句
     * @return string
     */
    public function lastSql()
    {
        return $this->_tmpSql;
    }

    /**
     * 关闭PDO连接
     */
    public function __destruct()
    {
        $this->_db = null;
    }

    /**
     * 开始一个事务
     * @return bool
     */
    public function beginTransaction(){
        return $this->_db->beginTransaction();
    }

    /**
     * 回滚一个事务
     * @return bool
     */
    public function rollback(){
        if($this->_db->inTransaction()){
            return $this->_db->rollBack();
        }else{
            return false;
        }
    }

    /**
     * 提交事务
     * @return bool
     */
    public function commit(){
        if($this->_db->inTransaction()){
            return $this->_db->commit();
        }else{
            return false;
        }
    }
}