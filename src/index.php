<?PHP
/**
 * Phproxy Client
 * 
 * PHP 5.3 !!ONLY!!
 * 
 * @package   PhProxy_Client
 * @author    Alex Shcneider <alex.shcneider@gmail.com>
 * @copyright 2010-2011 (c) Alex Shcneider
 * @license   license.txt
 * @version   2.1.8 Beta
 * @link      http://github.com/Shcneider/PhProxy   (sources, binares)
 * @link      http://pproxy.ru/forum                (binares, support)
 * @link      http://vk.com/shcneider               (author @ vk.com)
 * @link      http://vseti.by/shcneider             (author @ vseti.by)
 **/

// --------------------------------------------------- >> CONFIGURE PHP

/**
 * Default time zone set
 */
date_default_timezone_set('Europe/Minsk');

/**
 * Default timeout for socket
 */
ini_set('default_socket_timeout', 3);

/**
 * Max execution time is 0 (unlim)
 */
set_time_limit(0);

// --------------------------------------------------- >> START PHPROXY!

if (!isset($GLOBALS['argv'])) {  // CLI only!
    exit('PhProxy can be launched for CLI mode only! Terminated...');
}
PhProxy::factory($argv)->run();

// --------------------------------------------------- >> END!

/**
 * 
 * Main Application
 * 
 */
final class PhProxy {
        
    /**
     * Instance of PhProxy_Kernel
     * @var object
     */
    private static $_instance = null;
    
    
    /**
     * Default startup options. Another will be parsed from $argv
     * @var array
     */
    private static $_options = array(
        'action'          => 'start',
        'log_format'      => 'Y-m-d',
        'debug'           => 1  
    );
    
    
    /**
     * PhProxy version info
     * @var array 
     */
    private static $_version = array(
        'Name'              => 'PhProxy',
        'VersionMajor'      => '2',
        'VersionMinor'      => '1',
        'Build'             => '8',
        'BuildState'        => 'Beta',
        'BuildDate'         => '19.08.2011' 
    );
    
    
    /**
     * Default version format
     * @var string
     */
    private static $_version_format = '%an%/%avj%.%avn%.%avb% %avs% (%avd%)';
    
    
    /**
     * Auth data
     */
    private static $_authdata = array(
        'authkey'       => '0',
        'ahosts'        => array('/^login\.vk\.com$/i', '/^m\.vk\.com$/i', '/^pproxy\.ru$/i', '/^[a-z]+\.pproxy\.ru$/i'),
        'keep_alive'    => 0,
        'login'         => '',
        'group'         => 2, // guest
        'group_expire'  => 0,
        'is_vip'        => 0
    );
    
    
# ---------------------------------------------------------- >> Magic Methods

    /**
     * Dissalow instances
     */
    private function __construct() {} 
    
    
    /**
     * Dissalow instances
     */
    private function __clone() {}  
 
    
    /**
     * Creating a new instance of PhProxy_Kernel or return created early
     * @deprecated
     * 
     * @param array $argv Creating arguments (first call) <br/> Returned property name (next)
     * @return object
     */
    public static function factory($argv = false)
    {
        
        # --------------------------------------------------------------------- >> return already existed instance
        
        if (self::$_instance) {

            // return some property from instance only
            if (is_string($argv) && property_exists(self::$_instance, $argv)) {
                return self::$_instance->$argv;
            }
            
            return self::$_instance;
        }

        # --------------------------------------------------------------------- >> INITIALIZTION (Executed once)
        
        // Register our autoloader
        spl_autoload_register('PhProxy::autoloader');
        
        // Register our function on shutdown
        register_shutdown_function('PhProxy::shutdown');
        
        // Starting output buffering if it's PHC-mode
        // It's hack for PHC-compiled application (terminated after first byte in STDOUT)    
        defined('EMBEDED') ? ob_start() : true;
        
        # ----------------------------------- >> Parsing $argv
        
        // Parsing a launch options
        if (isset($argv) && is_array($argv)) { 
            foreach ($argv as $arg)
            {
                if (strpos($arg, '--') !== 0) {
                    continue;
                }
                $arg = substr($arg, 2);
                    if (strpos($arg, '=') === false) {
                        self::$_options[trim($arg)] = 1; continue;
                    }
                list($name, $val) = explode('=', $arg, 2);
                self::$_options[trim($name)] = trim($val);
            }
        }
        
        
        # ----------------------------------- >> Set some constants

		define('DS',   '/'); 
		
		define('PHPROXY_HOME',      realpath('.' . DS) . DS); // abs
			define('PHPROXY_HOME_LOGS',     PHPROXY_HOME . 'logs'  . DS);
			define('PHPROXY_HOME_LANGS',    PHPROXY_HOME . 'langs' . DS);
            define('PHPROXY_HOME_TEMP',     PHPROXY_HOME . 'temp' . DS);

		define('PHPROXY_RHOME',     './'); // all files will be compiled in final .exe
			define('PHPROXY_RHOME_INC',    PHPROXY_RHOME . 'include' . DS);
            define('PHPROXY_RHOME_HTML',   PHPROXY_RHOME . 'html' . DS); 
            define('PHPROXY_RHOME_IMAGES', PHPROXY_RHOME . 'images'. DS); // images root (ini)
								
        
        # ----------------------------------- >> Configure PhProxy
        
        /**
         * Debug mode: on/off
         * - Error_reporting: on/off (PHC always off)
         * - Log errors:      on/off
         */
        define('PHPROXY_DEBUG',          1);
        
        /**
         * Network debug mode: on/off
         * @todo PhProxy net debug const (0:disabled, 1:tcp, 2:http-headers, 4:http-bodyies and summ like chmod)
         */
        define('PHPROXY_NET_DEBUG',      0);
        
        /**
         * Set error reporting:
         * E_ALL if it is debug-mode and not PHC is on 0 otherwise
         * @todo use PhProxy_debug for error_reporting
         */
        error_reporting(E_ALL);

            
        // Error log
        ini_set('log_errors',               1);
        ini_set('log_errors_max_len',       1024);
        ini_set('error_log',                PHPROXY_HOME_LOGS . date(self::$_options['log_format']).'.log.txt');   
  
        // insert separator to log file
        self::event(null);
        
            if (version_compare(PHP_VERSION, '5.3.1', '<')) { // Check PHP version (5.3.1 and higher only)
                PhProxy::fatal('PhProxy need PHP >= 5.3.1'.PHP_EOL.'Your PHP version: '.PHP_VERSION);
            }
        
        // create and return
        return self::$_instance = new PhProxy_Kernel(self::$_options);
        #return self::$_instance = new PhProxy_Kernel(self::$_options['action']);
    }
    
    
    /**
     * Execute in last times after termination.
     */
    public static function shutdown()
    {
        # exit;
    }
      
    
# ---------------------------------------------------------- >> PhProxy base methods
    
    
    /**
     * PhProxy autoloader
     * 
     * @param string $class Name of required class
     * @return bool
     */
    public static function autoloader($class)
    {   
        // to lower case and replace _ on dots
        $fname = strtolower(str_replace('_', '.', $class)); 
        require_once self::path(PHPROXY_RHOME_INC . 'classes' . DS . $fname . '.class.php');
        return true;
    }
    
    
    /**
     * Return valid path to file $file for PHC or PHP mode
     * 
     * @param string $file Required path to file
     * @return string 
     */
    public static function path($file)
    {
        // PHC mode
        if (defined('EMBEDED')) { 
            $path = 'res:///PHP/'.strtoupper(md5(str_replace('\\', '/', $file)));
            self::event('Required file [' . $file . '] will be loaded as ['. $path .']!');
            return $path;
        }

        // PHP mode
        if (!file_exists($path = str_replace(PHPROXY_RHOME, PHPROXY_HOME, $file))) {
            self::fatal('Required file [' . $path.'] is not found!');
        }
        return $path;
    }
      
     
    /**
     * Return formated version info
     * 
     * @staticvar array $versions compiled strings
     * @param string $f version's flags <br/>Examples: [%an%/%avj%.%avn%.%avb% %avs% (%avd%)]
     * @return string
     */
    public static function version($f = false)
    {
        static $versions = array();
		
            if (!$f) { // default format
                $f = self::$_version_format;
            }
         
        if (isset($versions[$f])) {
            return $versions[$f];
        }
        
        return $versions[$f] = str_replace(
            array('%an%', '%avj%', '%avn%', '%avb%', '%avs%','%avd%'), self::$_version, $f
        );
    }
    
    
    /**
     * Display text of error and terminated script.
     * 
     * @static
     * @param string $error text of error occurred 
     * @return null
     */
    public static function fatal($error, $title = false) 
    {
        // Stop timers 
        define('PHPROXY_FATAL', true);
            
            if (!$title) { // default title
                $title = 'Fatal Error';
            }

		// show message box, log event
        self::mbox($error, self::version('%an%/%avj%.%avn%.%avb%').' - '.$title, WBC_STOP);
        self::event($error, 1); 
        exit;
    }

    
    /**
     * Warning handler
     * 
     * @param string $error text of warning
     * @return bool
     */
    public static function warn($error, $title = false)
    {
        // default title
        if (!$title) {
            $title = 'Warning';
        }
        
        // show message box, log event
        self::mbox($error, self::version('%an%/%avj%.%avn%.%avb%').' - '.$title, WBC_WARNING);
        self::event($error, 2);
        return true;
    }


    /**
     * PhProxy events handler
     * 
     * @param mixed $txt event text
     * @param int $level event level
     * @return true
     */
    public static function event($txt = false, $level = 0)
    {
        // insert delimetr
            if ($txt == false) { 
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
   
    
    /**
     * Network events handler
     * 
     * @param string $txt Event text
     * @param int $level Event level
     * @return bool 
     */
    public static function event_net($txt, $level = 1)
    {
        // if net debug is dissabled
        if (PHPROXY_NET_DEBUG == 0) {
            return false;
        }
        
        return self::event(' ' .  str_repeat('-', $level) . ' ' . $txt);
    }


    /**
     * Try to show GUI message box (php_winbinder.dll must be loaded)
     * Return -1 if winbinder is not loaded
     * 
     * @uses PHPROXY_MAINWIN_ID
     * @uses wb_message_box()
     * @param string $text text of error to show
     * @param string $title title of window
     * @param int $type type of window (as WBC_STOP)
     * @return mixed
     */
    public static function mbox($text, $title, $type)
    {
        if (function_exists('wb_message_box')) {                    
            return wb_message_box((defined('PHPROXY_MAINWIN_ID')) ? PHPROXY_MAINWIN_ID : 0, $text, $title, $type);
        }
        return -1;
    }
    
    
    /**
     * Display some string in STDOUT
     *
     * @param string $str displayed string 
     * @return true
     */
    public static function display($str)
    {    
        // hack for PHC-compiled application (terminated after first byte in STDOUT)
        if (defined('EMBEDED')) { 
           return;
        } 
        
        // STDOUT output - window1251
        echo PhProxy::utf2cp($str) . PHP_EOL;
        return true;
    }
    

    /**
     * Check on file existsing
     * 
     * @param string $path
     * @return bool 
     */
    public static function file_exists($path)
    {
        return file_exists($path);
    }
    
    
    /**
     * Load some file from FS. <br/>
     * Return false on fail, file content otherwise
     * 
     * @param string $path path to loaded file
     * @param bool $cache this file or not
     * @todo File repository and caching
     * @return mixed
     */
    public static function file_load($path, $cache = 0)
    {
        // read all file
        $content = file_get_contents($path);
            if ($content === false) {
                PhProxy::fatal('Cannot read "'.$path.'" file.');
                return false;
            }
            
        return $content;
    }
       
    
    /**
     * Save some file in FS
     * 
     * @param string $path
     * @param string $content
     * @return int 
     */
    public static function file_save($path, $content)
    {
        return file_put_contents($path, $content);
    }
    
    
    /**
     * Delete some file from file system
     * 
     * @param string $path Path to deleting file
     * @return bool 
     */
    public static function file_delete($path)
    {
        return unlink($path);
    }
    
     
    /**
     * Encode windows-1251 to UTF8 string
     * 
     * @param string $s encoded string
     * @return string
     */
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
    
    
    /**
     * Encode UTF8 to Widnows-1251 string
     * 
     * @param string $s encoded string
     * @return bool
     */
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
        
    /**
     * Run some shell command
     * 
     * @param type $cmd 
     */
    public static function exec($cmd)
    {
        return wb_exec($cmd);
    }
    
    
// ------------------------------------------------->   
    

    /**
     * Handler of new HTTP request from server socket
     * 
     * @param int $id
     * @param array $arr 
     * @return bool
     * @todo Add HTTPS support (CONNECT HTTP)
     */
	public static function new_request($id, &$arr)
	{     
        // get lang, cfg
        $lang = self::factory('lang');
        $cfg = self::factory('cfg');
        
        // Authkey is "0" - not authed
        // -------------------------------------------------------- >
        if (self::profile_get('authkey') == "0") {
            
            // 403 Guests no access
            // @TODO - Guests fuck off!
            #$resp = self::server_error(403, self::utf2cp($lang->get('request', 'error11')));
            #PhProxy::getInstance('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
            #return true;
            
        }
        
        
        // Request timeouted ("silence")
        // -------------------------------------------------------- >
        if ($arr['request'] == '$TIMEOUT_SILENCE$') {

            // 408 Request Timeout
            $resp = self::server_error(408, self::utf2cp($lang->get('request', 'error1')));
            PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
            return true;
            
        }
        
        // CONNECT NOT ALLOWED YET
        // -------------------------------------------------------- >
        if ($arr['is_tonnel']) { // deny
 
            // 405 Method Not Allowed
            $resp = self::server_error(405, sprintf(self::utf2cp($lang->get('request', 'error2')), 'CONNECT'));
            PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
            return true;
            
        }
        
        // Normal HTTP request Parsing
        // -------------------------------------------------------- >
        $request = new PhProxy_HTTP_Request($arr['request']); // parse request
        
            if (($error = $request->error()) > 0) { // parsing error
                   
                    if ($error == 2) { // unkown METHOD
                    
                        // 501 Not Implemented
                        $resp = self::server_error(501, self::utf2cp($lang->get('request', 'error3')));
                        PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
                        return true;
                    
                    }
                    
                    
                    // 400 Bad Request
                    $resp = self::server_error(400, sprintf(self::utf2cp($lang->get('request', 'error4')), $request->error_get()));
                    PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
                    return true;
                    
             }

        // get HTTP method
        $method = $request->method_get();
            if ($method == 'CONNECT') { // CONNECT not allowed
                
                // 405 Method Not Allowed
                $resp = self::server_error(405, sprintf(self::utf2cp($lang->get('request', 'error2')), 'CONNECT'));
                PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
                return true;
                
            }
            
        // check host
        // -------------------------------------------------------- >    
        $ahosts = self::profile_get('ahosts');   // get allowed hosts    
        $host = $request->host_get(); $port = $request->port_get();
        
        
        // prepare query
        $request->header_add('Connection', 'close'); // add header [Connection: close]
        $request->header_rm('Proxy-Connection');     // remove header [Proxy-Connection:]
        $arr['request'] = $request->build();         // build request again
        $request->destroy(); unset($request);        // destroy and clean      

        
        // direct connection to remote
        // -------------------------------------------------------- >
        
        $allow = false;
        
            foreach ($ahosts as $h) {
                if (preg_match($h, $host)) {
                    $allow = true; 
                    break;
                }
            }

        if (!$allow) {

            if ((int)$cfg->get('phproxy', 'direct_connections_allow')) {
                // new query to some host
                $added = self::factory('client')->new_query(
                    $host, 
                    (int)$port, 
                    (int)$cfg->get('socket.client', 'direct_open_timeout'), // timeout
                    $arr['request'], // string for sending
                    function(&$cnx) {  // handler

                        if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok

                            PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_WRITING, $cnx['response']);

                        } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted

                            PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$TIMEOUT_CLIENT$');

                        } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open

                            PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$CANT_CONNECT$');

                        }
                    }, 
                    $id // binded server-socket
                );

                // task not added
                if (!$added) {

                    // 509 Bandwidth Limit Exceeded
                    return PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, 
                        self::server_error(509, self::utf2cp($lang->get('request', 'error5')))
                    );

                }

                return true;    

            } // else DENY!
            
            
            // 403 Access Deny
            PhProxy::event('Access deny to '.$host);
            $resp = self::server_error(403, sprintf(self::utf2cp($lang->get('request', 'error10')), $host));
            PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp);
            return true;
   
        }
        
        // Access VIA proxy
        // -------------------------------------------------------- >

        // create task for sending request to API
        $added = self::factory('client')->new_query_to_api(
            self::api_make('get', array('request' => $arr['request'])), 
            function(&$cnx) {

                if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok

                    PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, $cnx['response']);

                } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted

                    PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$TIMEOUT_CLIENT$');

                } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open

                    PhProxy::factory('socket')->state_set_for($cnx['binded_socket'], SOCKET_STATE_PARSING_RESP, '$CANT_CONNECT$');

                }
            }, 
            $id
        );

        // task not added
        if (!$added) {

            // 509 Bandwidth Limit Exceeded
            $resp = self::server_error(509, self::utf2cp($lang->get('request', 'error6')));
            PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp );

            return false;

        }

        return true;    
  
    }
    

    /**
     * Parsing response
     * 
     * @param type $id
     * @param type $arr
     * @return type 
     */
    public static function new_response($id, &$arr)
    {   
        // get lang
        $lang = self::factory()->lang;
        
        // get response
        $resp = $arr['response'];
        
            // handling socket-event
            if ($resp == '$CANT_CONNECT$') {

                // 502 Bad Gateway
                $resp = self::server_error(502, self::utf2cp($lang->get('request', 'error7')));
                PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;


            } elseif ($resp == '$TIMEOUT_CLIENT$') {

                // 504 Gateway Timeout
                $resp = self::server_error(504, self::utf2cp($lang->get('request', 'error8')));
                PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;

            } // else
    
        // parse response
        $error = null;
        $resp = self::api_parse($resp, $error);
            if (!$resp) {
                
                // 500 Internal Server Error
                $resp = self::server_error(500, self::utf2cp($lang->get('request', 'error9')).'<br/>Raw: <tt>'.$arr['response'].'</tt>');
                PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp );
                return true;
                
            }

        // error handler
        if ($resp['state'] == 'error') {
            
            // 200 OK
            $answer = self::server_error(200, $resp['content']);
            PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $answer); 
            return true;
            
        } // Okey state
        
        
        
        PhProxy::factory('socket')->state_set_for($id, SOCKET_STATE_WRITING, $resp['content']); 
        return true;
    }
           
    
    /**
     * Build request to API server from params
     * 
     * @param string $method Name of called method
     * @param array $params Array of params for sending
     * @return string 
     */
    public static function api_make($method, $params = array())
    {
        // get instance
        $phproxy = PhProxy::factory();
        
        // form data to send
        $out = array('method' => $method, 'params' => $params);
        $data = 'cmd='.base64_encode(serialize($out)).'&version='.self::version('%avb%').'&authkey='.self::profile_get('authkey');

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
    
    
    /**
     * Parse response from API server
     * 
     * @param string $response Response for parsing
     * @param strng $error &Error
     * @return mixed
     */
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
            $arr = unserialize($body);
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
            
            
        } // else raw data
            
        $arr = array('state' => 'ok', 'content' => $body);
        
        return $arr;
             
    }
    
    
    /**
     * Generate HTML server error
     * 
     * @param type $code
     * @param type $error
     * @return type 
     */
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
          

    /**
     * Get some options from user-profile
     * 
     * @param string $what 
     * @return mixed
     */
    public static function profile_get($what = false)
    {
        if (!$what) {
            return self::$_authdata;
        }
        
        if (isset(self::$_authdata[$what])) {
            return self::$_authdata[$what];
        }
        
        // @TODO
        PhProxy::fatal('PhProxy::profile_get() -  unkown key called ['.$what.']');
    }
    
    /**
     * Set user-profile data
     * 
     * @param array $arr
     * @return null
     */
    public static function profile_set($arr)
    {
        foreach ($arr as $key => $value)
        {
            self::$_authdata[$key] = $value;
        }
    }
    
 
    
}

/* // 200 Connection established
$resp = "HTTP/1.0 200 Connection established\r\nProxy-agent:".self::version()."\r\n\r\n";
$arr['is_tonnel'] = true;
$arr['tonnel_to'] = $request->host_get().':'.$request->port_get();
PhProxy::getInstance()->socket->state_set_for($id, SOCKET_STATE_WRITING_KA, $resp);
return true;* 
var_dump($resp); exit;
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
                
            } * /

 */
?>