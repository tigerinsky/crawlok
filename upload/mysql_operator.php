<?php

require_once dirname(__FILE__).'/conf/conf.php';
// 设置时区
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ERROR);

class MysqlOperate {
    private $conn;
	private $mysql_host = '';
	private $mysql_user = '';
	private $mysql_passwd = '';
    private $mysql_db = '';

    function __construct() {
        $this->conn = NULL;
    }

    function init($conf) {
        if (!isset($conf['host']) || !isset($conf['user']) || !isset($conf['passwd']) || !isset($conf['db'])) {
            echo "mysql conf error\n";
            return false;
        }

        $this->mysql_host = $conf['host'];
        $this->mysql_user = $conf['user'];
        $this->mysql_passwd = $conf['passwd'];
        $this->mysql_db = $conf['db'];
        return true;
    }

    function connect() {
		// 连接数据库
		$this->conn = mysql_connect($this->mysql_host, $this->mysql_user, $this->mysql_passwd);
		if (!$this->conn)
		{
			die('Connection failed: ' . mysql_error());
        }
        mysql_query("set names 'utf8mb4'");
        mysql_query("set character_set_client=utf8mb4");
        mysql_query("set character_set_results=utf8mb4");
        mysql_select_db($this->mysql_db, $this->conn);
    }

    function dis_connect() {
        if ($this->conn) {
            mysql_close($this->conn);
        }
    }

	function execute_sql_mysql($sql) {
		// 返回值
		$result_arr = array();
		$result = mysql_query($sql);
		while($row = mysql_fetch_array($result))
		{
			$result_arr[] = $row;
		}
		// 释放资源
        mysql_free_result($result);

		return $result_arr;
    }

    function last_insert_id() {
        $last_id = mysql_insert_id();
        return $last_id;
    }
}

?>
