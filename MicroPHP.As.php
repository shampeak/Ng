<?php

/**
 * 一些有用的
 *
 */



/**
 * Description of WoniuTableModel
 *
 * @author pm
 */
class MpTableModel extends MpModel {
    /**
     * 表主键名称
     * @var string
     */
    public $pk;
    /**
     * 表的字段名称数组
     * @var array
     */
    public $keys = array();
    /**
     * 不含表前缀的表名称
     * @var string
     */
    public $table;
    /**
     * 含表前缀的表名称
     * @var string
     */
    public $full_table;
    /**
     * 字段映射，$key是表单name名称，$val是字段名
     * @var array
     */
    public $map = array();
    /**
     * 当前$this->db使用的表前缀
     * @var string
     */
    public $prefix;
    /**
     * 完整的表字段信息
     * @var array
     */
    public $fields = array();
    private static $models = array(), $table_cache = array();
    /**
     * 初始化一个表模型，返回模型实例
     * @param type $table         名称
     * @param CI_DB_active_record $db 数据库连接对象
     * @return MpTableModel
     */
    public function init($table, $db = null) {
        if (is_null($this->db)) {
            $this->database();
        }
        if (!is_null($db)) {
            $this->db = $db;
        }
        $this->prefix = $this->db->dbprefix;
        $this->table = $table;
        $this->full_table = $this->prefix . $table;
        $this->fields = $fields = $this->getTableFieldsInfo($table, $this->db);
        foreach ($fields as $col => $info) {
            if ($info['primary']) {
                $this->pk = $col;
            }
            $this->keys[] = $col;
            $this->map[$col] = $col;
        }
        return $this;
    }
    /**
     * 实例化一个默认表模型
     * @param type $table
     * @return MpTableModel
     */
    public static function M($table, $db = null) {
        if (!isset(self::$models[$table])) {
            self::$models[$table] = new MpTableModel();
            self::$models[$table]->init($table, $db);
        }
        return self::$models[$table];
    }
    /**
     * 表所有字段数组
     * @return array
     */
    public function columns() {
        return $this->keys;
    }
    /**
     * 缓存表字段信息，并返回
     * @staticvar array $info  字段信息数组
     * @param type $tableName  不含前缀的表名称
     * @return array
     */
    public static function getTableFieldsInfo($tableName, $db) {
        if (!empty(self::$table_cache[$tableName])) {
            return self::$table_cache[$tableName];
        }
        if (!file_exists($cache_file = systemInfo('table_cache_folder') . DIRECTORY_SEPARATOR . $tableName . '.php')) {
            $info = array();
            $result = $db->query('SHOW FULL COLUMNS FROM ' . $db->dbprefix . $tableName)->result_array();
            if ($result) {
                foreach ($result as $val) {
                    $info[$val['Field']] = array(
                        'name' => $val['Field'],
                        'type' => $val['Type'],
                        'comment' => $val['Comment'] ? $val['Comment'] : $val['Field'],
                        'notnull' => $val['Null'] == 'NO' ? 1 : 0,
                        'default' => $val['Default'],
                        'primary' => (strtolower($val['Key']) == 'pri'),
                        'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
                    );
                }
            }
            $content = 'return ' . var_export($info, true) . ";\n";
            $content = '<?' . 'php' . "\n" . $content;
            file_put_contents($cache_file, $content);
            $ret_info[$tableName] = $info;
        } else {
            $ret_info[$tableName] = include ($cache_file);
        }
        return $ret_info[$tableName];
    }
    /**
     * 数据验证
     * @param type $source_data 数据源，要检查的数据
     * @param type $ret_data    数据验证通过$ret_data是验证规则处理后的数据用户插入或者更新到数据库,数据验证失败$ret_data是空数组
     * @param type $rule 验证规则<br/>
     *                   格式：array(<br/>
     *                               '字段名称'=>array(<br/>
     *                                               '表单验证规则'=>'验证失败提示信息'<br/>
     *                                               ,...   <br/>
     *                                               )<br/>
     *                               ,...<br/>
     *                             )<br/>
     * @param type $map  字段映射信息数组。格式：array('表单name名称'=>'表字段名称',...)
     * @return string 返回null:验证通过。非空字符串:验证失败提示信息。
     */
    public function check($source_data, &$ret_data, $rule = null, $map = null) {
        $rule = !is_array($rule) ? array() : $rule;
        $map = is_null($map) ? $this->map : $map;
        $data = $this->readData($map, $source_data);
        return $this->checkData($rule, $data, $ret_data);
    }
    /**
     * 添加数据
     * @param array $ret_data  需要添加的数据
     * @return boolean
     */
    public function insert($ret_data) {
        return $this->db->insert($this->table, $ret_data);
    }
    /**
     * 更新数据
     * @param type $ret_data  需要更新的数据
     * @param type $where     可以是where条件关联数组，还可以是主键值。
     * @return boolean
     */
    public function update($ret_data, $where) {
        $where = is_array($where) ? $where : array($this->pk => $where);
        return $this->db->where($where)->update($this->table, $ret_data);
    }
    /**
     * 获取一条或者多条数据
     * @param type $values      可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean $is_rows  返回多行记录还是单行记录，true：多行，false：单行
     * @param type $order_by    当返回多行记录时，可以指定排序，比如：'time desc'
     * @return int
     */
    public function find($values, $is_rows = false, $order_by = null) {
        if (empty($values)) {
            return 0;
        }
        if (is_array($values)) {
            $is_asso = array_diff_assoc(array_keys($values), range(0, sizeof($values))) ? TRUE : FALSE;
            if ($is_asso) {
                $this->db->where($values);
            } else {
                $is_rows = true;
                $this->db->where_in($this->pk, array_values($values));
            }
        } else {
            $this->db->where(array($this->pk => $values));
        }
        if ($order_by) {
            $this->db->order_by($order_by);
        }
        if (!$is_rows) {
            $this->db->limit(1);
        }
        $rs = $this->db->get($this->table);
        if ($is_rows) {
            return $rs->result_array();
        } else {
            return $rs->row_array();
        }
    }
    /**
     * 获取所有数据
     * @param type $where   where条件数组
     * @param type $orderby 排序，比如: id desc
     * @param type $limit   limit数量，比如：10
     * @param type $fileds  要搜索的字段，比如：id,name。留空默认*
     * @return type
     */
    public function findAll($where = null, $orderby = NULL, $limit = null, $fileds = null) {
        if (!is_null($fileds)) {
            $this->db->select($fileds);
        }
        if (!is_null($where)) {
            $this->db->where($where);
        }
        if (!is_null($orderby)) {
            $this->db->order_by($orderby);
        }
        if (!is_null($limit)) {
            $this->db->limit($limit);
        }
        return $this->db->get($this->table)->result_array();
    }
    /**
     * 根据条件获取一个字段的值或者数组
     * @param type $col         字段名称
     * @param type $where       可以是一个主键的值或者主键的值数组，还可以是where条件
     * @param boolean $is_rows  返回多行记录还是单行记录，true：多行，false：单行
     * @param type $order_by    当返回多行记录时，可以指定排序，比如：'time desc'
     * @return type
     */
    public function findCol($col, $where, $is_rows = false, $order_by = null) {
        $row = $this->find($where, $is_rows, $order_by);
        if (!$is_rows) {
            return isset($row[$col]) ? $row[$col] : null;
        } else {
            $vals = array();
            foreach ($row as $v) {
                $vals[] = $v[$col];
            }
            return $vals;
        }
    }
    /**
     *
     * 根据条件删除记录
     * @param type $values 可以是一个主键的值或者主键主键的值数组
     * @param type $cond   附加的where条件，关联数组
     * 成功则返回影响的行数，失败返回false
     */
    public function delete($values, Array $cond = NULL) {
        return $this->deleteIn($this->pk, $values, $cond);
    }
    /**
     *
     * 根据条件删除记录
     * @param type $key    where in的字段名称
     * @param type $values 可以是一个主键的值或者主键主键的值数组
     * @param type $cond   附加的where条件，关联数组
     * 成功则返回影响的行数，失败返回false
     * @return int|boolean
     */
    public function deleteIn($key, $values, Array $cond = NULL) {
        if (empty($values)) {
            return 0;
        }
        if (is_array($values)) {
            $this->db->where_in($key, array_values($values));
        } else {
            $this->db->where(array($key => $values));
        }
        if (!empty($cond)) {
            $this->db->where($cond);
        }
        if ($this->db->delete($this->table)) {
            return $this->db->affected_rows();
        } else {
            return false;
        }
    }
    /**
     * 分页方法
     * @param int $page       第几页
     * @param int $pagesize   每页多少条
     * @param string $url     基础url，里面的{page}会被替换为实际的页码
     * @param string $fields  select的字段，全部用*，多个字段用逗号分隔
     * @param array $where    where条件，关联数组
     * @param array $like     搜素的字段，比如array('title'=>'java');搜索title包含java
     * @param string $orderby 排序字段，比如: 'id desc'
     * @param array $page_bar_order   分页条组成，可以参考手册分页条部分
     * @param int   $page_bar_a_count 分页条a的数量，可以参考手册分页条部分
     * @return type
     */
    public function getPage($page, $pagesize, $url, $fields = '*', Array $where = null, Array $like = null, $orderby = null, $page_bar_order = array(1, 2, 3, 4, 5, 6), $page_bar_a_count = 10) {
        $data = array();
        if (is_array($where)) {
            $this->db->where($where);
        }
        if (is_array($like)) {
            $this->db->like($like);
        }
        $total = $this->db->from($this->table)->count_all_results();
        //这里必须重新附加条件，上面的count会重置条件
        if (is_array($where)) {
            $this->db->where($where);
        }
        if (is_array($like)) {
            $this->db->like($like);
        }
        if (!is_null($orderby)) {
            $this->db->order_by($orderby);
        }
        $data['items'] = $this->db->select($fields)->limit($pagesize, ($page - 1) * $pagesize)->get($this->table)->result_array();
        $data['page'] = $this->page($total, $page, $pagesize, $url, $page_bar_order, $page_bar_a_count);
        return $data;
    }
    /**
     * SQL搜索
     * @param type $page      第几页
     * @param type $pagesize  每页多少条
     * @param type $url       基础url，里面的{page}会被替换为实际的页码
     * @param type $fields    select的字段，全部用*，多个字段用逗号分隔
     * @param type $cond      SQL语句where后面的部分，不要带limit
     * @param array $page_bar_order   分页条组成，可以参考手册分页条部分
     * @param int   $page_bar_a_count 分页条a的数量，可以参考手册分页条部分
     * @return type
     */
    public function search($page, $pagesize, $url, $fields, $cond, $page_bar_order = array(1, 2, 3, 4, 5, 6), $page_bar_a_count = 10) {
        $data = array();
        $table = $this->full_table;
        $query = $this->db->query('select count(*) as total from ' . $table . (strpos(trim($cond), 'order') === 0 ? '' : ' where') . $cond)->row_array();
        $total = $query['total'];
        $data['items'] = $this->db->query('select ' . $fields . ' from ' . $table . (strpos(trim($cond), 'order') === 0 ? '' : ' where') . $cond . ' limit ' . (($page - 1) * $pagesize) . ',' . $pagesize)->result_array();
        $data['page'] = $this->page($total, $page, $pagesize, $url, $page_bar_order, $page_bar_a_count);
        return $data;
    }
}
/* End of file Model.php */


/**
 * 表单规则助手类，再不用记忆规则名称
 */
class WoniuRule {
    /**
     * 规则说明：<br/>
     * 如果元素为空，则返回FALSE<br/><br/><br/>
     */
    public static function required() {
        return 'required';
    }
    /**
     * 规则说明：<br/>
     * 当没有post对应字段的值或者值为空的时候那么就会使用默认规则的值作为该字段的值。<br/>
     * 然后用这个值继续 后面的规则进行验证。<br/>
     * @param string $val 默认值<br/><br/><br/>
     */
    public static function defaultVal($val = '') {
        return 'default[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 可以为空规则。例如user字段规则中有optional,当没有传递字段user的值或者值是空的时候，<br/>
     * user验证会通过(忽略其它规则即使有required规则)， <br/>
     * 提示： <br/>
     * $this->checkData($rule, $_POST, $ret_data)返回的数据$ret_data， <br/>
     * 如果传递了user字段$ret_data就有user字段，反之没有user字段. <br/>
     * 如果user传递有值，那么就会用这个值继续后面的规则进行验证。<br/><br/><br/>
     */
    public static function optional() {
        return 'optional';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素的值与参数中对应的表单字段的值不相等，则返回FALSE<br/>
     * @param string $field_name 表单字段名称<br/><br/><br/>
     */
    public static function match($field_name) {
        return 'match[' . $field_name . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素的值不与指定的值相等，则返回FALSE<br/>
     * @param string $val 指定的值<br/><br/><br/>
     */
    public static function equal($val) {
        return 'equal[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不在指定的几个值中，则返回FALSE<br/>
     * @param string $val 规则内容,多个值用逗号分割，或者用第个参数指定的分割符<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function enum($val, $delimiter = '') {
        return 'enum[' . $val . ']' . $delimiter;
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素的值与指定数据表栏位有重复，则返回False<br/>
     * 比如unique[user.email]，那么验证类会去查找user表中email字段有没有与表单元素一样的值，<br/>
     * 如存重复，则返回false，这样开发者就不必另写callback验证代码。<br/>
     * 如果指定了id:1,那么除了id为1之外的记录的email字段不能与表单元素一样，<br/>
     * 如果一样返回false<br/>
     * @param string $val 规则内容，比如：1、table.field 2、table.field,id:1<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function unique($val, $delimiter = '') {
        return 'unique[' . $val . ']' . $delimiter;
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素的值在指定数据表的字段中不存在则返回false，如果存在返回true<br/>
     * 比如exists[cat.cid]，那么验证类会去查找cat表中cid字段有没有与表单元素一样的值<br/>
     * cat.cid后面还可以指定附加的where条件<br/>
     * 比如：exists[users.uname,user_id:2,...] 可以多个条件，逗号分割。<br/>
     * 上面的规测生成的where就是array('uname'=>$value,'user_id'=>2,....)<br/>
     * @param string $val 规则内容，比如：1、table.field 2、table.field,id:1<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function exists($val, $delimiter = '') {
        return 'exists[' . $val . ']' . $delimiter;
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值的字符长度小于参数定义的值，则返回FALSE<br/>
     * @param int $val 长度数值<br/><br/><br/>
     */
    public static function min_len($val) {
        return 'min_len[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值的字符长度小于参数定义的值，则返回FALSE<br/>
     * @param int $val 长度数值<br/><br/><br/>
     */
    public static function max_len($val) {
        return 'min_len[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值的字符长度不在指定的范围，则返回FALSE<br/>
     * @param int $min_len 最小长度数值<br/>
     * @param int $max_len 最大长度数值<br/><br/><br/>
     */
    public static function range_len($min_len, $max_len) {
        return 'range_len[' . $min_len . ',' . $max_len . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值的字符长度不是指定的长度，则返回FALSE<br/>
     * @param int $val 长度数值<br/><br/><br/>
     */
    public static function len($val) {
        return 'len[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是数字或者小于指定的值，则返回FALSE<br/>
     * @param int $val 数值<br/><br/><br/>
     */
    public static function min($val) {
        return 'min[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是数字或者大于指定的值，则返回FALSE<br/>
     * @param int $val 数值<br/><br/><br/>
     */
    public static function max($val) {
        return 'max[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是数字或者大小不在指定的范围内，则返回 FALSE<br/>
     * @param int $min 最小数值<br/>
     * @param int $max 最大数值<br/><br/><br/>
     */
    public static function range($min, $max) {
        return 'range[' . $min . ',' . $max . ']';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中包含除字母以外的字符，则返回FALSE<br/><br/><br/>
     */
    public static function alpha() {
        return 'alpha';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中包含除字母和数字以外的字符，则返回FALSE<br/><br/><br/>
     */
    public static function alpha_num() {
        return 'alpha_num';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值中包含除字母/数字/下划线/破折号以外的其他字符，则返回FALSE<br/><br/><br/>
     */
    public static function alpha_dash() {
        return 'alpha_dash';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中不是字母开头，则返回FALSE<br/><br/><br/>
     */
    public static function alpha_start() {
        return 'alpha_start';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中不是纯数字，则返回FALSE<br/><br/><br/>
     */
    public static function num() {
        return 'num';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中不是整数，则返回FALSE<br/><br/><br/>
     */
    public static function int() {
        return 'int';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中不是小数，则返回FALSE<br/><br/><br/>
     */
    public static function float() {
        return 'float';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素中不是一个数，则返回FALSE<br/><br/><br/>
     */
    public static function numeric() {
        return 'numeric';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值中包含了非自然数的其他数值 （其他数值不包括零），则返回FALSE。<br/><br/><br/>
     * 自然数形如：0,1,2,3....等等。
     */
    public static function natural() {
        return 'natural';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值包含了非自然数的其他数值 （其他数值包括零），则返回FALSE。<br/><br/><br/>
     * 非零的自然数：1,2,3.....等等。
     */
    public static function natural_no_zero() {
        return 'natural_no_zero';
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个网址，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function url($can_empty = false) {
        return self::can_empty_rule('url', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值包含不合法的email地址，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function email($can_empty = false) {
        return self::can_empty_rule('email', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个QQ号，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function qq($can_empty = false) {
        return self::can_empty_rule('qq', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个电话号码，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function phone($can_empty = false) {
        return self::can_empty_rule('phone', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个手机号，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function mobile($can_empty = false) {
        return self::can_empty_rule('mobile', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个邮政编码，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function zipcode($can_empty = false) {
        return self::can_empty_rule('zipcode', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个身份证号，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function idcard($can_empty = false) {
        return self::can_empty_rule('idcard', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是一个合法的IPv4地址，则返回FALSE。<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function ip($can_empty = false) {
        return self::can_empty_rule('ip', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是汉字，或者不是指定的长度，则返回FALSE<br/>
     * 规则示例：<br/>
     * 1.规则内容：false    描述：必须是汉字，不能为空<br/>
     * 2.规则内容：true     描述：必须是汉字，可以为空<br/>
     * 3.规则内容：false,2  描述：必须是2个汉字，不能为空<br/>
     * 4.规则内容：true,2   描述：必须是2个汉字，可以为空<br/>
     * 5.规则内容：true,2,3 描述：必须是2-3个汉字，可以为空<br/>
     * 6.规则内容：false,2, 描述：必须是2个以上汉字，不能为空<br/>
     * @param boolean $val 规则内容。默认为空，即规则：必须是汉字不能为空<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function chs($val = '', $delimiter = '') {
        return 'chs' . ($val ? '[' . $val . ']' . $delimiter : '');
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是正确的日期格式YYYY-MM-DD，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function date($can_empty = false) {
        return self::can_empty_rule('date', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是正确的日期时间格式YYYY-MM-DD HH:MM:SS，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function datetime($can_empty = false) {
        return self::can_empty_rule('datetime', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不是正确的时间格式HH:MM:SS，则返回FALSE<br/>
     * @param boolean $can_empty 是否允许为空。true:允许 false:不允许。默认：false<br/><br/><br/>
     */
    public static function time($can_empty = false) {
        return self::can_empty_rule('time', $can_empty);
    }
    /**
     * 规则说明：<br/>
     * 如果表单元素值不匹配指定的正则表达式，则返回FALSE<br/>
     * @param string $val 正则表达式。比如：1./^[]]$/ 2./^A$/i<br/>
     * 模式修正符说明:<br/>
     * i 表示在和模式进行匹配进不区分大小写<br/>
     * m 将模式视为多行，使用^和$表示任何一行都可以以正则表达式开始或结束<br/>
     * s 如果没有使用这个模式修正符号，元字符中的"."默认不能表示换行符号,将字符串视为单行<br/>
     * x 表示模式中的空白忽略不计<br/>
     * e 正则表达式必须使用在preg_replace替换字符串的函数中时才可以使用(讲这个函数时再说)<br/>
     * A 以模式字符串开头，相当于元字符^<br/>
     * Z 以模式字符串结尾，相当于元字符$<br/>
     * U 正则表达式的特点：就是比较“贪婪”，使用该模式修正符可以取消贪婪模式<br/><br/><br/>
     */
    public static function reg($val) {
        return 'reg[' . $val . ']';
    }
    /**
     * 规则说明：<br/>
     * 数据在验证之前处理数据的规则，数据在验证的时候验证的是处理过的数据<br/>
     * 注意：<br/>
     * set和set_post后面是一个或者多个函数或者方法，多个逗号分割<br/>
     * 1.无论是函数或者方法都必须有一个字符串返回<br/>
     * 2.如果是系统函数，系统会传递当前值给系统函数，因此系统函数必须是至少接受一个字符串参数<br/>
     * 3.如果是自定义的函数，系统会传递当前值和全部数据给自定义的函数，因此自定义函数可以接收两个参数第一个是值，第二个是全部数据$data<br/>
     * 4.如果是类的方法写法是：类名称::方法名 （方法静态动态都可以，public，private，都可以）<br/>
     * @param string $val 规则内容。比如：trim<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function set($val, $delimiter = '') {
        return 'set[' . $val . ']' . $delimiter;
    }
    /**
     * 规则说明：<br/>
     * 数据在验证通过之后处理数据的规则，$this->checkData()第三个变量接收的就是set和set_post处理过的数据<br/>
     * 注意：<br/>
     * set和set_post后面是一个或者多个函数或者方法，多个逗号分割<br/>
     * 1.无论是函数或者方法都必须有一个字符串返回<br/>
     * 2.如果是系统函数，系统会传递当前值给系统函数，因此系统函数必须是至少接受一个字符串参数<br/>
     * 3.如果是自定义的函数，系统会传递当前值和全部数据给自定义的函数，因此自定义函数可以接收两个参数第一个是值，第二个是全部数据$data<br/>
     * 4.如果是类的方法写法是：类名称::方法名 （方法静态动态都可以，public，private，都可以）<br/>
     * @param string $val 规则内容。比如：sha1,md5<br/>
     * @param string $delimiter 规则内容的分割符，比如：# ，默认为空即可<br/><br/><br/>
     */
    public static function set_post($val, $delimiter = '') {
        return 'set_post[' . $val . ']' . $delimiter;
    }
    private static function can_empty_rule($rule_name, $can_empty) {
        return $rule_name . ($can_empty ? '[true]' : '');
    }
}
class MpRule extends WoniuRule{}

