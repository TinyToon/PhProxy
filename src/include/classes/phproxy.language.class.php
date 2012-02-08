<?PHP
/**
 * Phproxy Client
 * 
 * PHP 5.3 !!ONLY!!
 * 
 * @package   PhProxy_Client
 * @author    Alex Shcneider <alex.shcneider@gmail.com>
 * @copyright 2010-2011 (c) Alex Shcneider
 * @license   license.txt
 * @version   2.1.8 Beta
 * @link      http://github.com/Shcneider/PhProxy   (sources, binares)
 * @link      http://pproxy.ru/forum                (binares, support)
 * @link      http://vk.com/shcneider               (author @ vk.com)
 * @link      http://vseti.by/shcneider             (author @ vseti.by)
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