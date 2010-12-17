<?php

class Server
{
	/**
	 * Handles errors and tries to open them up more to strictly find problems.
	 *
	 * @param string $file Filename to output to silently.
	 */
	static function HandleErrors($file = null)
	{
		if (!empty($file)) $GLOBALS['__err_file'] = $file;
		else ini_set('display_errors', 1);
		$ver = phpversion();
		ini_set('error_log', 'errors_php.txt');
		set_error_handler(array('Server', 'ErrorHandler'));
	}

	/**
	 * Callback for HandleErrors, used internally.
	 *
	 * @param int $errno Error number.
	 * @param string $errmsg Error message.
	 * @param string $filename Source filename of the problem.
	 * @param int $linenum Source line of the problem.
	 */
	static function ErrorHandler($errno, $errmsg, $filename, $linenum)
	{
		if (error_reporting() == 0) return;
		$errortype = array (
			E_ERROR           => "Error",
			E_WARNING         => "Warning",
			E_PARSE           => "Parsing Error",
			E_NOTICE          => "Notice",
			E_CORE_ERROR      => "Core Error",
			E_CORE_WARNING    => "Core Warning",
			E_COMPILE_ERROR   => "Compile Error",
			E_COMPILE_WARNING => "Compile Warning",
			E_USER_ERROR      => "User Error",
			E_USER_WARNING    => "User Warning",
			E_USER_NOTICE     => "User Notice",
		);
		$ver = phpversion();
		if ($ver[0] >= 5) $errortype[E_STRICT] = 'Strict Error';
		if ($ver[0] >= 5 && $ver[2] >= 2)
			$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';
		if ($ver[0] >= 5 && $ver[2] >= 3)
		{
			$errortype[E_DEPRECATED] = 'Deprecated';
			$errortype[E_USER_DEPRECATED] = 'User Deprecated';
		}

		$err = "[{$errortype[$errno]}] ".nl2br($errmsg)."<br/>";
		$err .= "Error seems to be in one of these places...\n";

		if (isset($GLOBALS['_trace']))
			$err .= '<p>Template Trace</p><p>'.$GLOBALS['_trace'].'</p>';

		$err .= Server::GetCallstack($filename, $linenum);

		if (isset($GLOBALS['__err_callback']))
			call_user_func($GLOBALS['__err_callback'], $err);

		if (!empty($GLOBALS['__err_file']))
		{
			$fp = fopen($GLOBALS['__err_file'], 'a+');
			fwrite($fp, $err);
			fclose($fp);
		}
		else echo $err;
	}

	/**
	 * Returns a human readable callstack in html format.
	 *
	 * @param string $file Source of caller.
	 * @param int $line Line of caller.
	 * @return string Rendered callstack.
	 */
	static function GetCallstack($file = __FILE__, $line = __LINE__)
	{
		$err = "<table><tr><td>File</td><td>#</td><td>Function</td>\n";
		$err .= "<tr>\n\t<td>$file</td>\n\t<td>$line</td>\n";
		$array = debug_backtrace();
		$err .= "\t<td>{$array[1]['function']}</td>\n</tr>";
		foreach ($array as $ix => $entry)
		{
			if ($ix < 1) continue;
			$err .= "<tr>\n";
			if (isset($entry['file']))
			{ $err .= "\t<td>{$entry['file']}</td>\n"; }
			if (isset($entry['line']))
			{ $err .= "\t<td>{$entry['line']}</td>\n"; }
			if (isset($entry['class']))
			{ $err .= "\t<td>{$entry['class']}{$entry['type']}{$entry['function']}</td>\n"; }
			else if (isset($entry['function']))
			{ $err .= "\t<td>{$entry['function']}</td>\n"; }
			$err .= "</tr>";
		}
		$err .= "</table>\n<hr size=\"1\">\n";
		return $err;
	}

	/**
	 * Triggers an error.
	 *
	 * @param string $msg Message to the user.
	 * @param int $level How critical this error is.
	 */
	static function Error($msg, $level = E_USER_ERROR) { trigger_error($msg, $level); }

	/**
	 * Use this when you wish to output debug information only when $debug is
	 * true.
	 *
	 * @param string $msg The message to output.
	 * @version 1.0
	 * @see Error, ErrorHandler, HandleErrors
	 * @since 1.0
	 * @todo Alternative output locations.
	 * @todo Alternative verbosity levels.
	 * @example test_utility.php
	 */
	static function Trace($msg)
	{
		if (!empty($GLOBALS['debug'])) varinfo($msg);
		if (!empty($GLOBALS['__debfile'])) file_put_contents('trace.txt', $msg."\r\n", FILE_APPEND);
	}

	/**
	 * Attempts to set a variable using sessions.
	 *
	 * @param string $name Name of the value to set.
	 * @param string $value Value to set.
	 * @return mixed Passed $value
	 */
	static function SetVar($name, $value)
	{
		global $HTTP_SESSION_VARS;
		if (is_array(@$_SESSION)) $_SESSION[$name] = $value;
		if (is_array($HTTP_SESSION_VARS)) $HTTP_SESSION_VARS[$name] = $value;
		return $value;
	}

	/**
	 * Returns a value from files, post, get, session, cookie and finally
	 * server in that order.
	 *
	 * @param string $name Name of the value to get.
	 * @param mixed $default Default value to return if not available.
	 * @return mixed
	 */
	static function GetVar($name, $default = null)
	{
		if (strlen($name) < 1) return $default;

		global $HTTP_POST_FILES, $HTTP_POST_VARS, $HTTP_GET_VARS, $HTTP_SERVER_VARS,
		$HTTP_SESSION_VARS, $HTTP_COOKIE_VARS;

		if (isset($_FILES[$name])) return $_FILES[$name];
		if (isset($_POST[$name])) return $_POST[$name];
		if (isset($_GET[$name])) return $_GET[$name];
		if (isset($_SESSION[$name])) return $_SESSION[$name];
		if (isset($_COOKIE[$name])) return $_COOKIE[$name];
		if (isset($_SERVER[$name])) return $_SERVER[$name];

		if (isset($HTTP_POST_FILES[$name]) && strlen($HTTP_POST_FILES[$name]) > 0)
			return $HTTP_POST_FILES[$name];
		if (isset($HTTP_POST_VARS[$name]) && strlen($HTTP_POST_VARS[$name]) > 0)
			return $HTTP_POST_VARS[$name];
		if (isset($HTTP_GET_VARS[$name]) && strlen($HTTP_GET_VARS[$name]) > 0)
			return $HTTP_GET_VARS[$name];
		if (isset($HTTP_SESSION_VARS[$name]) && strlen($HTTP_SESSION_VARS[$name]) > 0)
			return $HTTP_SESSION_VARS[$name];
		if (isset($HTTP_COOKIE_VARS[$name]) && strlen($HTTP_COOKIE_VARS[$name]) > 0)
			return $HTTP_COOKIE_VARS[$name];
		if (isset($HTTP_SERVER_VARS[$name]) && strlen($HTTP_SERVER_VARS[$name]) > 0)
			return $HTTP_SERVER_VARS[$name];

		return $default;
	}

	static function SanitizeEnvironment()
	{
		if (ini_get('magic_quotes_gpc'))
			foreach ($_POST as $k => $v) Sanitize($_POST[$k]);
	}

	/**
	 * Will set a session variable to $name with the value of GetVar and return it.
	 *
	 * @param string $name Name of our state object.
	 * @return mixed The GetVar value of $name.
	 */
	static function GetState($name, $def = null)
	{
		return Server::SetVar($name, Server::GetVar($name, $def));
	}

	/**
	 * Gets the webserver path for a given local filesystem directory.
	 *
	 * @param string $path
	 * @return string Translated path.
	 */
	static function GetRelativePath($path)
	{
		$dr = $_SERVER['DOCUMENT_ROOT']; //Probably Apache situated

		if (empty($dr)) //Probably IIS situated
		{
			//Get the document root from the translated path.
			$pt = str_replace('\\\\', '/', Server::GetVar('PATH_TRANSLATED',
				Server::GetVar('ORIG_PATH_TRANSLATED')));
			$dr = substr($pt, 0, -strlen(Server::GetVar('SCRIPT_NAME')));
		}

		$dr = str_replace('\\\\', '/', $dr);

		return substr(str_replace('\\', '/', str_replace('\\\\', '/', $path)), strlen($dr));
	}
}

?>
