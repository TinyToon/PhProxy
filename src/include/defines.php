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

// ------------------------------ Параметры не изменяемые

// таймер авторизации
define('APP_AUTH_TIMER_INT',         500);
// системный таймер
define('APP_SYSTEM_TIMER_INT',       500);


// ------------------------------ ID контролов

// кнопки
// управления сервером
define('ID_SERVER_BSTART',  1001);
define('ID_SERVER_BSTOP',   1002);
// авторизации-регистрации
define('ID_BAUTH_REGDO',     1003);


// поля ввода - авторизация
define('ID_IAUTH_EMAIL',    6001);
define('ID_IAUTH_PASS',     6002);


// таймеры
define('ID_SOCKET_TIMER',   2001);
define('ID_CLIENT_TIMER',   2002);

// косметические фреймы - главное окно
define('ID_FRAME_SERVER',   3001);
define('ID_FRAME_AUTH',     3002);
define('ID_FRAME_STATE',    3003);

// картинки логотип
define('ID_LOGO',           4001);

// лейблы авторизация
define('ID_LAUTH_EMAIL',    5001);
define('ID_LAUTH_PASS',     5002);


// ------------------------------ Локализация

// Косметические фреймы
define('L_CFSERVER',            'Управление PhProxy');
define('L_CFAUTH',              'Данные для авторизации на сервере');
#define('L_CSTATE',              'Состояние PhProxy');

// Кнопки управления сервером
define('L_BSTART',              'Запустить сервер');
define('L_BSTART_DES',          'Авторизация на сервере и запуск прослушивания локального сокета');
define('L_BSTOP',               'Остановить сервер');
define('L_BSTOP_DES',           'Логаут на сервере и остановка прослушивания локального сокета');

// Блок авторизации
define('L_LAUTH_EMAIL',         'E-Mail:');
define('L_LAUTH_PASS',          'Пароль:');
define('L_BAUTH_REGDO',         'Регистрация');



?>