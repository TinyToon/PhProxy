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
 * Socket-server state flag: connection was accepted, now is reading
 */
define('SOCKET_STATE_READING',       1);

/**
 * Socket-server state flag: has read, now is parsing request
 */
define('SOCKET_STATE_PARSING',       2);

/**
 * Socket-server state flag: was parsed, now do nothing (wait client-socket state change)
 */
define('SOCKET_STATE_WAITING',       3);

/**
 * Socket-server state flag: response has read, now parsing response
 */
define('SOCKET_STATE_PARSING_RESP',  4);

/**
 * Socket-server state flag: response was parsed, now write response to client
 */
define('SOCKET_STATE_WRITING',       5);

/**
 * Socket-server state flag: response was sended, now closing socket and delete data
 */
define('SOCKET_STATE_CLOSING',       6);

/**
 * Socket-server state flag: was parsed, now write to client and read again (Keep Alive connection)
 */
#define('SOCKET_STATE_WRITING_KA',    7);


/**
 * Server Socket
 * Managing of incoming connections 
 */
class PhProxy_Socket_Server {
  
// --------------------------------------------- >> Default options
    
    /**
     * Default socket type
     * (TCP only)
     * @var string
     */
    private $_sock_type = 'TCP';
    
    /**
     * Binding IP4 addres
     * @var string
     */
    private $_sock_ip = '0.0.0.0';
    
    /**
     * Binding port
     * @var int
     */
    private $_sock_port = 80;
    
    /**
     * Max of TCP-halfopen connections
     * @var int
     */
    private $_sock_max_backlog = 128;

    /**
     * Max incoming connections
     * @var int
     */
    private $_sock_max_connections = 10;

    // socket reading buffer (bytes)
    /**
     * Read buffer (bytes)
     * @var int
     */
    private $_sock_read_buffer = 8192;
    
    /**
     * Readings in one cycle
     * @var int
     */
    private $_sock_read_again_max = 5; 
    
    /**
     * Silence timeout
     * @var int
     */
    private $_timeout_silence = 3;
    
    // timeoutes
    #private $_timeout = 30;
    
// --------------------------------------------- >> RunTime Vars    
    
    /**
     * Listing socket resource
     * @var resource
     */
    private $_socket = null;
    
    /**
     * List of opened connections
     * @var array
     */
    private $_cnx = array();
    
// --------------------------------------------- >> Public methods begin
    
   
    /**
     * Set params of listing socket
     * 
     * @param string $ip
     * @param int $port
     * @return true 
     */
    public function __construct($ip = '0.0.0.0', $port = 80)
    {
        // socket type 
        $this->_sock_type = 'TCP';
        
        // binding options
        $this->_sock_ip   = $ip;
        $this->_sock_port = $port;
        return true;
    }

    
    /**
     * Create TCP socket and start listing
     * 
     * @param int $backlog
     * @param int $max_cnx
     * @param int $rbuf
     * @param int $_sock_read_again_max
     * @param int $_timeout_silence
     * @return int
     */
    public function create($backlog = 128, $max_cnx = 1, $rbuf = 8192, $_sock_read_again_max = 1, $_timeout_silence = 1)
    {       
        // lang link
        $lang = PhProxy::factory('lang');
        
        // config it
        $this->_sock_max_backlog = $backlog;                    // set max half-open connections
        $this->_sock_max_connections = $max_cnx;                // set limit cnx
        $this->_sock_read_buffer = $rbuf;                       // set reading buffer size
        $this->_sock_read_again_max = $_sock_read_again_max;    // max readings
        $this->_timeout_silence = $_timeout_silence;            // silence timeout
            
        // debug
        PhProxy::event('-Max incoming connections: '.$this->_sock_max_connections);
        PhProxy::event('-Reading buffer: ' . $this->_sock_read_buffer . ' bytes');
        PhProxy::event('-Read again max: '.$this->_sock_read_again_max);
        PhProxy::event('-Timeout silence: ' . $this->_timeout_silence . ' seconds');
        

            // created already - need destroy and re-create
            if ($this->_socket) { 
                PhProxy::fatal( 
                    $lang->get('socket.server', 'error1')
                );
            }

        // TCP constant
        if (!defined('SOL_TCP')) { // is not defined

            PhProxy::event('SOL_TCP is not defined!');

            $proto = getprotobyname('tcp');
                if (!$proto || $proto == -1) {
                    PhProxy::event('getprotobyname is failed');
                    
                    // try check registered transports
                    $proto = implode(',', stream_get_transports());
                    PhProxy::event('Registered trasports: '.$proto);
                    
                    // default value for TCP
                    $proto = 6;
                    
                    PhProxy::warn(
                        $lang->get('socket.server', 'warn1')
                    );
                }

            PhProxy::event('Set SOL_TCP!');         
            define('SOL_TCP', $proto);
        }
        
        // creating a socket
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$this->_socket) {
                PhProxy::fatal(
                    sprintf($lang->get('socket.server', 'error2'), $this->_sock_error())
                );
            } else {
                PhProxy::event('Socket AF_INET-SOCK_STREAM-TCP created!');
            }
            
        // bind socket and addr
        $res = socket_bind($this->_socket, $this->_sock_ip, $this->_sock_port);
            if (!$res) {
                PhProxy::fatal(
                    sprintf($lang->get('socket.server', 'error3'), $this->_sock_error($this->_socket))
                );
            } else {
                PhProxy::event('Socket was binded on - '.$this->_sock_ip.':'.$this->_sock_port);
            } 
            
        // set nonblock
        socket_set_nonblock($this->_socket);
        
        
        // start listing
        $res = socket_listen($this->_socket, $this->_sock_max_backlog);
            if (!$res) {
                PhProxy::fatal(
                    sprintf($lang->get('socket.server', 'error4'), $this->_sock_error($this->_socket))
                );
            } else {
                PhProxy::event('Socket listing with backlog: '.$this->_sock_max_backlog);
            }

        PhProxy::event('Socket listening!');    
            
            return true;
    }
    
    
    /**
     * Timer worker
     * 
     * @return bool
     */
    public function timer_worker()
    {  
        // if was Fatal Error - terminated listing
        if (defined('PHPROXY_FATAL')) {
            return false;
        } if (!$this->_socket) {   // not listing yet
            return false;
        }
        

        // foreach all opened connections
        foreach ($this->_cnx as $key => &$cnx)
        {    
            
            // waiting
            if ($cnx['state'] == SOCKET_STATE_WAITING) {
                
                // Net Debug
                PhProxy::event_net('Connection '.$cnx['resource'].' is waiting...', 2);
                continue;
                
            }
                  

            // switch on state
            switch ($cnx['state']) { 
            
                // Connected - reading data
                case(SOCKET_STATE_READING):
                    
                    // Check timeout
                    if ($cnx['timeout_silence'] < microtime(1) && strlen($cnx['request']) == 0) {
                        
                        // Net Debug
                        PhProxy::event_net('Connection '.$cnx['resource'].' was timeouted! (silence)', 2);
                
                        $cnx['request'] = '$TIMEOUT_SILENCE$';
                        $cnx['state']   = SOCKET_STATE_PARSING;
                            break; 
                            
                    }
                    
                    // reading
                    $this->_read($cnx['resource'], $cnx['request'], $cnx['state']);
                        // break only if need read again
                        if ($cnx['state'] != SOCKET_STATE_PARSING) {
                            break;
                        }
                
                    
                // has read, parsing
                case(SOCKET_STATE_PARSING):
                    $cnx['state'] = SOCKET_STATE_WAITING;
                    PhProxy::new_request($key, $cnx);
                    break;
                
                
                // response parsing
                case(SOCKET_STATE_PARSING_RESP):
                    PhProxy::new_response($key, $cnx);
                    #break;
                      
                
                // write answer
                case(SOCKET_STATE_WRITING):
                    $this->_write($cnx['resource'], $cnx['response']);
                    $cnx['state'] = SOCKET_STATE_CLOSING;
                    break;
                
                
                // write answer (in Keep-Alive mode) and reading again
                #case(SOCKET_STATE_WRITING_KA):
                    #$this->_write($cnx['resource'], $cnx['response']);
                    #$cnx['response'] = ''; $cnx['request'] = '';
                    #$cnx['state'] = SOCKET_STATE_READING;
                    #break;
                
                
                // closing and destroying data
                case(SOCKET_STATE_CLOSING):
                    $this->_close($cnx['resource']); $this->_destroy($key);
                    break;
                
            }
        }
        
        // add new connection (if not limited)
        if (sizeof($this->_cnx) < $this->_sock_max_connections) {
            
            // check new incoming connections
            $_n_cnx = @socket_accept($this->_socket);
                if ($_n_cnx) { // new connection
                    
                    // remote port and IP
                    $port = $ip = null; 
                    socket_getpeername($_n_cnx, $ip, $port);
                    
                    // add new connection in opened list
                    $this->_cnx[] = array(
                        'is_tonnel'             => false,
                        'tonnel_to'             => '',
                        'resource'              => $_n_cnx,
                        'state'                 => SOCKET_STATE_READING,
                        'request'               => '',
                        'response'              => '',
                        'ip'                    => $ip,
                        'port'                  => $port,
                        'timeout_silence'       => (microtime(1) + $this->_timeout_silence) 
                    );
                     
                    PhProxy::event_net('New connection '.$_n_cnx.' to server_socket was accepted from: '.$ip.':'.$port, 1);             
                }
                
        } else {
            
            PhProxy::event_net('Incoming limit! New connections not allowed!', 1);      
            
        }
            
        
    }
    

    /**
     * Change state for some opened connection
     * @param int $key
     * @param int $state
     * @param string $data
     * @return bool
     */
    public function state_set_for($key, $state, $data = 'Tro!')
    {
        // not exists
        if (!isset($this->_cnx[$key])) {
            PhProxy::event_net('Try change state for not exists connection!');
            return false;
        }
 
        // change
        $this->_cnx[$key]['state']    = $state;
        $this->_cnx[$key]['response'] = $data;
        
        PhProxy::event_net('State for '.$this->_cnx[$key]['resource'].' changed to #'.$state);
        
        return true;
    }
    
// --------------------------------------------- >> Private methods begin    
    
    /**
     * Return socket error
     * 
     * @param resource $r
     * @return string
     */
    private function _sock_error($r = null)
    {
        if ($r == null) {
            $code = socket_last_error();
        } else {
            $code = socket_last_error($r);
        }
        
        if (!$code) {
            return 'Unkown error';
        }

        return '#'.$code.' - '.PhProxy::cp2utf(socket_strerror($code));
    }
    

    /**
     * Read data from socket
     * @param resource $cnx
     * @param string $request
     * @param int $state
     * @return bool
     */
    private function _read($cnx, &$request, &$state)
    {      
        // readings counter
        $readings = 0;

        do {
            
            // try to read
            $data = socket_read($cnx, $this->_sock_read_buffer, PHP_BINARY_READ); $readings++;

                if (is_string($data)) { // we'd read some string
                    
                    // count read bytes
                    $real_buff_size = strlen($data);
                    
                    // Net Debug
                    PhProxy::event_net('Trying #'.$readings.' - '.$real_buff_size.' bytes was read from socket '.$cnx, 2);
                
                        // client close a connection    
                        if ($real_buff_size == 0) { 

                            // Net Debug
                            PhProxy::event_net('Client '.$cnx.' close a connection!', 2);

                            $state = SOCKET_STATE_CLOSING;  // GOTO Closing socket
                            return true;
                            
                        } // else
                    
                    // add reading to request
                    $request .= $data;
                        
                        // we can read again
                        if ($real_buff_size == $this->_sock_read_buffer) {
                            
                            // check limit
                            if ($readings < $this->_sock_read_again_max) {
                                
                                // Net Debug
                                PhProxy::event_net('Go to read again ('.$readings.' was) in server socket', 3);
                                
                                continue;
                            }
                            
                            return true;
                               
                        } else {
                         
                            // maybe it is all?
                            if (strpos($request, "\r\n\r\n") !== false) {

                                // Net Debug
                                PhProxy::event_net('Finally ' .strlen($request).' bytes was read from socket '.$cnx, 1);

                                $state = SOCKET_STATE_PARSING; 
                                return true;

                            } else {

                                // Net Debug
                                PhProxy::event_net(strlen($request).' bytes was read from socket '.$cnx.' HTTP separator not found!', 2);

                            }
                        }
                        
 
                } else { // return false - error or end

                    // request is not empty
                    if (strlen($request)) {
                        
                        // Net Debug
                        PhProxy::event_net('Finally ' . strlen($request).' bytes was read from socket '.$cnx, 1);
                        
                        $state = SOCKET_STATE_PARSING; 
                        return true;
                        
                    }

                    PhProxy::event_net('Nothing to read in '.$cnx, 3);
                    return true;
                }

        } while (true);
   
    }
         
    
    /**
     * Write  data to socket
     * @param resource $cnx
     * @param string $data
     * @return bool
     * @todo  Partital writing
     */
    private function _write($cnx, $data)
    {
        
        PhProxy::event_net(strlen($data).' bytes need send into socket '.$cnx, 2); 
        
        $len =  strlen($data);
        while (true) {
            $sent = socket_write($cnx, $data, $len);

            if ($sent === false) {
                PhProxy::warn('Sending failed into socket '.$cnx); 
                break;
            }

            // Check if the entire message has been sented
            if ($sent < $len) {

                 // If not sent the entire message.
                 // Get the part of the message that has not yet been sented as message
                 $data = substr($data, $sent);

                 // Get the length of the not sented part
                 $len -= $sent;
                 
                 PhProxy::event_net($len.' bytes was sended into socket '.$cnx, 2); 

            } else {
                 PhProxy::event_net($sent.' bytes was sended into socket '.$cnx, 2); 
                 break;
            }
             
        }
        /*
        $buf_len = 8192;
        
        
         $pos = 0;
            if ($len < $buf_len) {
               return socket_write($cnx, $data, $len); 
            }
        
        while ($pos < $len) {
            $buf = substr($data, $pos, $buf_len); 
                if (socket_write($cnx, $buf, strlen($buf))) {
                    $pos += $buf_len;
                } 
        }    
            
            
        return true;

        */
    }
       
    
    /**
     * Close a opened incoming connections
     * @param resource $cnx
     * @return bool
     */
    private function _close($cnx)
    {
        PhProxy::event_net('Connection '.$cnx.' was closed!', 2);
        return socket_close($cnx);
    }
    
    
    /**
     * Clean data about closed connection
     * 
     * @param int $key
     * @return bool
     */
    private function _destroy($key)
    {
        PhProxy::event_net('Connection '.$this->_cnx[$key]['resource'].' was destroyed!', 1);
        unset($this->_cnx[$key]);
        return true;
    }
    
    
       
}


?>