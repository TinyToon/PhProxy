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

// ------------------------------------------------------------- >> PHPROXY



// функция закрытия программы
function phproxy_stop()
{
    global $w_main, $phproxy;
    event('Попытка завершения работы программы по требованию пользователя...');

        // завершаем работу логики
        if ($phproxy->socket) {
            return error('Перед выходом из программы Вы должны остановить PhProxy-сервер!');
        }

    // завершаем работу GUI
    $w_main->close();
    event('Выполнение программы успешно завершенно!');
}

// Попытка запуска сервера
function phproxy_server_start()
{
    global $phproxy, $w_main;

    // эвент
    event('Попытка запуска PhProxy сервера с графического интерфейса...');
    $w_main->status('Попытка запуска PhProxy сервера...');

    // пробуем запустить сервер
    $result = $phproxy->server_start();
        if (!$result) { // Запуск сервера не удался
            event('Попытка запуска сервера завершилась неудачей:');
            return error('Ошибка: '.$phproxy->error);
        }

    event('Сервер успешно запущен!');
    $w_main->status('Локальный сервер успешно запущен!');

    
    // Пробуем авторизоваться
    event('Попытка авторизации на сервере...');


    // вручную прописываем данные, как будто после авторизации
    $phproxy->auth_state  = 1;
    $phproxy->auth_email  = 'guest@guest';
    $phproxy->auth_pass   = 'guest';
    $phproxy->auth_key    = 'auth_key';
    $phproxy->auth_expire = time()+180;
    
    event('Авторизация успешно пройденна!');

    
    // добавляем таймер реакции на события в сокете
    event('Создание таймера сокета...');
    $phproxy->socket_timer = wb_create_timer($w_main->wobj, ID_SOCKET_TIMER, cfg_get('net_server_socket_timer'));
        if (!$phproxy->socket_timer) {
            event('Ошибка при создании таймера!');
            error('Не удалось создать таймер. Прослушивание сокета не возможно!');
        } else {
            event('Таймер сокета успешно создан!');
        }

    wb_set_enabled($w_main->serverButtonStop, 1);
    wb_set_enabled($w_main->serverButtonStart, 0);

    $w_main->status('Сервер успешно запущен!');
    return true;
}

// попытка остановки сервера
function phproxy_server_stop()
{
    global $phproxy, $w_main;
    event('Попытка остановки PhProxy сервера с графического интерфейса...');
    $w_main->status('Пробуем остановить сервер...');

    $result = $phproxy->server_stop();
        if (!$result) {
           event('Попытка остановки сервера завершилась неудачей:');
           $w_main->status('Ошибка при остановке сервера!');
           return error('Ошибка: '.$phproxy->error);
        }

    $w_main->status('Сервер успешно остановлен!');
    event('Сервер успешно остановлен!');
    wb_set_enabled($w_main->serverButtonStop,  0);
    wb_set_enabled($w_main->serverButtonStart, 1);
    return true;
}



// ------------------------------------------------------------- >> ОШИБКИ, ЛОГИРОВАНИЕ, ДЕБАГГ

// функция логирования некого события
function event($txt = false)
{
    if ($txt == false) {
        $txt = "\r\n\r\n".str_repeat('-', 50)."\r\n\r\n";
    } else {
        $tx = 'EVENT - ['.$txt.']';
    }

    error_log($txt);
}

// произошла фатальная ошибка - пистарулю
function error_fatal($error)
{
    error_log('FATAL - ['.$error.']');
    error_gui($error.PHP_EOL.'Завершение работы...', ' - Произошла фатальная ошибка', WBC_STOP);
        exit;
}

function error($error)
{
    error_log('ERROR - ['.$error.']');
    error_gui($error, ' - Произошла ошибка', WBC_WARNING);
    return false;
}

// показываем графическое окошко с текстом
function error_gui($text, $title, $type)
{
    global $w_main;
        if (!function_exists('wb_message_box')) {
            return false;
        }

    if (isset($w_main) && isset($w_main->wobj)) {
        $ref = $w_main->wobj;
    } else {
        $ref = 0;
    }

    return wb_message_box($ref, $text, version('%an%/%avj%.%avn%.%avb%').$title, $type);
}

// ------------------------------------------------------------- >> НАСТРОЙКИ


// получаем параметр с настроек
function cfg_get($param)
{
    global $config;
        if (!isset($config[$param])) {
            error_fatal('Запроешенна несуществующая настрока ['.$param.']...');
        }
    return $config[$param];
}

// ------------------------------------------------------------- >> ПРОЧИЕ ФУНКЦИИ

// функция генерации версии по формату
function version($format = '%an%/%avj%.%avn%.%avb% %avs% (%avd%)')
{
    return str_replace(
        array('%an%', '%avj%', '%avn%', '%avb%', '%avs%','%avd%'),
        array(APP_NAME, APP_jVERSION, APP_nVERSION, APP_nBUILD, APP_sBUILD, APP_dBUILD),
        $format
    );
}

// создаем удобный таймштамп
function timer()
{
    list($s, $ms) = explode(" ",  microtime());
    return (float)$s + (float)$ms;
}


/*
// авторизация на сервере
function server_auth($timer)
{
    global $phproxy, $w_main;


    // нажата кнопка - готовимся к авторизации
    if (!$timer) {
        event('Попытка авторизации с графического интерфейса...');

            if ($phproxy->auth_state) {
                error('Вы уже авторизованны на сервере!'."\r\n".
                      'Для получения подробных сведений обратитесь к инструкции!');
                return false;
            }

            if ($phproxy->server_state) {
                error('Сервер запущен, а ты не авторизован...'."\r\n".
                      'Убей себя апстену, жывотнае!');
                return false;
            }

        // получаем мыло и пароль из форм
        $phproxy->auth_email = 'email';
        $phproxy->auth_pass  = 'password';

        // создаем таймер авторизации
        $phproxy->auth_timer = wb_create_timer($w_main->wobj, ID_AUTH_TIMER, APP_AUTH_TIMER_INT);

        // сбрасываем флаг прогресса авторизации
        $phproxy->auth_stage = 1;

        $w_main->status('Идет авторизация на сервере...');
        return true;
    }





    // дальше работа с таймером

        // это -1 - обработка ошибки
        if ($phproxy->auth_stage == -1) {
            $w_main->status('Ошибка при авторизации...');
            wb_destroy_timer($w_main->wobj, ID_AUTH_TIMER);
            error('Авторизоваться не удалось. Подробности в логе.');
            $phproxy->auth_stage = 0;
        }

        // этап 1 - установка соеденения
        if ($phproxy->auth_stage == 1) {
            $w_main->status('Попытка соеденения с сервером...');

            // создаем новое исходящее соеденение
            $phproxy->auth_cnx = $phproxy->client_open(cfg_get('net_remote_domain'), cfg_get('net_remote_port'), cfg_get('net_remote_timeout'));
                if (!$phproxy->auth_cnx) { // не удалось
                    $phproxy->auth_stage = -1;
                    return false;
                }

            $phproxy->auth_stage = 2;
            $w_main->status('Соеденение с серверов установленно...');
            return false;
        }


        // этап 2 - отправка данных
        if ($phproxy->auth_stage == 2) {
            $w_main->status('Попытка отправки данных...');

            // Кодируем данные
            $email  = base64_encode($phproxy->auth_email);
            $passMD = base64_encode(md5($phproxy->auth_pass));

            // собираем пост строку
            $post = 'act=auth&email='.$email.'&pass='.$passMD.'&version='.APP_nBUILD;

            // отправляем данные
            $result = $phproxy->client_send($phproxy->auth_cnx, $post);
                if (!$result) {
                    client_close($phproxy->auth_cnx);
                    $phproxy->auth_stage = -1;
                    return false;
                }

            $phproxy->auth_stage = 3;
            $w_main->status('Данные успешно отправленны...');
                return false;
        }


        // этап 3 - чтение данных
        if ($phproxy->auth_stage == 3) {
            $w_main->status('Получение ответа ['.$phproxy->auth_sub_stage.']...');

                // пока есть что читать
                if (!$data = $phproxy->client_read($phproxy->auth_cnx)) {
                    $phproxy->auth_stage = 4;
                    $w_main->status('Данные успешно прочитанны...');
                } else {
                    $phproxy->auth_data .= $data;
                    $phproxy->auth_sub_stage++;
                }

        }

        // этап 3 - чтение данных
        if ($phproxy->auth_stage == 4) {

            // закрываем соеденение
            $phproxy->client_close($phproxy->auth_cnx);

            // удаляем таймер
            wb_destroy_timer($w_main->wobj, ID_AUTH_TIMER);

            // парсим полученные данные
            $data = $phproxy->html_parse_response($phproxy->auth_data);
            // сбрасываем
            $phproxy->auth_stage = $phproxy->auth_sub_stage = 0; $phproxy->auth_data = '';

                if ($data === false) {
                    error('Ошибка при попытке авторизации!'."\r\n".'Подробности в логе...');
                    $w_main->status('Авторизация: Ошибка');
                    return false;
                }

            // ошибка автторизации
            if ($data['state'] == 'error') {
                if (!isset($data['error'])) {
                    $error = 'Неизвестная ошибка';
                } else {
                    $error = $data['error'];
                }

                $w_main->status('Авторизация: Ошибка');
                error('Ошибка авторизации:'."\r\n".$error);
                return false;
            }

            // непонятно (
            if ($data['state'] != 'ok') {
                $w_main->status('Авторизация: Ошибка');
                error('Ошибка авторизации:'."\r\n".'Не удалось понять ответ сервера...');
                return false;
            }

            // авторизация прошла успешно
            $phproxy->auth_key    = $data['authkey'];
            $phproxy->auth_expire = (int)$data['expire'];

            // флаг успешной авторизации
            $phproxy->auth_state = 1;


                // меняем кнопку авторизации на "выход"
                wb_set_text($w_main->serverButtonAuthDo, 'Выйти');


            $w_main->status('Авторизация: пройдена');
            return true;
        }


    return true;
}




// обновление системной информации
function app_refresh_info()
{
    global $phproxy;

    // Состояние сервера
    wb_set_text($phproxy->gui_server_state_ctrl, ($phproxy->server_state) ? 'Запущен' : 'Остановлен');


    // Время работы сервера
        if ($phproxy->server_started == 0) {
            $temp_time = time();
        } else {
            $temp_time = $phproxy->server_started;
        }
    wb_set_text($phproxy->gui_server_uptime_state_ctrl, gmdate('H:i:s', time()-$temp_time));

    // кол-во входящих
    wb_set_text($phproxy->gui_server_incom_state_ctrl, $phproxy->server_incoming);

    // состояние авторизации
    wb_set_text($phproxy->gui_auth_state_ctrl, ($phproxy->auth_state) ?     'Авторизован' : 'Не авторизован');


}





*/
?>