<?php





class WoniuDB {
    private static $conns = array();
    public static function &getInstance($config, $force_new_conn = false) {
        $default['dbdriver'] = "mysql";
        $default['hostname'] = '127.0.0.1';
        $default['port'] = '3306';
        $default['username'] = 'root';
        $default['password'] = '';
        $default['database'] = 'test';
        $default['dbprefix'] = '';
        $default['pconnect'] = TRUE;
        $default['db_debug'] = TRUE;
        $default['char_set'] = 'utf8';
        $default['dbcollat'] = 'utf8_general_ci';
        $default['swap_pre'] = '';
        $default['autoinit'] = TRUE;
        $default['stricton'] = FALSE;
        $config=  array_merge($default,$config);
        $class = 'CI_DB_' . $config['dbdriver'] . '_driver';
        if(!class_exists($class, false)){
            return null;
        }
        $config0=$config;
        asort($config0);
        $hash = md5(sha1(var_export($config0, TRUE)));
        if ($force_new_conn || !isset(self::$conns[$hash])) {
            self::$conns[$hash] = new $class($config);
        }
        if ($config['dbdriver'] == 'pdo' && strpos($config['hostname'], 'mysql') !== FALSE) {
            self::$conns[$hash]->simple_query('set names ' . $config['char_set']);
        }
        return self::$conns[$hash];
    }
}
/**
 * CI_DB_mysql_driver -> CI_DB -> CI_DB_active_record -> CI_DB_driver
 * CI_DB_mysql_result -> Woniu_DB_result -> CI_DB_result
 */
class CI_DB extends CI_DB_active_record {

}
/**
 * Database Driver Class
 *
 * This is the platform-independent base DB implementation class.
 * This class will not be called directly. Rather, the adapter
 * class for the specific database will extend and instantiate it.
 *
 * @package                CodeIgniter
 * @subpackage        Drivers
 * @category        Database
 * @author                ExpressionEngine Dev Team
 * @link                http://codeigniter.com/user_guide/database/
 */
class CI_DB_driver {
    var $username;
    var $password;
    var $hostname;
    var $database;
    var $dbdriver = 'mysql';
    var $dbprefix = '';
    var $char_set = 'utf8';
    var $dbcollat = 'utf8_general_ci';
    var $autoinit = TRUE; // Whether to automatically initialize the DB
    var $swap_pre = '';
    var $port = '';
    var $pconnect = FALSE;
    var $conn_id = FALSE;
    var $result_id = FALSE;
    var $db_debug = FALSE;
    var $benchmark = 0;
    var $query_count = 0;
    var $bind_marker = '?';
    var $save_queries = TRUE;
    var $queries = array();
    var $query_times = array();
    var $data_cache = array();
    var $trans_enabled = TRUE;
    var $trans_strict = TRUE;
    var $_trans_depth = 0;
    var $_trans_status = TRUE; // Used with transactions to determine if a rollback should occur
    var $_protect_identifiers = TRUE;
    var $_reserved_identifiers = array('*'); // Identifiers that should NOT be escaped
    var $stmt_id;
    var $curs_id;
    var $limit_used;
    /**
     * Constructor.  Accepts one parameter containing the database
     * connection settings.
     *
     * @param array
     */
    function __construct($params) {
        if (is_array($params)) {
            foreach ($params as $key => $val) {
                $this->$key = $val;
            }
        }
        log_message('debug', 'Database Driver Class Initialized');
    }
    /**
     * Initialize Database Settings
     *
     * @access        private Called by the constructor
     * @param        mixed
     * @return        void
     */
    function initialize() {
        if (is_resource($this->conn_id) OR is_object($this->conn_id)) {
            return TRUE;
        }
// ----------------------------------------------------------------
        $this->conn_id = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
        if (!$this->conn_id) {
            log_message('error', 'Unable to connect to the database');
            if ($this->db_debug || systemInfo('error_manage')) {
                $this->display_error('db_unable_to_connect');
            }
            return FALSE;
        }
// ----------------------------------------------------------------
        if ($this->database != '') {
            if (!$this->db_select()) {
                log_message('error', 'Unable to select database: ' . $this->database);
                if ($this->db_debug || systemInfo('error_manage')) {
                    $this->display_error('db_unable_to_select', $this->database);
                }
                return FALSE;
            } else {
                if (!$this->db_set_charset($this->char_set, $this->dbcollat)) {
                    return FALSE;
                }
                return TRUE;
            }
        }
        return TRUE;
    }
    /**
     * Set client character set
     *
     * @access        public
     * @param        string
     * @param        string
     * @return        resource
     */
    function db_set_charset($charset, $collation) {
        if (!$this->_db_set_charset($this->char_set, $this->dbcollat)) {
            log_message('error', 'Unable to set database connection charset: ' . $this->char_set);
            if ($this->db_debug || systemInfo('error_manage')) {
                $this->display_error('db_unable_to_set_charset', $this->char_set);
            }
            return FALSE;
        }
        return TRUE;
    }
    /**
     * The name of the platform in use (mysql, mssql, etc...)
     *
     * @access        public
     * @return        string
     */
    function platform() {
        return $this->dbdriver;
    }
    /**
     * Database Version Number.  Returns a string containing the
     * version of the database being used
     *
     * @access        public
     * @return        string
     */
    function version() {
        if (FALSE === ($sql = $this->_version())) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $driver_version_exceptions = array('oci8', 'sqlite', 'cubrid');
        if (in_array($this->dbdriver, $driver_version_exceptions)) {
            return $sql;
        } else {
            $query = $this->query($sql);
            return $query->row('ver');
        }
    }
    /**
     * Execute the query
     *
     * Accepts an SQL string as input and returns a result object upon
     * successful execution of a "read" type query.  Returns boolean TRUE
     * upon successful execution of a "write" type query. Returns boolean
     * FALSE upon failure, and if the $db_debug variable is set to TRUE
     * will raise an error.
     *
     * @access        public
     * @param        string        An SQL query string
     * @param        array        An array of binding data
     * @return        mixed
     */
    function query($sql, $binds = FALSE, $return_object = TRUE) {
        if ($sql == '') {
            if ($this->db_debug || systemInfo('error_manage')) {
                log_message('error', 'Invalid query: ' . $sql);
                return $this->display_error('db_invalid_query');
            }
            return FALSE;
        }
        if (($this->dbprefix != '' AND $this->swap_pre != '') AND ( $this->dbprefix != $this->swap_pre)) {
            $sql = preg_replace("/(\W)" . $this->swap_pre . "(\S+?)/", "\\1" . $this->dbprefix . "\\2", $sql);
        }
        if ($binds !== FALSE) {
            $sql = $this->compile_binds($sql, $binds);
        }
        if ($this->save_queries == TRUE) {
            $this->queries[] = $sql;
        }
        $time_start = list($sm, $ss) = explode(' ', microtime());
        if (FALSE === ($this->result_id = $this->simple_query($sql))) {
            if ($this->save_queries == TRUE) {
                $this->query_times[] = 0;
            }
            $this->_trans_status = FALSE;
            if ($this->db_debug || systemInfo('error_manage')) {
                $error_no = $this->_error_number();
                $error_msg = $this->_error_message();
                $this->trans_complete();
                log_message('error', 'Query error: ' . $error_msg);
                return $this->display_error(
                    array(
                        'Error Number: ' . $error_no,
                        $error_msg,
                        $sql
                    )
                );
            }
            return FALSE;
        }
        $time_end = list($em, $es) = explode(' ', microtime());
        $this->benchmark += ($em + $es) - ($sm + $ss);
        if ($this->save_queries == TRUE) {
            $this->query_times[] = ($em + $es) - ($sm + $ss);
        }
        $this->query_count++;
        if ($this->is_write_type($sql) === TRUE) {
            return TRUE;
        }
        if ($return_object !== TRUE) {
            return TRUE;
        }
        $driver = $this->load_rdriver();
        $RES = new $driver();
        $RES->conn_id = $this->conn_id;
        $RES->result_id = $this->result_id;
        if ($this->dbdriver == 'oci8') {
            $RES->stmt_id = $this->stmt_id;
            $RES->curs_id = NULL;
            $RES->limit_used = $this->limit_used;
            $this->stmt_id = FALSE;
        }
        $RES->num_rows = $RES->num_rows();
        return $RES;
    }
    /**
     * Load the result drivers
     *
     * @access        public
     * @return        string        the name of the result class
     */
    function load_rdriver() {
        $driver = 'CI_DB_' . $this->dbdriver . '_result';
        if (!class_exists($driver, FALSE)) {
            include_once(BASEPATH . 'database/DB_result.php');
            include_once(BASEPATH . 'database/drivers/' . $this->dbdriver . '/' . $this->dbdriver . '_result.php');
        }
        return $driver;
    }
    /**
     * Simple Query
     * This is a simplified version of the query() function.  Internally
     * we only use it when running transaction commands since they do
     * not require all the features of the main query() function.
     *
     * @access        public
     * @param        string        the sql query
     * @return        mixed
     */
    function simple_query($sql) {
        if (!$this->conn_id) {
            $this->initialize();
        }
        return $this->_execute($sql);
    }
    /**
     * Disable Transactions
     * This permits transactions to be disabled at run-time.
     *
     * @access        public
     * @return        void
     */
    function trans_off() {
        $this->trans_enabled = FALSE;
    }
    /**
     * Enable/disable Transaction Strict Mode
     * When strict mode is enabled, if you are running multiple groups of
     * transactions, if one group fails all groups will be rolled back.
     * If strict mode is disabled, each group is treated autonomously, meaning
     * a failure of one group will not affect any others
     *
     * @access        public
     * @return        void
     */
    function trans_strict($mode = TRUE) {
        $this->trans_strict = is_bool($mode) ? $mode : TRUE;
    }
    /**
     * Start Transaction
     *
     * @access        public
     * @return        void
     */
    function trans_start($test_mode = FALSE) {
        if (!$this->trans_enabled) {
            return FALSE;
        }
        if ($this->_trans_depth > 0) {
            $this->_trans_depth += 1;
            return;
        }
        $this->trans_begin($test_mode);
    }
    /**
     * Complete Transaction
     *
     * @access        public
     * @return        bool
     */
    function trans_complete() {
        if (!$this->trans_enabled) {
            return FALSE;
        }
        if ($this->_trans_depth > 1) {
            $this->_trans_depth -= 1;
            return TRUE;
        }
        if ($this->_trans_status === FALSE) {
            $this->trans_rollback();
            if ($this->trans_strict === FALSE) {
                $this->_trans_status = TRUE;
            }
            log_message('debug', 'DB Transaction Failure');
            return FALSE;
        }
        $this->trans_commit();
        return TRUE;
    }
    /**
     * Lets you retrieve the transaction flag to determine if it has failed
     *
     * @access        public
     * @return        bool
     */
    function trans_status() {
        return $this->_trans_status;
    }
    /**
     * Compile Bindings
     *
     * @access        public
     * @param        string        the sql statement
     * @param        array        an array of bind data
     * @return        string
     */
    function compile_binds($sql, $binds) {
        if (strpos($sql, $this->bind_marker) === FALSE) {
            return $sql;
        }
        if (!is_array($binds)) {
            $binds = array($binds);
        }
        $segments = explode($this->bind_marker, $sql);
        if (count($binds) >= count($segments)) {
            $binds = array_slice($binds, 0, count($segments) - 1);
        }
        $result = $segments[0];
        $i = 0;
        foreach ($binds as $bind) {
            $result .= $this->escape($bind);
            $result .= $segments[++$i];
        }
        return $result;
    }
    /**
     * Determines if a query is a "write" type.
     *
     * @access        public
     * @param        string        An SQL query string
     * @return        boolean
     */
    function is_write_type($sql) {
        if (!preg_match('/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s+/i', $sql)) {
            return FALSE;
        }
        return TRUE;
    }
    /**
     * Calculate the aggregate query elapsed time
     *
     * @access        public
     * @param        integer        The number of decimal places
     * @return        integer
     */
    function elapsed_time($decimals = 6) {
        return number_format($this->benchmark, $decimals);
    }
    /**
     * Returns the total number of queries
     *
     * @access        public
     * @return        integer
     */
    function total_queries() {
        return $this->query_count;
    }
    /**
     * Returns the last query that was executed
     *
     * @access        public
     * @return        void
     */
    function last_query() {
        return end($this->queries);
    }
    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     * Sets boolean and null types
     *
     * @access        public
     * @param        string
     * @return        mixed
     */
    function escape($str) {
        if (is_string($str)) {
            $str = "'" . $this->escape_str($str) . "'";
        } elseif (is_bool($str)) {
            $str = ($str === FALSE) ? 0 : 1;
        } elseif (is_null($str)) {
            $str = 'NULL';
        }
        return $str;
    }
    /**
     * Escape LIKE String
     *
     * Calls the individual driver for platform
     * specific escaping for LIKE conditions
     *
     * @access        public
     * @param        string
     * @return        mixed
     */
    function escape_like_str($str) {
        return $this->escape_str($str, TRUE);
    }
    /**
     * Primary
     *
     * Retrieves the primary key.  It assumes that the row in the first
     * position is the primary key
     *
     * @access        public
     * @param        string        the table name
     * @return        string
     */
    function primary($table = '') {
        $fields = $this->list_fields($table);
        if (!is_array($fields)) {
            return FALSE;
        }
        return current($fields);
    }
    /**
     * Returns an array of table names
     *
     * @access        public
     * @return        array
     */
    function list_tables($constrain_by_prefix = FALSE) {
        if (isset($this->data_cache['table_names'])) {
            return $this->data_cache['table_names'];
        }
        if (FALSE === ($sql = $this->_list_tables($constrain_by_prefix))) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $retval = array();
        $query = $this->query($sql);
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                if (isset($row['TABLE_NAME'])) {
                    $retval[] = $row['TABLE_NAME'];
                } else {
                    $retval[] = array_shift($row);
                }
            }
        }
        $this->data_cache['table_names'] = $retval;
        return $this->data_cache['table_names'];
    }
    /**
     * Determine if a particular table exists
     * @access        public
     * @return        boolean
     */
    function table_exists($table_name) {
        return (!in_array($this->_protect_identifiers($table_name, TRUE, FALSE, FALSE), $this->list_tables())) ? FALSE : TRUE;
    }
    /**
     * Fetch MySQL Field Names
     *
     * @access        public
     * @param        string        the table name
     * @return        array
     */
    function list_fields($table = '') {
        if (isset($this->data_cache['field_names'][$table])) {
            return $this->data_cache['field_names'][$table];
        }
        if ($table == '') {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_field_param_missing');
            }
            return FALSE;
        }
        if (FALSE === ($sql = $this->_list_columns($table))) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $query = $this->query($sql);
        $retval = array();
        foreach ($query->result_array() as $row) {
            if (isset($row['COLUMN_NAME'])) {
                $retval[] = $row['COLUMN_NAME'];
            } else if ($this->dbdriver == 'sqlite3') {
                $retval[] = $row['name'];
            } else {
                $retval[] = current($row);
            }
        }
        $this->data_cache['field_names'][$table] = $retval;
        return $this->data_cache['field_names'][$table];
    }
    /**
     * Determine if a particular field exists
     * @access        public
     * @param        string
     * @param        string
     * @return        boolean
     */
    function field_exists($field_name, $table_name) {
        return (!in_array($field_name, $this->list_fields($table_name))) ? FALSE : TRUE;
    }
    /**
     * Returns an object with field data
     *
     * @access        public
     * @param        string        the table name
     * @return        object
     */
    function field_data($table = '') {
        if ($table == '') {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_field_param_missing');
            }
            return FALSE;
        }
        $query = $this->query($this->_field_data($this->_protect_identifiers($table, TRUE, NULL, FALSE)));
        return $query->field_data();
    }
    /**
     * Generate an insert string
     *
     * @access        public
     * @param        string        the table upon which the query will be performed
     * @param        array        an associative array data of key/values
     * @return        string
     */
    function insert_string($table, $data) {
        $fields = array();
        $values = array();
        foreach ($data as $key => $val) {
            $fields[] = $this->_escape_identifiers($key);
            $values[] = $this->escape($val);
        }
        return $this->_insert($this->_protect_identifiers($table, TRUE, NULL, FALSE), $fields, $values);
    }
    /**
     * Generate an update string
     *
     * @access        public
     * @param        string        the table upon which the query will be performed
     * @param        array        an associative array data of key/values
     * @param        mixed        the "where" statement
     * @return        string
     */
    function update_string($table, $data, $where) {
        if ($where == '') {
            return false;
        }
        $fields = array();
        foreach ($data as $key => $val) {
            $fields[$this->_protect_identifiers($key)] = $this->escape($val);
        }
        if (!is_array($where)) {
            $dest = array($where);
        } else {
            $dest = array();
            foreach ($where as $key => $val) {
                $prefix = (count($dest) == 0) ? '' : ' AND ';
                if ($val !== '') {
                    if (!$this->_has_operator($key)) {
                        $key .= ' =';
                    }
                    $val = ' ' . $this->escape($val);
                }
                $dest[] = $prefix . $key . $val;
            }
        }
        return $this->_update($this->_protect_identifiers($table, TRUE, NULL, FALSE), $fields, $dest);
    }
    /**
     * Tests whether the string has an SQL operator
     *
     * @access        private
     * @param        string
     * @return        bool
     */
    function _has_operator($str) {
        $str = trim($str);
        if (!preg_match("/(\s|<|>|!|=|is null|is not null)/i", $str)) {
            return FALSE;
        }
        return TRUE;
    }
    /**
     * Enables a native PHP function to be run, using a platform agnostic wrapper.
     *
     * @access        public
     * @param        string        the function name
     * @param        mixed        any parameters needed by the function
     * @return        mixed
     */
    function call_function($function) {
        $driver = ($this->dbdriver == 'postgre') ? 'pg_' : $this->dbdriver . '_';
        if (FALSE === strpos($driver, $function)) {
            $function = $driver . $function;
        }
        if (!function_exists($function)) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        } else {
            $args = (func_num_args() > 1) ? array_splice(func_get_args(), 1) : null;
            if (is_null($args)) {
                return call_user_func($function);
            } else {
                return call_user_func_array($function, $args);
            }
        }
    }

    /**
     * Close DB Connection
     *
     * @access        public
     * @return        void
     */
    function close() {
        if (is_resource($this->conn_id) OR is_object($this->conn_id)) {
            $this->_close($this->conn_id);
        }
        $this->conn_id = FALSE;
    }
    /**
     * Display an error message
     *
     * @access        public
     * @param        string        the error message
     * @param        string        any "swap" values
     * @param        boolean        whether to localize the message
     * @return        string        sends the application/error_db.php template
     */
    function display_error($error = '', $swap = '', $native = FALSE) {
        woniu_db_error_handler($error, $swap, $native);
    }
    /**
     * Protect Identifiers
     *
     * This function adds backticks if appropriate based on db type
     *
     * @access        private
     * @param        mixed        the item to escape
     * @return        mixed        the item with backticks
     */
    function protect_identifiers($item, $prefix_single = FALSE) {
        return $this->_protect_identifiers($item, $prefix_single);
    }
    /**
     * Protect Identifiers
     *
     * This function is used extensively by the Active Record class, and by
     * a couple functions in this class.
     * It takes a column or table name (optionally with an alias) and inserts
     * the table prefix onto it.  Some logic is necessary in order to deal with
     * column names that include the path.  Consider a query like this:
     *
     * SELECT * FROM hostname.database.table.column AS c FROM hostname.database.table
     *
     * Or a query with aliasing:
     *
     * SELECT m.member_id, m.member_name FROM members AS m
     *
     * Since the column name can include up to four segments (host, DB, table, column)
     * or also have an alias prefix, we need to do a bit of work to figure this out and
     * insert the table prefix (if it exists) in the proper position, and escape only
     * the correct identifiers.
     *
     * @access        private
     * @param        string
     * @param        bool
     * @param        mixed
     * @param        bool
     * @return        string
     */
    function _protect_identifiers($item, $prefix_single = FALSE, $protect_identifiers = NULL, $field_exists = TRUE) {
        if (!is_bool($protect_identifiers)) {
            $protect_identifiers = $this->_protect_identifiers;
        }
        if (is_array($item)) {
            $escaped_array = array();
            foreach ($item as $k => $v) {
                $escaped_array[$this->_protect_identifiers($k)] = $this->_protect_identifiers($v);
            }
            return $escaped_array;
        }
        $item = preg_replace('/[\t ]+/', ' ', $item);
        if (strpos($item, ' ') !== FALSE) {
            $alias = strstr($item, ' ');
            $item = substr($item, 0, - strlen($alias));
        } else {
            $alias = '';
        }
        if (strpos($item, '(') !== FALSE) {
            return $item . $alias;
        }
        if (strpos($item, '.') !== FALSE) {
            $parts = explode('.', $item);
            if (in_array($parts[0], $this->ar_aliased_tables)) {
                if ($protect_identifiers === TRUE) {
                    foreach ($parts as $key => $val) {
                        if (!in_array($val, $this->_reserved_identifiers)) {
                            $parts[$key] = $this->_escape_identifiers($val);
                        }
                    }
                    $item = implode('.', $parts);
                }
                return $item . $alias;
            }
            if ($this->dbprefix != '') {
                if (isset($parts[3])) {
                    $i = 2;
                }
                elseif (isset($parts[2])) {
                    $i = 1;
                }
                else {
                    $i = 0;
                }
                if ($field_exists == FALSE) {
                    $i++;
                }
                if ($this->swap_pre != '' && strncmp($parts[$i], $this->swap_pre, strlen($this->swap_pre)) === 0) {
                    $parts[$i] = preg_replace("/^" . $this->swap_pre . "(\S+?)/", $this->dbprefix . "\\1", $parts[$i]);
                }
                if (substr($parts[$i], 0, strlen($this->dbprefix)) != $this->dbprefix) {
                    $parts[$i] = $this->dbprefix . $parts[$i];
                }
                $item = implode('.', $parts);
            }
            if ($protect_identifiers === TRUE) {
                $item = $this->_escape_identifiers($item);
            }
            return $item . $alias;
        }
        if ($this->dbprefix != '') {
            if ($this->swap_pre != '' && strncmp($item, $this->swap_pre, strlen($this->swap_pre)) === 0) {
                $item = preg_replace("/^" . $this->swap_pre . "(\S+?)/", $this->dbprefix . "\\1", $item);
            }
            if ($prefix_single == TRUE AND substr($item, 0, strlen($this->dbprefix)) != $this->dbprefix) {
                $item = $this->dbprefix . $item;
            }
        }
        if ($protect_identifiers === TRUE AND ! in_array($item, $this->_reserved_identifiers)) {
            $item = $this->_escape_identifiers($item);
        }
        return $item . $alias;
    }
    /**
     * Dummy method that allows Active Record class to be disabled
     *
     * This function is used extensively by every db driver.
     *
     * @return        void
     */
    protected function _reset_select() {

    }
}

/**
 * Database Result Class
 *
 * This is the platform-independent result class.
 * This class will not be called directly. Rather, the adapter
 * class for the specific database will extend and instantiate it.
 *
 * @category        Database
 * @author                ExpressionEngine Dev Team
 * @link                http://codeigniter.com/user_guide/database/
 */
class CI_DB_result {
    var $conn_id = NULL;
    var $result_id = NULL;
    var $result_array = array();
    var $result_object = array();
    var $custom_result_object = array();
    var $current_row = 0;
    var $num_rows = 0;
    var $row_data = NULL;
    /**
     * Query result.  Acts as a wrapper function for the following functions.
     *
     * @access        public
     * @param        string        can be "object" or "array"
     * @return        mixed        either a result object or array
     */
    public function result($type = 'object') {
        if ($type == 'array')
            return $this->result_array();
        else if ($type == 'object')
            return $this->result_object();
        else
            return $this->custom_result_object($type);
    }
    /**
     * Custom query result.
     *
     * @param class_name A string that represents the type of object you want back
     * @return array of objects
     */
    public function custom_result_object($class_name) {
        if (array_key_exists($class_name, $this->custom_result_object)) {
            return $this->custom_result_object[$class_name];
        }
        if ($this->result_id === FALSE OR $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        $result_object = array();
        while ($row = $this->_fetch_object()) {
            $object = new $class_name();
            foreach ($row as $key => $value) {
                if (method_exists($object, 'set_' . $key)) {
                    $object->{'set_' . $key}($value);
                } else {
                    $object->$key = $value;
                }
            }
            $result_object[] = $object;
        }
        return $this->custom_result_object[$class_name] = $result_object;
    }
    /**
     * Query result.  "object" version.
     *
     * @access        public
     * @return        object
     */
    public function result_object() {
        if (count($this->result_object) > 0) {
            return $this->result_object;
        }
        if ($this->result_id === FALSE OR $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        while ($row = $this->_fetch_object()) {
            $this->result_object[] = $row;
        }
        return $this->result_object;
    }
    /**
     * Query result.  "array" version.
     *
     * @access        public
     * @return        array
     */
    public function result_array() {
        if (count($this->result_array) > 0) {
            return $this->result_array;
        }
        if ($this->result_id === FALSE OR $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        while ($row = $this->_fetch_assoc()) {
            $this->result_array[] = $row;
        }
        return $this->result_array;
    }
    /**
     * Query result.  Acts as a wrapper function for the following functions.
     *
     * @access        public
     * @param        string
     * @param        string        can be "object" or "array"
     * @return        mixed        either a result object or array
     */
    public function row($n = 0, $type = 'object') {
        if (!is_numeric($n)) {
            if (!is_array($this->row_data)) {
                $this->row_data = $this->row_array(0);
            }
            if (array_key_exists($n, $this->row_data)) {
                return $this->row_data[$n];
            }
            $n = 0;
        }
        if ($type == 'object')
            return $this->row_object($n);
        else if ($type == 'array')
            return $this->row_array($n);
        else
            return $this->custom_row_object($n, $type);
    }
    /**
     * Assigns an item into a particular column slot
     *
     * @access        public
     * @return        object
     */
    public function set_row($key, $value = NULL) {
        if (!is_array($this->row_data)) {
            $this->row_data = $this->row_array(0);
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->row_data[$k] = $v;
            }
            return;
        }
        if ($key != '' AND ! is_null($value)) {
            $this->row_data[$key] = $value;
        }
    }
    /**
     * Returns a single result row - custom object version
     *
     * @access        public
     * @return        object
     */
    public function custom_row_object($n, $type) {
        $result = $this->custom_result_object($type);
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row AND isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns a single result row - object version
     *
     * @access        public
     * @return        object
     */
    public function row_object($n = 0) {
        $result = $this->result_object();
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row AND isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns a single result row - array version
     *
     * @access        public
     * @return        array
     */
    public function row_array($n = 0) {
        $result = $this->result_array();
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row AND isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns the "first" row
     *
     * @access        public
     * @return        object
     */
    public function first_row($type = 'object') {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        return $result[0];
    }
    /**
     * Returns the "last" row
     *
     * @access        public
     * @return        object
     */
    public function last_row($type = 'object') {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        return $result[count($result) - 1];
    }
    /**
     * Returns the "next" row
     *
     * @access        public
     * @return        object
     */
    public function next_row($type = 'object') {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        if (isset($result[$this->current_row + 1])) {
            ++$this->current_row;
        }
        return $result[$this->current_row];
    }
    /**
     * Returns the "previous" row
     *
     * @access        public
     * @return        object
     */
    public function previous_row($type = 'object') {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        if (isset($result[$this->current_row - 1])) {
            --$this->current_row;
        }
        return $result[$this->current_row];
    }
    /**
     * The following functions are normally overloaded by the identically named
     * methods in the platform-specific driver -- except when query caching
     * is used.  When caching is enabled we do not load the other driver.
     * These functions are primarily here to prevent undefined function errors
     * when a cached result object is in use.  They are not otherwise fully
     * operational due to the unavailability of the database resource IDs with
     * cached results.
     */
    public function num_rows() {
        return $this->num_rows;
    }
    public function num_fields() {
        return 0;
    }
    public function list_fields() {
        return array();
    }
    public function field_data() {
        return array();
    }
    public function free_result() {
        return TRUE;
    }
    protected function _data_seek() {
        return TRUE;
    }
    protected function _fetch_assoc() {
        return array();
    }
    protected function _fetch_object() {
        return array();
    }
}
/**
 * Active Record Class
 *
 * This is the platform-independent base Active Record implementation class.
 *
 * @package                CodeIgniter
 * @subpackage        Drivers
 * @category        Database
 * @author                ExpressionEngine Dev Team
 * @link                http://codeigniter.com/user_guide/database/
 */
class CI_DB_active_record extends CI_DB_driver {
    var $ar_select = array();
    var $ar_distinct = FALSE;
    var $ar_from = array();
    var $ar_join = array();
    var $ar_where = array();
    var $ar_like = array();
    var $ar_groupby = array();
    var $ar_having = array();
    var $ar_keys = array();
    var $ar_limit = FALSE;
    var $ar_offset = FALSE;
    var $ar_order = FALSE;
    var $ar_orderby = array();
    var $ar_set = array();
    var $ar_wherein = array();
    var $ar_aliased_tables = array();
    var $ar_store_array = array();
    var $ar_no_escape = array();
    /**
     * Select
     *
     * Generates the SELECT portion of the query
     *
     * @param        string
     * @return        object
     */
    public function select($select = '*', $escape = NULL) {
        if (is_string($select)) {
            $select = explode(',', $select);
        }
        foreach ($select as $val) {
            $val = trim($val);
            if ($val != '') {
                $this->ar_select[] = $val;
                $this->ar_no_escape[] = $escape;
            }
        }
        return $this;
    }
    /**
     * Select Max
     *
     * Generates a SELECT MAX(field) portion of a query
     *
     * @param        string        the field
     * @param        string        an alias
     * @return        object
     */
    public function select_max($select = '', $alias = '') {
        return $this->_max_min_avg_sum($select, $alias, 'MAX');
    }
    /**
     * Select Min
     *
     * Generates a SELECT MIN(field) portion of a query
     *
     * @param        string        the field
     * @param        string        an alias
     * @return        object
     */
    public function select_min($select = '', $alias = '') {
        return $this->_max_min_avg_sum($select, $alias, 'MIN');
    }
    /**
     * Select Average
     *
     * Generates a SELECT AVG(field) portion of a query
     *
     * @param        string        the field
     * @param        string        an alias
     * @return        object
     */
    public function select_avg($select = '', $alias = '') {
        return $this->_max_min_avg_sum($select, $alias, 'AVG');
    }
    /**
     * Select Sum
     *
     * Generates a SELECT SUM(field) portion of a query
     *
     * @param        string        the field
     * @param        string        an alias
     * @return        object
     */
    public function select_sum($select = '', $alias = '') {
        return $this->_max_min_avg_sum($select, $alias, 'SUM');
    }
    /**
     * Processing Function for the four functions above:
     *
     *         select_max()
     *         select_min()
     *         select_avg()
     *  select_sum()
     *
     * @param        string        the field
     * @param        string        an alias
     * @return        object
     */
    protected function _max_min_avg_sum($select = '', $alias = '', $type = 'MAX') {
        if (!is_string($select) OR $select == '') {
            $this->display_error('db_invalid_query');
        }
        $type = strtoupper($type);
        if (!in_array($type, array('MAX', 'MIN', 'AVG', 'SUM'))) {
            show_error('Invalid function type: ' . $type);
        }
        if ($alias == '') {
            $alias = $this->_create_alias_from_table(trim($select));
        }
        $sql = $type . '(' . $this->_protect_identifiers(trim($select)) . ') AS ' . $alias;
        $this->ar_select[] = $sql;
        return $this;
    }
    /**
     * Determines the alias name based on the table
     *
     * @param        string
     * @return        string
     */
    protected function _create_alias_from_table($item) {
        if (strpos($item, '.') !== FALSE) {
            return end(explode('.', $item));
        }
        return $item;
    }
    /**
     * DISTINCT
     *
     * Sets a flag which tells the query string compiler to add DISTINCT
     *
     * @param        bool
     * @return        object
     */
    public function distinct($val = TRUE) {
        $this->ar_distinct = (is_bool($val)) ? $val : TRUE;
        return $this;
    }
    /**
     * From
     *
     * Generates the FROM portion of the query
     *
     * @param        mixed        can be a string or array
     * @return        object
     */
    public function from($from) {
        foreach ((array) $from as $val) {
            if (strpos($val, ',') !== FALSE) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    $this->_track_aliases($v);
                    $this->ar_from[] = $this->_protect_identifiers($v, TRUE, NULL, FALSE);
                }
            } else {
                $val = trim($val);
                $this->_track_aliases($val);
                $this->ar_from[] = $this->_protect_identifiers($val, TRUE, NULL, FALSE);
            }
        }
        return $this;
    }
    /**
     * Join
     *
     * Generates the JOIN portion of the query
     *
     * @param        string
     * @param        string        the join condition
     * @param        string        the type of join
     * @return        object
     */
    public function join($table, $cond, $type = '') {
        if ($type != '') {
            $type = strtoupper(trim($type));
            if (!in_array($type, array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'))) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }
        $this->_track_aliases($table);
        if (preg_match('/([\w\.]+)([\W\s]+)(.+)/', $cond, $match)) {
            $match[1] = $this->_protect_identifiers($match[1]);
            $match[3] = $this->_protect_identifiers($match[3]);
            $cond = $match[1] . $match[2] . $match[3];
        }
        $join = $type . 'JOIN ' . $this->_protect_identifiers($table, TRUE, NULL, FALSE) . ' ON ' . $cond;
        $this->ar_join[] = $join;
        return $this;
    }
    /**
     * Where
     *
     * Generates the WHERE portion of the query. Separates
     * multiple calls with AND
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function where($key, $value = NULL, $escape = TRUE) {
        return $this->_where($key, $value, 'AND ', $escape);
    }
    /**
     * OR Where
     *
     * Generates the WHERE portion of the query. Separates
     * multiple calls with OR
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function or_where($key, $value = NULL, $escape = TRUE) {
        return $this->_where($key, $value, 'OR ', $escape);
    }
    /**
     * Where
     *
     * Called by where() or or_where()
     *
     * @param        mixed
     * @param        mixed
     * @param        string
     * @return        object
     */
    protected function _where($key, $value = NULL, $type = 'AND ', $escape = NULL) {
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        if (!is_bool($escape)) {
            $escape = $this->_protect_identifiers;
        }
        foreach ($key as $k => $v) {
            $prefix = (count($this->ar_where) == 0) ? '' : $type;
            if (is_null($v) && !$this->_has_operator($k)) {
                $k .= ' IS NULL';
            }
            if (!is_null($v)) {
                if ($escape === TRUE) {
                    $k = $this->_protect_identifiers($k, FALSE, $escape);
                    $v = ' ' . $this->escape($v);
                }
                if (!$this->_has_operator($k)) {
                    $k .= ' = ';
                }
            } else {
                $k = $this->_protect_identifiers($k, FALSE, $escape);
            }
            $this->ar_where[] = $prefix . $k . $v;
        }
        return $this;
    }
    /**
     * Where_in
     *
     * Generates a WHERE field IN ('item', 'item') SQL query joined with
     * AND if appropriate
     *
     * @param        string        The field to search
     * @param        array        The values searched on
     * @return        object
     */
    public function where_in($key = NULL, $values = NULL) {
        return $this->_where_in($key, $values);
    }
    /**
     * Where_in_or
     *
     * Generates a WHERE field IN ('item', 'item') SQL query joined with
     * OR if appropriate
     *
     * @param        string        The field to search
     * @param        array        The values searched on
     * @return        object
     */
    public function or_where_in($key = NULL, $values = NULL) {
        return $this->_where_in($key, $values, FALSE, 'OR ');
    }
    /**
     * Where_not_in
     *
     * Generates a WHERE field NOT IN ('item', 'item') SQL query joined
     * with AND if appropriate
     *
     * @param        string        The field to search
     * @param        array        The values searched on
     * @return        object
     */
    public function where_not_in($key = NULL, $values = NULL) {
        return $this->_where_in($key, $values, TRUE);
    }
    /**
     * Where_not_in_or
     *
     * Generates a WHERE field NOT IN ('item', 'item') SQL query joined
     * with OR if appropriate
     *
     * @param        string        The field to search
     * @param        array        The values searched on
     * @return        object
     */
    public function or_where_not_in($key = NULL, $values = NULL) {
        return $this->_where_in($key, $values, TRUE, 'OR ');
    }
    /**
     * Where_in
     *
     * Called by where_in, where_in_or, where_not_in, where_not_in_or
     *
     * @param        string        The field to search
     * @param        array        The values searched on
     * @param        boolean        If the statement would be IN or NOT IN
     * @param        string
     * @return        object
     */
    protected function _where_in($key = NULL, $values = NULL, $not = FALSE, $type = 'AND ') {
        if ($key === NULL OR $values === NULL) {
            return;
        }
        if (!is_array($values)) {
            $values = array($values);
        } elseif (empty($values)) {
            $values = array('');
        }
        $not = ($not) ? ' NOT' : '';
        foreach ($values as $value) {
            $this->ar_wherein[] = $this->escape($value);
        }
        $prefix = (count($this->ar_where) == 0) ? '' : $type;
        $where_in = $prefix . $this->_protect_identifiers($key) . $not . " IN (" . implode(", ", $this->ar_wherein) . ") ";
        $this->ar_where[] = $where_in;
        $this->ar_wherein = array();
        return $this;
    }
    /**
     * Like
     *
     * Generates a %LIKE% portion of the query. Separates
     * multiple calls with AND
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function like($field, $match = '', $side = 'both') {
        return $this->_like($field, $match, 'AND ', $side);
    }
    /**
     * Not Like
     *
     * Generates a NOT LIKE portion of the query. Separates
     * multiple calls with AND
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function not_like($field, $match = '', $side = 'both') {
        return $this->_like($field, $match, 'AND ', $side, 'NOT');
    }
    /**
     * OR Like
     *
     * Generates a %LIKE% portion of the query. Separates
     * multiple calls with OR
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function or_like($field, $match = '', $side = 'both') {
        return $this->_like($field, $match, 'OR ', $side);
    }
    /**
     * OR Not Like
     *
     * Generates a NOT LIKE portion of the query. Separates
     * multiple calls with OR
     *
     * @param        mixed
     * @param        mixed
     * @return        object
     */
    public function or_not_like($field, $match = '', $side = 'both') {
        return $this->_like($field, $match, 'OR ', $side, 'NOT');
    }
    /**
     * Like
     *
     * Called by like() or orlike()
     *
     * @param        mixed
     * @param        mixed
     * @param        string
     * @return        object
     */
    protected function _like($field, $match = '', $type = 'AND ', $side = 'both', $not = '') {
        if (!is_array($field)) {
            $field = array($field => $match);
        }
        foreach ($field as $k => $v) {
            $k = $this->_protect_identifiers($k);
            $prefix = (count($this->ar_like) == 0) ? '' : $type;
            $v = $this->escape_like_str($v);
            if ($side == 'none') {
                $like_statement = $prefix . " $k $not LIKE '{$v}'";
            } elseif ($side == 'before') {
                $like_statement = $prefix . " $k $not LIKE '%{$v}'";
            } elseif ($side == 'after') {
                $like_statement = $prefix . " $k $not LIKE '{$v}%'";
            } else {
                $like_statement = $prefix . " $k $not LIKE '%{$v}%'";
            }
            if ($this->_like_escape_str != '') {
                $like_statement = $like_statement . sprintf($this->_like_escape_str, $this->_like_escape_chr);
            }
            $this->ar_like[] = $like_statement;
        }
        return $this;
    }
    /**
     * GROUP BY
     *
     * @param        string
     * @return        object
     */
    public function group_by($by) {
        if (is_string($by)) {
            $by = explode(',', $by);
        }
        foreach ($by as $val) {
            $val = trim($val);
            if ($val != '') {
                $this->ar_groupby[] = $this->_protect_identifiers($val);
            }
        }
        return $this;
    }
    /**
     * Sets the HAVING value
     *
     * Separates multiple calls with AND
     *
     * @param        string
     * @param        string
     * @return        object
     */
    public function having($key, $value = '', $escape = TRUE) {
        return $this->_having($key, $value, 'AND ', $escape);
    }
    /**
     * Sets the OR HAVING value
     *
     * Separates multiple calls with OR
     *
     * @param        string
     * @param        string
     * @return        object
     */
    public function or_having($key, $value = '', $escape = TRUE) {
        return $this->_having($key, $value, 'OR ', $escape);
    }
    /**
     * Sets the HAVING values
     *
     * Called by having() or or_having()
     *
     * @param        string
     * @param        string
     * @return        object
     */
    protected function _having($key, $value = '', $type = 'AND ', $escape = TRUE) {
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        foreach ($key as $k => $v) {
            $prefix = (count($this->ar_having) == 0) ? '' : $type;
            if ($escape === TRUE) {
                $k = $this->_protect_identifiers($k);
            }
            if (!$this->_has_operator($k)) {
                $k .= ' = ';
            }
            if ($v != '') {
                $v = ' ' . $this->escape($v);
            }
            $this->ar_having[] = $prefix . $k . $v;
        }
        return $this;
    }
    /**
     * Sets the ORDER BY value
     *
     * @param        string
     * @param        string        direction: asc or desc
     * @return        object
     */
    public function order_by($orderby, $direction = '') {
        if (strtolower($direction) == 'random') {
            $orderby = ''; // Random results want or don't need a field name
            $direction = $this->_random_keyword;
        } elseif (trim($direction) != '') {
            $direction = (in_array(strtoupper(trim($direction)), array('ASC', 'DESC'), TRUE)) ? ' ' . $direction : ' ASC';
        }
        if (strpos($orderby, ',') !== FALSE) {
            $temp = array();
            foreach (explode(',', $orderby) as $part) {
                $part = trim($part);
                if (!in_array($part, $this->ar_aliased_tables)) {
                    $part = $this->_protect_identifiers(trim($part));
                }
                $temp[] = $part;
            }
            $orderby = implode(', ', $temp);
        } else if ($direction != $this->_random_keyword) {
            $orderby = $this->_protect_identifiers($orderby);
        }
        $orderby_statement = $orderby . $direction;
        $this->ar_orderby[] = $orderby_statement;
        return $this;
    }
    /**
     * Sets the LIMIT value
     *
     * @param        integer        the limit value
     * @param        integer        the offset value
     * @return        object
     */
    public function limit($value, $offset = '') {
        $this->ar_limit = (int) $value;
        if ($offset != '') {
            $this->ar_offset = (int) $offset;
        }
        return $this;
    }
    /**
     * Sets the OFFSET value
     *
     * @param        integer        the offset value
     * @return        object
     */
    public function offset($offset) {
        $this->ar_offset = $offset;
        return $this;
    }
    /**
     * The "set" function.  Allows key/value pairs to be set for inserting or updating
     *
     * @param        mixed
     * @param        string
     * @param        boolean
     * @return        object
     */
    public function set($key, $value = '', $escape = TRUE) {
        $key = $this->_object_to_array($key);
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        foreach ($key as $k => $v) {
            if ($escape === FALSE) {
                $this->ar_set[$this->_protect_identifiers($k)] = $v;
            } else {
                $this->ar_set[$this->_protect_identifiers($k, FALSE, TRUE)] = $this->escape($v);
            }
        }
        return $this;
    }
    /**
     * Get
     *
     * Compiles the select statement based on the other functions called
     * and runs the query
     *
     * @param        string        the table
     * @param        string        the limit clause
     * @param        string        the offset clause
     * @return        object
     */
    public function get($table = '', $limit = null, $offset = null) {
        if ($table != '') {
            $this->_track_aliases($table);
            $this->from($table);
        }
        if (!is_null($limit)) {
            $this->limit($limit, $offset);
        }
        $sql = $this->_compile_select();
        $result = $this->query($sql);
        $this->_reset_select();
        return $result;
    }
    /**
     * "Count All Results" query
     *
     * Generates a platform-specific query string that counts all records
     * returned by an Active Record query.
     *
     * @param        string
     * @return        string
     */
    public function count_all_results($table = '') {
        if ($table != '') {
            $this->_track_aliases($table);
            $this->from($table);
        }
        $sql = $this->_compile_select($this->_count_string . $this->_protect_identifiers('numrows'));
        $query = $this->query($sql);
        $this->_reset_select();
        if ($query->num_rows() == 0) {
            return 0;
        }
        $row = $query->row();
        return (int) $row->numrows;
    }
    /**
     * Get_Where
     *
     * Allows the where clause, limit and offset to be added directly
     *
     * @param        string        the where clause
     * @param        string        the limit clause
     * @param        string        the offset clause
     * @return        object
     */
    public function get_where($table = '', $where = null, $limit = null, $offset = null) {
        if ($table != '') {
            $this->from($table);
        }
        if (!is_null($where)) {
            $this->where($where);
        }
        if (!is_null($limit)) {
            $this->limit($limit, $offset);
        }
        $sql = $this->_compile_select();
        $result = $this->query($sql);
        $this->_reset_select();
        return $result;
    }
    /**
     * Insert_Batch
     *
     * Compiles batch insert strings and runs the queries
     *
     * @param        string        the table to retrieve the results from
     * @param        array        an associative array of insert values
     * @return        object
     */
    public function insert_batch($table = '', $set = NULL) {
        if (!is_null($set)) {
            $this->set_insert_batch($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {
            $sql = $this->_insert_batch($this->_protect_identifiers($table, TRUE, NULL, FALSE), $this->ar_keys, array_slice($this->ar_set, $i, 100));
            $this->query($sql);
        }
        $this->_reset_write();
        return TRUE;
    }
    /**
     * The "set_insert_batch" function.  Allows key/value pairs to be set for batch inserts
     *
     * @param        mixed
     * @param        string
     * @param        boolean
     * @return        object
     */
    public function set_insert_batch($key, $value = '', $escape = TRUE) {
        $key = $this->_object_to_array_batch($key);
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        $keys = array_keys(current($key));
        sort($keys);
        foreach ($key as $row) {
            if (count(array_diff($keys, array_keys($row))) > 0 OR count(array_diff(array_keys($row), $keys)) > 0) {
                $this->ar_set[] = array();
                return;
            }
            ksort($row); // puts $row in the same order as our keys
            if ($escape === FALSE) {
                $this->ar_set[] = '(' . implode(',', $row) . ')';
            } else {
                $clean = array();
                foreach ($row as $value) {
                    $clean[] = $this->escape($value);
                }
                $this->ar_set[] = '(' . implode(',', $clean) . ')';
            }
        }
        foreach ($keys as $k) {
            $this->ar_keys[] = $this->_protect_identifiers($k);
        }
        return $this;
    }
    /**
     * Insert
     *
     * Compiles an insert string and runs the query
     *
     * @param        string        the table to insert data into
     * @param        array        an associative array of insert values
     * @return        object
     */
    function insert($table = '', $set = NULL) {
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        $sql = $this->_insert($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_keys($this->ar_set), array_values($this->ar_set));
        $this->_reset_write();
        return $this->query($sql);
    }
    /**
     * Replace
     *
     * Compiles an replace into string and runs the query
     *
     * @param        string        the table to replace data into
     * @param        array        an associative array of insert values
     * @return        object
     */
    public function replace($table = '', $set = NULL) {
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        $sql = $this->_replace($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_keys($this->ar_set), array_values($this->ar_set));
        $this->_reset_write();
        return $this->query($sql);
    }
    /**
     * Update
     *
     * Compiles an update string and runs the query
     *
     * @param        string        the table to retrieve the results from
     * @param        array        an associative array of update values
     * @param        mixed        the where clause
     * @return        object
     */
    public function update($table = '', $set = NULL, $where = NULL, $limit = NULL) {
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        if ($where != NULL) {
            $this->where($where);
        }
        if ($limit != NULL) {
            $this->limit($limit);
        }
        $sql = $this->_update($this->_protect_identifiers($table, TRUE, NULL, FALSE), $this->ar_set, $this->ar_where, $this->ar_orderby, $this->ar_limit);
        $this->_reset_write();
        return $this->query($sql);
    }
    /**
     * Update_Batch
     *
     * Compiles an update string and runs the query
     *
     * @param        string        the table to retrieve the results from
     * @param        array        an associative array of update values
     * @param        string        the where key
     * @return        object
     */
    public function update_batch($table = '', $set = NULL, $index = NULL) {
        if (is_null($index)) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_index');
            }
            return FALSE;
        }
        if (!is_null($set)) {
            $this->set_update_batch($set, $index);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {
            $sql = $this->_update_batch($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_slice($this->ar_set, $i, 100), $this->_protect_identifiers($index), $this->ar_where);
            $this->query($sql);
        }
        $this->_reset_write();
    }
    /**
     * The "set_update_batch" function.  Allows key/value pairs to be set for batch updating
     *
     * @param        array
     * @param        string
     * @param        boolean
     * @return        object
     */
    public function set_update_batch($key, $index = '', $escape = TRUE) {
        $key = $this->_object_to_array_batch($key);
        if (!is_array($key)) {
// @todo error
        }
        foreach ($key as $k => $v) {
            $index_set = FALSE;
            $clean = array();
            foreach ($v as $k2 => $v2) {
                if ($k2 == $index) {
                    $index_set = TRUE;
                } else {
                    $not[] = $k2 . '-' . $v2;
                }
                if ($escape === FALSE) {
                    $clean[$this->_protect_identifiers($k2)] = $v2;
                } else {
                    $clean[$this->_protect_identifiers($k2)] = $this->escape($v2);
                }
            }
            if ($index_set == FALSE) {
                return $this->display_error('db_batch_missing_index');
            }
            $this->ar_set[] = $clean;
        }
        return $this;
    }
    /**
     * Empty Table
     *
     * Compiles a delete string and runs "DELETE FROM table"
     *
     * @param        string        the table to empty
     * @return        object
     */
    public function empty_table($table = '') {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        $sql = $this->_delete($table);
        $this->_reset_write();
        return $this->query($sql);
    }
    /**
     * Truncate
     *
     * Compiles a truncate string and runs the query
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @param        string        the table to truncate
     * @return        object
     */
    public function truncate($table = '') {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        $sql = $this->_truncate($table);
        $this->_reset_write();
        return $this->query($sql);
    }
    /**
     * Delete
     *
     * Compiles a delete string and runs the query
     *
     * @param        mixed        the table(s) to delete from. String or array
     * @param        mixed        the where clause
     * @param        mixed        the limit clause
     * @param        boolean
     * @return        object
     */
    public function delete($table = '', $where = '', $limit = NULL, $reset_data = TRUE) {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug || systemInfo('error_manage')) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } elseif (is_array($table)) {
            foreach ($table as $single_table) {
                $this->delete($single_table, $where, $limit, FALSE);
            }
            $this->_reset_write();
            return;
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        if ($where != '') {
            $this->where($where);
        }
        if ($limit != NULL) {
            $this->limit($limit);
        }
        if (count($this->ar_where) == 0 && count($this->ar_wherein) == 0 && count($this->ar_like) == 0) {
            if ($this->db_debug || systemInfo('error_manage')) {
                return $this->display_error('db_del_must_use_where');
            }
            return FALSE;
        }
        $sql = $this->_delete($table, $this->ar_where, $this->ar_like, $this->ar_limit);
        if ($reset_data) {
            $this->_reset_write();
        }
        return $this->query($sql);
    }
    /**
     * DB Prefix
     *
     * Prepends a database prefix if one exists in configuration
     *
     * @param        string        the table
     * @return        string
     */
    public function dbprefix($table = '') {
        if ($table == '') {
            $this->display_error('db_table_name_required');
        }
        return $this->dbprefix . $table;
    }
    /**
     * Set DB Prefix
     *
     * Set's the DB Prefix to something new without needing to reconnect
     *
     * @param        string        the prefix
     * @return        string
     */
    public function set_dbprefix($prefix = '') {
        return $this->dbprefix = $prefix;
    }
    /**
     * Track Aliases
     *
     * Used to track SQL statements written with aliased tables.
     *
     * @param        string        The table to inspect
     * @return        string
     */
    protected function _track_aliases($table) {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->_track_aliases($t);
            }
            return;
        }
        if (strpos($table, ',') !== FALSE) {
            return $this->_track_aliases(explode(',', $table));
        }
        if (strpos($table, " ") !== FALSE) {
            $table = preg_replace('/\s+AS\s+/i', ' ', $table);
            $table = trim(strrchr($table, " "));
            if (!in_array($table, $this->ar_aliased_tables)) {
                $this->ar_aliased_tables[] = $table;
            }
        }
    }
    /**
     * Compile the SELECT statement
     *
     * Generates a query string based on which functions were used.
     * Should not be called directly.  The get() function calls it.
     *
     * @return        string
     */
    protected function _compile_select($select_override = FALSE) {
// ----------------------------------------------------------------
        if ($select_override !== FALSE) {
            $sql = $select_override;
        } else {
            $sql = (!$this->ar_distinct) ? 'SELECT ' : 'SELECT DISTINCT ';
            if (count($this->ar_select) == 0) {
                $sql .= '*';
            } else {
                foreach ($this->ar_select as $key => $val) {
                    $no_escape = isset($this->ar_no_escape[$key]) ? $this->ar_no_escape[$key] : NULL;
                    $this->ar_select[$key] = $this->_protect_identifiers($val, FALSE, $no_escape);
                }
                $sql .= implode(', ', $this->ar_select);
            }
        }
// ----------------------------------------------------------------
        if (count($this->ar_from) > 0) {
            $sql .= "\nFROM ";
            $sql .= $this->_from_tables($this->ar_from);
        }
// ----------------------------------------------------------------
        if (count($this->ar_join) > 0) {
            $sql .= "\n";
            $sql .= implode("\n", $this->ar_join);
        }
// ----------------------------------------------------------------
        if (count($this->ar_where) > 0 OR count($this->ar_like) > 0) {
            $sql .= "\nWHERE ";
        }
        $sql .= implode("\n", $this->ar_where);
// ----------------------------------------------------------------
        if (count($this->ar_like) > 0) {
            if (count($this->ar_where) > 0) {
                $sql .= "\nAND ";
            }
            $sql .= implode("\n", $this->ar_like);
        }
// ----------------------------------------------------------------
        if (count($this->ar_groupby) > 0) {
            $sql .= "\nGROUP BY ";
            $sql .= implode(', ', $this->ar_groupby);
        }
// ----------------------------------------------------------------
        if (count($this->ar_having) > 0) {
            $sql .= "\nHAVING ";
            $sql .= implode("\n", $this->ar_having);
        }
// ----------------------------------------------------------------
        if (count($this->ar_orderby) > 0) {
            $sql .= "\nORDER BY ";
            $sql .= implode(', ', $this->ar_orderby);
            if ($this->ar_order !== FALSE) {
                $sql .= ($this->ar_order == 'desc') ? ' DESC' : ' ASC';
            }
        }
// ----------------------------------------------------------------
        if (is_numeric($this->ar_limit)) {
            $sql .= "\n";
            $sql = $this->_limit($sql, $this->ar_limit, $this->ar_offset);
        }
        return $sql;
    }
    /**
     * Object to Array
     *
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param        object
     * @return        array
     */
    public function _object_to_array($object) {
        if (!is_object($object)) {
            return $object;
        }
        $array = array();
        foreach (get_object_vars($object) as $key => $val) {
            if (!is_object($val) && !is_array($val) && $key != '_parent_name') {
                $array[$key] = $val;
            }
        }
        return $array;
    }
    /**
     * Object to Array
     *
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param        object
     * @return        array
     */
    public function _object_to_array_batch($object) {
        if (!is_object($object)) {
            return $object;
        }
        $array = array();
        $out = get_object_vars($object);
        $fields = array_keys($out);
        foreach ($fields as $val) {
            if ($val != '_parent_name') {
                $i = 0;
                foreach ($out[$val] as $data) {
                    $array[$i][$val] = $data;
                    $i++;
                }
            }
        }
        return $array;
    }

    /**
     * Resets the active record values.  Called by the get() function
     *
     * @param        array        An array of fields to reset
     * @return        void
     */
    protected function _reset_run($ar_reset_items) {
        foreach ($ar_reset_items as $item => $default_value) {
            if (!in_array($item, $this->ar_store_array)) {
                $this->$item = $default_value;
            }
        }
    }
    /**
     * Resets the active record values.  Called by the get() function
     *
     * @return        void
     */
    protected function _reset_select() {
        $ar_reset_items = array(
            'ar_select' => array(),
            'ar_from' => array(),
            'ar_join' => array(),
            'ar_where' => array(),
            'ar_like' => array(),
            'ar_groupby' => array(),
            'ar_having' => array(),
            'ar_orderby' => array(),
            'ar_wherein' => array(),
            'ar_aliased_tables' => array(),
            'ar_no_escape' => array(),
            'ar_distinct' => FALSE,
            'ar_limit' => FALSE,
            'ar_offset' => FALSE,
            'ar_order' => FALSE,
        );
        $this->_reset_run($ar_reset_items);
    }
    /**
     * Resets the active record "write" values.
     *
     * Called by the insert() update() insert_batch() update_batch() and delete() functions
     *
     * @return        void
     */
    protected function _reset_write() {
        $ar_reset_items = array(
            'ar_set' => array(),
            'ar_from' => array(),
            'ar_where' => array(),
            'ar_like' => array(),
            'ar_orderby' => array(),
            'ar_keys' => array(),
            'ar_limit' => FALSE,
            'ar_order' => FALSE
        );
        $this->_reset_run($ar_reset_items);
    }
}

/**
 * MySQL Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package                CodeIgniter
 * @subpackage        Drivers
 * @category        Database
 * @author                ExpressionEngine Dev Team
 * @link                http://codeigniter.com/user_guide/database/
 */
class CI_DB_mysql_driver extends CI_DB {
    var $dbdriver = 'mysql';
    var $_escape_char = '`';
    var $_like_escape_str = '';
    var $_like_escape_chr = '';
    /**
     * Whether to use the MySQL "delete hack" which allows the number
     * of affected rows to be shown. Uses a preg_replace when enabled,
     * adding a bit more processing to all queries.
     */
    var $delete_hack = TRUE;
    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    var $_count_string = 'SELECT COUNT(*) AS ';
    var $_random_keyword = ' RAND()'; // database specific random keyword
    var $use_set_names;
    /**
     * Non-persistent database connection
     *
     * @access        private called by the base class
     * @return        resource
     */
    function db_connect() {
        if ($this->port != '') {
            $this->hostname .= ':' . $this->port;
        }
        return @mysql_connect($this->hostname, $this->username, $this->password, TRUE);
    }
    /**
     * Persistent database connection
     *
     * @access        private called by the base class
     * @return        resource
     */
    function db_pconnect() {
        if ($this->port != '') {
            $this->hostname .= ':' . $this->port;
        }
        return @mysql_pconnect($this->hostname, $this->username, $this->password);
    }
    /**
     * Reconnect
     *
     * Keep / reestablish the db connection if no queries have been
     * sent for a length of time exceeding the server's idle timeout
     *
     * @access        public
     * @return        void
     */
    function reconnect() {
        if (mysql_ping($this->conn_id) === FALSE) {
            $this->conn_id = FALSE;
        }
    }
    /**
     * Select the database
     *
     * @access        private called by the base class
     * @return        resource
     */
    function db_select() {
        return @mysql_select_db($this->database, $this->conn_id);
    }
    /**
     * Set client character set
     *
     * @access        public
     * @param        string
     * @param        string
     * @return        resource
     */
    function _db_set_charset($charset, $collation) {
        if (!isset($this->use_set_names)) {
            $this->use_set_names = (version_compare(PHP_VERSION, '5.2.3', '>=') && version_compare(mysql_get_server_info(), '5.0.7', '>=')) ? FALSE : TRUE;
        }
        if ($this->use_set_names === TRUE) {
            return @mysql_query("SET NAMES '" . $this->escape_str($charset) . "' COLLATE '" . $this->escape_str($collation) . "'", $this->conn_id);
        } else {
            return @mysql_set_charset($charset, $this->conn_id);
        }
    }
    /**
     * Version number query string
     *
     * @access        public
     * @return        string
     */
    function _version() {
        return "SELECT version() AS ver";
    }
    /**
     * Execute the query
     *
     * @access        private called by the base class
     * @param        string        an SQL query
     * @return        resource
     */
    function _execute($sql) {
        $sql = $this->_prep_query($sql);
        return @mysql_query($sql, $this->conn_id);
    }
    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access        private called by execute()
     * @param        string        an SQL query
     * @return        string
     */
    function _prep_query($sql) {
// "DELETE FROM TABLE" returns 0 affected rows This hack modifies
        if ($this->delete_hack === TRUE) {
            if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql)) {
                $sql = preg_replace("/^\s*DELETE\s+FROM\s+(\S+)\s*$/", "DELETE FROM \\1 WHERE 1=1", $sql);
            }
        }
        return $sql;
    }
    /**
     * Begin Transaction
     *
     * @access        public
     * @return        bool
     */
    function trans_begin($test_mode = FALSE) {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;
        $this->simple_query('SET AUTOCOMMIT=0');
        $this->simple_query('START TRANSACTION'); // can also be BEGIN or BEGIN WORK
        return TRUE;
    }
    /**
     * Commit Transaction
     *
     * @access        public
     * @return        bool
     */
    function trans_commit() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('COMMIT');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }
    /**
     * Rollback Transaction
     *
     * @access        public
     * @return        bool
     */
    function trans_rollback() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('ROLLBACK');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }
    /**
     * Escape String
     *
     * @access        public
     * @param        string
     * @param        bool        whether or not the string will be used in a LIKE condition
     * @return        string
     */
    function escape_str($str, $like = FALSE) {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_str($val, $like);
            }
            return $str;
        }
        if (function_exists('mysql_real_escape_string') AND is_resource($this->conn_id)) {
            $str = mysql_real_escape_string($str, $this->conn_id);
        } elseif (function_exists('mysql_escape_string') && (version_compare(PHP_VERSION, '5.3.0','<'))) {
            $str = mysql_escape_string($str);
        } else {
            $str = addslashes($str);
        }
        if ($like === TRUE) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }
        return $str;
    }
    /**
     * Affected Rows
     *
     * @access        public
     * @return        integer
     */
    function affected_rows() {
        return @mysql_affected_rows($this->conn_id);
    }
    /**
     * Insert ID
     *
     * @access        public
     * @return        integer
     */
    function insert_id() {
        return @mysql_insert_id($this->conn_id);
    }
    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access        public
     * @param        string
     * @return        string
     */
    function count_all($table = '') {
        if ($table == '') {
            return 0;
        }
        $query = $this->query($this->_count_string . $this->_protect_identifiers('numrows') . " FROM " . $this->_protect_identifiers($table, TRUE, NULL, FALSE));
        if ($query->num_rows() == 0) {
            return 0;
        }
        $row = $query->row();
        $this->_reset_select();
        return (int) $row->numrows;
    }
    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access        private
     * @param        boolean
     * @return        string
     */
    function _list_tables($prefix_limit = FALSE) {
        $sql = "SHOW TABLES FROM " . $this->_escape_char . $this->database . $this->_escape_char;
        if ($prefix_limit !== FALSE AND $this->dbprefix != '') {
            $sql .= " LIKE '" . $this->escape_like_str($this->dbprefix) . "%'";
        }
        return $sql;
    }
    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access        public
     * @param        string        the table name
     * @return        string
     */
    function _list_columns($table = '') {
        return "SHOW COLUMNS FROM " . $this->_protect_identifiers($table, TRUE, NULL, FALSE);
    }
    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access        public
     * @param        string        the table name
     * @return        object
     */
    function _field_data($table) {
        return "DESCRIBE " . $table;
    }
    /**
     * The error message string
     *
     * @access        private
     * @return        string
     */
    function _error_message() {
        return mysql_error($this->conn_id);
    }
    /**
     * The error message number
     *
     * @access        private
     * @return        integer
     */
    function _error_number() {
        return mysql_errno($this->conn_id);
    }
    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access        private
     * @param        string
     * @return        string
     */
    function _escape_identifiers($item) {
        if ($this->_escape_char == '') {
            return $item;
        }
        foreach ($this->_reserved_identifiers as $id) {
            if (strpos($item, '.' . $id) !== FALSE) {
                $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.', $item);
                return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
            }
        }
        if (strpos($item, '.') !== FALSE) {
            $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.' . $this->_escape_char, $item) . $this->_escape_char;
        } else {
            $str = $this->_escape_char . $item . $this->_escape_char;
        }
        return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
    }
    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access        public
     * @param        type
     * @return        type
     */
    function _from_tables($tables) {
        if (!is_array($tables)) {
            $tables = array($tables);
        }
        return '(' . implode(', ', $tables) . ')';
    }
    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access        public
     * @param        string        the table name
     * @param        array        the insert keys
     * @param        array        the insert values
     * @return        string
     */
    function _insert($table, $keys, $values) {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }
    /**
     * Replace statement
     *
     * Generates a platform-specific replace string from the supplied data
     *
     * @access        public
     * @param        string        the table name
     * @param        array        the insert keys
     * @param        array        the insert values
     * @return        string
     */
    function _replace($table, $keys, $values) {
        return "REPLACE INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }
    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access        public
     * @param        string        the table name
     * @param        array        the insert keys
     * @param        array        the insert values
     * @return        string
     */
    function _insert_batch($table, $keys, $values) {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES " . implode(', ', $values);
    }
    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @access        public
     * @param        string        the table name
     * @param        array        the update data
     * @param        array        the where clause
     * @param        array        the orderby clause
     * @param        array        the limit clause
     * @return        string
     */
    function _update($table, $values, $where, $orderby = array(), $limit = FALSE) {
        foreach ($values as $key => $val) {
            $valstr[] = $key . ' = ' . $val;
        }
        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;
        $orderby = (count($orderby) >= 1) ? ' ORDER BY ' . implode(", ", $orderby) : '';
        $sql = "UPDATE " . $table . " SET " . implode(', ', $valstr);
        $sql .= ($where != '' AND count($where) >= 1) ? " WHERE " . implode(" ", $where) : '';
        $sql .= $orderby . $limit;
        return $sql;
    }
    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access        public
     * @param        string        the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    function _update_batch($table, $values, $index, $where = NULL) {
        $ids = array();
        $where = ($where != '' AND count($where) >= 1) ? implode(" ", $where) . ' AND ' : '';
        foreach ($values as $key => $val) {
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = 'WHEN ' . $index . ' = ' . $val[$index] . ' THEN ' . $val[$field];
                }
            }
        }
        $sql = "UPDATE " . $table . " SET ";
        $cases = '';
        foreach ($final as $k => $v) {
            $cases .= $k . ' = CASE ' . "\n";
            foreach ($v as $row) {
                $cases .= $row . "\n";
            }
            $cases .= 'ELSE ' . $k . ' END, ';
        }
        $sql .= substr($cases, 0, -2);
        $sql .= ' WHERE ' . $where . $index . ' IN (' . implode(',', $ids) . ')';
        return $sql;
    }
    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _truncate($table) {
        return "TRUNCATE " . $table;
    }
    /**
     * Delete statement
     *
     * Generates a platform-specific delete string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the where clause
     * @param	string	the limit clause
     * @return	string
     */
    function _delete($table, $where = array(), $like = array(), $limit = FALSE) {
        $conditions = '';
        if (count($where) > 0 OR count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);
            if (count($where) > 0 && count($like) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }
        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;
        return "DELETE FROM " . $table . $conditions . $limit;
    }
    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access	public
     * @param	string	the sql query string
     * @param	integer	the number of rows to limit the query to
     * @param	integer	the offset value
     * @return	string
     */
    function _limit($sql, $limit, $offset) {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }
        return $sql . "LIMIT " . $offset . $limit;
    }
    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    function _close($conn_id) {
        @mysql_close($conn_id);
    }
}
/* End of file mysql_driver.php */
/* Location: ./system/database/drivers/mysql/mysql_driver.php */
/**
 * MySQL Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_mysql_result extends CI_DB_result {
    /**
     * Number of rows in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_rows() {
        return @mysql_num_rows($this->result_id);
    }
    /**
     * Number of fields in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_fields() {
        return @mysql_num_fields($this->result_id);
    }
    /**
     * Fetch Field Names
     *
     * Generates an array of column names
     *
     * @access	public
     * @return	array
     */
    function list_fields() {
        $field_names = array();
        while ($field = mysql_fetch_field($this->result_id)) {
            $field_names[] = $field->name;
        }
        return $field_names;
    }
    /**
     * Field data
     *
     * Generates an array of objects containing field meta-data
     *
     * @access	public
     * @return	array
     */
    function field_data() {
        $retval = array();
        while ($field = mysql_fetch_object($this->result_id)) {
            preg_match('/([a-zA-Z]+)(\(\d+\))?/', $field->Type, $matches);
            $type = (array_key_exists(1, $matches)) ? $matches[1] : NULL;
            $length = (array_key_exists(2, $matches)) ? preg_replace('/[^\d]/', '', $matches[2]) : NULL;
            $F = new stdClass();
            $F->name = $field->Field;
            $F->type = $type;
            $F->default = $field->Default;
            $F->max_length = $length;
            $F->primary_key = ( $field->Key == 'PRI' ? 1 : 0 );
            $retval[] = $F;
        }
        return $retval;
    }
    /**
     * Free the result
     *
     * @return	null
     */
    function free_result() {
        if (is_resource($this->result_id)) {
            mysql_free_result($this->result_id);
            $this->result_id = FALSE;
        }
    }
    /**
     * Data Seek
     *
     * Moves the internal pointer to the desired offset.  We call
     * this internally before fetching results to make sure the
     * result set starts at zero
     *
     * @access	private
     * @return	array
     */
    function _data_seek($n = 0) {
        return mysql_data_seek($this->result_id, $n);
    }
    /**
     * Result - associative array
     *
     * Returns the result set as an array
     *
     * @access	private
     * @return	array
     */
    function _fetch_assoc() {
        return mysql_fetch_assoc($this->result_id);
    }
    /**
     * Result - object
     *
     * Returns the result set as an object
     *
     * @access	private
     * @return	object
     */
    function _fetch_object() {
        return mysql_fetch_object($this->result_id);
    }
}
/* End of file mysql_result.php */
/* Location: ./system/database/drivers/mysql/mysql_result.php */
/**
 * MySQLi Database Adapter Class - MySQLi only works with PHP 5
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_mysqli_driver extends CI_DB {
    var $dbdriver = 'mysqli';
    var $_escape_char = '`';
    var $_like_escape_str = '';
    var $_like_escape_chr = '';
    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    var $_count_string = "SELECT COUNT(*) AS ";
    var $_random_keyword = ' RAND()'; // database specific random keyword
    /**
     * Whether to use the MySQL "delete hack" which allows the number
     * of affected rows to be shown. Uses a preg_replace when enabled,
     * adding a bit more processing to all queries.
     */
    var $delete_hack = TRUE;
    var $use_set_names;

    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_connect() {
        if ($this->port != '') {
            return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database, $this->port);
        } else {
            return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database);
        }
    }

    /**
     * Persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_pconnect() {
        return $this->db_connect();
    }

    /**
     * Reconnect
     *
     * Keep / reestablish the db connection if no queries have been
     * sent for a length of time exceeding the server's idle timeout
     *
     * @access	public
     * @return	void
     */
    function reconnect() {
        if (mysqli_ping($this->conn_id) === FALSE) {
            $this->conn_id = FALSE;
        }
    }

    /**
     * Select the database
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_select() {
        return @mysqli_select_db($this->conn_id, $this->database);
    }

    /**
     * Set client character set
     *
     * @access	private
     * @param	string
     * @param	string
     * @return	resource
     */
    function _db_set_charset($charset, $collation) {
        if (!isset($this->use_set_names)) {
            $this->use_set_names = (version_compare(mysqli_get_server_info($this->conn_id), '5.0.7', '>=')) ? FALSE : TRUE;
        }
        if ($this->use_set_names === TRUE) {
            return @mysqli_query($this->conn_id, "SET NAMES '" . $this->escape_str($charset) . "' COLLATE '" . $this->escape_str($collation) . "'");
        } else {
            return @mysqli_set_charset($this->conn_id, $charset);
        }
    }

    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    function _version() {
        return "SELECT version() AS ver";
    }

    /**
     * Execute the query
     *
     * @access	private called by the base class
     * @param	string	an SQL query
     * @return	resource
     */
    function _execute($sql) {
        $sql = $this->_prep_query($sql);
        $result = @mysqli_query($this->conn_id, $sql);
        return $result;
    }

    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access	private called by execute()
     * @param	string	an SQL query
     * @return	string
     */
    function _prep_query($sql) {
        // "DELETE FROM TABLE" returns 0 affected rows This hack modifies
        if ($this->delete_hack === TRUE) {
            if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql)) {
                $sql = preg_replace("/^\s*DELETE\s+FROM\s+(\S+)\s*$/", "DELETE FROM \\1 WHERE 1=1", $sql);
            }
        }
        return $sql;
    }

    /**
     * Begin Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_begin($test_mode = FALSE) {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;
        $this->simple_query('SET AUTOCOMMIT=0');
        $this->simple_query('START TRANSACTION'); // can also be BEGIN or BEGIN WORK
        return TRUE;
    }

    /**
     * Commit Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_commit() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('COMMIT');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }

    /**
     * Rollback Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_rollback() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('ROLLBACK');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }

    /**
     * Escape String
     *
     * @access	public
     * @param	string
     * @param	bool	whether or not the string will be used in a LIKE condition
     * @return	string
     */
    function escape_str($str, $like = FALSE) {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_str($val, $like);
            }
            return $str;
        }
        if (function_exists('mysqli_real_escape_string') AND is_object($this->conn_id)) {
            $str = mysqli_real_escape_string($this->conn_id, $str);
        } elseif (function_exists('mysql_escape_string') && version_compare(PHP_VERSION, '5.3.0', '<')) {
            $str = mysql_escape_string($str);
        } else {
            $str = addslashes($str);
        }
        if ($like === TRUE) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }
        return $str;
    }

    /**
     * Affected Rows
     *
     * @access	public
     * @return	integer
     */
    function affected_rows() {
        return @mysqli_affected_rows($this->conn_id);
    }

    /**
     * Insert ID
     *
     * @access	public
     * @return	integer
     */
    function insert_id() {
        return @mysqli_insert_id($this->conn_id);
    }

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access	public
     * @param	string
     * @return	string
     */
    function count_all($table = '') {
        if ($table == '') {
            return 0;
        }
        $query = $this->query($this->_count_string . $this->_protect_identifiers('numrows') . " FROM " . $this->_protect_identifiers($table, TRUE, NULL, FALSE));
        if ($query->num_rows() == 0) {
            return 0;
        }
        $row = $query->row();
        $this->_reset_select();
        return (int) $row->numrows;
    }

    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access	private
     * @param	boolean
     * @return	string
     */
    function _list_tables($prefix_limit = FALSE) {
        $sql = "SHOW TABLES FROM " . $this->_escape_char . $this->database . $this->_escape_char;
        if ($prefix_limit !== FALSE AND $this->dbprefix != '') {
            $sql .= " LIKE '" . $this->escape_like_str($this->dbprefix) . "%'";
        }
        return $sql;
    }

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _list_columns($table = '') {
        return "SHOW COLUMNS FROM " . $this->_protect_identifiers($table, TRUE, NULL, FALSE);
    }

    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access	public
     * @param	string	the table name
     * @return	object
     */
    function _field_data($table) {
        return "DESCRIBE " . $table;
    }

    /**
     * The error message string
     *
     * @access	private
     * @return	string
     */
    function _error_message() {
        return mysqli_error($this->conn_id);
    }

    /**
     * The error message number
     *
     * @access	private
     * @return	integer
     */
    function _error_number() {
        return mysqli_errno($this->conn_id);
    }

    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access	private
     * @param	string
     * @return	string
     */
    function _escape_identifiers($item) {
        if ($this->_escape_char == '') {
            return $item;
        }
        foreach ($this->_reserved_identifiers as $id) {
            if (strpos($item, '.' . $id) !== FALSE) {
                $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.', $item);
                return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
            }
        }
        if (strpos($item, '.') !== FALSE) {
            $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.' . $this->_escape_char, $item) . $this->_escape_char;
        } else {
            $str = $this->_escape_char . $item . $this->_escape_char;
        }
        return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
    }

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access	public
     * @param	type
     * @return	type
     */
    function _from_tables($tables) {
        if (!is_array($tables)) {
            $tables = array($tables);
        }
        return '(' . implode(', ', $tables) . ')';
    }

    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    function _insert($table, $keys, $values) {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    function _insert_batch($table, $keys, $values) {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES " . implode(', ', $values);
    }

    /**
     * Replace statement
     *
     * Generates a platform-specific replace string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    function _replace($table, $keys, $values) {
        return "REPLACE INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @param	array	the orderby clause
     * @param	array	the limit clause
     * @return	string
     */
    function _update($table, $values, $where, $orderby = array(), $limit = FALSE) {
        foreach ($values as $key => $val) {
            $valstr[] = $key . " = " . $val;
        }
        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;
        $orderby = (count($orderby) >= 1) ? ' ORDER BY ' . implode(", ", $orderby) : '';
        $sql = "UPDATE " . $table . " SET " . implode(', ', $valstr);
        $sql .= ($where != '' AND count($where) >= 1) ? " WHERE " . implode(" ", $where) : '';
        $sql .= $orderby . $limit;
        return $sql;
    }

    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    function _update_batch($table, $values, $index, $where = NULL) {
        $ids = array();
        $where = ($where != '' AND count($where) >= 1) ? implode(" ", $where) . ' AND ' : '';
        foreach ($values as $key => $val) {
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = 'WHEN ' . $index . ' = ' . $val[$index] . ' THEN ' . $val[$field];
                }
            }
        }
        $sql = "UPDATE " . $table . " SET ";
        $cases = '';
        foreach ($final as $k => $v) {
            $cases .= $k . ' = CASE ' . "\n";
            foreach ($v as $row) {
                $cases .= $row . "\n";
            }
            $cases .= 'ELSE ' . $k . ' END, ';
        }
        $sql .= substr($cases, 0, -2);
        $sql .= ' WHERE ' . $where . $index . ' IN (' . implode(',', $ids) . ')';
        return $sql;
    }

    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _truncate($table) {
        return "TRUNCATE " . $table;
    }

    /**
     * Delete statement
     *
     * Generates a platform-specific delete string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the where clause
     * @param	string	the limit clause
     * @return	string
     */
    function _delete($table, $where = array(), $like = array(), $limit = FALSE) {
        $conditions = '';
        if (count($where) > 0 OR count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);
            if (count($where) > 0 && count($like) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }
        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;
        return "DELETE FROM " . $table . $conditions . $limit;
    }

    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access	public
     * @param	string	the sql query string
     * @param	integer	the number of rows to limit the query to
     * @param	integer	the offset value
     * @return	string
     */
    function _limit($sql, $limit, $offset) {
        $sql .= "LIMIT " . $limit;
        if ($offset > 0) {
            $sql .= " OFFSET " . $offset;
        }
        return $sql;
    }

    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    function _close($conn_id) {
        @mysqli_close($conn_id);
    }
}

/**
 * MySQLi Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_mysqli_result extends CI_DB_result {
    /**
     * Number of rows in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_rows() {
        return @mysqli_num_rows($this->result_id);
    }

    /**
     * Number of fields in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_fields() {
        return @mysqli_num_fields($this->result_id);
    }

    /**
     * Fetch Field Names
     *
     * Generates an array of column names
     *
     * @access	public
     * @return	array
     */
    function list_fields() {
        $field_names = array();
        while ($field = mysqli_fetch_field($this->result_id)) {
            $field_names[] = $field->name;
        }
        return $field_names;
    }

    /**
     * Field data
     *
     * Generates an array of objects containing field meta-data
     *
     * @access	public
     * @return	array
     */
    function field_data() {
        $retval = array();
        while ($field = mysqli_fetch_object($this->result_id)) {
            preg_match('/([a-zA-Z]+)(\(\d+\))?/', $field->Type, $matches);
            $type = (array_key_exists(1, $matches)) ? $matches[1] : NULL;
            $length = (array_key_exists(2, $matches)) ? preg_replace('/[^\d]/', '', $matches[2]) : NULL;
            $F = new stdClass();
            $F->name = $field->Field;
            $F->type = $type;
            $F->default = $field->Default;
            $F->max_length = $length;
            $F->primary_key = ( $field->Key == 'PRI' ? 1 : 0 );
            $retval[] = $F;
        }
        return $retval;
    }

    /**
     * Free the result
     *
     * @return	null
     */
    function free_result() {
        if (is_object($this->result_id)) {
            mysqli_free_result($this->result_id);
            $this->result_id = FALSE;
        }
    }

    /**
     * Data Seek
     *
     * Moves the internal pointer to the desired offset.  We call
     * this internally before fetching results to make sure the
     * result set starts at zero
     *
     * @access	private
     * @return	array
     */
    function _data_seek($n = 0) {
        return mysqli_data_seek($this->result_id, $n);
    }

    /**
     * Result - associative array
     *
     * Returns the result set as an array
     *
     * @access	private
     * @return	array
     */
    function _fetch_assoc() {
        return mysqli_fetch_assoc($this->result_id);
    }

    /**
     * Result - object
     *
     * Returns the result set as an object
     *
     * @access	private
     * @return	object
     */
    function _fetch_object() {
        return mysqli_fetch_object($this->result_id);
    }
}

/**
 * PDO Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_pdo_driver extends CI_DB{
    var $dbdriver = 'pdo';
    // the character used to excape - not necessary for PDO
    var $_escape_char = '';
    var $_like_escape_str;
    var $_like_escape_chr;

    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    var $_count_string = "SELECT COUNT(*) AS ";
    var $_random_keyword;

    var $options = array();
    function __construct($params)
    {
        parent::__construct($params);
        // clause and character used for LIKE escape sequences
        if (strpos($this->hostname, 'mysql') !== FALSE)
        {
            $this->_like_escape_str = '';
            $this->_like_escape_chr = '';
            //Prior to this version, the charset can't be set in the dsn
            if(is_php('5.3.6'))
            {
                $this->hostname .= ";charset={$this->char_set}";
            }
            //Set the charset with the connection options
            $this->options['PDO::MYSQL_ATTR_INIT_COMMAND'] = "SET NAMES {$this->char_set}";
        }
        elseif (strpos($this->hostname, 'odbc') !== FALSE)
        {
            $this->_like_escape_str = " {escape '%s'} ";
            $this->_like_escape_chr = '!';
        }
        else
        {
            $this->_like_escape_str = " ESCAPE '%s' ";
            $this->_like_escape_chr = '!';
        }
        empty($this->database) OR $this->hostname .= ';dbname='.$this->database;
        $this->trans_enabled = FALSE;
        $this->_random_keyword = ' RND('.time().')'; // database specific random keyword
    }
    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_connect()
    {
        $this->options['PDO::ATTR_ERRMODE'] = PDO::ERRMODE_SILENT;
        return new PDO($this->hostname, $this->username, $this->password, $this->options);
    }

    /**
     * Persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_pconnect()
    {
        $this->options['PDO::ATTR_ERRMODE'] = PDO::ERRMODE_SILENT;
        $this->options['PDO::ATTR_PERSISTENT'] = TRUE;

        return new PDO($this->hostname, $this->username, $this->password, $this->options);
    }

    /**
     * Reconnect
     *
     * Keep / reestablish the db connection if no queries have been
     * sent for a length of time exceeding the server's idle timeout
     *
     * @access	public
     * @return	void
     */
    function reconnect()
    {
        if ($this->db->db_debug)
        {
            return $this->db->display_error('db_unsuported_feature');
        }
        return FALSE;
    }

    /**
     * Select the database
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_select()
    {
        // Not needed for PDO
        return TRUE;
    }

    /**
     * Set client character set
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	resource
     */
    function _db_set_charset($charset, $collation)
    {
        // @todo - add support if needed
        return TRUE;
    }

    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    function _version()
    {
        return $this->conn_id->getAttribute(PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * Execute the query
     *
     * @access	private called by the base class
     * @param	string	an SQL query
     * @return	object
     */
    function _execute($sql)
    {
        $sql = $this->_prep_query($sql);
        $result_id = $this->conn_id->prepare($sql);
        $result_id->execute();

        if (is_object($result_id))
        {
            if (is_numeric(stripos($sql, 'SELECT')))
            {
                $this->affect_rows = count($result_id->fetchAll());
                $result_id->execute();
            }
            else
            {
                $this->affect_rows = $result_id->rowCount();
            }
        }
        else
        {
            $this->affect_rows = 0;
        }

        return $result_id;
    }

    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access	private called by execute()
     * @param	string	an SQL query
     * @return	string
     */
    function _prep_query($sql)
    {
        return $sql;
    }

    /**
     * Begin Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_begin($test_mode = FALSE)
    {
        if ( ! $this->trans_enabled)
        {
            return TRUE;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0)
        {
            return TRUE;
        }
        // Reset the transaction failure flag.
        // If the $test_mode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->_trans_failure = (bool) ($test_mode === TRUE);
        return $this->conn_id->beginTransaction();
    }

    /**
     * Commit Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_commit()
    {
        if ( ! $this->trans_enabled)
        {
            return TRUE;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0)
        {
            return TRUE;
        }
        $ret = $this->conn->commit();
        return $ret;
    }

    /**
     * Rollback Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_rollback()
    {
        if ( ! $this->trans_enabled)
        {
            return TRUE;
        }
        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0)
        {
            return TRUE;
        }
        $ret = $this->conn_id->rollBack();
        return $ret;
    }

    /**
     * Escape String
     *
     * @access	public
     * @param	string
     * @param	bool	whether or not the string will be used in a LIKE condition
     * @return	string
     */
    function escape_str($str, $like = FALSE)
    {
        if (is_array($str))
        {
            foreach ($str as $key => $val)
            {
                $str[$key] = $this->escape_str($val, $like);
            }
            return $str;
        }

        //Escape the string
        $str = $this->conn_id->quote($str);

        //If there are duplicated quotes, trim them away
        if (strpos($str, "'") === 0)
        {
            $str = substr($str, 1, -1);
        }

        // escape LIKE condition wildcards
        if ($like === TRUE)
        {
            $str = str_replace(	array('%', '_', $this->_like_escape_chr),
                array($this->_like_escape_chr.'%', $this->_like_escape_chr.'_', $this->_like_escape_chr.$this->_like_escape_chr),
                $str);
        }
        return $str;
    }

    /**
     * Affected Rows
     *
     * @access	public
     * @return	integer
     */
    function affected_rows()
    {
        return $this->affect_rows;
    }

    /**
     * Insert ID
     *
     * @access	public
     * @return	integer
     */
    function insert_id($name=NULL)
    {
        //Convenience method for postgres insertid
        if (strpos($this->hostname, 'pgsql') !== FALSE)
        {
            $v = $this->_version();
            $table	= func_num_args() > 0 ? func_get_arg(0) : NULL;
            if ($table == NULL && $v >= '8.1')
            {
                $sql='SELECT LASTVAL() as ins_id';
            }
            $query = $this->query($sql);
            $row = $query->row();
            return $row->ins_id;
        }
        else
        {
            return $this->conn_id->lastInsertId($name);
        }
    }

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access	public
     * @param	string
     * @return	string
     */
    function count_all($table = '')
    {
        if ($table == '')
        {
            return 0;
        }
        $query = $this->query($this->_count_string . $this->_protect_identifiers('numrows') . " FROM " . $this->_protect_identifiers($table, TRUE, NULL, FALSE));
        if ($query->num_rows() == 0)
        {
            return 0;
        }
        $row = $query->row();
        $this->_reset_select();
        return (int) $row->numrows;
    }

    /**
     * Show table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access	private
     * @param	boolean
     * @return	string
     */
    function _list_tables($prefix_limit = FALSE)
    {
        $sql = "SHOW TABLES FROM `".$this->database."`";
        if ($prefix_limit !== FALSE AND $this->dbprefix != '')
        {
            //$sql .= " LIKE '".$this->escape_like_str($this->dbprefix)."%' ".sprintf($this->_like_escape_str, $this->_like_escape_chr);
            return FALSE; // not currently supported
        }
        return $sql;
    }

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _list_columns($table = '')
    {
        return "SHOW COLUMNS FROM ".$table;
    }

    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access	public
     * @param	string	the table name
     * @return	object
     */
    function _field_data($table)
    {
        return "SELECT TOP 1 FROM ".$table;
    }

    /**
     * The error message string
     *
     * @access	private
     * @return	string
     */
    function _error_message()
    {
        $error_array = $this->conn_id->errorInfo();
        return $error_array[2];
    }

    /**
     * The error message number
     *
     * @access	private
     * @return	integer
     */
    function _error_number()
    {
        return $this->conn_id->errorCode();
    }

    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access	private
     * @param	string
     * @return	string
     */
    function _escape_identifiers($item)
    {
        if ($this->_escape_char == '')
        {
            return $item;
        }
        foreach ($this->_reserved_identifiers as $id)
        {
            if (strpos($item, '.'.$id) !== FALSE)
            {
                $str = $this->_escape_char. str_replace('.', $this->_escape_char.'.', $item);
                // remove duplicates if the user already included the escape
                return preg_replace('/['.$this->_escape_char.']+/', $this->_escape_char, $str);
            }
        }
        if (strpos($item, '.') !== FALSE)
        {
            $str = $this->_escape_char.str_replace('.', $this->_escape_char.'.'.$this->_escape_char, $item).$this->_escape_char;

        }
        else
        {
            $str = $this->_escape_char.$item.$this->_escape_char;
        }
        // remove duplicates if the user already included the escape
        return preg_replace('/['.$this->_escape_char.']+/', $this->_escape_char, $str);
    }

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access	public
     * @param	type
     * @return	type
     */
    function _from_tables($tables)
    {
        if ( ! is_array($tables))
        {
            $tables = array($tables);
        }
        return (count($tables) == 1) ? $tables[0] : '('.implode(', ', $tables).')';
    }

    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    function _insert($table, $keys, $values)
    {
        return "INSERT INTO ".$table." (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
    }


    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access  public
     * @param   string  the table name
     * @param   array   the insert keys
     * @param   array   the insert values
     * @return  string
     */
    function _insert_batch($table, $keys, $values)
    {
        return "INSERT INTO ".$table." (".implode(', ', $keys).") VALUES ".implode(', ', $values);
    }

    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @param	array	the orderby clause
     * @param	array	the limit clause
     * @return	string
     */
    function _update($table, $values, $where, $orderby = array(), $limit = FALSE)
    {
        foreach ($values as $key => $val)
        {
            $valstr[] = $key." = ".$val;
        }
        $limit = ( ! $limit) ? '' : ' LIMIT '.$limit;
        $orderby = (count($orderby) >= 1)?' ORDER BY '.implode(", ", $orderby):'';
        $sql = "UPDATE ".$table." SET ".implode(', ', $valstr);
        $sql .= ($where != '' AND count($where) >=1) ? " WHERE ".implode(" ", $where) : '';
        $sql .= $orderby.$limit;
        return $sql;
    }


    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    function _update_batch($table, $values, $index, $where = NULL)
    {
        $ids = array();
        $where = ($where != '' AND count($where) >=1) ? implode(" ", $where).' AND ' : '';
        foreach ($values as $key => $val)
        {
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field)
            {
                if ($field != $index)
                {
                    $final[$field][] =  'WHEN '.$index.' = '.$val[$index].' THEN '.$val[$field];
                }
            }
        }
        $sql = "UPDATE ".$table." SET ";
        $cases = '';
        foreach ($final as $k => $v)
        {
            $cases .= $k.' = CASE '."\n";
            foreach ($v as $row)
            {
                $cases .= $row."\n";
            }
            $cases .= 'ELSE '.$k.' END, ';
        }
        $sql .= substr($cases, 0, -2);
        $sql .= ' WHERE '.$where.$index.' IN ('.implode(',', $ids).')';
        return $sql;
    }

    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _truncate($table)
    {
        return $this->_delete($table);
    }

    /**
     * Delete statement
     *
     * Generates a platform-specific delete string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the where clause
     * @param	string	the limit clause
     * @return	string
     */
    function _delete($table, $where = array(), $like = array(), $limit = FALSE)
    {
        $conditions = '';
        if (count($where) > 0 OR count($like) > 0)
        {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);
            if (count($where) > 0 && count($like) > 0)
            {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }
        $limit = ( ! $limit) ? '' : ' LIMIT '.$limit;
        return "DELETE FROM ".$table.$conditions.$limit;
    }

    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access	public
     * @param	string	the sql query string
     * @param	integer	the number of rows to limit the query to
     * @param	integer	the offset value
     * @return	string
     */
    function _limit($sql, $limit, $offset)
    {
        if (strpos($this->hostname, 'cubrid') !== FALSE || strpos($this->hostname, 'sqlite') !== FALSE)
        {
            if ($offset == 0)
            {
                $offset = '';
            }
            else
            {
                $offset .= ", ";
            }
            return $sql."LIMIT ".$offset.$limit;
        }
        else
        {
            $sql .= "LIMIT ".$limit;
            if ($offset > 0)
            {
                $sql .= " OFFSET ".$offset;
            }

            return $sql;
        }
    }

    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    function _close($conn_id)
    {
        $this->conn_id = null;
    }
}
/* End of file pdo_driver.php */
/**
 * PDO Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
class CI_DB_pdo_result extends CI_DB_result {
    public $num_rows;
    /**
     * Number of rows in the result set
     *
     * @return	int
     */
    public function num_rows()
    {
        if (is_int($this->num_rows))
        {
            return $this->num_rows;
        }
        elseif (($this->num_rows = $this->result_id->rowCount()) > 0)
        {
            return $this->num_rows;
        }
        $this->num_rows = count($this->result_id->fetchAll());
        $this->result_id->execute();
        return $this->num_rows;
    }

    /**
     * Number of fields in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_fields()
    {
        return $this->result_id->columnCount();
    }

    /**
     * Fetch Field Names
     *
     * Generates an array of column names
     *
     * @access	public
     * @return	array
     */
    function list_fields()
    {
        if ($this->db->db_debug)
        {
            return $this->db->display_error('db_unsuported_feature');
        }
        return FALSE;
    }

    /**
     * Field data
     *
     * Generates an array of objects containing field meta-data
     *
     * @access	public
     * @return	array
     */
    function field_data()
    {
        $data = array();

        try
        {
            for($i = 0; $i < $this->num_fields(); $i++)
            {
                $data[] = $this->result_id->getColumnMeta($i);
            }

            return $data;
        }
        catch (Exception $e)
        {
            if ($this->db->db_debug)
            {
                return $this->db->display_error('db_unsuported_feature');
            }
            return FALSE;
        }
    }

    /**
     * Free the result
     *
     * @return	null
     */
    function free_result()
    {
        if (is_object($this->result_id))
        {
            $this->result_id = FALSE;
        }
    }

    /**
     * Data Seek
     *
     * Moves the internal pointer to the desired offset.  We call
     * this internally before fetching results to make sure the
     * result set starts at zero
     *
     * @access	private
     * @return	array
     */
    function _data_seek($n = 0)
    {
        return FALSE;
    }

    /**
     * Result - associative array
     *
     * Returns the result set as an array
     *
     * @access	private
     * @return	array
     */
    function _fetch_assoc()
    {
        return $this->result_id->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Result - object
     *
     * Returns the result set as an object
     *
     * @access	private
     * @return	object
     */
    function _fetch_object()
    {
        return $this->result_id->fetchObject();
    }
}
/* End of file pdo_result.php */
/**
 * PDO Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Dready
 * @link		http://dready.jexiste.fr/dotclear/
 */
class CI_DB_sqlite3_driver extends CI_DB {
    var $dbdriver = 'pdo';
    var $_escape_char = ''; // The character used to escape with - not needed for SQLite
    var $conn_id;
    var $_random_keyword = ' Random()'; // database specific random keyword
    var $_like_escape_str = '';
    var $_like_escape_chr = '';
    /**
     * Whether to use the MySQL "delete hack" which allows the number
     * of affected rows to be shown. Uses a preg_replace when enabled,
     * adding a bit more processing to all queries.
     */
    var $delete_hack = TRUE;
    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the count_all() and count_all_results() functions.
     */
    var $_count_string = 'SELECT COUNT(*) AS ';
    var $use_set_names;
    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_connect() {
        $conn_id = false;
        try {
            $conn_id = new PDO($this->database, $this->username, $this->password);
            log_message('debug', "PDO driver connecting " . $this->database);
        } catch (PDOException $e) {
            log_message('debug', 'merde');
            log_message('error', $e->getMessage());
            if ($this->db_debug) {
                $this->display_error($e->getMessage(), '', TRUE);
            }
        }
        log_message('debug', print_r($conn_id, true));
        if ($conn_id) {
            log_message('debug', 'PDO driver connection ok');
        }
        $this->conn_id = $conn_id;
        return $conn_id;
    }
    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _list_columns($table = '') {
        return "PRAGMA table_info('" . $this->_protect_identifiers($table, TRUE, NULL, FALSE) . "') ";
    }

    /**
     * Persistent database connection
     *
     * @access	private, called by the base class
     * @return	resource
     */
    function db_pconnect() {
        return $this->db_connect();
    }

    /**
     * Select the database
     *
     * @access	private called by the base class
     * @return	resource
     */
    function db_select() {
        return TRUE;
    }

    /**
     * Execute the query
     *
     * @access	private, called by the base class
     * @param	string	an SQL query
     * @return	resource
     */
    function _execute($sql) {
        $sql = $this->_prep_query($sql);
        log_message('debug', 'SQL : ' . $sql);
        return @$this->conn_id->query($sql);
    }

    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access	private called by execute()
     * @param	string	an SQL query
     * @return	string
     */
    function &_prep_query($sql) {
        return $sql;
    }
    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     * Sets boolean and null types
     *
     * @access	public
     * @param	string
     * @return	integer
     */
    function escape($str) {
        switch (gettype($str)) {
            case 'string' : $str = "'" . $this->escape_str($str) . "'";
                break;
            case 'boolean' : $str = ($str === FALSE) ? 0 : 1;
                break;
            default : $str = ($str === NULL) ? 'NULL' : $str;
                break;
        }
        return $str;
    }

    /**
     * Escape String
     *
     * @access      public
     * @param       string
     * @return      string
     */
    function escape_str($str) {
        if (function_exists('sqlite_escape_string')) {
            return sqlite_escape_string($str);
        } else {
            return SQLite3::escapeString($str);
        }
    }
    /**     * Escape the SQL Identifiers *
     * This function escapes column and table names *
     * @accessprivate
     * @paramstring
     * @returnstring */
    function _escape_identifiers($item) {
        if ($this->_escape_char == '') {
            return $item;
        }
        foreach ($this->_reserved_identifiers as $id) {
            if (strpos($item, '.' . $id) !== FALSE) {
                $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.', $item);
                return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
            }
        }
        if (strpos($item, '.') !== FALSE) {
            $str = $this->_escape_char . str_replace('.', $this->_escape_char . '.' . $this->_escape_char, $item) . $this->_escape_char;
        } else {
            $str = $this->_escape_char . $item . $this->_escape_char;
        }
        return preg_replace('/[' . $this->_escape_char . ']+/', $this->_escape_char, $str);
    }
    /**
     * Begin Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_begin($test_mode = FALSE) {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;
        $this->simple_query('BEGIN TRANSACTION');
        return TRUE;
    }

    /**
     * Commit Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_commit() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('COMMIT');
        return TRUE;
    }

    /**
     * Rollback Transaction
     *
     * @access	public
     * @return	bool
     */
    function trans_rollback() {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('ROLLBACK');
        return TRUE;
    }

    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    function destroy($conn_id) {
        $conn_id = null;
    }

    /**
     * Insert ID
     *
     * @access	public
     * @return	integer
     */
    function insert_id() {
        return @$this->conn_id->lastInsertId();
    }

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access	public
     * @param	string
     * @return	string
     */
    function count_all($table = '') {
        if ($table == '')
            return '0';
        $query = $this->query("SELECT COUNT(*) AS numrows FROM `" . $table . "`");
        if ($query->num_rows() == 0)
            return '0';
        $row = $query->row();
        return $row->numrows;
    }

    /**
     * The error message string
     *
     * @access	private
     * @return	string
     */
    function _error_message() {
        $infos = $this->conn_id->errorInfo();
        return $infos[2];
    }

    /**
     * The error message number
     *
     * @access	private
     * @return	integer
     */
    function _error_number() {
        $infos = $this->conn_id->errorInfo();
        return $infos[1];
    }

    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    function version() {
        return $this->conn_id->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
    }

    /**
     * Escape Table Name
     *
     * This function adds backticks if the table name has a period
     * in it. Some DBs will get cranky unless periods are escaped
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function escape_table($table) {
        if (stristr($table, '.')) {
            $table = preg_replace("/\./", "`.`", $table);
        }
        return $table;
    }

    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access	public
     * @param	string	the table name
     * @return	object
     */
    function _field_data($table) {
        $sql = "SELECT * FROM " . $this->escape_table($table) . " LIMIT 1";
        $query = $this->query($sql);
        return $query->field_data();
    }

    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    function _insert($table, $keys, $values) {
        return "INSERT INTO " . $this->escape_table($table) . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    function _update($table, $values, $where) {
        foreach ($values as $key => $val) {
            $valstr[] = $key . " = " . $val;
        }
        return "UPDATE " . $this->escape_table($table) . " SET " . implode(', ', $valstr) . " WHERE " . implode(" ", $where);
    }

    /**
     * Delete statement
     *
     * Generates a platform-specific delete string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the where clause
     * @return	string
     */
    function _delete($table, $where) {
        return "DELETE FROM " . $this->escape_table($table) . " WHERE " . implode(" ", $where);
    }

    /**
     * Show table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access	public
     * @return	string
     */
    function _show_tables() {
        return "SELECT name from sqlite_master WHERE type='table'";
    }

    /**
     * Show columnn query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function _show_columns($table = '') {
        return FALSE;
    }

    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access	public
     * @param	string	the sql query string
     * @param	integer	the number of rows to limit the query to
     * @param	integer	the offset value
     * @return	string
     */
    function _limit($sql, $limit, $offset) {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }
        return $sql . "LIMIT " . $offset . $limit;
    }
    /**
     * From Tables ... contributed/requested by CodeIgniter user: quindo
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access  public
     * @param   type
     * @return  type
     */
    function _from_tables($tables) {
        if (!is_array($tables)) {
            $tables = array($tables);
        }
        return implode(', ', $tables);
    }
    /**
     * Set client character set
     * contributed/requested by CodeIgniter user:  jtiai
     *
     * @access    public
     * @param    string
     * @param    string
     * @return    resource
     */
    function db_set_charset($charset, $collation) {
        return TRUE;
    }

    /**
     * Close DB Connection
     *
     * @access    public
     * @param    resource
     * @return    void
     */
    function _close($conn_id) {
    }
    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access      private
     * @param       boolean
     * @return      string
     */
    function _list_tables($prefix_limit = FALSE) {
        $sql = "SELECT name from sqlite_master WHERE type='table'";
        if ($prefix_limit !== FALSE AND $this->dbprefix != '') {
            $sql .= " AND 'name' LIKE '" . $this->dbprefix . "%'";
        }
        return $sql;
    }
}
/**
 * PDO Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		Dready
 * @link			http://dready.jexiste.fr/dotclear/
 */
class CI_DB_sqlite3_result extends CI_DB_result {
    var $pdo_results = '';
    var $pdo_index = 0;
    /**
     * Number of rows in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_rows() {
        if (!$this->pdo_results) {
            $this->pdo_results = $this->result_id->fetchAll(PDO::FETCH_ASSOC);
        }
        return sizeof($this->pdo_results);
    }

    /**
     * Number of fields in the result set
     *
     * @access	public
     * @return	integer
     */
    function num_fields() {
        if (is_array($this->pdo_results)) {
            return sizeof($this->pdo_results[$this->pdo_index]);
        } else {
            return $this->result_id->columnCount();
        }
    }

    /**
     * Result - associative array
     *
     * Returns the result set as an array
     *
     * @access	private
     * @return	array
     */
    function _fetch_assoc() {
        if (is_array($this->pdo_results)) {
            $i = $this->pdo_index;
            $this->pdo_index++;
            if (isset($this->pdo_results[$i]))
                return $this->pdo_results[$i];
            return null;
        }
        return $this->result_id->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Result - object
     *
     * Returns the result set as an object
     *
     * @access	private
     * @return	object
     */
    function _fetch_object() {
        if (is_array($this->pdo_results)) {
            $i = $this->pdo_index;
            $this->pdo_index++;
            if (isset($this->pdo_results[$i])) {
                $back = new stdClass();
                foreach ($this->pdo_results[$i] as $key => $val) {
                    $back->$key = $val;
                }
                return $back;
            }
            return null;
        }
        return $this->result_id->fetch(PDO::FETCH_OBJ);
    }
}
/* End of file sqlite3.php */
