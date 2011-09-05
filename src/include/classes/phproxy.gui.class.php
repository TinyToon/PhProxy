<?PHP
/** 
 * Phproxy Client
 * 
 * PHP 5.3
 * 
 * @package   PhProxy_Client
 * @author    Alex Shcneider <alex.shcneider at gmail dot com>
 * @copyright 2010-2011 (c) Alex Shcneider
 * @license   license.txt
 * @link      http://github.com/Shcneider/PhProxy (sources, binares)
 * @link      http://vk.shcneider.in/forum (binares, support)
 * @link      http://alex.shcneider.in/ (author)
 **/

/**
 * PhProxy GUI Layer
 */
class PhProxy_GUI {
  
// Class configs
# ---------------------------------------------------------- >

    // main windows default size
    private $main_default_w = 315;
    private $main_default_h = 330;     

// RunTime Params
# ---------------------------------------------------------- >

    // Main Windows handler
    private $wObject = 0; 
    
    // target for created controlls
    private $wTarget = 0;
    private $nTarget = 0;
    
    // main window title
    private $main_title = '';

    // main window coord
    private $main_x, $main_y = 0;

    // main window size
    private $main_w, $main_h = 0;
    
    
    // status bar controller ID
    private $statusbar = 0;
    
    
    // save controlls id here
    private $controlls = array();
    
    // save fonts here
    private $fonts = array();
    
    // handlers
    private $handlers = array();

    
// Magic Methods
# ---------------------------------------------------------- >

    // Build main window
    public function __construct($title = 'PhProxy', $type = AppWindow, $style = null, $params = null)
    {
        // set title
        $this->main_title = $title;

        // Build main window
        $this->wObject = wb_create_window(0, $type, $title, WBC_CENTER, WBC_CENTER, $this->main_default_w, $this->main_default_h, $style, $params);
        PhProxy::event('Window was builded: ' . $this->wObject);
        return true;
    } 
    

    // window handler setter
    public function handler_set($handler, $id = 0)
    {     
        return wb_set_handler($this->wObject, $handler);
    }
    
     
    
// Window managment Methods
# ---------------------------------------------------------- > 
  
    // get resource of this window
    public function get_wobj()
    {
        return $this->wObject;
    }
    
    // set target for created controlls
    public function set_target($target, $ntab = 0)
    {
        $this->nTarget = $ntab;
        $this->wTarget = $target;
    }
    
    // main loop start
    public function loop()
    {
        if (!$this->wObject) { // if not builded - loop is not need
            return false;
        }

        wb_main_loop();
    }
    
    // Set/Get title of Window
    public function title($title = null)
    {
        if (!$this->wObject) { // window don't was builded earlier
            $return = ($title) ? $this->main_title = $title : false;
            return $return;
        }

        // set title
        if ($title) {
            return wb_set_text($this->wObject, $title);
        }

        return wb_get_text($this->wObject, $title);
    }
    
    // Set/Get coords of Window
    public function position($x = -1, $y = -1)
    {
        // sizes is not set
        if ($x == -1 && $y == -1) {
            if (!$this->wObject) { // and not builded
                return array($this->main_x, $this->main_y);
            }
            return wb_get_position($this->wObject);
        }
        
        $x = ($x == -1) ? WBC_CENTER : $x;
        $y = ($y == -1) ? WBC_CENTER : $y;

        if (!$this->wObject) { // window wasn't builded early
            $this->main_x = $x; $this->main_y = $y;
            return true;
        }

        return wb_set_position($this->wObject, $x, $y);
    }
    
    // Set/Get size of Window
    public function size($x = -1, $y = -1)
    {
        if ($x == -1 && $y == -1) {
            if (!$this->wObject) {
                return array($this->main_w, $this->main_h);
            }
            return wb_get_size($this->wObject);
        }

        $x = ($x == -1) ? WBC_NORMAL : $x;
        $y = ($y == -1) ? WBC_NORMAL : $y;

            if (!$this->wObject) {
                $this->main_w = $x; $this->main_w = $y;
                return true;
            }

        return wb_set_size($this->wObject, $x, $y);
    }
    
    // Set icon of Window
    public function icon($icon = null)
    {
        if (!$this->wObject) {
            return $this->main_icon = $icon;
        }
        
        return wb_set_image($this->wObject, $icon);
    }
        
    // Set visible window
    public function visible($bool)
    {     
        return wb_set_visible($this->wObject, $bool);
    }
    
    // set focus
    public function focus()
    {
        return wb_set_focus($this->wObject);
    }
    
    // create/destroy timer
    public function timer($id, $int = 0)
    {
        if ($int > 0) { // create
            return wb_create_timer($this->wObject, $id, $int);
        } else { // destroy
            return wb_create_timer($this->wObject, $id);
        }
    }

    
// FONTS-MANAGMENT METHOD BEGIN
# --------------------------------------------------->

    // Create a new font
    public function font_new($family, $size, $color, $flag, $id)
    {
        if (!isset($this->fonts[$id])) {
            return $this->fonts[$id] = wb_create_font($family, $size, $color, $flag);
        }

        return $this->fonts[$id];
    }

    // get font wobj
    public function font_get($id)
    {
        return (isset($this->fonts[$id]) ? $this->fonts[$id] : 0);
    }
    
  
    
// CONTROLS-MANAGMENT METHOD BEGIN
# --------------------------------------------------->
 
    
    // create a controll
    private function _controlCreate($type, $caption = '', $coord = array(0, 0), $size = array(0, 0), $id = 0, $style = null, $param = null)
    {
        // random ID
        if (!$id) {
            $id = rand(1000, 9999);
        }

        $ret =  wb_create_control($this->wTarget, $type, $caption, $coord[0], $coord[1], $size[0], $size[1], $id, $style, $param, $this->nTarget);
            if ($ret) { // remember control
                PhProxy::event(__CLASS__ . ' - Controll with ID #'.$id.' was created!');
                return $this->controlls[$id] = $ret;
            }

        $phproxy = PhProxy::getInstance();
        PhProxy::fatal(__CLASS__ . ' - ' . sprintf($phproxy->lang->get('gui', 'Error1'), $id));
        return false;
    }
 

    // Create a image control
    public function image($src, array $coord, array $size, $id = 0)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }
        // Creating
        $image = $this->_controlCreate(Frame, '', $coord, $size, $id, WBC_IMAGE);
        wb_set_image($image, $src);
        
        return $image;
    }
    
    // create a new inputBox
    public function input($caption, array $coord, array $size, $id, $style = null, $font = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->_controlCreate(EditBox, $caption, $coord, $size, $id, $style);

            // if not null $font - set $font to this control
            if ($font) {
                wb_set_font($ctrl, $this->font_get($font));
            }

        return $ctrl;
    }
    
    // create a new Label
    public function label($caption, array $coord, array $size, $id, $style = null, $font = null, $param = 0)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->_controlCreate(Label, $caption, $coord, $size, $id, $style, $param);

            // if not null $font - set $font to this control
            if ($font) {
                wb_set_font($ctrl, $this->font_get($font));
            }

        return $ctrl;
    }
    
    // create a new PushButton
    public function button($caption, array $coord, array $size, $id, $style = null, $font = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->_controlCreate(PushButton, $caption, $coord, $size, $id, $style);

            // if not null $font - set $font to this control
            if ($font) {
                wb_set_font($ctrl, $this->font_get($font));
            }

        return $ctrl;
    }
    
    // create a new hyperlink
    public function hyperlink($caption, array $coord, array $size, $id, $style = null, $color = null, $font = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->_controlCreate(HyperLink, $caption, $coord, $size, $id, $style, $color);

            // if not null $font - set $font to this control
            if ($font) {
                wb_set_font($ctrl, $this->font_get($font));
            }

        return $ctrl;
    }
    
    // Create and edit StatusBar control
    public function statusBar($arr)
    {
        if (!$this->statusbar) {
            $this->statusbar = $this->_controlCreate(StatusBar, '');
        }

        wb_create_items($this->statusbar, $arr);

        // re-write all cells in statusbar (can be troubles with encoding)
        $c = sizeof($arr);

            for ($i=0; $i < $c; $i++) { // foreach dont work in .exe
                $txt = array_key_exists(0, $arr[$i]) ? $arr[$i][0] : 'Empty';
                wb_set_text($this->statusbar, $txt, $i);
            }

        return $this->statusbar;
    }

  
    // destroying of a control with ID
    public function c_destroy($id)
    {
        if (!isset($this->controlls[$id])) {
            PhProxy::event(__CLASS__ . ' - Try to destroy not-exists conntrol with ID #'.$id);
            return false;
        }

        wb_destroy_control($this->controlls[$id]);
        unset($this->controlls[$id]);
        return true;
    }
    
    
    // controlls handler setter
    public function c_handler_set($id, $handler)
    {
        $this->handlers[$id] = $handler;
        return true;
    }
    
    // handler start
    public function c_handler_invoke($id, $con = 0, $param1 = 0, $param2 = 0)
    {
        if (array_key_exists($id, $this->handlers)) { // call function
            return $this->handlers[$id]();
        }
        // write notice
        PhProxy::event('GUI EVENT: Unkown event ID - ' . $id);
        return false;   
    }   
    
    // set focus
    public function c_focus($id)
    {
        if (!isset($this->controlls[$id])) {
            return false;
        }
        return wb_set_focus($this->controlls[$id]);
    }
    
    // get text from control
    public function c_text_get($id)
    {
        // controll is not exists
        if (!isset($this->controlls[$id])) {
            PhProxy::fatal(__CLASS__ . ' - ' . sprintf(PhProxy::getInstance('lang')->get('gui', 'Error2'), $id));
        }
        return wb_get_text($this->controlls[$id]);
    }
    
    // set text of controll
    public function c_text_set($id, $text)
    {
        if (!isset($this->controlls[$id])) {
            return false;
        }
        
        return wb_set_text($this->controlls[$id], $text);
    }
    
    // set enabled/disabled on control
    public function c_enabled($id, $bool)
    {
        if (!isset($this->controlls[$id])) {
            return false;
        }

        return wb_set_enabled($this->controlls[$id], $bool);
    }
    
    // Set visible of controll
    public function c_visible($id, $bool = 1)
    {     
        if (!isset($this->controlls[$id])) {
            return false;
        }
        
        return wb_set_visible($this->controlls[$id], $bool);
    }
    
    // 
    public function c_size($id, $x, $y)
    {
        if (!isset($this->controlls[$id])) {
            return false;
        }

        return wb_set_size($this->controlls[$id], $x, $y);
    }
    
    // 
    public function c_position($id, $x, $y)
    {
        if (!isset($this->controlls[$id])) {
            return false;
        }

        return wb_set_position($this->controlls[$id], $x, $y);
    }
    
    
    
}

/*


/*
// Winbinder Window's class
class PhProxy_Window {

    // destroy a window
    public function destroy()
    {
        return wb_destroy_window($this->wobj);
    }
    
 # --------------------------------------------------->



    

    


    

    // Create a frame control
    public function frame($caption, array $coord, array $size, $id = 1, $style = null, $font = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }
        // Creating
        $cntrl = $this->controlCreate(Frame, $caption, $coord, $size, $id, $style);
            if ($font) { // if not null $font - set $font to this control
                wb_set_font($cntrl, $this->fontGet($font));
            }
        return $cntrl;
    }
    
   

    // creating a tabcontrol
    public function tabs(array $coord, array $size, $id, $font = null, $elems = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->controlCreate(TabControl, '', $coord, $size, $id);
            if ($elems) {
                wb_create_items($ctrl, $elems);
            }  if ($font) { // if not null $font - set $font to this control
                wb_set_font($ctrl, $this->fontGet($font));
            }
            
        return $ctrl;
    }

    // create a browser
    public function browser(array $coord, array $size, $id, $loc = null)
    {
        // Default values
        if (sizeof($coord) < 2) {
            $coord = array(0, 0);
        } if (sizeof($size) < 2) {
            $size = array(50, 50);
        }

        // Creating
        $ctrl = $this->controlCreate(HTMLControl, '', $coord, $size, $id);
            if ($loc) {
                wb_set_location($ctrl, $loc);
            }

        return $ctrl;
    }


}
 * 
 */

?>