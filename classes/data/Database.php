<?php

/**
 * on our way to the storage phase! We shall support the following!
 *
 * sqlite: client data stored in filesystem
 * firebird: database engine storage
 * mssql: database engine storage
 * mysql: database engine storage
 * postgresql: database engine storage
 *
 * odbc: proxy for many of the above
 *
 * @package Data
 */

/**
 * Associative get, returns arrays as $item['column'] instead of $item[index].
 * @todo Don't use defines, defines aren't redeclareable.
 */
define("GET_ASSOC", 1);
/**
 * Both get, returns arrays as $item['column'] as well as $item[index] instead
 * of $item[index].
 * @todo Don't use defines, defines aren't redeclareable.
 */
define("GET_BOTH", 3);

define('DB_MY', 0); //MySQL
define('DB_MI', 1); //MySQLi
define('DB_OD', 2); //ODBC
define('DB_SL', 3); //SQLite

define('ER_NO_SUCH_TABLE', 1146);
define('ER_INVALID_LOGIN', 9999);

define('SQLOPT_NONE', 0);
define('SQLOPT_QUOTE', 1);
define('SQLOPT_TQUOTE', 2);
define('SQLOPT_UNQUOTE', 4);

/**
 * A generic database interface, currently only supports MySQL apparently.
 */
class Database
{
	/**
	 * A link returned from mysql_connect(), don't worry about it.
	 * @var resource
	 */
	public $link;

	/**
	 * Name of this database, set from constructor.
	 * @var string
	 */
	public $name;

	/**
	 * Type of database this is attached to.
	 * @var int
	 */
	public $type;

	/**
	 * Left quote compatible with whatever database we're sitting on.
	 * @var char
	 */
	public $lq;

	/**
	 * Right quote compatible with whatever server we are sitting on.
	 * @var char
	 */
	public $rq;

	/**
	 * A series of handlers that will be called on when specific actions
	 * take place.
	 * @var array
	 */
	public $Handlers;

	/**
	 * The error handler for when something goes wrong.
	 * @var callback
	 */
	public $ErrorHandler;

	function CheckMiError($query, $handler)
	{
		if (mysqli_errno($this->link))
		{
			if (!empty($handler))
				if (call_user_func($handler, mysqli_errno($this->link))) return;
			if (isset($this->Handlers[mysqli_errno($this->link)]))
				if (call_user_func($this->Handlers[mysqli_errno($this->link)])) return;
			Error('MySQLi Error ['.mysqli_errno($this->link).']: '
				.mysqli_error($this->link)."<br />\nQuery: {$query}<br/>\n");
		}
	}

	/**
	 * Checks for a mysql error.
	 * @param string $query Query that was attempted.
	 * @param callback $handler Handler to take care of this problem.
	 */
	function CheckMyError($query, $handler)
	{
		if (mysql_errno($this->link))
		{
			if (!empty($handler))
				if (call_user_func($handler, mysql_errno($this->link))) return;
			if (isset($this->Handlers[mysql_errno($this->link)]))
				if (call_user_func($this->Handlers[mysql_errno($this->link)])) return;

			Server::Error('MySQL Error ['.mysql_errno($this->link).']: '
				.mysql_error($this->link)."<br/>\nQuery: {$query}<br/>\n");
		}
	}

	/**
	 * Checks for an handles an ODBC error generically.
	 * @param string $query Query that was attempted.
	 * @param callback $handler Handler used to process this error.
	 */
	function CheckODBCError($query, $handler)
	{
	}

	function CheckSQLiteError($query, $handler)
	{
		if (sqlite_last_error($this->link))
		{
			echo "Sqlite Error on: {$query}";
		}
	}

	/**
	 * Instantiates a new Database object.
	 */
	function Database()
	{
	}

	function Escape($val)
	{
		switch ($this->type)
		{
			case DB_MI:
				return mysqli_real_escape_string($this->link, $val);
			case DB_MY:
				return mysql_real_escape_string($val, $this->link);
			case DB_SL:
				return sqlite_escape_string($val);
			default:
				return addslashes($val);
		}
	}

	/**
	 * Opens a connection to a database.
	 * @param string $url Example: mysql://user:pass@host/database
	 */
	function Open($url)
	{
		$m = null;
		if (!preg_match('#([^:]+)://(([^:]*):*(.*)@|)([^/]*)/*(.*)#', $url, $m))
			Error("Invalid url for database.");

		switch ($m[1])
		{
			case 'mysqli':
				$this->ErrorHandler = array($this, 'CheckMiError');
				$this->func_aff = 'mysqli_affected_rows';
				$this->link = mysqli_connect($m[5], $m[3], $m[4]);
				mysqli_select_db($this->link, $m[6]);
				$this->type = DB_MI;
				$this->lq = $this->rq = '`';
				break;
			case 'mysql':
				$this->ErrorHandler = array($this, 'CheckMyError');
				$this->func_aff = 'mysql_affected_rows';
				if (!$this->link = mysql_connect($m[5], $m[3], $m[4], true))
					return false;
				mysql_select_db($m[6], $this->link);
				$this->type = DB_MY;
				$this->lq = $this->rq = '`';
				break;
			case 'odbc':
				$this->ErrorHandler = array($this, 'CheckODBCError');
				$this->link = odbc_connect($m[5], $m[3], $m[4]);
				$this->type = DB_OD;
				$this->lq = '[';
				$this->rq = ']';
				break;
			case 'sqlite':
				$this->ErrorHandler = array($this, 'CheckSQLiteError');
				$this->func_aff = 'sqlite_num_rows';
				$this->link = sqlite_open($m[5]);
				$this->type = DB_SL;
				break;
			default:
				Error("Invalid database type.");
				break;
		}
		call_user_func($this->ErrorHandler, null, null);
		$this->name = $m[6];
	}

	/**
	 * Perform a manual query on the associated database, try to use this sparingly because
	 * we will be moving to abstract database support so it'll be a load parsing the sql-like
	 * query and translating.
	 * @param string $query The actual SQL formatted query.
	 * @param callback $handler Handler in case something goes wrong.
	 * @return resource Query result object.
	 */
	function Query($query, $handler = null)
	{
		if (!isset($this->type)) Error("Database has not been opened.");
		Trace($query);
		switch ($this->type)
		{
			case DB_MI:
				$res = mysqli_query($this->link, $query);
				break;
			case DB_MY:
				$res = mysql_query($query, $this->link);
				break;
			case DB_OD:
				$res = odbc_exec($this->link, $query);
				break;
			case DB_SL:
				$res = sqlite_query($this->link, $query);
				break;
		}
		call_user_func($this->ErrorHandler, $query, $handler);
		return $res;
	}

	function Queries($query)
	{
		foreach (explode(';', $query) as $q) $this->Query($q);
	}

	/**
	* Quickly create this database
	*/
	function Create()
	{
		mysql_query("CREATE DATABASE {$this->name}", $this->link);
		if (mysql_error($this->link)) echo "Create(): " . mysql_error($this->link) . "<br>\n";
	}

	/**
	* Drop a child table.
	* @param string $name The name of the table to make a boo boo to.
	*/
	function DropTable($name)
	{
		$this->Query("DROP TABLE $name");
	}

	/**
	* Drop this whole database, I suggest you stay away from this command unless you really
	* mean it cause it doesn't kid around, and mysql is pretty obedient.
	*/
	function Drop()
	{
		mysql_query("DROP DATABASE {$this->name}", $this->link);
		if (mysql_error($this->link)) echo "Drop(): " . mysql_error($this->link) . "<br>\n";
	}

	/**
	 * Ensure this database exists, according to it's specified schema.
	 *
	 */
	function CheckInstall()
	{
		mysql_select_db($this->name, $this->link);
		if (mysql_error($this->link))
		{
			echo "Database: Could not locate database, installing...<br/>\n";
			mysql_query("CREATE DATABASE {$this->name}", $this->link);
		}
	}

	/**
	 * Returns the last unique ID that was inserted.
	 *
	 * @return mixed
	 */
	function GetLastInsertID()
	{
		if ($this->type == DB_MY) return mysql_insert_id($this->link);
		if ($this->type == DB_MI) return mysqli_insert_id($this->link);
		if ($this->type == DB_SL) return sqlite_last_insert_rowid($this->link);
		return 0;
	}

	/**
 * Removes quoting from a database field to perform functions and
 * such.
 * @param string $data Information that will not be quited.
 * @return array specifying that this string shouldn't be quoted.
 */
	function SqlUnquote($data) { return array('val' => $data, 'opt' => SQLOPT_UNQUOTE); }
	function SqlBetween($from, $to) { return array('cmp' => 'BETWEEN', 'opt' => SQLOPT_UNQUOTE, 'val' => "'$from' AND '$to'"); }
	function SqlIs($val) { return array('cmp' => 'IS', 'opt' => SQLOPT_UNQUOTE, 'val' => $val); }
	function SqlNot($val) { return array('cmp' => '!=', 'val' => $val); }
	function SqlAnd($val) { return array('inc' => 'AND', 'val' => $val); }
	function SqlOr($val)
	{
		if (is_array($val)) { $val['inc'] = 'OR'; return $val; }
		else return array('inc' => 'OR', 'val' => $val);
	}
	function SqlLess($val) { return array('cmp' => '<', 'val' => $val); }
	function SqlMore($val) { return array('cmp' => '>', 'val' => $val); }
	function SqlDistinct($val) { return array('cmp' => 'DISTINCT', 'val' => $val); }
	function SqlCount($val) { return array('val' => 'COUNT('.$val.')', 'opt' => SQLOPT_UNQUOTE); }
	function SqlLike($val) { return array('val' => $val, 'cmp' => 'LIKE'); }
	function SqlIn($vals)
	{
		$ix = 0; $nv = '';
		if (!empty($vals))
		foreach ($vals as $v) { if ($ix++ > 0) $nv .= ', '; $nv .= "'$v'"; }
		return array('val' => 'IN('.$nv.')', 'opt' => SQLOPT_UNQUOTE, 'cmp' => '');
	}
	/**
	 * Returns the proper format for DataSet to generate the current time.
	 * @return array This column will get translated into the current time.
	 */
	function SqlNow() { return array("now"); }

	/**
	 * Convert a mysql date to a regular readable date.
	 * @param DataSet $ds For compatability with something or other.
	 * @param array $data Data result from a query.
	 * @param string $col Name of column to get date from.
	 * @param string $dbcol For compatability with something or other.
	 * @return string Localized date string.
	 */
	static function MySqlDateCallback($ds, $data, $col, $dbcol)
	{
		$ts = MyDateTimestamp($data[$col]);
		return strftime('%x', $ts);
	}

	/**
	 * Converts a mysql date to a timestamp.
	 *
	 * @param string $date MySql Date/DateTime
	 * @param bool $include_time Whether hours, minutes and seconds are included.
	 * @return int Timestamp
	 */
	function MyDateTimestamp($date, $include_time = false)
	{
		if ($include_time) {
			return mktime(
				substr($date, 11, 2), //hh
				substr($date, 14, 2), //mm
				substr($date, 17, 2), //ss
				substr($date, 5, 2), //m
				substr($date, 8, 2), //d
				substr($date, 0, 4) //y
			);
		}
		else
		{
			$match = null;
			if (!preg_match('/([0-9]+)-([0-9]+)-([0-9]+)/', $date, $match)) return null;
			return mktime(0, 0, 0,
				$match[2], //m
				$match[3], //d
				$match[1] //y
			);
		}
	}
}

?>
