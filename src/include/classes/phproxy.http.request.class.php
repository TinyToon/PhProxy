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
 * @todo      PhpDocs
 **/

/**
 * PhProxy HTTP_Request Parser/Generator
 */
class PhProxy_HTTP_Request {
    
    // work mode (1 - parser, 0 - generator)
    private $_mode = 0;
    
    // raw request text, and length
    private $_raw = '';
    private $_raw_len = 0;
    
    // raw body, raw head
    private $_raw_body, $_raw_head = '';
    
    // raw first line of request
    private $_raw_status_line = '';
    
    private $_headers = array();
    
    // HTTP method
    private $_method = 'GET';
    
    // HTTP URI
    private $_uri = '/';
    
    // HTTP version
    private $_version = '1.1';
    
    // request HOST and PORT
    private $_host = '';
    private $_port = 80;
    
    
    // eol
    private $_eol = "\r\n";
    
    // last error
    private $_error_code = 0;
    private $_error = 'null';
    
    
    
    // construct
    public function __construct($raw = false, $uri = false)
    {
        // mode - HTTP_Parser
        if (is_string($raw) && $uri == false) { 
            
            $this->_mode = 1;
            $this->_parse($raw);
            
        } else { // mode - HTTP_Generator
            
            // mode - generator
            $this->_mode = 0;
            
            // first line data
            $this->_method = $raw;
            $this->_uri = $uri;
            $this->_version = '1.1';
            
            // set default useragent
            $this->header_add('User-Agent', PhProxy::version());

        }
        
        return true;
    }
    
    // destroy object
    public function destroy()
    {
        unset($this);
    }
    
    // return error-state
    public function error()
    {
        return $this->_error_code;
    }
    
    // return text of error
    public function error_get()
    {
        return $this->_error;
    }
    
    // get method from request
    public function method_get()
    {
        return $this->_method;
    }
    
    // get host
    public function host_get()
    {
        return $this->_host;
    }
    
    // get port
    public function port_get()
    {
        return $this->_port;
    }
    
    // get uri
    public function uri_get()
    {
        return $this->_uri;
    }
          
    // add header (existed will be overwritten)
    public function header_add($name, $value)
    {
        return $this->_headers[$name] = $value;
    }
    
    // remove header
    public function header_rm($name)
    {
        if (!isset($this->_headers[$name])) {
            return false;
        }
        unset($this->_headers[$name]);
        return true;
    }
    
    // set body
    public function body_set($raw)
    {
        $this->_raw_body = $raw;
    }

    // build request
    public function build()
    {
        // add content length in gen-mode
        if ($this->_mode == 0) {
            if (!isset($this->_headers['Content-Length'])) {
                 $this->header_add('Content-Length', strlen($this->_raw_body));
            }
        }

        // first line
        $return = $this->_method.' '.$this->_uri.' HTTP/'.$this->_version.$this->_eol;

            // add all headers
            foreach ($this->_headers as $header => $value)
            {
                $return .= $header.': '.$value.$this->_eol;
            }
 
        // final eol
        $return .= $this->_eol;

        // add body
        $return .= $this->_raw_body;

        return $return;
    }
  
    
# -------------------------------------------------------- >> Private Methods
    
    /**
     * Parse raw HTTP request to parts
     * @param type $raw
     * @return type 
     */
    private function _parse($raw)
    {
        // set raw text and raw length
        $this->_raw = $raw;
        $this->_raw_len = strlen($this->_raw);

            // explode on headers and body
            if (strpos($this->_raw, $this->_eol.$this->_eol) === false) {
                $this->_error_code = 1; 
                $this->_error = 'HTTP request must have separator between body and headers!';
                return false;
            }

        // exploding
        list($this->_raw_head, $this->_raw_body) = @explode($this->_eol.$this->_eol, $this->_raw, 2);

        // parse head to method, path, proto, headers and etc.
        $ret = $this->_parse_head($this->_raw_head);
            if ($ret === false) {
                return false;
            }
        $this->_headers = $ret;
        
            // parse Host header
            if (!isset($this->_headers['Host'])) {
                $this->_error_code = 3; 
                $this->_error = 'Please, set "Host:" header!';
                return false;
            }
        
        $host = $this->_headers['Host'];
        
            // check port
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host, 2);
            } else {
                $port = 80;
            }
        
        // set host and port
        $this->_host = $host; $this->_port = $port;

        return true;    
    }
    

    /**
     * Parsing HTTP request head to parts
     * 
     * @param string $head
     * @return mixed 
     */
    private function _parse_head($head)
    {       
        // parsing headers
        if (strpos($head, $this->_eol) === false) {
            $hh = array($head);
        } else {
            $hh = explode($this->_eol, $head);
        }
        
        // return array
        $ret = array(); 
        
        foreach ($hh as $num => $h)
        {
            if ($num == 0) { // first line
                
                $this->_raw_status_line = $h;
                preg_match('/(GET|POST|CONNECT|HEAD|OPTIONS)\s([^\s]+)\sHTTP\/([0-9\.]+)/i', $h, $arr);
                    if ($arr == false) { 
                        $this->_error_code = 2; 
                        $this->_error = 'Cannot parse first line of headers.';
                        PhProxy::event('Cannot parse first line of request: ['.$h.']');
                        return false;
                    }
                    
                // set request data
                $this->_method = $arr[1];
                $this->_uri = $arr[2];
                $this->_version = $arr[3];
                
                
                // uri to RFC
                if (strpos($this->_uri, 'http://') == 0) {
                    
                    
                    $tmp = explode('/', substr($this->_uri, 7), 2);
                        if (isset($tmp[1])) {
                            $this->_uri = '/'.$tmp[1];
                        } else {
                            $this->_uri = '/';
                        }
 
                    
                } elseif (strpos($this->_uri, 'https://') == 0) {
                    
                    $tmp = @explode('/', substr($this->_uri, 8), 2);
                        if (isset($tmp[1])) {
                            $this->_uri = '/'.$tmp[1];
                        } else {
                            $this->_uri = '/';
                        }
                    
                }
                
                
                
                continue;
                
            } else {
                
                // unkown format
                if (strpos($h, ": ") === false) {
                    PhProxy::event('HTTP request error parse: not found ":" on line '.$num);
                    continue;
                }
                
                // split
                list($name, $val) = explode(': ', $h, 2);
                $ret[trim($name)] = trim($val);  
            }  
        }
        
        return $ret;
    }
    
 
    
    
    
}
// build response
        /*
        $return = 'HTTP/'.$this->_version_http.' '.$this->status_code.' '.$this->status_codes[$this->status_code].$this->end;

        $this->addHeader('Content-Length', strlen($this->raw_body));
        $this->addHeader('Date', date('r'));

            foreach ($this->headers as $name => $value)
            {
                $return .= $name.': '.$value.$this->end;
            }

        $return .= $this->end;
        $return .= $this->raw_body;

        return $return;
        */
/**
 * PhProxy HTTP
 */
/*
class PhProxy_HTTP {


    private $status_code = 0;


    // return body
    public function getBody()
    {
        if ($this->error) {
            return false;
        }
        return $this->raw_body;
    }

    // set body
    public function setBody($text)
    {
        $this->raw_body = $text;
    }

    // add to body
    public function addBody($text)
    {
        $this->raw_body .= $text;
    }

    // replace string in body
    public function replaceBody($str1, $str2)
    {
        $this->raw_body = str_replace($str1, $str2, $this->raw_body);
    }


}
 * 
 */





?>