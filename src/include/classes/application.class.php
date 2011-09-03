<?PHP
/**
 * PhProxy_Client - HTTP-прокси сервер на PHP под Win32 системы.
 *
 * PHP 4 && BamCompiler 1.21
 *
 * @package   PhProxy_Client
 * @category  PhProxy
 * @author    Alex Shcneider <alex.shcneider@gmail.com>
 * @copyright 2009-2011 (c) Alex Shcneider
 * @license   Lisense.txt
 * @version   {$VERSION}
 * @link      http://shcneider.in/forum
 **/



// Класс приложения (логика PhProxy)
class PhProxy_Net {


# --------------------------------------- >
// Храним здесь состояние авторизации
var $auth_state  = 0;
var $auth_email  = 'guest@guest';
var $auth_pass   = 'guest';
var $auth_key    = 'no';
var $auth_expire = 0;


# --------------------------------------- >
// текст последней ошибки серверной части
var $error = null;

// ресурс сокета
var $socket = null;

// таймер сокета
var $socket_timer = null;

// состояние серверного сокета
var $server_state = 0;

// ресурс входящего подключения на сокет сервера
var $server_cnx = 0;

// буфер чтения
var $server_rbuffer = '';

// буфер ответа
var $serve_answer = '';

// подключенно (таймаут чтения)
var $server_connected = 0;



# --------------------------------------- >

// клиентский таймер
var $client_timer = 0;

// состояние клиентской части
var $client_state = 0;

// таймаут сокета
var $client_connected = 0;

// ресурс исходящего соеденение
var $client_cnx = 0;

// запрос на отправку
var $client_query = '';

// полученный ответ
var $client_answer = '';






    // обработка ошибки сокета
    function serror($r = null)
    {
        $code = @socket_last_error($r);
            if (!$code) {
                return '{Неизвестная ошибка сокета}';
            }

        return '{'.$code.' - '.socket_strerror($code).'}';
    }


    // Попытка запуска сервера
    function server_start()
    {
        global $w_main;

        // сбрасываем текст последней ошибки
        $this->error = 'Неизвестная ошибка';
       
        // уже создан какой то сокет
        if ($this->socket) {
            $this->error = 'Создание сокета невозможно - сокет уже создан!';
            return false;
        }

            // загруженно ли расширение для работы с сокетами
            if (!extension_loaded('sockets')) {
                $this->error = 'Расширение для работы с сокетами не найденно. Запуск сервера не возможен!';
                return false;
            }

        // второй раз на всякий случай
        if (!function_exists('socket_create')) {
            $this->error = 'Функция для работы с сокетами не найденна. Запуск сервера не возможен!';
            return false;
        }

            // костанта для TCP
            if (!defined('SOL_TCP')) { // константы нету...
                event('Константа для TCP сокета не определенна...');
                // определяем номер протокола для TCP
                $proto = @getprotobyname("TCP");
                    if (!$proto || $proto == -1) {
                        $this->error = 'Вы не поверите, но в Вашем окружении нет поддержки TCP/IP!';
                        return false;
                    }

                // сами установим константу
                define('SOL_TCP', $proto);
                event('Вручную устанавливаем константу для TCP!');
            }

        // создаем сокет
        $this->socket = $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$sock) {
                $this->error = 'При создании сокета: '.$this->serror($sock);
                return false;
            } else {
                event(' - Сокет домена AF_INET типа SOCK_STREAM протокола TCP успешно создан!');
            }

        // связываем сокет с адресом
        $res = @socket_bind($sock, cfg_get('net_server_ip'), cfg_get('net_server_port'));
            if (!$res) {
                $this->error = 'При закреплении сокета: '.$this->serror($sock);
                return false;
            } else {
                event(' - Созданный сокет успешно закреплен на адрес:порт - '.cfg_get('net_server_ip').':'.cfg_get('net_server_port'));
            }

        // переводим сокет в неблокирующий режим
        socket_set_nonblock($sock);

        // ставим сокет на прослушивание
        $res = socket_listen($sock, cfg_get('net_server_backlog'));
            if (!$res) {
                $this->error = 'При установки сокета на прослушивание: '.$this->serror($sock);
                return false;
            } else {
                event(' - Сокет успешно установлен на прослушивание с максимальной длинной очереди: '.cfg_get('net_server_backlog'));
            }

        // сокет успешно установлен на прослушивание
        return true;
    }


    // попытка остановки сервера
    function server_stop()
    {
        global $w_main;
            if (!$this->socket) {
                $this->error = 'Сервер не запущен. Невозможно остановить!';
                return false;
            }

        event(' - Уничтожение таймера сокета');
        wb_destroy_timer($w_main->wobj, $this->socket_timer);

        event(' - Закрытие сокета');
        socket_close($this->socket);
        $this->socket = null;

        return true;
    }


    // Функция работы сервера вызываемая по таймеру
    function server_main()
    {
        // Состояние равное 0 - не подключенно
        if ($this->server_state == 0) {
            $this->server_cnx = @socket_accept($this->socket);
                if ($this->server_cnx) { // новое соеденение полученно
                    socket_set_nonblock($this->server_cnx);
                    $this->server_state = 1;
                    $this->server_connected = timer()+cfg_get('net_server_read_timeout'); // запоминаем время подключения
                } else {
                    return true;
                }
        }

        
        // Состояние равное 1 - подключенно, необходимо прочитать
        if ($this->server_state == 1) {

                // проверяем таймаут чтения
                if ($this->server_connected < timer()) {
                    $this->server_answer = 'HTTP/1.0 408 Request Timeout'."\r\n\r\nRequest Timeout";
                    $this->server_state = 5;
                    return true;
                }

            // пробуем прочитать
            $data = @socket_read($this->server_cnx, cfg_get('net_server_read_buffer'), PHP_BINARY_READ);
                if (is_string($data)) { // удалось что то прочитать
                    if (strlen($data) == 0) { // соеденение было закрыто
                        $this->server_state = 6;
                    } else {
                        $this->server_rbuffer .= $data;
                        if (strlen($data) < cfg_get('net_server_read_buffer') && strpos($this->server_rbuffer, "\r\n\r\n") !== false) {
                            $this->server_state = 2;
                        }
                    }
                    
                } else { // обработка ошибки чтения == false
                    if (strlen($this->server_rbuffer)) {
                        $this->server_state = 2;
                    } else {
                        $this->server_state = 6;
                    }
                }
               
        }


        // Состояние равное 2 - запрос прочитан, нужно его обработать
        if ($this->server_state == 2) {

            // обрабатываем заголовки
            list($h, $b) = explode("\r\n\r\n", $this->server_rbuffer, 2);
                if ($h === false or $b === false) {
                    $this->server_answer = 'Не удалось распарсить запрос!';
                    $this->server_state = 5;
                    return true;
                }

            $hh = @explode("\r\n", $h);
                if (!is_array($hh)) {
                     $this->server_answer = 'Не удалось распарсить запрос!';
                    $this->server_state = 5;
                    return true;
                }

            $nh = '';
                // перебираем все
                foreach ($hh as $h) {
                    if (strpos($h, 'Proxy-Connection: ') === 0) {
                        
                    } elseif (strpos($h, 'Connection: ') === 0) {

                    } else {
                        $nh .= $h."\r\n";
                    }
                }

            $nh .= 'Connection: close'."\r\n";
            $nh .= "\r\n".$b;

            $this->server_rbuffer = $nh;

            $this->server_state = 3;
        }

        
        // состояние равное 3 - запрос обработан, нужно отправить на удаленный сервер
        if ($this->server_state == 3) {

                // если клиенсткая часть еще не готова принимать соеденения
                if ($this->client_state != 0) {
                    return false;
                }

            // передаем в клиентский класс наш запрос и меняем статус
            $this->client_query = $this->client_query_encode('getURL', $this->server_rbuffer);
            $this->client_state = 1;

            // а сами ждем ответа от клиентской части
            $this->server_state =  4;
        }


        // состояние равное 4 - ждем ответа с сервера и ничего не делаем


        // состояние равное 5 - ответ получен,нужно ответить
        if ($this->server_state == 5) {

            // удаляем заголовки прокси сервера (2.0.5+)
            if (strpos($this->server_answer, "\r\n\r\n") !== false) {
                list($temp, $ans) = @explode("\r\n\r\n", $this->server_answer, 2);
            } else {
                $ans = $this->server_answer;
            }

            // отвечаем
            @socket_write($this->server_cnx, $ans, strlen($ans));
            $this->server_state =  6;
        }


        // состояние равное 6 - нужно закрыть сокет, и очистить все переменные
        if ($this->server_state == 6) {
            socket_close($this->server_cnx);
            $this->server_rbuffer = '';
            $this->server_state = 0;
        }

    }

// ----------------------------------------------------------- >> Клиенсткие функции

    // функция работы клиента вызываемая по таймеру
    function client_main()
    {
        // состояние 0 - ничего делать не нужно - return
        if ($this->client_state == 0) {
            return true;
        }
        
        // состояние 1 - нужно связаться с сервером
        if ($this->client_state == 1) {
            // Пытаемся установить соеденение
            $this->client_cnx = $this->client_open(cfg_get('net_remote_domain'), cfg_get('net_remote_port'), cfg_get('net_remote_timeout'));
                if (!$this->client_cnx) { // не удалось
                    $this->client_state = 4;
                    $this->client_answer = 'Не удалось связаться с прокси-сервером!';
                } else {
                    $this->client_state = 2;
                }
        }

        
        // состояние 2 - нужно записать в сокет запрос
        if ($this->client_state == 2) {
            $res = $this->client_send_post($this->client_cnx, $this->client_query);
                if (!$res) {
                    $this->client_state = 4;
                    $this->client_answer = 'Не удалось связаться с прокси-сервером!';
                } else {
                   $this->client_state = 3;
                   return true;
                }
        }


        // состояние 3 - нужно прочитать ответ
        if ($this->client_state == 3) {
            // читаем
            $data = $this->client_read($this->client_cnx);
                if ($data) {
                    $this->client_answer .= $data; return true;
                } else {
                    if (strlen($this->client_answer)) {
                         $this->client_state = 4;
                    } else {
                        return true;
                    }
                }
        }


        // состояние 4 - отдать ответ серверной части, сбросить все
        if ($this->client_state == 4) {
            $this->server_answer = $this->client_answer;
            $this->server_state = 5;

            // сбрасываем все параметры
            $this->client_answer = $this->client_query = '';

            $this->client_close($this->client_cnx);
            $this->client_state = $this->client_cnx = 0;
        }


    }


    // открытие клиентского соеденения к серверу
    function client_open($host, $port, $timeout)
    {
        // программа ошибки
        $errno = $errstr = '';

        // пытаемся проверить связь до сервера
        $d = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if (!$d) {
                event('Не удалось связаться с сервером за '.$timeout.' секунд ['.$errstr.']');
                return false;
            }
        stream_set_blocking($d, 0);

        return $d;
    }


    // отправка данных в клиентское соеденение
    function client_send_post($cnx, $data)
    {
        // строим пост запрос
        $req = 'POST '.cfg_get('net_remote_path').' HTTP/1.1'."\r\n".
        'Host: '.cfg_get('net_remote_domain')."\r\n".
        'User-Agent: '.version('%an%/%avj%.%avn%.%avb% %avs%')."\r\n".
        'Referer: '.cfg_get('net_remote_referer')."\r\n".
        'Connection: close'."\r\n".
        'Content-Length: '.strlen($data)."\r\n".
        'Content-Type: application/x-www-form-urlencoded'."\r\n".
        "\r\n".
        $data;


        // отправляем данные на сервер
        $send = @fwrite($cnx, $req, strlen($req));
            if (!$send) {
                event('Не удалось записать данные в сокет!'); return false;
            }

        return $send;
    }

    
    // читаем данные с соеденения
    function client_read($cnx)
    {
        if (feof($cnx)) {
            return false;
        }
        $data = fread($cnx, cfg_get('net_remote_read_buff'));
            if (!$data) {
                return false;
            }

        return $data;
    }


    // закрытие клиентского соеденения
    function client_close($cnx)
    {
        return @fclose($cnx);
    }


    // собираем запрос для отправки
    function client_query_encode($act = 'getURL', $data = null)
    {
        // начинаем с путого шаблона
        $query = 'version='.version('%avb%').'&';
        $query .= 'authkey='.$this->auth_key.'&';

            // если кодируется запрос getURL - получение удаленного документа
            if ($act == 'getURL') {
                $query .= 'act=getURL&';
                $query .= 'data='.urlencode(base64_encode($data));
            }

        return $query;
    }


/*
// таймер авторизации
var $auth_timer = 0;

// прогресс авторизации
var $auth_stage = 0;
var $auth_sub_stage = 1;

// ресурс открытого соеденения авторизации
var $auth_cnx = 0;

// данные прочитанные от сервера
var $auth_data = '';

# --------------------------------------- >

// храним здесь состояние сервера
var $server_state = 0;

// время запуска сервера
var $server_started = 0;

// колличество входящих соеденений с момента запуска
var $server_incoming = 0;

# --------------------------------------- >

// ресурс шрифта Tahoma 8pt Bold
var $gui_bold_font = 0;

// котролы статистики
var $gui_server_state_ctrl = 0;
var $gui_server_uptime_state_ctrl = 0;
var $gui_server_incom_state_ctrl = 0;

#var $gui_uptime_state_ctrl = 0;

var $gui_auth_state_ctrl = 0;

# --------------------------------------- >

// системный таймер
var $system_timer = 0;

// время запуска
var $started = 0;

# --------------------------------------- > HTTP

// парсим ответ сервера
function html_parse_response($data)
{
    // Ищем разделитель заголовка и тела
    if (strpos($data, "\r\n\r\n") === false) {
        event('Ошибка при парсинге ответа - нет раздлителя заголовка-тела!');
        return false;
    }

    // делим
    list($headers, $body) = @explode("\r\n\r\n", $data, 2);

    // пустое тело
    if (empty($body)) {
        event('Полученно пустое тело ответа!'); return false;
    }

    // Разбираем параметры
        if (strpos($body, '&') === false) {
            $params = array($body);
        } else {
            $params = @explode('&', $body);
        }

    // разбиваем на пара=значение
    $c = sizeof($params); $return = array();

    // перебираем весь массив
    for ($i=0; $i<$c; $i++) {
        if (strpos($params[$i], '=') !== false) {
            list($key, $value) = @explode('=', $params[$i]);
            $return[$key] = $value;
        }
    }

    // проверяем обязательный флаг стейт
    if (!isset($return['state'])) {
        event('Нет обязательного параметра state в ответе...'); return false;
    }

    return $return;
}

// текст последней ошибки
var $error = null;

// прослушиваемый сокет
var $socket = null;

// ресурс таймера
var $stimer = null;

# --------------------------------------- >

// ресурс текущего соеденения
var $cnx = null;

// флаг состояния текущего соеденения
var $state = 0;

// данные прочитанные с сокета
var $srequest = '';

// данные для отправки
var $sresponse = '';

// открытый сокет
var $fs = null;

# --------------------------------------- >



*/

}


/*

    /* // определяем Program Files
        if (isset($_SERVER["ProgramFiles"])) {
            $pf = strtolower($_SERVER["ProgramFiles"].DS);
        } else {
            fatal('Ошибка определения рабочей дирректории...');
        }
    // Не разрешим запускать программу ниоткуда, кроме папки program files
        if (strtolower(APP_ROOT) != $pf . 'phproxy' . DS && strtolower(APP_ROOT) != 'e:\\phproxy\\home\\') {
            fatal('Программа установленна неверно...'."\r\n".'Пожалуйста, переустановите программу!');
        }
    // Определяем корневой каталог для данных
        if (!isset($_SERVER["APPDATA"])) {
            fatal('Ошибка определения дирректории для хранения данных...');
        }
    // проверяем есть ли папка с данными программы
        if (!file_exists($_SERVER["APPDATA"] . DS . 'PhProxy')) { // папки нету - значит это первый запуск
            #$succ = @mkdir($_SERVER["APPDATA"] . DS . 'PhProxy');
        } */





/*

// обработка нажатия на крестик - закрытие окна и программы
function w_close($w) {
    global $w_main;

        if (wb_message_box($w_main, WM_TXT_CLOSEW_TEXT, WM_TXT_CLOSEW_TITLE, WBC_YESNO)) {
            wb_destroy_window($w);
        }

    return true;
}




// выполняем требуемое действие
pclose(popen('start "PhProxy" "'.RT_ROOT_EXE . 'php-win.exe" '. RT_ROOT_SCRIPTS . 'phproxy.php '.$action, 'r'));

*/


?>