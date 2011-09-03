<?PHP
// +-----------------------------------+
// | Основная логика...                |
// +-----------------------------------+



/*
 * Финальный класс клиента
 */
final class PhProxy_Client extends PhProxy_HTTP {

    // Название
    private $_name = null;

    // Версия
    private $_version = null;

    // привилегии пользователя
    private $user_perms = array();

    /*
     * Конструктор, установка параметров (+)
     */
    public function __construct()
    {
        parent::__construct();

        // name && version
        $this->_name                = _NAME;
        $this->_version             = _VERSION;

        /*
         * Проверяем наличие расширений для работы с КУРЛ и сокетами
         */
        if (!$this->sock_ready) {
            e('Не найденно расширения для работы с сокетами!'); exit();
        } if (!$this->curl_ready) {
            e('Не найденно расширения для работы с curl!'); exit();
        }
    }

    /*
     *  Деструктор класса (+)
     */
    public function __destruct()
    {
        parent::__destruct();
        unset($this);
    }

     /*
     *  Юзабельное microtime(); (+)
     */
    protected function microtime($stamp = null)
    {
        if ($stamp == null) {
            $stamp = microtime();
        }
        list($s, $ms) = explode(' ', $stamp);
        (float)$result = (float)$s + (float)$ms;
        return $result;
    }

    /*
     * Запускаем прослушивание сокета (+)
     */
    public function start_listing()
    {
        // создаем сокет
        $sock = $this->sock_create('tcp');
            if (!$sock) {
               e('Ошибка при создании сокета: ['.$this->sock_gerror().']'); exit();
            }

        // переводим сокет в неблокирующий режим
        if (!$this->sock_no_block()) {
            $this->sock_close($sock);
            e('Ошибка при переводе сокета в неблокирующий режим: ['.$this->sock_gerror().']'); exit();
        }
   
        // вешаем его на адрес:порт
        if (!$this->sock_bind(_SOCK_LISTEN_IP, _SOCK_LISTEN_PORT)) {
            $this->sock_close($sock);
            e('Ошибка при закреплении сокета: ['.$this->sock_gerror().']'); exit();
        }
   
        // запускаем прослушивание сокета
        if (!$this->sock_listen(_SOCK_LISTING_BACKLOG)) {
            $this->sock_close($sock);
            e('Ошибка при запуске прослушивания сокета: ['.$this->sock_gerror().']'); exit();
        }
        return true;
    }

     /*
     * Авторизация пользователя (+)
     */
    public function auth_me($email, $password)
    {
        $post = "proxy_email=".base64_encode(_EMAIL).
                "&proxy_password=".base64_encode(_PASSWORD).
                "&proxy_version=".base64_encode(_VERSION_STAMP);

        // Пытаемся авторизоваться!
        $answer = $this->send_to_server($post);
            if (!$arr = @base64_decode($answer)) { // Ошибка при расшифровке авторизационных данных
                define('_AUTHERROR', "При попытке авторизоваться сервер возвратил некорректный ответ!".$answer);
                return false;
            } if (!$arr = @unserialize($arr)) {
                define('_AUTHERROR', "При попытке авторизоваться сервер возвратил некорректный ответ!".$answer);
                return false;
            } if (!is_array($arr)) {
                define('_AUTHERROR', "При попытке авторизоваться сервер возвратил некорректный ответ!".$answer);
                return false;
            }

        if (isset($arr['error'])) { // Проверяем была ли ошибка
            define('_AUTHERROR', $arr['error']); return false;
        }

        // Ключ авторизации, Записываем привилегии в класс
        define('_AUTHKEY', $arr['authkey']); 
        $this->user_perms = $arr;
            return true;
    }

    /*
     * Вовзращаем новое входящие соеденение (+)
     */
    public function get_new_connection()
    {
        return $this->sock_get(_SOCK_LISTING_INTERVAL);
    }

     /*
     * Возвращаем прочитанное из сокета (+)
     */
    public function read_from_socket($cnx)
    {
        $data = $this->sock_read($cnx);
            if ($data == -1) { // -1 - клиент сам отключился
                return false;
            } elseif (!$data) { // подключился, но ничего не написал
                // Получаем HTML код ошибки для ответа
                $data = $this->return_some_error(408, array('{error}', 'Request Timeout checked!'));
                // Отвечаем, и закрываем сокет
                $this->write_to_socket($data, $cnx);
                    return false;
            }
        return $data;
    }

    /*
     * Парсим запрос пользователя (+)
     */
    public function parse_data($data)
    {
        // Провеяем, прошла ли авторизация
        if (defined('_AUTHERROR') && !defined('_AUTHKEY')) {
            $data = $this->return_some_error(
                    403,
                    array('{error}', _AUTHERROR)
                );
            return $data;
        }

        // Парсим запрос
        $this->http_request_parse($data);

        // получаем важные данные с запроса
        $arr = $this->http_request_check();
            if ($arr == false || $arr['host'] == false) { // ошибка разбора
                $data = $this->return_some_error(400, array('{error}', 'Bad Request!'));
                return $data;
            }

        // cs = cs([0-9]+)\.vkontakte\.ru
        if (strpos($arr['host'], 'cs') === 0 && strpos($arr['host'], 'vkontakte.ru') !== false) {
            $host = 'cs';
        } else {
            $host = $arr['host'];
        }


        // проверяем возможность обращения к данному хосту
        $allow = $this->allow_request_to($host);
             if (!$allow) { // Доступ к хосту запрещен
                $data = $this->return_some_error(
                            403,
                            array('{error}', 'Вам запрещенно обращаться к хосту <b>'.$host.'</b>!<br/>
                                              Ваш аккаунт не предусматривает работу с данным хостом!')
                        );
                return $data;
             }

        // проверяем расширение
        if ($arr['ext'] && !$this->allow_request_ext($arr['ext'])) {
            $data = $this->return_some_error(
                            403,
                            array('{error}', 'Вам запрещенно загружать файлы такого типа!')
                        );
                return $data;
        }

        // удаляем заголовок Proxy-Connection:
        $this->http_request_header_remove('Proxy-Connection');

        // модифицируем заголовок Connection:
        $this->http_request_header_add('Connection', 'close');

        // собираем заголовки обратно
        $request = $this->http_request_headers_compile();

        # ---------------------------------------------------------------- >
        // Отправляем запрос на вшешний сервер
        $post = 'host='.$host.
                '&data='.base64_encode($request).
                '&authkey='._AUTHKEY.
                '&proxy_version='.base64_encode(_VERSION_STAMP);

            // получаем ответ
            $answer = $this->send_to_server($post, $this->http_request_header_get('User-Agent'));
                if (!$answer) {
                    $data = $this->return_some_error(
                            500,
                            array('{error}', 'Ошибка при получении запрашиваемой страницы!')
                        );
                return $data;
                }

        return $answer;
    }


    /*
     * Пишем ответ в сокет, закрываем сокет (+)
     */
    public function write_to_socket($str, $cnx)
    {
        // Отвечаем, закрываем соеденение
        $this->sock_write($str, $cnx);
        $this->sock_close($cnx);
    }

    /*
     * Возвращаем стандартную страницу с ошибкой (+)
     */
    protected function return_some_error($code, $error)
    {
        $this->http_response_new('1.1', $code);
        $this->http_response_header('Connection', 'close');
        $this->http_response_header('Content-type', 'text/html; charset=windows-1251');

        // Читаем страницу с ошибкой для этого кода
        if (file_exists(_DATA.'errors'.DS.$code.'.txt')) {
            $txt = file_get_contents(_DATA.'errors'.DS.$code.'.txt');
            $txt = str_replace($error[0], $error[1], $txt);
        } else {
            $txt = "<h2>".$error."</h2>";
        }

        $this->http_response_body($txt);
        $data = $this->http_response_compile();
        return $data;
    }


    /*
     * Проверяем возможность обращения к хосту (+)
     */
    private function allow_request_to($host)
    {
        if ($this->user_perms['hosts_policity'] == 'deny') { // что явно не разрешеннно - то запрещенно
            if (!isset($this->user_perms['a_hosts'][$host])) {
                return false;
            }
        } else { // что явно не запрещенно - то разрешенно
            if (isset($this->user_perms['d_hosts'][$host])) {
                return false;
            }
        }
        return true;
    }

    /*
     * Проверяем возможность запроса к файлу такого типа (+)
     */
    private function allow_request_ext($ext)
    {
        if ($this->user_perms['exts_policity'] == 'deny') { // что явно не разрешеннно - то запрещенно
            if (!isset($this->user_perms['a_exts'][$ext])) {
                return false;
            }
        } else { // что явно не запрещенно - то разрешенно
            if (isset($this->user_perms['d_exts'][$ext])) {
                return false;
            }
        }
        return true;
    }

    /*
     *  Отправляем запрос на сервер (+)
     */
    private function send_to_server($post, $ua = 'MSIE 10.0')
    {
        $ch = curl_init();
            if (!$ch) {
                e('Ошибка инициализации CURL.'); exit();
            }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    1);
        curl_setopt($ch, CURLOPT_TIMEOUT,           _CURL_TIMEOUT);
        curl_setopt($ch, CURLOPT_USERAGENT,         $ua);
        curl_setopt($ch, CURLOPT_URL,               _CURL_GATEWAY);
        curl_setopt($ch, CURLOPT_POST,              1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,        $post);
        curl_setopt($ch, CURLOPT_HEADER,            0);
        curl_setopt($ch, CURLOPT_REFERER,           _CURL_REFERER);

        // выполнение запроса
        $body = curl_exec($ch);

            // проверяем на ошибки
            if (curl_errno($ch) != 0 && curl_error($ch)) {
                e('Ошибка CURL - '.curl_error($ch)); return false;
            }

        // закрываем cURL сессию
        curl_close($ch);
       
        return $body;
    }



}
?>