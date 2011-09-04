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

    /*
    100 Continue (Продолжать).
    101 Switching Protocols (Переключение протоколов).
    102 Processing (Идёт обработка).

    200 OK (Хорошо).
    201 Created (Создано).
    202 Accepted (Принято).
    203 Non-Authoritative Information (Информация не авторитетна).
    204 No Content (Нет содержимого).
    205 Reset Content (Сбросить содержимое).
    206 Partial Content (Частичное содержимое).
    207 Multi-Status (Многостатусный).
    226 IM Used (IM использовано).

    300 Multiple Choices (Множество выборов).
    301 Moved Permanently (Перемещено окончательно).
    302 Found (Найдено).
    303 See Other (Смотреть другое).
    304 Not Modified (Не изменялось).
    305 Use Proxy (Использовать прокси).
    306 (зарезервировано).
    307 Temporary Redirect (Временное перенаправление).

    400 Bad Request (Плохой запрос).
    401 Unauthorized (Неавторизован).
    402 Payment Required (Необходима оплата).
    403 Forbidden (Запрещено).
    404 Not Found (Не найдено).
    405 Method Not Allowed (Метод не поддерживается).
    406 Not Acceptable (Не приемлемо).
    407 Proxy Authentication Required (Необходима аутентификация прокси).
    408 Request Timeout (Время ожидания истекло).
    409 Conflict (Конфликт).
    410 Gone (Удалён).
    411 Length Required (Необходима длина).
    412 Precondition Failed (Условие «ложно»).
    413 Request Entity Too Large (Размер запроса слишком велик).
    414 Request-URI Too Long (Запрашиваемый URI слишком длинный).
    415 Unsupported Media Type (Неподдерживаемый тип данных).
    416 Requested Range Not Satisfiable (Запрашиваемый диапазон не достижим).
    417 Expectation Failed (Ожидаемое не приемлемо).
    418 I'm a teapot (Я - чайник).
    422 Unprocessable Entity (Необрабатываемый экземпляр).
    423 Locked (Заблокировано).
    424 Failed Dependency (Невыполненная зависимость).
    425 Unordered Collection (Неупорядоченный набор).
    426 Upgrade Required (Необходимо обновление).
    449 Retry With (Повторить с...).
    456 Unrecoverable Error (Некорректируемая ошибка...).



    502 Bad Gateway (Плохой шлюз).
    503 Service Unavailable (Сервис недоступен).

    505 HTTP Version Not Supported (Версия HTTP не поддерживается).
    506 Variant Also Negotiates (Вариант тоже согласован).
    507 Insufficient Storage (Переполнение хранилища).
    509 Bandwidth Limit Exceeded (Исчерпана пропускная ширина канала).
    510 Not Extended (Не расширено).
     * 
     */


/**
 * PhProxy HTTP_Response Parser/Generator
 */
class PhProxy_HTTP_Response {
        
    // work mode (1 - parser, 0 - generator)
    private $_mode = 0;
    
    private $_version = '1.0';
    
    private $_code = 200;
    
    // raw body
    private $_raw_body = '';
    private $_replace = array();
    private $_replacement = array();
    
    // eol
    private $_eol = "\r\n";
    
    private $_headers = array();
    
        // last error
    private $_error_code = 0;
    private $_error = 'null';
    
    
    private $_codes = array(
        200 => 'OK',
        
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        504 => 'Gateway Timeout',
        509 => 'Bandwidth Limit Exceeded'
    );
    
    
    // constructor
    public function __construct($code = 404)
    {
        // generator mode
        if (is_int($code)) {
            
            $this->_mode = 0;
            
            // set status code
            $this->_code = $code;
            
            // set server
            $this->header_add('Server', PhProxy::version());
            
            // set date
            $this->header_add('Date', $this->date());

            
        } else {
            
            // set raw data and work mode
            $this->_raw = $code;
            $this->_mode = 1;
            
            $this->_parse($this->_raw);
            
        }
        
        return true;
    }
    
    // destroy object
    public function destroy()
    {
        unset($this);
    }
    
    // return HTTP-valid time
    public function date($time = 0)
    {
        if ($time == 0) {
            $time = time();
        }

        return gmdate("D, d M Y H:i:s", $time)." GMT";
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
    
    // set replace in body
    public function body_replace($it, $to)
    {
        $this->_replace[] = $it; $this->_replacement[] = $to;
    }
     
    // build
    public function build()
    {
        // compile body
        $this->_raw_body = str_replace($this->_replace, $this->_replacement, $this->_raw_body);
        
        // add content length in gen-mode
        if ($this->_mode == 0) {
            if (!isset($this->_headers['Content-Length'])) {
                 $this->header_add('Content-Length', strlen($this->_raw_body));
            }
        }
        
        // status line
        $return = 'HTTP/'.$this->_version.' '. $this->_code.' '. $this->_codes[$this->_code]. $this->_eol;

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
    
    // parsing raw request
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
    
    // parse HTTP head 
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






?>