<?PHP #file_get_contents("res:///PHP/icon.ico");
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
 * @version   2.0.5 alpha2
 * @link      http://shcneider.in/forum
 **/

// Дебаггинг включен
define('_DEBUG', 1);

# ---------------------------------------------------------- > Только для PHP 4.4.4

if (version_compare(PHP_VERSION, '4.4.4')) {
    exit('For PHP 4.4.4 only!');
}

# ---------------------------------------------------------- > Константы нужные для работы

// Данные приложения
define('APP_NAME',          'PhProxy');
define('APP_jVERSION',      '2');
define('APP_nVERSION',      '0');
define('APP_nBUILD',        5);
define('APP_sBUILD',        'Alpha2');
define('APP_dBUILD',        '11.04.2011');

// Пути и дирректории при работе
define('DS',                DIRECTORY_SEPARATOR);
define('APP_ROOT',          realpath('.'.DS) . DS);
define('APP_ROOT_LOGS',     APP_ROOT . 'logs' . DS); // 2.0.5

// пути и дирректории компиляции
define('CT_APP_ROOT',       '');
define('CT_APP_ROOT_SRC',   CT_APP_ROOT . 'include' . DS);
define('CT_APP_ROOT_IMGS',  CT_APP_ROOT . 'srcs' . DS);

// пути и дирректории компиляции ( >= 2.0.5)
define('COMPILE_PATH',           '');
define('COMPILE_PATH_INCLUDE',   COMPILE_PATH . 'include' . DS);
define('COMPILE_PATH_IMGS',      COMPILE_PATH . 'imgs' . DS);


# ---------------------------------------------------------- > Настраиваем интерпритатор

error_reporting(E_ALL | E_NOTICE);
ob_implicit_flush(1);
set_time_limit(0);

ini_set('display_errors',           True);
ini_set('register_argc_argv',       True);
ini_set('log_errors',               True);
ini_set('log_errors_max_len',       1024);
ini_set('error_log',                APP_ROOT_LOGS. 'Log_'.date('Y-m-d').'.log.txt');

# ---------------------------------------------------------- > Подключение необходимых файлов

// новая константа пути (2.0.5 ++)
require COMPILE_PATH_INCLUDE . 'config.default.php';
require COMPILE_PATH_INCLUDE . 'defines.php';
require COMPILE_PATH_INCLUDE . 'functions.php';

# ---------------------------------------------------------- > Инициализация

// проверяем возможность записи в корневую папку (2.0.5 ++)
$tname = APP_ROOT . md5(microtime());
$fp = @fopen($tname, 'w+');
    if (!$fp) {
        error_fatal(APP_NAME.' не может писать в корневую дирректорию ['.APP_ROOT."]\r\nПожалуйста, разрешите программ доступ на запись!");
    }
@fclose($fp);
unlink($tname);


// проверяем существование лог-папки, если нет - создаем (2.0.5 ++)
if (!@file_exists(APP_ROOT_LOGS)) {
    if (!@mkdir(APP_ROOT_LOGS, 0777)) {
        error_fatal('Не удалось создать папку для хранения лог-файлов: ['.APP_ROOT_LOGS.']');
    }
}

// Основа заголовка окна (2.0.5 ++)
define('M_WIN_TITLE', version(cfg_get('gui_mainwin_title')));


    // защита от двойного запуска (2.0.5 ++)
    if (wb_get_instance(M_WIN_TITLE, true)) {
        event('---------------------->> Обнаруженна попытка повторного запуска приложения! <<-----------------------'); die();
    }

    
# ---------------------------------------------------------- > Инициализация

// Логируем инициализацию
event();
event('Попытка запуска программы...');
    if (_DEBUG) { // запись информации для отладки
        event(' -- Версия PhProxy: ['.version().']');
        event(' -- Версия PHP: ['.PHP_VERSION.']');
        event(' -- Версия ОС: ['.@php_uname().']');
        event(' -- Путь запуска: ['.APP_ROOT.']');
    }

    // проверяем загруженно ли расширение WinBindera
    if (!extension_loaded('winbinder')) {
        error_fatal('Расширение дла работы с WinBinder не найденно!');
    } elseif(_DEBUG) {
        event('Расширение для работы с WinBinder найденно и загруженно успешно!');
    }

    // Подключаем необходимые файлы WB
    if (!include COMPILE_PATH_INCLUDE . 'winbinder' . DS . 'winbinder.php') {
        error_fatal('Ошибка при загрузке системных WB файлов!');
    } elseif(_DEBUG) {
        event('Системные WB файлы загруженны успешно!');
    }

    
// подключаем GUI класс и класс сетевой логики приложения (новая константа пути (2.0.5 ++))
require COMPILE_PATH_INCLUDE . 'classes' . DS . 'wb_window.class.php';
require COMPILE_PATH_INCLUDE . 'classes' . DS . 'application.class.php';


    // проверяем наличие необходимого нам класса GUI
    if (!class_exists('WB_Window')) {
        error_fatal('Не найден класс GUI в контексте приложения!');
    } elseif(_DEBUG) {
        event('Класс GUI успешно подключен!');
    }

event('Инициализация: успешно заверешенна!');

# ---------------------------------------------------------- > Поехали!

// Создаем экземпляр класса сетевой логкики
$phproxy = new PhProxy_Net();

// Создаем каркас главного окна
event('Начало создания GUI интерфейса...');


    // проверяем существования графических файлов (2.0.5+)
    if (!file_exists(cfg_get('gui_mainwin_icon'))) {
        error_fatal('На удалост найти необходимый для работы файл: ['.cfg_get('gui_mainwin_icon').']');
    } if (!file_exists(cfg_get('gui_mainwin_logo'))) {
        error_fatal('На удалост найти необходимый для работы файл: ['.cfg_get('gui_mainwin_logo').']');
    }

$w_main = new WB_Window(0, AppWindow, WBC_TASKBAR | WBC_INVISIBLE);
    $w_main->title(M_WIN_TITLE);
    $w_main->position(WBC_CENTER, WBC_CENTER);
    $w_main->size(cfg_get('gui_mainwin_width'), cfg_get('gui_mainwin_height'));
    $w_main->icon(cfg_get('gui_mainwin_icon'));
$w_main->build();

event('Построение каркаса окна законченно!');


// Создаем главное меню
/*$mainmenu = wb_create_control($w_main->wobj, Menu, array(
   "&Файл",
       array(10,     "&New\tCtrl+N",     "", cfg_get('gui_mainwin_icon'),  "Ctrl+N"),
   "&Помощь",
       array(11,    "&Help topics\tF1", "", null,  "F1")
)); */


// Добавляем логотип в самый верх страницы
wb_set_image(
    wb_create_control($w_main->wobj, Frame, "", 0, 0, 600, 100, ID_LOGO, WBC_IMAGE),
    cfg_get('gui_mainwin_logo')
);


// Косметические фреймы
wb_create_control($w_main->wobj, Frame, L_CFSERVER, 5,   105, 170, 80, ID_FRAME_SERVER);
wb_create_control($w_main->wobj, Frame, L_CFAUTH,   180, 105, 310, 80, ID_FRAME_AUTH);
#wb_create_control($w_main->wobj, Frame, L_CSTATE,   5,   185, 245, 80, ID_FRAME_STATE);


// кнопки управления сервером
$w_main->serverButtonStart = wb_create_control(
    $w_main->wobj, PushButton, array(L_BSTART, L_BSTART_DES), 15, 125, 150, 20, ID_SERVER_BSTART
);
$w_main->serverButtonStop  = wb_create_control(
    $w_main->wobj, PushButton, array(L_BSTOP, L_BSTOP_DES), 15, 150, 150, 20, ID_SERVER_BSTOP
);

// дисайблим кнопку выключения сервера (выключеный сервер не выключить :))
wb_set_enabled($w_main->serverButtonStop, 0);


// БЛОК АВТОРИЗАЦИИ
// Подписи к полям ввода
wb_create_control($w_main->wobj, Label,   L_LAUTH_EMAIL,   190, 127, 50,  15, ID_LAUTH_EMAIL);
wb_create_control($w_main->wobj, Label,   L_LAUTH_PASS,    190, 152, 50,  15, ID_LAUTH_PASS);


// Поля ввода
$w_main->serverButtinAuthEmail = wb_create_control(
    $w_main->wobj, EditBox, 'guest@guest', 240, 125, 245, 20, ID_IAUTH_EMAIL
);
$w_main->serverButtinAuthPass  = wb_create_control(
    $w_main->wobj, EditBox, 'guest',       240, 150, 120, 20, ID_IAUTH_PASS, WBC_MASKED
);

// Кнопки авторизации и регистрации
$w_main->serverButtonRegDo  = wb_create_control(
    $w_main->wobj, PushButton, L_BAUTH_REGDO,  365, 150, 120, 20, ID_BAUTH_REGDO
);

// диcейблим поля ввода мыла и пароля, а так же кнопку регистрации - в разработке
wb_set_enabled($w_main->serverButtinAuthEmail, 0);
wb_set_enabled($w_main->serverButtinAuthPass, 0);
wb_set_enabled($w_main->serverButtonRegDo, 0);


// Создаем статусбар и устанавливаем туда версию программы
$w_main->statusbar = wb_create_control($w_main->wobj, StatusBar, ' ');
$w_main->status(version());


event('GUI окно успешно созданно...');

// Включаем обработку событий для окна
wb_set_handler($w_main->wobj, 'phproxy_eventsHandler');

// запускаем клиентский таймер
$phproxy->client_timer = wb_create_timer($w_main->wobj, ID_CLIENT_TIMER, cfg_get('net_client_timer'));

// показываем окно и уходим в цикл
$w_main->visible(1); $w_main->loop();


    // обработчик событий в GUI окне
    function phproxy_eventsHandler($win, $id, $con = 0, $param1 = 0, $param2 = 0)
    {
        global $phproxy;

            switch ($id) {

                // нажата клавиша закрытия окошка
                case IDCLOSE:
                    phproxy_stop(); break;

                // Кнопка запуска сервера
                case ID_SERVER_BSTART:
                    phproxy_server_start(); break;

                // Кнопка остановки сервера
                case ID_SERVER_BSTOP:
                    phproxy_server_stop(); break;

                // Сработал таймер сервеного сокета
                case ID_SOCKET_TIMER:
                    $phproxy->server_main(); break;

                // сработал таймер клиентской части
                case ID_CLIENT_TIMER:
                    $phproxy->client_main(); break;

            }

        return true;
    }


?>