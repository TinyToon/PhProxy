<?PHP
// +-----------------------------------+
// | Основная логика...                |
// +-----------------------------------+


class PhProxy_HTTP extends PhProxy_Sockets {

# -------------------------------------------------------------------> REQUEST
    /*
     * Запрос, который необходимо распарсить
     */
    private $http_request_raw = NULL;


    /*
     * Только заголовки с запроса
     */
    private $http_request_raw_headers = null;

    /*
     * Только тело с запроса
     */
    private $http_request_raw_body = null;

    /*
     * Первая строка запроса
     */
    private $http_request_start = null;

    /*
     * Массив со всеми заголовками
     */
    private $http_request_headers = array();

    /*
     * Конструктор, установка параметров (+)
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*
     *  Деструктор класса (+)
     */
    public function __destruct()
    {
        parent::__destruct();
    }

     /*
      *  парсим запрос, разбиваем на составляющие (+)
      */
    protected function http_request_parse($data)
    {
        // Запоминаем исходный запрос
        $this->http_request_raw = $data; $arr = array();
        $this->http_request_start = null; $this->http_request_headers = array();

        // РАЗБИВАЕМ НА ШАПКУ И ТЕЛО
        if (strpos($this->http_request_raw, "\r\n\r\n") === false) {
            $this->http_request_raw_headers = trim($this->http_request_raw);
            $this->http_request_raw_body = false;
        } else {
            $tmp = explode("\r\n\r\n", $this->http_request_raw, 2);
            $this->http_request_raw_headers = trim($tmp[0]);
            $this->http_request_raw_body    = trim($tmp[1]);
        }

        // ВЫРЫВАЕМ ВСЕ ЗАГОЛОВКИ
        if (strpos($this->http_request_raw_headers, "\r\n") === false) {
            $arr[] = trim($this->http_request_raw_headers);
        } else {
            $arr = explode("\r\n", $this->http_request_raw_headers);
        }

        // разбиваем заголовки на ИМЯ: ПАРАМЕТР
        foreach ($arr as $str)
        {
            // первая строка
            if (strpos($str, 'GET') === 0 || strpos($str, 'POST') === 0 ) {
                $this->http_request_start = $str; continue;
            } elseif (strpos($str, ':') === false) {
                continue;
            } else {
                list($name, $val) = explode(':', $str, 2);
                $this->http_request_headers[trim($name)] = trim($val);
            }
        } 
        return true;
    }

    /*
     *  выдираем данные ХОСТ, ДОКУМЕНТ, РАСШИРЕНИЕ, МЕТОД (+)
     */
    protected function http_request_check()
    {
        if (!$this->http_request_start || !$this->http_request_header_get('Host')) {
            return false;
        }

        // параметры по умолчанию
        $method = 'GET'; // method
        $host = $this->http_request_header_get('Host'); 
        $path = false; // путь до файла
        $ext = false; // расширение файла

        @preg_match('/(GET|POST)\s([^\s]+)\sHTTP\/([0-9\.]+)/i', $this->http_request_start, $arr);
            if ($arr == false) { // ошибка разбора
                return false;
            }
            
        $method = $arr[1]; $uri = $arr[2];

        // если есть - обрезаем http
        if (strpos($uri, 'http://') === 0) {
            $uri = preg_replace('/^http:\/\//', '', $uri, 1);
        }
        
        // определяем домен/путь
        if (strpos($uri, '/') === false) { // если нет слэша - domain.com
            $host = trim($uri);
        } elseif(strpos($uri, '/') === 0) { // если слеш первый - /docs/file.ext
            $path = substr($uri, 1);
        } else {
            list($host, $path) = explode('/', $uri, 2);
        }
       

        // определяем расширение
        if ($path && strpos($path, '?') !== false) {
            list($d, $vars) = explode('?', $path, 2);
        } else {
            $d = $path;
        }
        if ($d && strpos($d, '.') !== false) {
            $a = explode('.', $d);
            $ext = $a[sizeof($a)-1];
        }

        return array(
            'method' => $method,
            'host' => $host,
            'path' => $path,
            'ext' => $ext
        ); 
    }

    /*
     *  вернуть значение заголовка (+)
     */
    protected function http_request_header_get($name)
    {
        if (isset($this->http_request_headers[$name])) {
            return $this->http_request_headers[$name];
        }
        return false;
    }

    /*
     *  удаляем заголовок (+)
     */
    protected function http_request_header_remove($name)
    {
        if (isset($this->http_request_headers[$name])) {
            unset($this->http_request_headers[$name]);
        }
    }

    /*
     *  добавляем/модифицируем заголовок (+)
     */
    protected function http_request_header_add($name, $value)
    {
        $this->http_request_headers[$name] = trim($value);
    }

    /*
     *  собираем заголовки обратно (+)
     */
    protected function http_request_headers_compile()
    {
        $answer = $this->http_request_start."\r\n";


        // добавляем все заголовки
        foreach ($this->http_request_headers as $h => $v)
        {
            $answer .= $h.': '.$v."\r\n";
        }

        // добавляем тело
        $answer .= "\r\n".$this->http_request_raw_body;
            return $answer;
    }


# ---------------------------------------------------------------------------> RESPONSE
    /*
     * Возможные статусы ответа
     */
    private $http_response_codes = array(
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        408 => 'Request Timeout',
        500 => 'Internal Server Error'
    );
    
    /*
     * Текущий статус ответа
     */
    private $http_response_code = null;
    
    /*
     * Версия протокола
     */
    private $http_response_version = "1.1";

    /*
     * Заголовки ответа
     */
    private $http_response_headers = array();

    /*
     * Тело ответа
     */
    private $http_response_body = '';


    
    /*
     *  формируем новый ответ
     */
    protected function http_response_new($version = '1.1', $code = '200')
    {
        if (!isset($this->http_response_codes[$code])) {
            $code = 500;
        }
        $this->http_response_code = $code;
        $this->http_response_version = $version;
        $this->http_response_headers = array();
        $this->http_response_body = '';
    }

    /*
     *  добавить заголовок (+)
     */
    protected function http_response_header($header, $value)
    {
        $this->http_response_headers[$header] = $value;
    }

    /*
     *  добавить тело сообщения (+)
     */
    public function http_response_body($body)
    {
        $this->http_response_body .= $body;
    }

    /*
     *  компиляция ответа (+)
     */
    public function http_response_compile()
    {
        // первая строка
        $answer = 'HTTP/'.$this->http_response_version.' '.
                  $this->http_response_code.' '.
                  $this->http_response_codes[$this->http_response_code].
                  "\r\n";

        // добавляем все заголовки
        foreach ($this->http_response_headers as $h => $v)
        {
            $answer .= $h.': '.$v."\r\n";
        }

        // добавляем контент-длинну
        $answer .= 'Content-Length: '.strlen($this->http_response_body)."\r\n";

        // добавляем тело
        $answer .= "\r\n".$this->http_response_body;
            return $answer;
    }
  
}
?>