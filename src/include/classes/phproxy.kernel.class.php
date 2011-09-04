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
 * @link      http://github.com/Shcneider/PhProxy (sources, binares)
 * @link      http://vk.shcneider.in/forum (binares, support)
 * @link      http://alex.shcneider.in/ (author)
 **/




// PhProxy Controller
function PhProxy_Controller($win, $id, $con = 0, $param1 = 0, $param2 = 0)
{
    $phproxy = PhProxy::getInstance();
    
    // select widnow
    switch ($win) {
        
        // unkown window
        default: PhProxy::event('GUI-EVENT: Unkown window ID - ' . $win);
            break;

        // main window
        case PHPROXY_MAINWIN_ID: $phproxy->gui->c_handler_invoke($id, $con, $param1, $param2);
            break;
    }
    return true;
}



/**
 * PhProxy Kernel
 */
final class PhProxy_Kernel {
    
    // internal classes
    public $cfg = null;
    public $gui = null;
    public $lang = null;
    public $client = null;
    public $server = null;
    
// --------------------------------------------------- >    
    
    // Construct
    public function __construct($options)
    {
        // load config file
        $this->cfg = new PhProxy_Config(PHPROXY_HOME . 'phproxy.ini');
        
        // get current lang from config and load it
        $lang = preg_replace('/[^a-z]/i', '', $this->cfg->get('phproxy', 'language'));
            if (!file_exists(PHPROXY_HOME_LANGS . $lang . '.ini')) {
                PhProxy::fatal('Cannot find localization file: '.$lang.'.ini');
            }
        $this->lang = new PhProxy_Language(PHPROXY_HOME_LANGS . $lang . '.ini');
               
        // Try write in root dir
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
                if (!@mkdir(PHPROXY_HOME_LOGS, 0777, true)) {
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
                    PhProxy::fatal('Cannot make a temp folder!');
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
        require_once PhProxy::path(PHPROXY_RHOME_INC .  'phproxy.gui.controlls.php');
            if (!defined('PHPROXY_WB_LOADED')) {
               PhProxy::fatal(
                   $this->lang->get('bootstrap', 'error4')
               );
            } elseif(PHPROXY_DEBUG) {
               PhProxy::event('WB files loaded!');
            }    
        
        // double-launch protect (2.0.5 ++)
        define('PHPROXY_WIN_MAIN_TITLE', PhProxy::version());
            if (wb_get_instance(PHPROXY_WIN_MAIN_TITLE, true)) {
                PhProxy::warn($this->lang->get('bootstrap', 'error5')); die();
            } 
         
        // create instance of server socket's class
        $this->socket = new PhProxy_Socket_Server(
            $this->cfg->get('socket.server', 'listen_ip'), 
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
    
    // start PhProxy
    public function run()
    {         
        // GUI start - create main-window
        $gui = $this->gui = new PhProxy_GUI(PHPROXY_WIN_MAIN_TITLE, AppWindow, WBC_TASKBAR | WBC_INVISIBLE | WBC_TOP);
        
            // set-up window
            $gui->title(PHPROXY_WIN_MAIN_TITLE);
            $gui->position(WBC_CENTER, WBC_CENTER);
            $gui->size(MAINWIN_AUTH_W, MAINWIN_AUTH_H);
            
            // @TODO - Do it pretty!
            // unpack image from exe and write in temp dir
            $image = PhProxy::file_load(PhProxy::path(PHPROXY_RHOME_IMAGES . 'main_icon_1.ico'));
            $name = PHPROXY_HOME_TEMP.md5(microtime(1)).'.ico';
            file_put_contents($name, $image);
            
                $gui->icon($name);
            
            unlink($name);
            
        
        // get window ID and set global constant
        define('PHPROXY_MAINWIN_ID', $gui->get_wobj());
               
        // set window-handler
        $gui->handler_set('PhProxy_Controller');
        
        
        // set handlers for some controls
            $gui->c_handler_set(IDCLOSE, function() { // user wanna close app
               PhProxy::getInstance()->stop();
            });
            $gui->c_handler_set(ID_HYPER_FORUM, function() { // register
                #PhProxy::getInstance()->register();
            });
            $gui->c_handler_set(ID_BUTTON_AUTH_IN, function() { // auth
                PhProxy::getInstance()->auth();
            });
            $gui->c_handler_set(ID_TIMER_NET_CLIENT, function() { // client-socket-timer
                PhProxy::getInstance()->client->timer_worker();
            });
            $gui->c_handler_set(ID_TIMER_NET_SERVER, function() { // server-socket-timer
                PhProxy::getInstance()->socket->timer_worker();
            });
        
        // add system's timers
        $gui->timer(ID_TIMER_NET_SERVER, (int)$this->cfg->get('phproxy', 'server_timer_int')); 
        $gui->timer(ID_TIMER_NET_CLIENT, (int)$this->cfg->get('phproxy', 'client_timer_int'));    
                   
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
    
    // user want to close app
    public function stop()
    {
        PhProxy::event('User close app!');
        
        // hide window (i want to die alone, please)
        $this->gui->visible(0);

        exit;
    }
    
    // user autherization start
    public function auth()
    {
        return true;
    }
   
    // build new window and re-build last
    protected function _gui_rebuild($new, $old)
    {
        // short links
        $gui = $this->gui; $lang = $this->lang;
                
        // hide window
        $gui->visible(0);
        
        
        // Delete old controlls
        if ($old == 'empty') {
            // do nothing
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
            
                // @TODO - Do it pretty!
                // unpack image from exe and write in temp dir
                $image = PhProxy::file_load(PhProxy::path(PHPROXY_RHOME_IMAGES . 'main_logo_1.bmp'));
                $name = PHPROXY_HOME_TEMP.md5(microtime(1)).'.bmp';
                file_put_contents($name, $image);

                    // logotype
                    $gui->image($name, array(0, 0), array(600, 100), ID_IMAGE_LOGO);
                
                unlink($name);
                

                // login & pass inputs
                $gui->input('', array(10, 122), array(290, 32), ID_INPUT_AUTH_LOGIN, null,       ID_FONT_AUTH_INPUT);
                $gui->input('', array(10, 177), array(290, 32), ID_INPUT_AUTH_PASS,  WBC_MASKED, ID_FONT_AUTH_INPUT);
            
                // input's labels
                $gui->label($lang->get('gui.auth', 'String2'), array(10, 105), array(290, 16), ID_LABEL_AUTH_LOGIN, 0, ID_FONT_AUTH_LABEL);
                $gui->label($lang->get('gui.auth', 'String3'), array(10, 160), array(290, 16), ID_LABEL_AUTH_PASS, 0,  ID_FONT_AUTH_LABEL);

                // login button
                $gui->button($lang->get('gui.auth', 'String4'), array(80, 220), array(140, 24), ID_BUTTON_AUTH_IN, 0, ID_FONT_AUTH_LABEL);
                
                // statusbar
                $gui->statusBar(array(array(PhProxy::version('%an%/%avj%.%avn%.%avb% %avs% (%avd%)'), 170), array(PhProxy::version('   http://vk.shcneider.in '))));

                // hyperlink
                $gui->hyperlink($lang->get('gui.auth', 'String5'), array(95, 250), array(115, 20), ID_HYPER_FORUM, WBC_LINES, 0xFF8000, 0);

            // show window and start main loop
            $gui->visible(1);
            $gui->focus(ID_INPUT_AUTH_LOGIN);
            
            PhProxy::warn($lang->get('gui.auth', 'Warn1'));
            $gui->loop();

            return true; // dead code :)
             
        }
        
        return false;
    }
    
  
}


?>