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


/**
 * PhProxy GUI-Events Controller
 * @param int $win Window ID
 * @param int $id Controll ID (signal ID)
 * @param int $con Context 
 * @param int $param1 Param #1
 * @param int $param2 Param #2
 * @return bool
 */
function PhProxy_Controller($win, $id, $con = 0, $param1 = 0, $param2 = 0)
{
    // select widnow
    switch ($win) {
        
        // unkown window
        default: PhProxy::event('GUI-EVENT: Unkown window ID - ' . $win);
            break;

        // main window
        case PHPROXY_MAINWIN_ID: PhProxy::factory('gui')->c_handler_invoke($id, $con, $param1, $param2);
            break;
        
    }
    return true;
}



/**
 * PhProxy engine
 */
final class PhProxy_Kernel {
    

    /**
     * Instance of PhProxy_Config
     * Config manager
     * @var object 
     */
    public $cfg = null;
    
    /**
     * Instance of PhProxy_Language
     * @var object 
     */
    public $lang = null;
    
    /**
     * Instance of PhProxy_Socket_Client
     * @var object
     */
    public $client = null;
    
    /**
     * instance of PhProxy_Socket_Server
     * @var object 
     */
    public $server = null;
    
    /**
     * instance of PhProxy_GUI
     * @var object 
     */
    public $gui = null;
    
    /**
     * Last Keep-Alive connection with api
     * @var int
     */
    private $_auth_last_refresh = 0;
    
// --------------------------------------------------- >    
    
    /**
     * Contruct
     * 
     * @param array $options startup options
     * @return null 
     */
    public function __construct($options)
    {
        
        // load config file
        $this->cfg = new PhProxy_Config(PHPROXY_HOME . 'phproxy.ini');
        
        // get current lang from config
        $lang = preg_replace('/[^a-z]/i', '', $this->cfg->get('phproxy', 'language'));
            if (!file_exists(PHPROXY_HOME_LANGS . $lang . '.ini')) {
                PhProxy::fatal('Cannot find localization file: '.$lang.'.ini');
            }
        // load language file   
        $this->lang = new PhProxy_Language(PHPROXY_HOME_LANGS . $lang . '.ini');
               
        
        // Try write in root dir (root is writable?)
            $tname = PHPROXY_HOME . md5(microtime());
            $fp = fopen($tname, 'w+');
                if (!$fp) {
                    PhProxy::fatal(
                        sprintf($this->lang->get('bootstrap', 'error1'), PHPROXY_HOME)
                    );
                } else {
                    PhProxy::event('Root folder is writable!');
                }
            fclose($fp); unlink($tname);
                        
        // logs dir - creating if not exists (2.0.5 ++)
            if (!file_exists(PHPROXY_HOME_LOGS)) {
                if (!mkdir(PHPROXY_HOME_LOGS, 0777, true)) {
                    PhProxy::fatal(
                        sprintf($this->lang->get('bootstrap', 'error2'), PHPROXY_HOME_LOGS)
                    );
                }
            } else {
                PhProxy::event('Logs folder is exists and writable!');
            }
         
        // temp dir
            if (!file_exists(PHPROXY_HOME_TEMP)) {
                if (!mkdir(PHPROXY_HOME_TEMP, 0777, true)) {
                    PhProxy::fatal(
                        sprintf($this->lang->get('bootstrap', 'error7'), PHPROXY_HOME_TEMP)
                    );
                }
            } else {
                PhProxy::event('Temp folder is exists and writable!');
            }
                        
        // php_winbinder.dll was loaded?
            if (!extension_loaded('winbinder')) {
                PhProxy::fatal(
                    $this->lang->get('bootstrap', 'error3')
                );
            } else {
                PhProxy::event('WinBinder.dll is loaded!');
            }  
            
        // php_sockets.dll was loaded?
            if (!extension_loaded('sockets')) {
                PhProxy::fatal(
                    $this->lang->get('bootstrap', 'error6')
                );
            } else {
                PhProxy::event('sockets.dll is loaded!');
            }     
            
        // Including WB files (http://winbinder.org)
        require_once PhProxy::path(PHPROXY_RHOME_INC .  'winbinder.inc.php');
            if (!defined('PHPROXY_WB_LOADED')) {
               PhProxy::fatal(
                   $this->lang->get('bootstrap', 'error4')
               );
            } elseif(PHPROXY_DEBUG) {
               PhProxy::event('WB files loaded!');
            }    
        
        // double-launch protect ( @since 2.0.5 )
        define('PHPROXY_WIN_MAIN_TITLE', PhProxy::version('%an%'));
            if (wb_get_instance(PHPROXY_WIN_MAIN_TITLE, true)) {
                PhProxy::warn($this->lang->get('bootstrap', 'error5')); die();
            } 
            
        // require GUI controlls ID
        require_once PhProxy::path(PHPROXY_RHOME_INC .  'phproxy.gui.controlls.php');    
         
        
        // create instance of server socket's class
        $this->socket = new PhProxy_Socket_Server(
            $this->cfg->get('socket.server',      'listen_ip'), 
            (int)$this->cfg->get('socket.server', 'listen_port')
        );
        
        
        // create instance of client's class
        $this->client = new PhProxy_Socket_Client(
            (int)$this->cfg->get('socket.client', 'cnx_limit'), 
            (int)$this->cfg->get('socket.client', 'cnx_timeout'), 
            (int)$this->cfg->get('socket.client', 'read_buffer'), 
            (int)$this->cfg->get('socket.client', 'write_buffer'),
            (int)$this->cfg->get('socket.client', 'read_again_max')
        );
        
        return true;  
    }
    
    
    /**
     * Run PhProxy (call once after contruct)
     * 
     * @return null 
     */
    public function run()
    {         
        // GUI start - create main-window
        $gui = $this->gui = new PhProxy_GUI(PHPROXY_WIN_MAIN_TITLE, AppWindow, WBC_TASKBAR | WBC_INVISIBLE | WBC_TOP);
        
            // set-up window
            $gui->title(PHPROXY_WIN_MAIN_TITLE);
            $gui->position(WBC_CENTER, WBC_CENTER);
            $gui->size(MAINWIN_AUTH_W, MAINWIN_AUTH_H);
            
            // unpack image from exe and write in temp dir
            PhProxy::file_save($name = PHPROXY_HOME_TEMP.md5(microtime(1)).'.ico', PhProxy::file_load(PhProxy::path(PHPROXY_RHOME_IMAGES . 'main_icon_1.ico')));
            $gui->icon($name);
            PhProxy::file_delete($name);
            
            
        // get window ID and set global constant
        define('PHPROXY_MAINWIN_ID', $gui->get_wobj());
               
        // set window-handler
        $gui->handler_set('PhProxy_Controller');
        
        
        // set handlers for some controls
            $gui->c_handler_set(IDCLOSE, function() { // user wanna close app
               PhProxy::factory()->stop();
            });
            $gui->c_handler_set(ID_HYPER_FORUM, function() { // register
                PhProxy::factory()->register();
            });
            $gui->c_handler_set(ID_BUTTON_AUTH_NO, function() { // auth as Guest
                #PhProxy::getInstance()->guest();
            });
            $gui->c_handler_set(ID_BUTTON_AUTH_IN, function() { // autherization
                PhProxy::factory()->auth(false);
            });
            $gui->c_handler_set(ID_TIMER_NET_CLIENT, function() { // client-socket-timer
                PhProxy::factory()->client->timer_worker();
            });
            $gui->c_handler_set(ID_TIMER_NET_SERVER, function() { // server-socket-timer
                PhProxy::factory()->socket->timer_worker();
            });
            $gui->c_handler_set(ID_TIMER_SYSTEM, function() { // system timer
                PhProxy::factory()->tick();
            });
        
        // add system's timers
        $gui->timer(ID_TIMER_NET_CLIENT, (int)$this->cfg->get('phproxy', 'client_timer_int')); 
        $gui->timer(ID_TIMER_NET_SERVER, (int)$this->cfg->get('phproxy', 'server_timer_int')); 
        $gui->timer(ID_TIMER_SYSTEM,     (int)$this->cfg->get('phproxy', 'system_timer'));
                   
        // create server socket
        $this->socket->create(
            (int)$this->cfg->get('socket.server', 'max_half_open_cnx'),
            (int)$this->cfg->get('socket.server', 'max_cnx'),
            (int)$this->cfg->get('socket.server', 'read_buffer'),
            (int)$this->cfg->get('socket.server', 'read_again_max'),
            (int)$this->cfg->get('socket.server', 'timeout_silence')
        );   
               
        // Build auth window from empty window
        return $this->_gui_rebuild('auth', 'empty');
    }
    
    /**
     * System timer
     * 
     */
    public function tick()
    {
        
        // Keep Alive auth session
        # ------------------------------------- >
        $int = PhProxy::profile_get('keep_alive');
            if ($int != 0) {
                if ($this->_auth_last_refresh == 0) { // yet not updated
                    $this->_auth_last_refresh = time() - 3600;
                } if ($this->_auth_last_refresh < time()) { // expired
                    
                    // create task for sending request to API
                    $added = PhProxy::factory('client')->new_query_to_api(
                        PhProxy::api_make('keep_alive', array('time' => time())), 
                        function(&$cnx) {

                            if ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted

                                PhProxy::warn('Cant connect to API #1!');

                            } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open

                                PhProxy::warn('Cant connect to API #2!');

                            }
                        }, 
                        0
                    );
                        
                    if ($added) {
                        $this->_auth_last_refresh = time() + $int; 
                    } else {
                        PhProxy::warn('Cant connect to API #3!');
                        
                    } 
                }
            }
            
         // Memory Usage
         # ------------------------------------- >   
            
        
        
    }
    
    
    
    /**
     * Close application controller
     * 
     * @return null
     */
    public function stop()
    {
        // event log
        PhProxy::event('User wanna close the app!');
        
        // send terminated command to API
        $added = PhProxy::factory('client')->new_query_to_api(
            PhProxy::api_make('logout', array('time' => time())), 
            function(&$cnx) {

                if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok
                
                    // hide window (i want to die alone, please)
                    PhProxy::factory('gui')->visible(0);
                    PhProxy::event('Application terminated!'); 
                    exit;

                } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted

                    PhProxy::warn('Cant connect to API #1!');

                } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open

                    PhProxy::warn('Cant connect to API #2!');

                }
            }
        );
        
        
    }
    
    
    /**
     * Auth page - register hyper-link controller
     * 
     * @todo Register without jumping to site
     * @return type 
     */
    public function register()
    {
        // show warning about launching Browser
        if (PhProxy::mbox($this->lang->get('auth', 'RegString2'), $this->lang->get('auth', 'RegString1'), WBC_YESNO)) {
            
            PhProxy::event('User go to register');
            return PhProxy::exec(base64_decode($this->cfg->get('phproxy', 'register_link')));
            
        } 
     
        // it's so so ... so BAD!
        PhProxy::event('User abort go to register');
        PhProxy::mbox($this->lang->get('auth', 'RegString4'), $this->lang->get('auth', 'RegString3'), WBC_INFO);
        return false;  
    }
    
    
    /**
     * Auto-complete auth form for Guest access
     * 
     */
    public function guest()
    {
        // get lang inst
        #$cfg = PhProxy::getInstance('cfg');
        
        // get auth data
        #$login = $cfg->get('phproxy', 'guest_login');
        #$pass = $cfg->get('phproxy', 'guest_pass');  
    }
    
    
    /**
     * Auth page - auth controller
     * 
     * @return bool
     */
    public function auth($data = false)
    {
        // [Gui Object] and [Lang Object] and [Socket Client Object]
        $gui = $this->gui; $lang = $this->lang; $client = $this->client;
        
        PhProxy::event('Auth method was called!');
        
        // First stage - parse data and send request to api server
        if ($data == false) {
            
            PhProxy::event('Parsing authdata...');
            
                // get login from form
                $login = $gui->c_text_get(ID_INPUT_AUTH_LOGIN);
                    if (empty($login)) { // empty login
                        PhProxy::mbox($lang->get('auth', 'error1'), 'PhProxy - '.$lang->get('auth', 'error1T'), WBC_INFO);
                        return $gui->c_focus(ID_INPUT_AUTH_LOGIN);
                    }
                // utf to cp1251
                $login = PhProxy::utf2cp($login);
                    if (strlen($login) < 2 OR strlen($login) > 25) {
                        PhProxy::mbox($lang->get('auth', 'error2'), 'PhProxy - '.$lang->get('auth', 'error2T'), WBC_INFO);
                        return $gui->c_focus(ID_INPUT_AUTH_LOGIN);
                    }

                // get pass from form
                $pass = $gui->c_text_get(ID_INPUT_AUTH_PASS);
                    if (empty($pass)) { // empty pass
                        PhProxy::mbox($lang->get('auth', 'error3'), 'PhProxy - '.$lang->get('auth', 'error3T'), WBC_INFO);
                        return $gui->c_focus(ID_INPUT_AUTH_PASS);
                    }
                // utf to cp1251
                $pass = PhProxy::utf2cp($pass);
                    if (strlen($pass) < 4 OR strlen($pass) > 25) {
                        PhProxy::mbox($lang->get('auth', 'error4'), 'PhProxy - '.$lang->get('auth', 'error4T'), WBC_INFO);
                        return $gui->c_focus(ID_INPUT_AUTH_PASS);
                    }     
                
            PhProxy::event('Sending authdata to the server!'); 
            
            // make a query and add to tasks
            $added = $client->new_query_to_api(
                PhProxy::api_make('auth', array('login' => base64_encode($login), 'pass' => base64_encode($pass))),
                function(&$cnx) {
                    if ($cnx['state'] == SOCKET_CLIENT_CLOSING) { // ok
                        
                        PhProxy::factory()->auth($cnx['response']);   
                        
                    } elseif ($cnx['state'] == SOCKET_CLIENT_TIMEOUTED) { // timeouted
                        
                        PhProxy::factory()->auth('$TIMEOUT$');       

                        
                    } elseif ($cnx['state'] == SOCKET_CLIENT_NOT_OPENED) { // can't open

                        PhProxy::factory()->auth('$CANT_CONNECT$');
                        
                    }
                }
            );
            
            // if auth query was rejected
            if (!$added) {  
                PhProxy::warn($this->lang->get('auth', 'error5')); 
                return false;
            }
            
            // lock GUI ELEMENTS
            $gui->c_enabled(ID_BUTTON_AUTH_IN,   false);
            $gui->c_enabled(ID_INPUT_AUTH_LOGIN, false);
            $gui->c_enabled(ID_INPUT_AUTH_PASS,  false);
            
            // change text of button
            $gui->c_text_set(ID_BUTTON_AUTH_IN, $lang->get('auth', 'string1'));
            $gui->c_visible(ID_BUTTON_AUTH_NO, 0);
            $gui->c_size(ID_BUTTON_AUTH_IN, 140, 24);
            $gui->c_position(ID_BUTTON_AUTH_IN, 80, 220);
            
                
            return true;
            
            
        // request sended, response recieved
        } elseif (is_string($data)) {
            
            // socket errors handler
            if ($data == '$TIMEOUT$') {
                
                PhProxy::warn('Connection timeout!', $lang->get('auth', 'String2'));
                return $this->auth(1);
                
            } elseif ($data == '$CANT_CONNECT$') {
                
                PhProxy::warn('Cant connect to remote!', $lang->get('auth', 'String2'));
                return $this->auth(1);
                
            }
            
            // parse response
            $error = null;
            $resp = PhProxy::api_parse($data, $error);
            
                // api parsing error
                if ($resp == false) {
                    
                    PhProxy::warn($error, $lang->get('auth', 'String2'));
                    return $this->auth(1);
                    
                }
            
            
                // autherization error
                if ($resp['state'] == 'error') {

                    PhProxy::warn(PhProxy::cp2utf($resp['content']), $lang->get('auth', 'String2'));
                    return $this->auth(1);
                    
                } // else - OK
                
                
            // unserialize an array with auth data 
            $adata = unserialize($resp['content']);
                if (!$adata or !is_array($adata)) {
                    
                    PhProxy::warn($lang->get('auth', 'String3'), $lang->get('auth', 'String2'));
                    var_dump($resp['content']);
                    return $this->auth(1);
                    
                }
           
 
            // set authkey
            PhProxy::profile_set($adata);
            
            // rebuild GUI from auth to Main
            $this->_gui_rebuild('main', 'auth');
            
            return true;
        
            
        // return GUI to normal state after error    
        } else {
            
            // lock GUI ELEMENTS
            $gui->c_enabled(ID_BUTTON_AUTH_IN,   true);
            $gui->c_enabled(ID_INPUT_AUTH_LOGIN, true);
            $gui->c_enabled(ID_INPUT_AUTH_PASS,  true);
            
            // change text of button
            $gui->c_text_set(ID_BUTTON_AUTH_IN, $lang->get('gui.auth', 'String4'));
            
            $gui->c_size(ID_BUTTON_AUTH_IN, 100, 24);
            $gui->c_position(ID_BUTTON_AUTH_IN, 50, 220);
            $gui->c_visible(ID_BUTTON_AUTH_NO, 1);
            
            
            return true;
        }
    }
   

    /**
     * Rebuild main window to another states
     * 
     * @param string $new new keyword state
     * @param type $old old keywork state
     * @return null
     */
    protected function _gui_rebuild($new, $old)
    {
        // short links
        $gui = $this->gui; $lang = $this->lang;
        $gui->visible(0); // hide window
        
        
        // Delete old controlls
       if ($old == 'auth') {
            
            // hide and destroy all controlls
            $gui->c_visible(ID_INPUT_AUTH_LOGIN, 0); $gui->c_destroy(ID_INPUT_AUTH_LOGIN);
            $gui->c_visible(ID_INPUT_AUTH_PASS, 0); $gui->c_destroy(ID_INPUT_AUTH_PASS);
            
            // labels
            $gui->c_visible(ID_LABEL_AUTH_LOGIN, 0); $gui->c_destroy(ID_LABEL_AUTH_LOGIN);
            $gui->c_visible(ID_LABEL_AUTH_PASS, 0); $gui->c_destroy(ID_LABEL_AUTH_PASS);
            
            // buttons and hypers
            $gui->c_visible(ID_BUTTON_AUTH_IN, 0); $gui->c_destroy(ID_BUTTON_AUTH_IN);
            $gui->c_visible(ID_BUTTON_AUTH_NO, 0); $gui->c_destroy(ID_BUTTON_AUTH_NO);
            $gui->c_visible(ID_HYPER_FORUM, 0); $gui->c_destroy(ID_HYPER_FORUM);
            
        }
        
        
        
        // Build new window
        if ($new == 'auth') {
            
            // set size and central position
            $gui->size(MAINWIN_AUTH_W, MAINWIN_AUTH_H);
            $gui->position(WBC_CENTER, WBC_CENTER);
            
            // set title
            $gui->title(PhProxy::version($lang->get('gui.auth', 'String0')) . ' - ' . $lang->get('gui.auth', 'String1'));

            // create required fonts if is not exists
            $gui->font_new('Tahoma', 16, BLACK, FTA_BOLD, ID_FONT_AUTH_INPUT);
            $gui->font_new('Tahoma', 8,  BLACK, FTA_BOLD, ID_FONT_AUTH_LABEL);
                
                // set target for created controlls - window,0
                $gui->set_target($gui->get_wobj(), 0);
            
                // unpack image from exe and write in temp dir
                PhProxy::file_save($name = PHPROXY_HOME_TEMP.md5(microtime(1)).'.bmp', PhProxy::file_load(PhProxy::path(PHPROXY_RHOME_IMAGES . 'main_logo_1.bmp')));
                $gui->image($name, array(0, 0), array(600, 100), ID_IMAGE_LOGO);
                PhProxy::file_delete($name);
 
                // login & pass inputs
                $gui->input('', array(10, 122), array(290, 32), ID_INPUT_AUTH_LOGIN, null,       ID_FONT_AUTH_INPUT);
                $gui->input('', array(10, 177), array(290, 32), ID_INPUT_AUTH_PASS,  WBC_MASKED, ID_FONT_AUTH_INPUT);
            
                // input's labels
                $gui->label($lang->get('gui.auth', 'String2'), array(10, 105), array(290, 16), ID_LABEL_AUTH_LOGIN, 0, ID_FONT_AUTH_LABEL);
                $gui->label($lang->get('gui.auth', 'String3'), array(10, 160), array(290, 16), ID_LABEL_AUTH_PASS, 0,  ID_FONT_AUTH_LABEL);

                // login button
                $gui->button($lang->get('gui.auth', 'String4'), array(50, 220), array(100, 24), ID_BUTTON_AUTH_IN, WBC_ELLIPSIS, ID_FONT_AUTH_LABEL);
                $gui->button($lang->get('gui.auth', 'String6'), array(155, 220), array(100, 24), ID_BUTTON_AUTH_NO, 0, ID_FONT_AUTH_LABEL);
                
                // statusbar
                $gui->statusBar(array(array(PhProxy::version('%an%/%avj%.%avn%.%avb% %avs% (%avd%)'), 200), array(PhProxy::version(' http://pproxy.ru '))));

                // hyperlink
                $gui->hyperlink($lang->get('gui.auth', 'String5'), array(100, 250), array(115, 20), ID_HYPER_FORUM, WBC_LINES, 0xFF8000, 0);

           $gui->c_enabled(ID_BUTTON_AUTH_NO, 0);     
                
            // show window and start main loop
            $gui->visible(1);
            $gui->c_focus(ID_INPUT_AUTH_LOGIN);
            $gui->loop();

            return true; // dead code :)
             
        } elseif ($new == 'main') {
          
            // get authdata
            $login = PhProxy::cp2utf(PhProxy::profile_get('login'));
            $is_vip = (int)PhProxy::profile_get('is_vip');
            $group = (int)PhProxy::profile_get('group');
            $group_exp = PhProxy::profile_get('group_expire');
            
                if ($is_vip) {
                    $added = $lang->get('main', 'string3').' '.date('d.m.Y', $group_exp);
                } else {
                    $added = $lang->get('main', 'string2');
                }
            
            // set title
            $gui->title($login . ' ['.$added.']'. ' - ' . PhProxy::version(  $lang->get('gui.auth', 'String0') ));
            

            #$gui->label($lang->get('main', 'string1'), array(10, 102), array(75, 16), 0, 0);
            $gui->label($lang->get('main', 'string1'), array(60, 110), array(220, 80), 0, 0,  ID_FONT_AUTH_INPUT);
            $gui->label(PhProxy::cp2utf('(ÑÒÐÀÍÈÖÀ Â ÐÀÇÐÀÁÎÒÊÅ)'), array(60, 210), array(290, 16), ID_LABEL_AUTH_PASS, 0,  ID_FONT_AUTH_LABEL);
            
            
            // show window and start main loop
            $gui->visible(1); $gui->loop();

            return true; // dead code :)
  
        }
        
        return false;
    }
    
  
}


?>