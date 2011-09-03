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



// Класс создания простого окна
class WB_Window {

// созданно?
var $builded = false;

// ссылка на окно
var $wobj = 0;

// родитель окна
var $parent = 0;

// тип окна
var $type = 0;

// заголовок окна
var $title = '';

// позиция Y и Y
var $pos_x, $pos_y =  0;

// Размеры
var $size_x, $size_y = 0;

// дополнительные флаги
var $flags = 0;

// иконка
var $icon = null;



// контролы - кнопкизапуска и остановки сервера
var $serverButtonStart = 0;
var $serverButtonStop  = 0;

// контрол  - статусбар
var $statusbar = 0;

// контролы - поля ввода дляавторизации
var $serverButtinAuthEmail = 0;
var $serverButtonAuthPass  = 0;

// контролы - кнопки авторизации и регистрации
var $serverButtonRegDo  = 0;


    // Конструктор окна
    function WB_Window($parent = 0, $type = 1, $flags = null)
    {
        $this->parent = $parent;
        $this->type   = $type;
        $this->flags  = $flags;
    }

    // создать окно
    function build()
    {
        // если окно уже собранно
        if ($this->builded) {
            return false;
        }

        $this->wobj = wb_create_window(
            $this->parent, $this->type, $this->title, $this->pos_x, $this->pos_y, $this->size_x, $this->size_y, $this->flags
         );

            if ($this->icon) {
                wb_set_image($this->wobj, $this->icon);
            }

        return $this->builded = true;
    }

    // цикл
    function loop()
    {
        // если нет окна, нет смысла в цикле
        if (!$this->builded) {
            return false;
        }

        wb_main_loop();
    }

    // Заголовок окна - установить или получить
    function title($title = null)
    {
        // если окно еще не было отрисованно
        if (!$this->builded) {
            $return = ($title) ? $this->title = $title : false;
            return $return;
        }

            // установка заголовка
            if ($title) {
                return wb_set_text($this->wobj, $title);
            }

        return wb_get_text($this->wobj, $title);
    }

    // установить или получить позицию
    function position($x = null, $y = null)
    {
        // если параметры не переданны
        if (!$x && !$y) {
            if (!$this->builded) {
                return array($this->pos_x, $this->pos_y);
            }
            return wb_get_position($this->wobj);
        }

        // если переданны частично - доводим до совершенства :)
        $x = (!$x) ? WBC_CENTER : $x;
        $y = (!$y) ? WBC_CENTER : $y;

            // если окно еще не построенно
            if (!$this->builded) {
                $this->pos_x = $x; $this->pos_y = $y;
                return true;
            }

        return wb_set_position($this->wobj, $x, $y);
    }

    // установить или получить размер
    function size($x = null, $y = null)
    {
        // если не все параметры переданны
        if (!$x && !$y) {
            if (!$this->builded) {
                return array($this->size_x, $this->size_y);
            }
            return wb_get_size($this->wobj);
        }

        // нормализуем
        $x = (!$x) ? WBC_NORMAL : $x;
        $y = (!$y) ? WBC_NORMAL : $y;

            // если окно еще не было построенно
            if (!$this->builded) {
                $this->size_x = $x; $this->size_y = $y;
                return true;
            }

        return wb_set_size($this->wobj, $x, $y);
    }

    // установить иконку
    function icon($icon = null)
    {
        // Если окно еще не созданно
        if (!$this->builded) {
            return $this->icon = $icon;
        }

        return wb_set_image($this->wobj, $icon);
    }

    // установка значения статусбара
    function status($text1)
    {
        wb_create_items($this->statusbar, array(
            array($text1, 340),
            array(' Alex Shcneider © 2009-2011 ')
        ));
    }

    // управление видимостью окна
    function visible($bool)
    {
        return wb_set_visible($this->wobj, $bool);
    }

    // уничтожить окно
    function close()
    {
        wb_destroy_window($this->wobj);
    }

}





?>