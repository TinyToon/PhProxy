<?PHP
/**
 * Phproxy Client
 * 
 * PHP 5.3
 * 
 * @package   PhProxy_Client
 * @author    Alex Shcneider <alex.shcneider at gmail dot com>
 * @copyright 2010-2011 (c) Alex Shcneider
 * @license   license.txt
 * @version   2.1.6
 * @link      http://github.com/Shcneider/PhProxy (sources, binares)
 * @link      http://vk.shcneider.in/forum (binares, support)
 * @link      http://alex.shcneider.in/ (author)
 * */

// --------------------------------------------------- >> START PHPROXY!
ob_start();


// Debug Mode
define('PHPROXY_DEBUG',         1);
define('PHPROXY_NET_DEBUG',     1);
#define('PHPROXY_NET_LOG',       0);

// set error reporting
error_reporting(PHPROXY_DEBUG ? E_ALL : 0);

// Php.ini (timezone and exe-time-limit)
date_default_timezone_set('Europe/Minsk');
set_time_limit(0);

// register autoloader and shutdown function
spl_autoload_register('PhProxy::autoloader');
register_shutdown_function('temp');
    function temp()
    {
        echo "->>>>>>>>>>>>>>>>>>>>>>>>>>>>>> EXIT".PHP_EOL;
        $cont = ob_get_contents();
        
        PhProxy::event($cont);
    }

// register errors handler
#set_error_handler('error_handler');

    // not CLI-mode
    if (!isset($argv)) { 
        PhProxy::fatal('PhProxy can be launched for CLI mode only!');
    } 
    
    // Check PHP version (5.3.1 and higher only)
    if (version_compare(PHP_VERSION, '5.3.1', '<')) {
        PhProxy::fatal('PhProxy need PHP >= 5.3.1'.PHP_EOL.'Your PHP version: '.PHP_VERSION);
    }

// start!
$instance = PhProxy::getInstance($argv)->run();
// --------------------------------------------------- >> END!


/**
* PhProxy App
**/
final class PhProxy {
        
    // static: instance of PhProxy
    private static $_instance = null;
    
    // version info
    private static $_version = array(
        'Name'              => 'PhProxy',
        'VersionMajor'      => '2',
        'VersionMinor'      => '1',
        'Build'             => '6',
        'BuildState'        => 'Alpha',
        'BuildDate'         => '12.07.2011'
    );
    
    // deafault startup options
    private static $_options = array(
        'debug'             => 1,           // debug mode on
        'log_format'        => 'Y-m-d'      // log-file format
    );
    
    // default version format
    private static $_version_format = '%an%/%avj%.%avn%.%avb% %avs% (%avd%)';
    
# ---------------------------------------------------------- >> Magic Methods
    
    // singl
    private function __construct() {}   
    private function __clone() {}  
    public function __destruct() {}

# ---------------------------------------------------------- >> PhProxy Factory  
    
    // create a new instance of PhProxy or return created early
    public static function getInstance($argv = false)
    {
        // return already returned instance
        if (self::$_instance) {
            return self::$_instance;
        }
        
        // Set some constants :)
		define('DS',   DIRECTORY_SEPARATOR); // (\ for Windows)
		
		define('PHPROXY_HOME',      realpath('.' . DS) . DS); // abs
			define('PHPROXY_HOME_LOGS',     PHPROXY_HOME . 'logs'  . DS);
			define('PHPROXY_HOME_LANGS',    PHPROXY_HOME . 'langs' . DS);
            define('PHPROXY_HOME_TEMP',     PHPROXY_HOME . 'temp' . DS);

		define('PHPROXY_RHOME',     './'); // all files will be compiled in final .exe
			define('PHPROXY_RHOME_INC',    PHPROXY_RHOME . 'include' . DS);
            define('PHPROXY_RHOME_HTML',   PHPROXY_RHOME . 'html' . DS); 
            define('PHPROXY_RHOME_IMAGES', PHPROXY_RHOME . 'images'. DS); // images root (ini)
											  
        // Parsing a launch options
		if (isset($argv) && is_array($argv)) {
			foreach ($argv as $arg)
			{
				if (strpos($arg, '--') === false) {
					continue;
				}
				$arg = substr($arg, 2);
					if (strpos($arg, '=') === false) {
						self::$_options[trim($arg)] = 1; continue;
					}
				list($name, $val) = @explode('=', $arg, 2);
				self::$_options[trim($name)] = trim($val);
			}
		}
            
        // Error log
		ini_set('log_errors',               PHPROXY_DEBUG);
		ini_set('log_errors_max_len',       1024);
		ini_set('error_log',                PHPROXY_HOME_LOGS . date(self::$_options['log_format']).'.log.txt');     

        // create and start
        return self::$_instance = new PhProxy_Kernel(self::$_options);
    }
    
# ---------------------------------------------------------- >> Utilites   
    
    // return formated version info
    public static function version($f = false)
    {
		// default format
        if (!$f) {
            $f = self::$_version_format;
        }
        
        return str_replace(
            array('%an%', '%avj%', '%avn%', '%avb%', '%avs%','%avd%'), self::$_version, $f
        );
    }
    
    // fatal error, terminated
    public static function fatal($error) 
    {
        // stop-timers flag
        define('PHPROXY_FATAL', true);

		// show message box, log event
        self::mbox($error, 'PhProxy - Fatal Error', WBC_STOP);
        self::event($error, 1); 
        exit;
    }

    // warning handler
    public static function warn($error)
    {
        self::mbox($error, 'PhProxy - Warning', WBC_WARNING);
        self::event($error, 2);
        return true;
    }

    // events handler
    public static function event($txt = false, $level = 0)
    {
        if ($txt == false) { // insert delimetr
            $str = PHP_EOL . PHP_EOL . str_repeat('-', 50) . PHP_EOL;
            error_log($str); 
            self::display($str);
            return true;
        }

        // one string for log
        $txt = str_replace("\r\n", " ", $txt);

        // get memory usage
        $str = (PHPROXY_DEBUG) ?  ('['.microtime(1).' | '.round(memory_get_usage()/(1024), 0).' kb] - ') : '';
            if ($level == 0) { // event
                $str .= 'PHPROXY_EVENT ['.$txt.']';
            } elseif ($level == 1) {
                $str .= 'PHPROXY_FATAL ['.$txt.']';
            } else {
                $str .= 'PHPROXY_NOTICE ['.$txt.']';
            }

        // write log, STDOUT
        error_log($str); 
        self::display($str);
        return true;
    }
   
    // net events handler
    public static function event_net($txt, $level = 1)
    {
        if (PHPROXY_NET_DEBUG == 0) {
            return;
        }
        
        return self::event(' ' .  str_repeat('-', $level) . ' ' . $txt);
    }

    // try to show message box (WinBinder must be loaded)
    public static function mbox($text, $title, $type)
    {
        if (function_exists('wb_message_box')) {            
            // GUI output - UTF8
            #$title = PhProxy::cp2utf($title);
            #$text = PhProxy::cp2utf($text);         
            return wb_message_box(
				(defined('PHPROXY_MAINWIN_ID')) ? PHPROXY_MAINWIN_ID : 0, 
				$text, $title, $type
			);
        }
        return -1;
    }
    
    // display some string
    public static function display($str)
    {    
        // PHC mode - dont use STDOUT (memleaks)
        if (defined('EMBEDED') && !PHPROXY_DEBUG) { 
           # return;
        }
        
        // STDOUT output - window1251
        echo PhProxy::utf2cp($str) . PHP_EOL;
    }
    
    // autoloader
    public static function autoloader($class)
    {
        $fname = strtolower(str_replace('_', '.', $class)); // to lower and replace _ on dots
        $path = PHPROXY_RHOME_INC . 'classes' . DS . $fname . '.class.php';
        require_once self::path($path);
        return true;
    }
    
    // change included file-name for PHP or PHC
    public static function path($file)
    {
        if (defined('EMBEDED')) { // PHC mode
            $path = 'res:///PHP/'.strtoupper(md5(str_replace('\\', '/', $file)));
            self::event('Required file [' . $file . '] will be loaded as ['. $path .']!');
            return $path;
        }

        // PHP mode
        if (!@file_exists($path = str_replace(PHPROXY_RHOME, PHPROXY_HOME, $file))) {
            self::fatal('Required file [' . $path.'] is not found!');
        }
        return $path;
    }
    
    
// ------------------------------------------------------------------------ >> PHPROXY_API    
    
    
    // load file (any, except .php scripts)
    // cache loaded file
    public static function file_load($path, $cache = 0)
    {
        // read all file
        $content = @file_get_contents($path);
            if ($content === false) {
                PhProxy::fatal('Cannot read "'.$path.'" file.');
                return false;
            }
            
        return $content;
    }
        
    // cp1251 to UTF8
    public static function cp2utf($s)
    {
        $c209 = chr(209); $c208 = chr(208); $c129 = chr(129); $t = '';
            for($i = 0; $i < strlen($s); $i++) {
                $c = ord($s[$i]);
                if ($c >= 192 && $c <= 239) {
                    $t .= $c208 . chr($c - 48);
                } elseif ($c > 239) {
                    $t .= $c209 . chr($c - 112);
                } elseif ($c == 184) {
                    $t .= $c209 . $c209;
                } elseif ($c == 168) {
                    $t .= $c208 . $c129;
                } else {
                    $t .= $s[$i];
                }
            }
        return $t;
    }
    
    // UTF8 to cp1251
    public static function utf2cp($s)
    {
         $out = ''; $byte2 = false;

         for ($c = 0; $c < strlen($s); $c++) {
            $i = ord($s[$c]);

            if ($i<=127) {
                $out.=$s[$c];
            } if ($byte2) {
                $new_c2=($c1&3)*64 +($i&63);
                $new_c1 = ($c1 >> 2)&5;
                $new_i = $new_c1 * 256 + $new_c2;
                    if ($new_i == 1025){
                        $out_i = 168;
                    } else {
                        if ($new_i == 1105){
                            $out_i = 184;
                        } else {
                            $out_i = $new_i - 848;
                        }
                    }
                $out.= chr($out_i); $byte2 = false;
            } if (($i>>5) == 6) {
                $c1 = $i; $byte2 = true;
            }
         }
         return $out;
    }
                   
    // New HTTP request from server socket
    // @TODO Add HTTPS support (CONNECT HTTP)
	public static function new_request($id, &$arr)
	{     #var_dump($arr);
        // get lang
        $lang = self::getInstance()->lang;
        
        // Request timeouted ("silence")
        // -------------------------------------------------------- >
        if ($arr['request'] == '$TIMEOUT_SILENCE$') {
            
            // 408 Request Timeout
            $resp = self::server_error(408, self::utf2cp($lang->get('request', 'error1')));
            PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
            return true;
        }
         
        // parse HTTP Request (not tonnel) 
        // -------------------------------------------------------- >
        if ($arr['is_tonnel'] == false) { // normal HTTP workflow
            
            // HTTP Verify and Parse
            
            // parse request 
            $request = new PhProxy_HTTP_Request($arr['request']);
                
                if (($error = $request->error()) > 0) { // parsing error
                   
                    if ($error == 2) { // unkown METHOD
                    
                        // 501 Not Implemented
                        $resp = self::server_error(501, sprintf(self::utf2cp($lang->get('request', 'error2')), $request->error_get()));
                        PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                        return true;
                    
                    }
                    
                    
                    // 400 Bad Request
                    $resp = self::server_error(400, sprintf(self::utf2cp($lang->get('request', 'error3')), $request->error_get()));
                    PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                    return true;
                    
                }

            // get HTTP method
            $method = $request->method_get();
                
                // CONNECT
                if ($method == 'CONNECT') {
                    
                    /* // 200 Connection established
                    $resp = "HTTP/1.0 200 Connection established\r\nProxy-agent:".self::version()."\r\n\r\n";
                    $arr['is_tonnel'] = true;
                    $arr['tonnel_to'] = $request->host_get().':'.$request->port_get();
                    PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING_KA, $resp);
                    return true;* 
                    var_dump($resp); exit;*/
                    
                    // 405 Method Not Allowed
                    $resp = self::server_error(405, sprintf(self::utf2cp($lang->get('request', 'error4')), $method));
                    PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                    return true;
                     
                    
                }
            
            // --------------------------------- > GET, POST, HEAD

            // check host
            $host = $request->host_get(); $port = $request->port_get();
                if ($port != 80) {
                    // 400 Bad Request
                    $resp = self::server_error(400, sprintf(self::utf2cp($lang->get('request', 'error3')), $request->error_get()));
                    PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                    return true;
                } 
                if ($host != 'm.vk.com' && $host != 'login.vk.com') {
                    // 400 Bad Request
                    $resp = self::server_error(400, sprintf(self::utf2cp($lang->get('request', 'error9')), $request->error_get()));
                    PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                    return true;
                }
                
                
            // add header Connection: close
            $request->header_add('Connection', 'close');

            // remove header Proxy-Connection:
            $request->header_rm('Proxy-Connection');

            // build request again
            $arr['request'] = $request->build();

            // destroy
            $request->destroy(); unset($request);
            
            // -------------------------->> Send request to API

            // create task for sending request to API
            $added = self::getInstance()->client->new_query_to_api(
                self::api_make('get', array('request' => $arr['request'])), 
                function(&$cnx) {
                    
                    if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok
                        
                        PhProxy::getInstance()->socket->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, $cnx['response']);
                        
                    } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted
                        
                        PhProxy::getInstance()->socket->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$TIMEOUT_CLIENT$');
                            
                    } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open
                        
                        PhProxy::getInstance()->socket->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$CANT_CONNECT$');
                        
                    }
                }, 
                $id
            );

            // task not added
            if (!$added) {
                
                // 509 Bandwidth Limit Exceeded
                $resp = self::server_error(509, self::utf2cp($lang->get('request', 'error5')));
                PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                
                return false;
                
            }

            return true;
            
        } else { // ----------------------------- HTTP tonel work
            
            /*
            // create task for sending request to API
            $added = self::getInstance()->client->new_query_to_api(
                self::api_make('get_secure', array('data' => base64_encode($arr['request']), 'to' => $arr['tonnel_to'])), 
                function(&$cnx) {
                    
                    if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok
                        
                        // work here
                        $r = $cnx['response'];
                        list($t, $r) = explode("\r\n\r\n", $r, 2);
                        $r = str_replace('$PHPROXY_API_RESPONSE$'."\r\n", '', $r);
                        $r = unserialize($r); 
                        $r = base64_decode($r["content"]);
                        
                        PhProxy::getInstance()->socket->state_set_for($cnx['binded_socket'], SOCKET_STATE_WRITING, $r);

                        return true;
                        
                        
                        #PhProxy::getInstance()->socket->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, $cnx['response']);
                        
                    } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted
                        
                        exit('cannot add task 2!'); 
                            
                    } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open
                        
                        exit('cannot add task! 3'); 
                        
                    }
                }, 
                $id
            );

            // task not added
            if (!$added) {
                
               exit('cannot add task!'); 
                
            } */

        
            
            
        }
  
        
        // 400 Bad Request
        $resp = self::server_error(400, sprintf(self::utf2cp($lang->get('request', 'error3')), 'Trolololo!'));
        PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
        return true;
        
    }
    
    // http response wrapper
    public static function new_response($id, &$arr)
    {   
        // get lang
        $lang = self::getInstance()->lang;
        
        // get response
        $resp = $arr['response'];
        
        
            // handling socket-event
            if ($resp == '$CANT_CONNECT$') {

                // 502 Bad Gateway
                $resp = self::server_error(502, self::utf2cp($lang->get('request', 'error6')));
                PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;


            } elseif ($resp == '$TIMEOUT_CLIENT$') {

                // 504 Gateway Timeout
                $resp = self::server_error(504, self::utf2cp($lang->get('request', 'error7')));
                PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;

            } // else

        // parse response
        $error = null;
        $resp = self::api_parse($resp, $error);
            if (!$resp) {
                
                // 500 Internal Server Error
                $resp = self::server_error(500, self::utf2cp($lang->get('request', 'error8')).'<br/>Raw: <tt>'.$arr['response'].'</tt>');
                PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;
                
            }

        // error handler
        if ($resp['state'] == 'error') {
            
            // 200 OK
            $answer = self::server_error(200, $resp['content']);
            PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $answer); 
            return true;
            
        } // Okey state
        
        
        
        PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING, $resp['content']); 
        return true;
        

    }
           
    // make a request to API
    public static function api_make($method, $params = array())
    {
        // get instance
        $phproxy = PhProxy::getInstance();
        
        // form data to send
        $out = array('method' => $method, 'params' => $params);
        $data = 'cmd='.base64_encode(serialize($out)).'&version='.self::version('%avb%').'&authkey='.self::get_authkey();
        
        // Get params from config file
        $api_path = $phproxy->cfg->get('phproxy.api', 'path');
        $api_host = $phproxy->cfg->get('phproxy.api', 'host');
     
        // build request
        $query = new PhProxy_HTTP_Request('POST', $api_path);
            $query->header_add('Host', $api_host);
            $query->header_add('Referer', 'http://' . $api_host . $api_path);
            $query->header_add('Connection', 'close');
            $query->header_add('Content-Type', 'application/x-www-form-urlencoded');
            $query->body_set($data);
        $query2 = $query->build();
        $query->destroy();
        
        return $query2;
    }
    
    // parse a response from API
    public static function api_parse($response, &$error)
    {       
        // headers - body
        if (strpos($response, "\r\n\r\n") === 0) {
            $error = 'Api_Prase_Error #1 - Bad answer format'; 
            return false;
        }
        
        list($headers, $body) = explode("\r\n\r\n", $response, 2);
             
        // if it's API RESPONSE
        if (strpos($body, '$PHPROXY_API_RESPONSE$') === 0) {
            
            list($temp, $body) = explode("\r\n", $body, 2);
            
            // unserializa
            $arr = @unserialize($body);
                if (!$arr) {
                    
                    $error = 'Api_Prase_Error #2 - Unserialize error!'; 
                    return false;
                    
                } elseif (!is_array($arr)) {
                    
                    $error = 'Api_Prase_Error #3 - Data type error!'; 
                    return false;
                    
                } elseif (!isset($arr['state']) or !isset($arr['content'])) {
                    
                    $error = 'Api_Prase_Error #4 - Unkown response format!';
                    return false;
                    
                } 
                
            // base64 decode
            $arr['content'] = base64_decode($arr['content']);

            return $arr;
            
            
        } // else
            
        $arr = array('state' => 'ok', 'content' => $body);
        
        return $arr;
             
    }
    
    // generate server error
    public static function server_error($code, $error)
    {
        // load HTML server error
        $errorf = self::file_load(self::path(PHPROXY_RHOME_HTML . 'server-error.html'));

        // Generate HTTP Response with error
        $response = new PhProxy_HTTP_Response($code);          
            $response->header_add('Connection', 'close');
            $response->header_add('Content-Type', 'text/html; charset=windows-1251');
            $response->body_set($errorf);
                $response->body_replace('{version}', PhProxy::version());
                $response->body_replace('{system}', @php_uname());
                $response->body_replace('{error}', $error);
        $resp = $response->build();
        $response->destroy();
        
        return $resp;     
    }
          
    // return authkey
    public static function get_authkey()
    {
        return 'authkey';
    }
    
   
    
}

?>