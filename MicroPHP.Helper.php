<?php



if (!function_exists('args')) {
	function args($key = null) {
		return MpInput::parameters($key);
	}
}
if (!function_exists('xss_clean')) {
	function xss_clean($val) {
		return MpInput::xss_clean($val);
	}
}
foreach (array('set_cookie'=>'setCookie', 'set_cookie_raw'=>'setCookieRaw') as $func=>$true) {
	if (!function_exists($func)) {
		eval('function ' . $func . '($key, $value, $life = null, $path = "/", $domian = null, $http_only = false) {
					 return MpLoader::' . $true . '($key, $value, $life, $path, $domian, $http_only);
		 }');
	}
}
foreach (array('server', 'session') as $func) {
	if (!function_exists($func)) {
		eval('function ' . $func . '($key = null, $default = null) {
					 return MpInput::' . $func . '($key, $default);
		 }');
	}
}
foreach (array('get_rule', 'post_rule', 'get_post_rule', 'post_get_rule') as $func) {
	if (!function_exists($func)) {
		eval('function ' . $func . '($rule, $key, $default = null) {
					 return MpInput::' . $func . '($rule, $key, $default);
		 }');
	}
}
foreach (array('get', 'post', 'cookie', 'cookie_raw', 'get_post', 'post_get') as $func) {
	if (!function_exists($func)) {
		if ($func == 'cookie_raw') {
			$func = 'cookiRaw';
		}
		eval('function ' . $func . '($key = null, $default = null, $xss_clean = false) {
					 return MpInput::' . $func . '($key, $default, $xss_clean);
		 }');
	}
}
foreach (array('get_int', 'post_int', 'get_post_int', 'post_get_int',
			 'get_time', 'post_time', 'get_post_time', 'post_get_time',
			 'get_date', 'post_date', 'get_post_date', 'post_get_date',
			 'get_datetime', 'post_datetime', 'get_post_datetime', 'post_get_datetime') as $func) {
	if (!function_exists($func)) {
		eval('function ' . $func . '($key, $min = null, $max = null, $default = null) {
					 return MpInput::' . $func . '($key, $min, $max, $default);
		 }');
	}
}
if (!function_exists('dump')) {
	/**
	 * 打印变量内容，参数和var_dump一样
	 * @param type $arg
	 * @param type $_
	 */
	function dump($arg, $_ = null) {
		$args = func_get_args();
		if (MpInput::isCli()) {
			call_user_func_array('var_dump', $args);
		} else {
			echo '<pre>';
			call_user_func_array('var_dump', $args);
			echo '</pre>';
		}
	}
}
if (!function_exists('table')) {
	/**
	 * 实例化一个表模型
	 * @param string $table_name    不带表前缀的表名称
	 * @param CI_DB_active_record $db 使用的数据库连接对象，默认留空是当前数据库连接
	 * @return MpTableModel
	 */
	function table($table_name, $db = null) {
		return MpTableModel::M($table_name, $db);
	}
}
if (!function_exists('url')) {
	/**
	 * 生成url链接<br>
	 * 使用示例：<br>
	 * url(),<br>
	 * url('welcome.index'),<br>
	 * url('welcome.index','aa','bb'),<br>
	 * url('welcome.index',array('a'=>'bb','b'=>'ccc'),'dd','ee'),<br>
	 * url('welcome.index','dd','ee',array('a'=>'bb')),<br>
	 * url('welcome.index',array('a'=>'bb','b'=>'ccc')),<br>
	 * url('','aa','bb'),<br>
	 * url('',array('a'=>'bb','b'=>'ccc'),'dd','ee'),<br>
	 * url('',array('a'=>'bb','b'=>'ccc')),<br>
	 * 另外可以在第一个参数开始加上:<br>
	 * #和?用来控制url中显示入口文件名称和使用相对路经<br>
	 * 默认不显示入口文件名称，使用绝对路经<br>
	 * 使用示例：<br>
	 * url('#welcome.index'),<br>
	 * url('?welcome.index'),<br>
	 * url('#?welcome.index'),<br>
	 * url('?#welcome.index'),<br>
	 * @return string
	 */
	function url() {
		$action = null;
		$argc = func_num_args();
		if ($argc > 0) {
			$action = func_get_arg(0);
		}
		$args = array();
		$get_str_arr = array();
		if ($argc > 1) {
			for ($i = 1; $i < $argc; $i++) {
				if (is_array($arg = func_get_arg($i))) {
					foreach ($arg as $k => $v) {
						$get_str_arr[] = $k . '=' . urlencode($v);
					}
				} else {
					$args[] = $arg;
				}
			}
		}
		if (!systemInfo('url_rewrite')) {
			$self_name = stripos($action, '#') === 0 || stripos($action, '#') === 1 ? pathinfo(MpInput::server('php_self'), PATHINFO_BASENAME) : '';
			$app_start = '?';
			$get_start = '&';
		} else {
			$self_name = '';
			$app_start = '';
			$get_start = '?';
		}
		//是否使用相对路经检查
		$path = (stripos($action, '?') === 0 || stripos($action, '?') === 1 ? '' : urlPath() . '/' );
		$action = ltrim($action, '#?');
		$url_app = $path . $self_name .
			(empty($args) && empty($get_str_arr) && empty($action) ? '' : $app_start) .
			($action . (empty($args) || empty($action) ? '' : '/' ) . implode('/', $args)) .
			(empty($get_str_arr) ? '' : $get_start . implode('&', $get_str_arr));
		return str_replace('?&', '?', $url_app);
	}
}
if (!function_exists('urlPath')) {
	/**
	 * 获取入口文件所在目录url路径。
	 * 只能在web访问时使用，在命令行下面会抛出异常。
	 * @param type $subpath  子路径或者文件路径，如果非空就会被附加在入口文件所在目录的后面
	 * @return type
	 * @throws Exception
	 */
	function urlPath($subpath = null) {
		if (MpInput::isCli()) {
			throw new Exception('function urlPath() can not be used in cli mode');
		} else {
			$old_path = getcwd();
			$root = str_replace(array("/", "\\"), '/', MpInput::server('DOCUMENT_ROOT'));
			chdir($root);
			$root = getcwd();
			$root = str_replace(array("/", "\\"), '/', $root);
			chdir($old_path);
			$path = path($subpath);
			return str_replace($root, '', $path);
		}
	}
}
if (!function_exists('path')) {
	/**
	 * 获取入口文件所在目录绝对路径。
	 * @param type $subpath 子路径或者文件路径，如果非空就会被附加在入口文件所在目录的绝对路径后面
	 * @return type
	 */
	function path($subpath = null) {
		$path = str_replace(array("/", "\\"), '/', realpath('.') . ($subpath ? '/' . trim($subpath, '/\\') : ''));
		return truepath($path);
	}
}
/**
 * 获取系统配置信息,也就是MpLoader::$system里面的信息
 * @param type $key  MpLoader::$system的键
 * @return null
 */
if (!function_exists('systemInfo')) {
	function systemInfo($key = NULL) {
		if (is_null($key)) {
			return MpLoader::$system;
		} elseif (isset(MpLoader::$system[$key])) {
			return MpLoader::$system[$key];
		} else {
			return null;
		}
	}
}
/**
 * 获取系统数据库配置信息
 * @param type $group  数据库组名称，即MpLoader::$system['db']的键.
 *                     为null时返回默认的配置组,即MpLoader::$system['db']['active_group']指定的组。
 * @param type $key    配置组的键,指定了key可以获取指定组的键对应的值
 * @return null
 */
if (!function_exists('dbInfo')) {
	function dbInfo($group = NULL, $key = NULL) {
		if (is_null($group)) {
			$system=  systemInfo();
			$cfg = $system['db'][$system['db']['active_group']];
			if (is_null($key)) {
				return $cfg;
			} else {
				return isset($cfg[$key]) ? $cfg[$key] : null;
			}
		} elseif (isset($system['db'][$group])) {
			$cfg = $system['db'][$group];
			if (is_null($key)) {
				return $cfg;
			} else {
				return isset($cfg[$key]) ? $cfg[$key] : null;
			}
		} else {
			return null;
		}
	}
}
if (!function_exists('sessionStart')) {
	function sessionStart() {
		if (!isset($_SESSION)) {
			session_start();
		}
	}
}
if (!function_exists('getInstance')) {
	function &getInstance() {
		return WoniuController::getInstance();
	}
}
if (!function_exists('trigger404')) {
	function trigger404($msg = '<h1>Not Found</h1>') {
		$system = systemInfo();
		if (!headers_sent()) {
			header('HTTP/1.1 404 NotFound');
		}
		if (!empty($system['error_page_404']) && file_exists($system['error_page_404'])) {
			include $system['error_page_404'];
		} else {
			echo $msg;
		}
		exit();
	}
}
if (!function_exists('truepath')) {
	/**
	 * This function is to replace PHP's extremely buggy realpath().
	 * @param string The original path, can be relative etc.
	 * @return string The resolved path, it might not exist.
	 */
	function truepath($path) {
		//是linux系统么？
		$unipath = PATH_SEPARATOR == ':';
		//检测一下是否是相对路径，windows下面没有:,linux下面没有/开头
		//如果是相对路径就加上当前工作目录前缀
		if (strpos($path, ':') === false && strlen($path) && $path{0} != '/') {
			$path = realpath('.') . DIRECTORY_SEPARATOR . $path;
		}
		$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
		$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
		$absolutes = array();
		foreach ($parts as $part) {
			if ('.' == $part)
				continue;
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		//如果是linux这里会导致linux开头的/丢失
		$path = implode(DIRECTORY_SEPARATOR, $absolutes);
		//如果是linux，修复系统前缀
		$path = $unipath ? (strlen($path) && $path{0} != '/' ? '/' . $path : $path) : $path;
		//最后统一分隔符为/，windows兼容/
		$path = str_replace(array('/', '\\'), '/', $path);
		return $path;
	}
}
if (!function_exists('convertPath')) {
	function convertPath($path) {
		return str_replace(array("\\", "/"), '/', $path);
	}
}
if (!function_exists('trigger500')) {
	function trigger500($msg = '<h1>Server Error</h1>') {
		$system =  systemInfo();
		if (!headers_sent()) {
			header('HTTP/1.1 500 Server Error');
		}
		if (!empty($system['error_page_50x']) && file_exists($system['error_page_50x'])) {
			include $system['error_page_50x'];
		} else {
			echo $msg;
		}
		exit();
	}
}
if (!function_exists('woniu_exception_handler')) {
	function woniu_exception_handler($exception) {
		$errno = $exception->getCode();
		$errfile = pathinfo($exception->getFile(), PATHINFO_FILENAME);
		$errline = $exception->getLine();
		$errstr = $exception->getMessage();
		$system =systemInfo();
		if ($system['log_error']) {
			$handle = $system['log_error_handle']['exception'];
			if (!empty($handle)) {
				if (is_array($handle)) {
					$class = key($handle);
					$method = $handle[$class];
					$rclass_obj = new ReflectionClass($class);
					$rclass_obj = $rclass_obj->newInstanceArgs();
					if (method_exists($rclass_obj, $method)) {
						$rclass_obj->{$method}($errno, $errstr, $errfile, $errline, get_strace());
					}
				} else {
					if (function_exists($handle)) {
						$handle($errno, $errstr, $errfile, $errline, get_strace());
					}
				}
			}
		}
		if ($system['debug']) {
			//@ob_clean();
			trigger500('<pre>' . format_error($errno, $errstr, $errfile, $errline) . '</pre>');
		}
		exit;
	}
}
if (!function_exists('woniu_error_handler')) {
	/**
	 * 非致命错误处理函数。
	 * 该函数会接受所有类型的错误，应该过滤掉致命错误
	 * @param type $errno
	 * @param type $errstr
	 * @param type $errfile
	 * @param type $errline
	 * @return type
	 */
	function woniu_error_handler($errno, $errstr, $errfile, $errline) {
		if (!error_reporting()) {
			return;
		}
		$fatal_err = array(E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_PARSE, E_RECOVERABLE_ERROR);
		if (in_array($errno, $fatal_err)) {
			return true;
		}
		$system =systemInfo();
		if ($system['log_error']) {
			$handle = $system['log_error_handle']['error'];
			if (!empty($handle)) {
				if (is_array($handle)) {
					$class = key($handle);
					$method = $handle[$class];
					$rclass_obj = new ReflectionClass($class);
					$rclass_obj = $rclass_obj->newInstanceArgs();
					if (method_exists($rclass_obj, $method)) {
						$rclass_obj->{$method}($errno, $errstr, $errfile, $errline, get_strace());
					}
				} else {
					if (function_exists($handle)) {
						$handle($errno, $errstr, $errfile, $errline, get_strace());
					}
				}
			}
		}
		if ($system['debug']) {
			//@ob_clean();
			echo '<pre>' . format_error($errno, $errstr, $errfile, $errline) . '</pre>';
		}
	}
}
if (!function_exists('woniu_fatal_handler')) {
	/**
	 * 致命错误处理函数。
	 * 该函数会接受所有类型的错误，应该只处理致命错误
	 * @param type $errno
	 * @param type $errstr
	 * @param type $errfile
	 * @param type $errline
	 * @return type
	 */
	function woniu_fatal_handler() {
		$system = systemInfo();
		$errfile = "unknown file";
		$errstr = "shutdown";
		$errno = E_CORE_ERROR;
		$errline = 0;
		$error = error_get_last();
		$fatal_err = array(E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_PARSE, E_RECOVERABLE_ERROR);
		if ($error !== NULL && isset($error["type"]) && in_array($error["type"], $fatal_err)) {
			$errno = $error["type"];
			$errfile = $error["file"];
			$errline = $error["line"];
			$errstr = $error["message"];
			if ($system['log_error']) {
				$handle = $system['log_error_handle']['error'];
				if (!empty($handle)) {
					if (is_array($handle)) {
						$class = key($handle);
						$method = $handle[$class];
						$rclass_obj = new ReflectionClass($class);
						$rclass_obj = $rclass_obj->newInstanceArgs();
						if (method_exists($rclass_obj, $method)) {
							$rclass_obj->{$method}($errno, $errstr, $errfile, $errline, get_strace());
						}
					} else {
						if (function_exists($handle)) {
							$handle($errno, $errstr, $errfile, $errline, get_strace());
						}
					}
				}
			}
			if ($system['debug']) {
				//@ob_clean();
				trigger500('<pre>' . format_error($errno, $errstr, $errfile, $errline) . '</pre>');
			}
			exit;
		}
	}
}
if (!function_exists('woniu_db_error_handler')) {
	function woniu_db_error_handler($error) {
		$msg = '';
		if (is_array($error)) {
			foreach ($error as $m) {
				$msg.=$m . "\n";
			}
		} else {
			$msg = $error;
		}
		$system = systemInfo();
		$woniu_db = systemInfo('db');
		if ($system['log_error']) {
			$handle = $system['log_error_handle']['db_error'];
			if (!empty($handle)) {
				if (is_array($handle)) {
					$class = key($handle);
					$method = $handle[$class];
					$rclass_obj = new ReflectionClass($class);
					$rclass_obj = $rclass_obj->newInstanceArgs();
					if (method_exists($rclass_obj, $method)) {
						$rclass_obj->{$method}($msg, get_strace(TRUE));
					}
				} else {
					if (function_exists($handle)) {
						$handle($msg, get_strace(TRUE));
					}
				}
			}
		}
		if ($woniu_db[$woniu_db['active_group']]['db_debug'] && $system['debug']) {
			if (!empty($system['error_page_db']) && file_exists($system['error_page_db'])) {
				include $system['error_page_db'];
			} else {
				echo '<pre>' . $msg . get_strace(TRUE) . '</pre>';
			}
			exit;
		}
	}
}
if (!function_exists('format_error')) {
	function format_error($errno, $errstr, $errfile, $errline) {
		$path = truepath(systemInfo('application_folder'));
		$path.=empty($path) ? '' : '/';
		$array_map = array('0' => 'EXCEPTION', '1' => 'ERROR', '2' => 'WARNING', '4' => 'PARSE', '8' => 'NOTICE', '16' => 'CORE_ERROR', '32' => 'CORE_WARNING', '64' => 'COMPILE_ERROR', '128' => 'COMPILE_WARNING', '256' => 'USER_ERROR', '512' => 'USER_WARNING', '1024' => 'USER_NOTICE', '2048' => 'STRICT', '4096' => 'RECOVERABLE_ERROR', '8192' => 'DEPRECATED', '16384' => 'USER_DEPRECATED');
		$trace = get_strace();
		$content = '';
		$content .= "错误信息:" . nl2br($errstr) . "\n";
		$content .= "出错文件:" . str_replace($path, '', $errfile) . "\n";
		$content .= "出错行数:{$errline}\n";
		$content .= "错误代码:{$errno}\n";
		$content .= "错误类型:{$array_map[$errno]}\n";
		if (!empty($trace)) {
			$content .= "调用信息:{$trace}\n";
		}
		return $content;
	}
}
if (!function_exists('get_strace')) {
	function get_strace($is_db = false) {
		$trace = debug_backtrace(false);
		foreach ($trace as $t) {
			if (!in_array($t['function'], array('display_error', 'woniu_db_error_handler', 'woniu_fatal_handler', 'woniu_error_handler', 'woniu_exception_handler'))) {
				array_shift($trace);
			} else {
				array_shift($trace);
				break;
			}
		}
		if ($is_db) {
			array_shift($trace);
		}
		array_pop($trace);
		array_pop($trace);
		$str = '';
		$path = truepath(systemInfo('application_folder'));
		$path.=empty($path) ? '' : '/';
		foreach ($trace as $k => $e) {
			$file = !empty($e['file']) ? "File:" . str_replace($path, '', $e['file']) . "\n" : '';
			$line = !empty($e['line']) ? "   Line:{$e['line']}\n" : '';
			$space = (empty($file) && empty($line) ? '' : '   ');
			$func = $space . (!empty($e['class']) ? "Function:{$e['class']}{$e['type']}{$e['function']}()\n" : "Function:{$e['function']}()\n");
			$str.="\n#{$k} {$file}{$line}{$func}";
		}
		return $str;
	}
}
if (!function_exists('stripslashes_all')) {
	function stripslashes_all() {
		if (!get_magic_quotes_gpc()) {
			return;
		}
		$strip_list = array('_GET', '_POST', '_COOKIE');
		foreach ($strip_list as $val) {
			global $$val;
			$$val = stripslashes2($$val);
		}
	}
}
if (!function_exists('stripslashes2')) {
#过滤魔法转义，参数可以是字符串或者数组，支持嵌套数组
	function stripslashes2($var) {
		if (!get_magic_quotes_gpc()) {
			return $var;
		}
		if (is_array($var)) {
			foreach ($var as $key => $val) {
				if (is_array($val)) {
					$var[$key] = stripslashes2($val);
				} else {
					$var[$key] = stripslashes($val);
				}
			}
		} elseif (is_string($var)) {
			$var = stripslashes($var);
		}
		return $var;
	}
}
if (!function_exists('is_php')) {
	function is_php($version = '5.0.0') {
		static $_is_php;
		$version = (string) $version;
		if (!isset($_is_php[$version])) {
			$_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? FALSE : TRUE;
		}
		return $_is_php[$version];
	}
}
if (!function_exists('forceDownload')) {
	/**
	 * 强制下载
	 * 经过修改，支持中文名称
	 * Generates headers that force a download to happen
	 *
	 * @access    public
	 * @param    string    filename
	 * @param    mixed    the data to be downloaded
	 * @return    void
	 */
	function forceDownload($filename = '', $data = '') {
		if ($filename == '' OR $data == '') {
			return FALSE;
		}
		# Try to determine if the filename includes a file extension.
		# We need it in order to set the MIME type
		if (FALSE === strpos($filename, '.')) {
			return FALSE;
		}
		# Grab the file extension
		$x = explode('.', $filename);
		$extension = end($x);
		# Load the mime types
		$mimes = array('hqx' => 'application/mac-binhex40', 'cpt' => 'application/mac-compactpro', 'csv' => array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'), 'bin' => 'application/macbinary', 'dms' => 'application/octet-stream', 'lha' => 'application/octet-stream', 'lzh' => 'application/octet-stream', 'exe' => array('application/octet-stream', 'application/x-msdownload'), 'class' => 'application/octet-stream', 'psd' => 'application/x-photoshop', 'so' => 'application/octet-stream', 'sea' => 'application/octet-stream', 'dll' => 'application/octet-stream', 'oda' => 'application/oda', 'pdf' => array('application/pdf', 'application/x-download'), 'ai' => 'application/postscript', 'eps' => 'application/postscript', 'ps' => 'application/postscript', 'smi' => 'application/smil', 'smil' => 'application/smil', 'mif' => 'application/vnd.mif', 'xls' => array('application/excel', 'application/vnd.ms-excel', 'application/msexcel'), 'ppt' => array('application/powerpoint', 'application/vnd.ms-powerpoint'), 'wbxml' => 'application/wbxml', 'wmlc' => 'application/wmlc', 'dcr' => 'application/x-director', 'dir' => 'application/x-director', 'dxr' => 'application/x-director', 'dvi' => 'application/x-dvi', 'gtar' => 'application/x-gtar', 'gz' => 'application/x-gzip', 'php' => 'application/x-httpd-php', 'php4' => 'application/x-httpd-php', 'php3' => 'application/x-httpd-php', 'phtml' => 'application/x-httpd-php', 'phps' => 'application/x-httpd-php-source', 'js' => 'application/x-javascript', 'swf' => 'application/x-shockwave-flash', 'sit' => 'application/x-stuffit', 'tar' => 'application/x-tar', 'tgz' => array('application/x-tar', 'application/x-gzip-compressed'), 'xhtml' => 'application/xhtml+xml', 'xht' => 'application/xhtml+xml', 'zip' => array('application/x-zip', 'application/zip', 'application/x-zip-compressed'), 'mid' => 'audio/midi', 'midi' => 'audio/midi', 'mpga' => 'audio/mpeg', 'mp2' => 'audio/mpeg', 'mp3' => array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'), 'aif' => 'audio/x-aiff', 'aiff' => 'audio/x-aiff', 'aifc' => 'audio/x-aiff', 'ram' => 'audio/x-pn-realaudio', 'rm' => 'audio/x-pn-realaudio', 'rpm' => 'audio/x-pn-realaudio-plugin', 'ra' => 'audio/x-realaudio', 'rv' => 'video/vnd.rn-realvideo', 'wav' => 'audio/x-wav', 'bmp' => 'image/bmp', 'gif' => 'image/gif', 'jpeg' => array('image/jpeg', 'image/pjpeg'), 'jpg' => array('image/jpeg', 'image/pjpeg'), 'jpe' => array('image/jpeg', 'image/pjpeg'), 'png' => array('image/png', 'image/x-png'), 'tiff' => 'image/tiff', 'tif' => 'image/tiff', 'css' => 'text/css', 'html' => 'text/html', 'htm' => 'text/html', 'shtml' => 'text/html', 'txt' => 'text/plain', 'text' => 'text/plain', 'log' => array('text/plain', 'text/x-log'), 'rtx' => 'text/richtext', 'rtf' => 'text/rtf', 'xml' => 'text/xml', 'xsl' => 'text/xml', 'mpeg' => 'video/mpeg', 'mpg' => 'video/mpeg', 'mpe' => 'video/mpeg', 'qt' => 'video/quicktime', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'movie' => 'video/x-sgi-movie', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'word' => array('application/msword', 'application/octet-stream'), 'xl' => 'application/excel', 'eml' => 'message/rfc822', 'json' => array('application/json', 'text/json'));
		# Set a default mime if we can't find it
		if (!isset($mimes[$extension])) {
			$mime = 'application/octet-stream';
		} else {
			$mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
		}
		header('Content-Type: "' . $mime . '"');
		$tmpName = $filename;
		$filename = '"' . urlencode($tmpName) . '"'; #ie中文文件名支持
		if (strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'firefox') != false) {
			$filename = '"' . $tmpName . '"';
		}#firefox中文文件名支持
		if (strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'chrome') != false) {
			$filename = urlencode($tmpName);
		}#Chrome中文文件名支持
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header("Content-Transfer-Encoding: binary");
		header('Pragma: no-cache');
		header("Content-Length: " . strlen($data));
		exit($data);
	}
}
if (!function_exists('getRsCol')) {
	/**
	 * 获取结果集中的一个字段的数组
	 * @param type $rows
	 * @param type $col_name
	 * @return array
	 */
	function getRsCol($rows, $col_name) {
		$ret = array();
		foreach ($rows as &$row) {
			$ret[] = $row[$col_name];
		}
		return $ret;
	}
}
if (!function_exists('chRsKey')) {
	/**
	 * 改变结果集数组key
	 * @param type $rs  结果集
	 * @param type $col 作为结果集key的字段名称
	 * @return type
	 */
	function chRsKey($rs, $col) {
		$_rs = array();
		foreach ($rs as $v) {
			$_rs[$v[$col]] = $v;
		}
		return $_rs;
	}
}
if (!function_exists('sortRs')) {
	/**
	 * 按字段对结果集进行排序
	 * @param type $rows
	 * @param type $key
	 * @param type $order
	 * @return array
	 */
	function sortRs($rows, $key, $order = 'asc') {
		$sort = array();
		foreach ($rows as $k => $value) {
			$sort[$k] = $value[$key];
		}
		$order == 'asc' ? asort($sort) : arsort($sort);
		$ret = array();
		foreach ($sort as $k => $value) {
			$ret[] = $rows[$k];
		}
		return $ret;
	}
}
if (!function_exists('mergeRs')) {
	/**
	 * 合并多个结果集，参数是多个：array($rs,$column_name)，$column_name是该结果集和其它结果集关联的字段
	 * 比如：$rs1=array(array('a'=>'1111','b'=>'fasdfas'),array('a'=>'222','b'=>'fasdfas'),array('a'=>'333','b'=>'fasdfas'));
	$rs2=array(array('c'=>'1111','r'=>'fasd22fas'),array('c'=>'222','r'=>'fasd22fas'),array('c'=>'333','r'=>'fasdf22as'));
	$rs3=array(array('a'=>'1111','e'=>'fasd33fas'),array('a'=>'222','e'=>'fas33dfas'),array('a'=>'333','e'=>'fas33dfas'));
	var_dump(mergeRs(array($rs1,'a'),array($rs2,'c'),array($rs3,'a')));
	 * 上面的例子中三个结果集中的关联字段是$rs1.a=$rs2.c=$rs3.a
	 * @return array
	 */
	function mergeRs() {
		$argv = func_get_args();
		$argc = count($argv);
		$ret = array();
		foreach ($argv[0][0] as $v) {
			$r = $v;
			for ($j = 1; $j < $argc; $j++) {
				foreach ($argv[$j][0] as $row) {
					if ($v[$argv[0][1]] == $row[$argv[$j][1]]) {
						$r = array_merge($r, $row);
						break;
					}
				}
			}
			$ret[] = $r;
		}
		$allkeys = array();
		foreach ($argv as $rs) {
			foreach (array_keys($rs[0][0]) as $key) {
				$allkeys[] = $key;
			}
		}
		foreach ($ret as &$row) {
			foreach ($allkeys as $key) {
				if (!isset($row[$key])) {
					$row[$key] = null;
				}
			}
		}
		return $ret;
	}
}

