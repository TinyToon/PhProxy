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
 * @version   2.0.4 alpha1
 * @link      http://shcneider.in/forum
 **/

// Дебаггинг включен
define('DEBUG_MODE', 1);

# ---------------------------------------------------------- > Только для PHP 4.4.4

if (version_compare(PHP_VERSION, '4.4.4')) {
    exit('For PHP 4.4.4 only!');
}

# ---------------------------------------------------------- > Константы нужные для работы

// Данные приложения
define('APP_NAME',          'PhProxy');
define('APP_jVERSION',      '2');
define('APP_nVERSION',      '0');
define('APP_nBUILD',        4);
define('APP_sBUILD',        'Alpha1');
define('APP_dBUILD',        '09.02.2011');

// Пути и дирректории при работе
define('DS',                DIRECTORY_SEPARATOR);
define('APP_ROOT',          realpath('.'.DS) . DS);

// пути и дирректории компиляции
define('CT_APP_ROOT',       '');
define('CT_APP_ROOT_SRC',   CT_APP_ROOT . 'include' . DS);
define('CT_APP_ROOT_IMGS',  CT_APP_ROOT . 'srcs' . DS);


# ---------------------------------------------------------- > Настраиваем интерпритатор

error_reporting(E_ALL | E_NOTICE);
ob_implicit_flush(1);
set_time_limit(0);
ini_set('display_errors',           True);
ini_set('register_argc_argv',       True);
ini_set('log_errors',               True);
ini_set('log_errors_max_len',       1024);
ini_set('error_log',                APP_ROOT. 'PhProxyLog.txt');

# ---------------------------------------------------------- > Подключение необходимых файлов

require CT_APP_ROOT_SRC . 'configs.php';  
require CT_APP_ROOT_SRC . 'defines.php';  
require CT_APP_ROOT_SRC . 'functions.php';

# ---------------------------------------------------------- > Инициализация

// Логируем инициализацию
event();
event('Попытка запуска программы...');
event(' -- Версия PhProxy: ['.version().']');
event(' -- Версия PHP: ['.PHP_VERSION.']');
event(' -- Версия ОС: ['.@php_uname().']');
event(' -- Путь запуска: ['.APP_ROOT.']');

    // проверяем загруженно ли расширение WinBindera
    if (!extension_loaded('winbinder')) {
        error_fatal('Расширение дла работы с WinBinder не найденно!');
    } else {
        event('Расширение для работы с WinBinder найденно и загруженно успешно!');
    }

    // Подключаем необходимые файлы WB
    if (!include CT_APP_ROOT_SRC . 'winbinder' . DS . 'winbinder.php') {
        error_fatal('Ошибка при загрузке системных WB файлов!');
    } else {
        event('Системные WB файлы загруженны успешно!');
    }
    
// подключаем GUI класс и класс сетевой логики приложения
require CT_APP_ROOT_SRC . 'classes' . DS . 'wb_window.class.php';
require CT_APP_ROOT_SRC . 'classes' . DS . 'application.class.php';

event('Инициализация: успешно заверешенна!');


# ---------------------------------------------------------- > Поехали!

// Создаем экземпляр класса сетевой логкики
$phproxy = new PhProxy_Net();

    // проверяем наличие необходимого нам класса GUI
    if (!class_exists('WB_Window')) {
        error_fatal('Не найден класс GUI в контексте приложения!');
    } else {
        event('Класс GUI успешно подключен!');
    }


// Создаем каркас главного окна
event('Начало создания GUI интерфейса...');

$w_main = new WB_Window(0, AppWindow, WBC_TASKBAR | WBC_INVISIBLE);
    $w_main->title(version(cfg_get('gui_mainwin_title')));
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

/*// Создаем жирный шрифт
$phroxy->gui_bold_font = wb_create_font("Tahoma", 8, BLACK, FTA_BOLD);

// Создаем основу статистики
#wb_set_font(wb_create_control($w_main->wobj, Label, 'PhProxy-Сервер:', 10, 200, 110,  15, 100), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label, 'Время работы:',   10, 215, 110,  15, 101), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label, 'Соеденений всего:', 10, 230, 110,  15, 102), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label, 'Сервер:',        10, 245, 100,  15, 103), $phroxy->gui_bold_font);

// второй блок
#wb_set_font(wb_create_control($w_main->wobj, Label,   'Авторизация:',   240, 200, 100,  15, 104), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label,  'PhProxy-UpTime:',   240, 215, 100,  15, 105), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label,  'PhProxy-Сервер:',  240, 230, 100,  15, 106), $phroxy->gui_bold_font);
#wb_set_font(wb_create_control($w_main->wobj, Label,  'Сервер:',          240, 245, 100,  15, 107), $phroxy->gui_bold_font);

// создаем параметры статистики
#$phproxy->gui_server_state_ctrl =        wb_create_control($w_main->wobj, Label, '~', 125, 200, 100,  15, 110);
#$phproxy->gui_server_uptime_state_ctrl = wb_create_control($w_main->wobj, Label, '~', 125, 215, 100,  15, 111);
#$phproxy->gui_server_incom_state_ctrl =  wb_create_control($w_main->wobj, Label, '~', 125, 230, 100,  15, 111);

#$phproxy->gui_auth_state_ctrl   = wb_create_control($w_main->wobj, Label,   '~', 355, 200, 100,  15, 110);
#$phproxy->gui_uptime_state_ctrl = wb_create_control($w_main->wobj, Label, '~',   125, 215, 100,  15, 110);

// создаем системный таймер обновляющий статистику
#$phproxy->system_timer = wb_create_timer($w_main->wobj, ID_SYSTEM_TIMER, APP_SYSTEM_TIMER_INT);
#app_refresh_info();
 *
 */
event('GUI окно успешно созданно...');

// Включаем обработку событий для окна
wb_set_handler($w_main->wobj, 'phproxy_eventsHandler');

// запускаем клиентский таймер
$phproxy->client_timer = wb_create_timer($w_main->wobj, ID_CLIENT_TIMER, cfg_get('net_client_timer'));

// показываем окно и уходим в цикл
$w_main->visible(1); $w_main->loop();

?>