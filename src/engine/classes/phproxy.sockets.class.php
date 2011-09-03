<?PHP
// +-----------------------------------+
// | Основная логика...                |
// +-----------------------------------+

/*
 * Работа с сокетами
 */
class PhProxy_Sockets {
    

    // Готовность работы сокетов
    protected $sock_ready = false;

    // Готовность работы курла
    protected $curl_ready = false;

    // Ошибка сокета
    private $sock_error = null;

    // Ресурс открытого сокета
    private $socket = null;

    // счетчик входящих
    public $sock_counter = 0;

    // Данные последнего подключения
    public $sock_last = array();


    
    /*
     * Конструктор класса - проверка наличия расширений (+)
     */
    protected function __construct()
    {
        if (function_exists('socket_create')) {
            $this->sock_ready = true;
        } if (function_exists('curl_init')) {
            $this->curl_ready = true;
        }
    }
    
    /*
     * Деструктор класса - закрытие сокета (+)
     */
    protected function __destruct()
    {
        if ($this->socket) {
            $this->sock_close($this->socket);
        }
    }

    /*
     *  Cоздаем сокет (+)
     */
    protected function sock_create($type = 'tcp')
    {
        if ($type == 'tcp') { // TCP
            $type = SOCK_STREAM; $protocol = SOL_TCP;
        } else { // UDP
            $type = SOCK_DGRAM;  $protocol = SOL_UDP;
        }

        $socket = @socket_create(_SOCK_DEFAULT_DOMAIN, $type, $protocol);
            if (!$socket) {
                $this->sock_error = @socket_strerror($socket); return false;
            }

        $this->socket = $socket;
        return $this->socket;
    }

    /*
     *  возвращаем ошибку (+)
     */
    protected function sock_gerror()
    {
        return $this->sock_error;
    }

    /*
     *  закрываем сокет (+)
     */
    protected function sock_close($socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        }
        return @socket_close($socket);
    }

    /*
     *  неблокирующий режим для сокета (+)
     */
    protected function sock_no_block($socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        }
        $s = @socket_set_nonblock($socket);
            if (!$s) {
                $this->sock_error = @socket_strerror($s); return false;
            }
        return true;
    } 

    /*
     *  вешаем сокет на определенный адрес - порт (+)
     */
    protected function sock_bind($adr, $port, $socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        } 
        $bind = @socket_bind($socket, $adr, $port);
            if (!$bind) {
                $this->sock_error = @socket_strerror($bind); return false;
            }
        return true;
    }

    /*
     *  ставим сокет на прослушивание (+)
     */
    protected function sock_listen($bl, $socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        }
        $listen = @socket_listen($socket, $bl);
            if (!$listen) {
                $this->sock_error = @socket_strerror($listen); return false;
            }
        return true;
    }

    /*
     *  проверяем есть ли входящие соеденение  (+)
     */
    protected function sock_get($usleep, $socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        }

        while (true) {// ждем входящего соеденения
            $cnx = @socket_accept($socket);
                if ($cnx == false) {
                    usleep($usleep); continue;
                }
            break;
        }

        // дождались входящего соеденения!
        $this->sock_counter++;

        // получаем ХОСТ:ПОРТ
        $this->sock_last = $this->sock_get_name($cnx);
            return $cnx;
    }

    /*
     *  получаем port и ip (+)
     */
    protected function sock_get_name($cnx)
    {
        $adr = ''; $port = 0;
        $data = @socket_getpeername($cnx, $adr, $port);
        return array('ip'=>$adr, 'port'=>$port);
    }

   
    /*
     *  читаем с сокета (+)
     */
    protected function sock_read($cnx, $max = null)
    {
        // максимальная длинна строки читаемой с сокета
        if (!$max) $max =  _SOCK_READ_STR_MAX_LEN;

        // сюда пишем то, что читаем с сокета
        $data = '';

        // начала прослушивания (следим за таймаутом)
        $started = $this->microtime();

        // Читаем в цикле
        while (true) {
            $buf = @socket_read($cnx, $max, PHP_BINARY_READ);
                if ($buf === false) { // Он молчит - а мы спим
                    if ($started + _SOCK_READ_TIMEOUT < $this->microtime()) { // Проверяем таймаут
                        $return = (strlen($data)) ? $data : false;
                        return $return;
                    }
                    if (strlen($data) && strpos($data, "\r\n\r\n") > 1) { // Возвращаем вписанное
                        return $data;
                    }
                    usleep(_SOCK_READ_SLEEP);
                } elseif (is_string($buf) && strlen($buf) == 0) { // Соеденение было закрыто
                    return -1;
                } else {
                    $data .= $buf;
                }
        }
        return false;
    }


    /*
     *  пишем в сокет (+)
     */
    public function sock_write($data, $socket = null)
    {
        if ($socket == null) {
            $socket = $this->socket;
        }

        $wr = @socket_write($socket, $data, strlen($data));
            if (!$wr) {
                $this->sock_error = @socket_strerror($wr); return false;
            }
        return true;
    }




}


?>