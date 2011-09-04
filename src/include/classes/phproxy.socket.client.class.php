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


// connection was timeouted
define('SOCKET_CLIENT_NOT_OPENED',  -3);

// connection was timeouted
define('SOCKET_CLIENT_TIMEOUTED',  -2);

// client disconneted, but connection-data already exists - destroy it!
define('SOCKET_CLIENT_DESTROYING', -1);

// client not connected yet - now connecting
define('SOCKET_CLIENT_OPENING',     0);

// client connected - now request
define('SOCKET_CLIENT_SENDING',     1);

// data was sended - now reading
define('SOCKET_CLIENT_READING',     2);

// data was read - now closing
define('SOCKET_CLIENT_CLOSING',     3);


/**
 * 
 * Multi-Threading client socket
 * (non-blocking sockets)
 *
 */
final class PhProxy_Socket_Client {
 
    // opened connections
    protected $_cnx = array();

    // max outcoming
    protected $_cnx_max = 1;

    // cnx timeout
    protected $_cnx_timeot = 10;

    // read buffer
    protected $_read_buffer = 8192;

    // read buffer
    protected $_write_buffer = 0;
    
    // read try again times
    private $_read_again_max = 5;
    

    // set params
    public function __construct($cnx_max = 1, $cnx_timeout = 10, $read_buffer = 8192, $write_buffer = 8192, $_read_again_max = 1)
    {
        // max connections
        $this->_cnx_max = $cnx_max;
        // timeout for cnx
        $this->_cnx_timeout = $cnx_timeout;
        // read buffer 
        $this->_read_buffer = $read_buffer;
        // write buffer
        $this->_write_buffer = $write_buffer;
        // max read again
        $this->_read_again_max = $_read_again_max;
        

        // debug
        PhProxy::event(__CLASS__ . ' was init with params:');
        PhProxy::event('max outcoming connections: ' . $cnx_max);
        PhProxy::event('timeout: ' . $cnx_timeout);
        PhProxy::event('read buffer: ' . $read_buffer);
        PhProxy::event('write buffer: ' . $write_buffer);
        PhProxy::event('Read again max: ' . $this->_read_again_max);
    }
        
    
// base-methods
# --------------------------------------------------- >
    
    // new Query for sending to API
    public function new_query_to_api($query, $handler, $binded = -1)
    { 
        // get instance
        $phproxy = PhProxy::getInstance();
        
        // Get params from config file
        $api_port = (int)$phproxy->cfg->get('phproxy.api', 'port');
        $api_host = $phproxy->cfg->get('phproxy.api', 'host');
        $open_timeout = (int)$phproxy->cfg->get('socket.client', 'open_timeout');
        
        return $this->new_query(
            // IP and PORT - addr of API server
            $api_host, $api_port,
            // timeout
            (int)$open_timeout,
            // string for sending
            $query,
            // handler
            $handler,
            // binded server-socket
            $binded
        );
    }
    
    
    // new query for sending to anywhere
    public function new_query($ip, $port, $timeout, $data, $handler, $binded_server_sock_id = -1)
    {
        // check limit
        if ($this->_cnx_max <= sizeof($this->_cnx)) {
            PhProxy::warn(
                sprintf(PhProxy::getInstance()->lang->get('socket.client', 'Error1'), $ip.':'.$port)
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
    
    
    // worker
    public function timer_worker()
    {

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

                    break;
                
                // Opening connection
                case(SOCKET_CLIENT_OPENING): 
                    $cnx['resource'] = $this->_open($cnx['ip'], $cnx['port'], $cnx['tcp_timeout'], $cnx['state'], $key);
                    $this->_state_change($key, ($cnx['resource']) ? SOCKET_CLIENT_SENDING : SOCKET_CLIENT_DESTROYING);
                        if (!$cnx['resource']) {
                            break;
                        }
                    //break; 
                    
                // Reading data
                case(SOCKET_CLIENT_SENDING):
                    $this->_send($cnx['resource'], $cnx['request']);
                    $this->_state_change($key, SOCKET_CLIENT_READING);
                    #break;
                
                // Reading data
                case(SOCKET_CLIENT_READING):
                    $res = $this->_read($cnx['resource'], $this->_read_buffer, $cnx['response']);
                        if ($res) {
                             $this->_state_change($key, SOCKET_CLIENT_CLOSING);
                        }
                    break;
                   
                // closing
                case(SOCKET_CLIENT_CLOSING):
                    $this->_close($cnx['resource']);
                    $this->_state_change($key, SOCKET_CLIENT_DESTROYING);
                    #break;
                
                // connection closed - destroy it
                case(SOCKET_CLIENT_DESTROYING):
                    $this->_destroy($key);
                    break;
   
            }

        }
    
        
    }
    
    
    // change state of the socket
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
    
    // open a socket (and set nonblock mode)
    private function _open($host, $port, $timeout, &$state, $key)
    {
        // @TODO - Add get addr by name (connect to IP) and cache IP addrs
        #$ip = gethostbyname($host);
        #$ip = $host;
               
        // connecting
        $err = $errstr = null;
        $res = fsockopen($host, $port, $err, $errstr, $timeout);
            if (!$res) {
                // get socket error
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

    // sends a query into opened socket
    private function _send($cnx, $data)
    { 
        // @TODO - Partiral sending
        $num = fwrite($cnx, $data);
        
        // net debug
        PhProxy::event_net($num.' bytes from '.strlen($data).' bytes was sended to client connection '.$cnx, 2);
        
        return $num;
    }   
    
    // read data from socket
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
                        
                        PhProxy::event_net(strlen($response).' bytes was read from client socket '.$cnx, 2);
                        return true;
                        
                    } // else
                        
                    PhProxy::event_net('Nothing to read from client socket '.$cnx, 3);
                    return false;
 
                } else {
                    
                    // @TODO - Max response length limit add!
                    
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
                                
                                return false; // it's not all
                                
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
    
    // close socket
    private function _close($cnx)
    {
        PhProxy::event_net('Client connection {'.$cnx.'} was closed!', 2);
        return @fclose($cnx);
    }

    // destroy
    private function _destroy($key)
    {
        PhProxy::event_net('Connection {'.$this->_cnx[$key]['resource'].' (#'.$key.')} was destroyed!', 1);
        unset($this->_cnx[$key]);
    }
  
}


?>