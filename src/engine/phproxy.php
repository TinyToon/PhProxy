<?PHP
// 
// +-----------------------------------------------------------------------------------+
// | PHP Version 5                                                                     |
// +-----------------------------------------------------------------------------------+
// | PhProxy 1.0 alpha - прокси сервер для анонимного серфинга по сети и не только.    |
// +-----------------------------------------------------------------------------------+
// | Created by Alex Shcneider <alex.shcneider@gmail.com>(c) 2010                      |
// +-----------------------------------------------------------------------------------+
//

/*
 * Базовые настройки...
 */
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush(1);

/*
 * Определяем рабочую дирректорию для запускаемого скрипта, и назначаем константы
 * с путем до скриптов, лог файлов, кэша, данных
 */
define('DS',    DIRECTORY_SEPARATOR);
define('_ROOT', str_replace('engine', '', getcwd()).DS);
define("_LOGS", _ROOT.'logs'.DS);
define("_DATA", _ROOT.'data'.DS);

/*
 * Устанавливаем обязхательное логирование ошибок, так как приложение будет запускаться
 * через php-win.exe, который не имеет STDOUT и, как следствие, единственную возможность
 * общаться с пользователем до инициализации Web-интерфейса.
 */
ini_set('log_errors',           true);
ini_set('log_errors_max_len',   1024);
ini_set('error_log',            _LOGS.date('Y.m.d').'.txt');

    /*
     * Своя функция ведения лога ошибок.
     */
    function e($txt)
    {
        trigger_error($txt);
    }

/*
 * Подключаем файл с настройками скрипта
 */
if (!file_exists(_ROOT.'engine'.DS.'config.php')) {
    e('Файл с настройками не найден!'); exit();
}
require(_ROOT.'engine'.DS.'config.php');

# ------------------------------------------------------------> Разбираем параметры запуска
$act = isset($argv[1]) ? $argv[1] : 'start';
    if ($act == 'stop') { // Остановка сервера
        $fp = @fsockopen(_SOCK_LISTEN_IP, _SOCK_LISTEN_PORT, $errno, $errstr, 5);
            if ($fp == false && $errno) { exit('Server hasn`t been srart!'."\r\n"); }
        fputs($fp, '-off');
        while (!feof($fp)) { echo fgets($fp); }
        fclose($fp);  exit();
    }
    
# ------------------------------------------------------------> Запуск скрипта

/*
 *  данные авторизации
 */
if (!file_exists(_ROOT.'account.txt')) {
    e('Файл с данными авторизации не найден!'); exit();
}
$data = @file_get_contents(_ROOT.'account.txt');
    list($email, $pass) = @explode("\r\n", $data, 2);
$email = trim($email); $pass = trim($pass);

// Данные авторизации
define('_EMAIL',                $email);
define('_PASSWORD',             $pass);

/*
 * Класс клиентской части скрипта
 */
require(_ROOT.'engine'.DS. 'classes'.DS.'phproxy.sockets.class.php');
require(_ROOT.'engine'.DS. 'classes'.DS.'phproxy.http.class.php');
require(_ROOT.'engine'.DS. 'classes'.DS.'phproxy.client.class.php');
    if (!class_exists('PhProxy_Client')) {
        e('Системные файлы были поврежденны!'); exit();
    }

$proxy = new PhProxy_Client();

/*
 * Инициализируем прослушивание сокета
 */
$proxy->start_listing();

/*
 *  авторизация на сервере
 */
$proxy->auth_me(_EMAIL, _PASSWORD);

/*
 *  Входим в бесконечный цикл прослушивания сокета 
 */
do {
    // Получаем новое входящие соеденение (точно возвращает новое подключение)
    $new_cnx    = $proxy->get_new_connection();
    
    // Читаем данные из открытого соеденения (может вернуть false)
    $data       = $proxy->read_from_socket($new_cnx); 
        if (!$data) { // Была какая то ошибка при получении запроса (продолжаем слушать)
            continue;
        } elseif (trim($data) == '-off') {
            $proxy->write_to_socket('Server has been stoped!'."\r\n", $new_cnx);
            break;
        }
        
    // Разбираем запрос, генерим ответ
    $answer     = $proxy->parse_data($data);
   
    // Отвечаем
    $proxy->write_to_socket($answer, $new_cnx);
        continue;

} while(true);


?>