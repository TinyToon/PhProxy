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
 * Language manager
 */
final class PhProxy_Language extends PhProxy_Storage_INI {
    
    /**
     * Constructor. Call a parent contruct from PhProxy_Storage_INI
     * 
     * @param type $file path to lang file
     */
    public function __construct($file) 
    {
        PhProxy::event(__CLASS__ . ' new instance with file ['.$file.']!');
        parent::__construct($file);
    }
    
    /**
     * Deny to set lang value
     * @return false 
     */
    public function set()
    {
        return false;
    }
    
    /**
     * Deny to save re-written lang file
     * @return false
     */
    public function save()
    {
        return false;
    }
    
}


?>