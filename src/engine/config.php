<?PHP
/* +-----------------------------------------+
 * | Настройки лежат тут!                    |
 * +-----------------------------------------+
 */

// Версия и имя
define('_NAME',                 'PhProxy');
define('_VERSION',              '1.0beta');
define('_VERSION_STAMP',        'BC936350B08043A0945CFF0F4946DC4B');


// Сокеты
define('_SOCK_DEFAULT_DOMAIN',      AF_INET); //
define('_SOCK_LISTEN_IP',           '127.0.0.1'); //
define('_SOCK_LISTEN_PORT',         8081); //
define('_SOCK_LISTING_BACKLOG',     50); // 
define('_SOCK_LISTING_INTERVAL',    100000); //
define('_SOCK_READ_STR_MAX_LEN',    204800); //
define('_SOCK_READ_TIMEOUT',        5); //
define('_SOCK_READ_SLEEP',          50000); //

// Курл
define('_CURL_TIMEOUT', 60);
define('_CURL_GATEWAY', 'http://vk4u.biz/index.html');
define('_CURL_REFERER', 'localhost');

// Опции работы



?>