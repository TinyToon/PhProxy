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
 * @todo      Add PhPDoc
 **/


/**
 * Socket-client state: cant connect to remove
 */
define('SOCKET_CLIENT_NOT_OPENED',  -3);

/**
 * Socket-client state: connection timeouted
 */
define('SOCKET_CLIENT_TIMEOUTED',  -2);

/**
 * Socket-client state: client disconneted, but connection-data already exists - destroy it!
 */
define('SOCKET_CLIENT_DESTROYING', -1);

/**
 * Socket-client state: client not connected yet - now connecting
 */
define('SOCKET_CLIENT_OPENING',     0);

/**
 * Socket-client state: client connected - now request sending
 */
define('SOCKET_CLIENT_SENDING',     1);

/**
 * Socket-client state: data was sended - now reading
 */
define('SOCKET_CLIENT_READING',     2);

/**
 * Socket-client state: data was read - now closing
 */
define('SOCKET_CLIENT_CLOSING',     3);



/**
 * Multi-Threading client socket (non-blocking sockets)
 */
final class PhProxy_Socket_Client {
 
    /**
     * List of opened connections
     * @var array 
     */
    protected $_cnx = array();

    /**
     * Limit of opened connections
     * @var int 
     */
    protected $_cnx_max = 1;

    /**
     * Connection timeout
     * @var int 
     */
    protected $_cnx_timeot = 10;

    /**
     * Read buffer size
     * @var int 
     */
    protected $_read_buffer = 8192;

    /**
     * Write buffer size
     * @todo Dont using now
     * @var int 
     */
    protected $_write_buffer = 0;
    
    /**
     * Try to read this value times while socket has data
     * @var int 
     */
    private $_read_again_max = 5;
    
    /**
     * Save there all hosts
     * 
     * @var array 
     */
    private $_hosts_closed = array();
   
    
// Magic methods
# --------------------------------------------------- >

    
    /**
     * Set params
     * 
     * @param int $cnx_max Max outcoming connections
     * @param int $cnx_timeout Timeout of connection
     * @param int $read_buffer Read buffer size
     * @param int $write_buffer Write buffer size
     * @param int $_read_again_max Readings times per each call
     */
    public function __construct($cnx_max = 1, $cnx_timeout = 10, $read_buffer = 8192, $write_buffer = 8192, $_read_again_max = 1)
    {
        // set params
        $this->_cnx_max = $cnx_max;
        $this->_cnx_timeout = $cnx_timeout;
        $this->_read_buffer = $read_buffer;
        #$this->_write_buffer = $write_buffer; // @TODO Use it
        $this->_read_again_max = $_read_again_max;

        // write debug info
        PhProxy::event(__CLASS__ . ' was init with params:');
        PhProxy::event('-max connections: ' . $cnx_max);
        PhProxy::event('-timeout: ' . $cnx_timeout);
        PhProxy::event('-read buffer: ' . $read_buffer);
        #PhProxy::event('-write buffer: ' . $write_buffer);
        PhProxy::event('-read again max: ' . $_read_again_max);
    }
        
    
// base-methods
# --------------------------------------------------- >
    
    
    /**
     * Send query to API server
     * 
     * @param string $query HTTP query for sending
     * @param closure $handler Function be called for each socket-state change
     * @param int $binded Binded ServerSocket
     * @return bool 
     */
    public function new_query_to_api($query, $handler, $binded = -1)
    { 
        // get instance
        $cfg = PhProxy::getInstance('cfg');
 
        return $this->new_query(
            $cfg->get('phproxy.api', 'host'), 
            (int)$cfg->get('phproxy.api', 'port'), // IP and PORT - addr of API server
            (int)$cfg->get('socket.client', 'open_timeout'), // timeout
            $query, // string for sending
            $handler, // handler
            $binded // binded server-socket
        );
        
    }
    

    /**
     * New client query <br/>
     * Add new task for sending data to some remote addr
     * 
     * @param string $ip Remote ip or host
     * @param int $port Remote port
     * @param int $timeout Connection timeout
     * @param string $data Request for sending
     * @param closure $handler Connection handler
     * @param int $binded_server_sock_id Binded server-socket
     * @return bool 
     */
    public function new_query($ip, $port, $timeout, $data, $handler, $binded_server_sock_id = -1)
    {
        // check limit
        if ($this->_cnx_max <= sizeof($this->_cnx)) {
            PhProxy::warn(
                sprintf(PhProxy::getInstance('lang')->get('socket.client', 'Error1'), $ip.':'.$port)
            ); 
            return false;
        }
        
        // add query in active list
        $this->_cnx[] = array(
            'resource'          => null,
            'state'             => SOCKET_CLIENT_OPENING,
            'timeout'           => microtime(1) + $this->_cnx_timeout,
            'request'           => $data,
            'response'          => '',
            'ip'                => $ip,
            'port'              => $port,
            'tcp_timeout'       => $timeout,
            'handler'           => $handler,
            'binded_socket'     => $binded_server_sock_id
        );
             
        PhProxy::event_net('New client query to '.$ip.':'.$port.' added: {'.strlen($data).' bytes waiting to send}!', 1);
        return true;
    }
      

    /**
     * Client Socket worker <br/>
     * Call for timer
     * 
     * @return bool
     */
    public function timer_worker()
    {
        // if was Fatal Error
        if (defined('PHPROXY_FATAL')) {
            return false;
        }
        
        // for each connect in list
        foreach ($this->_cnx as $key => &$cnx)
        {

            // check global timeout
            if ($cnx['state'] != SOCKET_CLIENT_TIMEOUTED && $cnx['timeout'] < microtime(1)) { // Timeouted
                
                // net debug
                PhProxy::event_net('Client connection to '.$cnx['ip'].':'.$cnx['port'].' was timeouted!', 1);
                $this->_state_change($key, SOCKET_CLIENT_TIMEOUTED);
                continue;
                
            }

                        
            // switch on state
            switch($cnx['state']) {
                
                // connection was timeouted (closing and destroying)
                case(SOCKET_CLIENT_TIMEOUTED):
                    $this->_close($cnx['resource']); $this->_destroy($key);
                    break;
                
                // cant connect to remove
                case(SOCKET_CLIENT_NOT_OPENED):
                    // do nothing ( == SOCKET_CLIENT_DESTROYING)
                    break;
                
                // Opening connection
                case(SOCKET_CLIENT_OPENING): 
                    $cnx['resource'] = $this->_open($cnx['ip'], $cnx['port'], $cnx['tcp_timeout'], $cnx['state'], $key);
                    $this->_state_change($key, ($cnx['resource']) ? SOCKET_CLIENT_SENDING : SOCKET_CLIENT_DESTROYING);
                        // break if opening fail, try to send otherwise
                        if (!$cnx['resource']) { 
                            break;
                        }
                    
                // Reading data
                case(SOCKET_CLIENT_SENDING):
                    $this->_send($cnx['resource'], $cnx['request']);
                    $this->_state_change($key, SOCKET_CLIENT_READING);
                    #break;
                
                // Reading data
                case(SOCKET_CLIENT_READING):
                    $res = $this->_read($cnx['resource'], $this->_read_buffer, $cnx['response']);
                        if ($res) { // return true if reading is end, false otherwise
                             $this->_state_change($key, SOCKET_CLIENT_CLOSING);
                        }
                    break;
                   
                // closing
                case(SOCKET_CLIENT_CLOSING):
                    $this->_close($cnx['resource']);
                    $this->_state_change($key, SOCKET_CLIENT_DESTROYING);
                
                // connection closed - destroy it
                case(SOCKET_CLIENT_DESTROYING):
                    $this->_destroy($key);
                    break;
   
            }

        }
    
        
    }
     
    
    /**
     * Change connection state and run handler
     * 
     * @param id $key ID of connection 
     * @param id $state State
     * @return bool 
     */
    private function _state_change($key, $state)
    {
        // unkown connections
        if (!array_key_exists($key, $this->_cnx)) {
            return false;
        }
        
        // net debug
        PhProxy::event_net('State for client-connection '.$this->_cnx[$key]['resource'].' (#'.$key.') was changed to '.$state, 1);
        
        $this->_cnx[$key]['state'] = $state;
        $this->_cnx[$key]['handler']($this->_cnx[$key]);
        return true;
    }
    
    
    /**
     * Open socket to remove addr and set non-block mode
     * 
     * @param string $host Remote host
     * @param int $port Remote port
     * @param int $timeout Open timeout
     * @param int $state (link) State of connection
     * @param int $key Connection's ID
     * @return bool 
     */
    private function _open($host, $port, $timeout, &$state, $key)
    {
        // @TODO - Add get addr by name (connect to IP) and cache IP addrs
        #$ip = gethostbyname($host);
        #$ip = $host;
         
        // check hosts closed
        $name = $host.':'.$port;
        if (in_array($name, $this->_hosts_closed)) {
            if ($this->_hosts_closed[$name] < microtime(1)) {
                unset($this->_hosts_closed[$name]);
            } else {
                // net debug
                PhProxy::event_net('Client connect to '.$host.':'.$port.' failed! Reason: Host banned!', 1);
                
                // change state
                $this->_state_change($key, SOCKET_CLIENT_NOT_OPENED);
                return false;  
            }
        }
        
        
        
        // connecting
        $err = $errstr = null;
        $res = fsockopen($host, $port, $err, $errstr, $timeout);
            if (!$res) {
                
                // add in black lists
                $this->_hosts_closed[$name] = microtime(1) + 180;
                
                // get socket error and encode to UTF
                $errstr = PhProxy::cp2utf('#'.$err.' - '.$errstr);
                
                // net debug
                PhProxy::event_net('Client connect to '.$host.':'.$port.' failed! Reason: '.$errstr, 1);
                
                // change state
                $this->_state_change($key, SOCKET_CLIENT_NOT_OPENED);
                return false;   
            }
            
        // set non-blocking mode on socket
        stream_set_blocking($res, 0);
        
        // net debug
        PhProxy::event_net('Client connect to '.$host.':'.$port.' was opened! {'.$res.'}', 1);
        return $res;
    }

    
    /**
     * Send some data to socket
     * 
     * @todo - Partiral sending
     * @param resource $cnx Connection resource
     * @param string $data Data for sendings
     * @return int 
     */
    private function _send($cnx, $data)
    { 
        $len = strlen($data);
        
        while (true) {
            $num = fwrite($cnx, $data);
                if ($num < $len) {
                    $data = substr($data, $num);
                    $len -= $num;
                } else {
                    break;
                }
        }
        
        
        
        // net debug
        PhProxy::event_net($num.' bytes from '.strlen($data).' bytes was sended to client connection '.$cnx, 2);
        
        return $num;
    }   
    
    
    /**
     * Read data from socket
     * 
     * @todo Max response length limit add!
     * @param resource $cnx
     * @param int $rBuffer
     * @param string $response
     * @return bool 
     */
    private function _read($cnx, $rBuffer, &$response)
    {
        // counter
        $readings = 0;
        
        
        // cycle reading
        do {
            
            // read buffer-size bytes from socket
            $buf = fread($cnx, $rBuffer); $readings++;
            
                // return false
                if (!$buf) {
                    
                    // end of file
                    if (feof($cnx)) {
                        
                        PhProxy::event_net('Finally '.strlen($response).' bytes was read from client socket '.$cnx, 2);
                        return true;
                        
                    } // else
                        
                    PhProxy::event_net('Nothing to read from client socket '.$cnx, 3);
                    return false;
 
                } else {
   
                    // add 
                    $response .= $buf;
                    $real_buf_len = strlen($buf);
                    
                    // net debug
                    PhProxy::event_net($real_buf_len.' bytes was read from client socket '.$cnx .' for #'.$readings, 2);
                        
                        // maybe we can read another data
                        if ($real_buf_len == $rBuffer) {
                            if ($readings < $this->_read_again_max) {
                                
                                // Net Debug
                                PhProxy::event_net('Go to read again ('.$readings.' was) in CLIENT socket', 3);
                                
                                continue;
                                
                            } else {
                                
                                return false; // it's not all in socket
                                
                            }
                            
                        } else { // was read smaller then buffer size
                            
                            // end of file
                            if (feof($cnx)) {

                                PhProxy::event_net('Finally '.strlen($response).' bytes was read from client socket '.$cnx, 1);
                                return true;

                            } // else


                            return false;
                            
                        }
  
                }

        } while(1);
    
        
    }
    
    
    /**
     * Close connection
     * 
     * @param resource $cnx
     * @return bool 
     */
    private function _close($cnx)
    {
        PhProxy::event_net('Client connection {'.$cnx.'} was closed!', 2);
        return @fclose($cnx);
    }

    
    /**
     * Destroy all data
     * 
     * @param int $key 
     */
    private function _destroy($key)
    {
        PhProxy::event_net('Connection {'.$this->_cnx[$key]['resource'].' (#'.$key.')} was destroyed!', 1);
        unset($this->_cnx[$key]);
        return true;
    }

    
    
    
}


?>