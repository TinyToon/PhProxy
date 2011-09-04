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
 * Data storage based on .ini files
 */
class PhProxy_Storage_INI {

    // loaded file
    private $_file = null;

    // registry
    private $_registry = array();


// -------------------------------------------> METHODS

    // Load and parse file
    public function __construct($file, $buildin = false)
    {
        // set .ini file
        $this->_file = $file;
        
            // @TODO - Don't safe for internal (shared RES) files
            if (!file_exists($this->_file)) {
                return PhProxy::fatal('File "'.$this->_file.'" is not exists.');
            }
            
        // get file
        $raw_ini = PhProxy::file_load($this->_file);
            if ($raw_ini === false) {
                return PhProxy::fatal('Cannot read "'.$this->_file.'" file.');
            }

        // parse ini
        $ini = $this->_parse_ini($raw_ini, 0); 
        $recs = 0; // total records

            // add in registry 
            foreach ($ini as $section => $data)
            {
                if (!array_key_exists($section, $this->_registry)) { // if section is not exists
                    $this->_registry[$section] = array(); // add a empty array in registry
                }
                
                foreach ($data as $key => $value)
                {
                    $this->_registry[$section][$key] = str_replace('[RN]', "\r\n", $value);
                    $recs++;
                }
            }
            
        // debug
        PhProxy::event('File '.$file.' parsed, '.$recs.' records found!]');
            return true;
    }


    // return some key $key from section $section
    public function get($section, $key)
    {
        if (!array_key_exists($section, $this->_registry)) {
            PhProxy::fatal('Called key ['.$key.'] from not-exists section ['.$section.']!');
        } if (!array_key_exists($key, $this->_registry[$section])) {
            PhProxy::fatal('Called not-exists key ['.$key.'] from  section ['.$section.']!');
        }
        PhProxy::event('Option ['.$key.'] from section ['.$section.'] was requested!');
        return $this->_registry[$section][$key];
    }

    /*******************************************************************************

     Part of WINBINDER - The native Windows binding for PHP for PHP

     Copyright © Hypervisual - see LICENSE.TXT for details
     Author: Rubem Pechansky (http://winbinder.org/contact.php)

     General-purpose supporting functions

    *******************************************************************************/
    private function _parse_ini($initext, $changecase=TRUE, $convertwords=TRUE)
    {
        $ini = preg_split("/\r\n|\n/", $initext);
        $secpattern = "/^\[(.[^\]]*)\]/i";
        $entrypattern = "/^([a-z_0-9]*)\s*=\s*\"?([^\"]*)?\"?\$/i";
        $strpattern = "/^\"?(.[^\"]*)\"?\$/i";

        $section = array(); $sec = '';

        // Predefined words
        static $words  = array("yes", "on", "true", "no", "off", "false", "null");
        static $values = array(   1,    1,      1,    0,     0,       0,   null);

        // Lines loop
        for($i = 0; $i < count($ini); $i++) {

            $line = trim($ini[$i]);

            // Replaces escaped double-quotes (\") with special signal /%quote%/
            if(strstr($line, '\"'))
                $line = str_replace('\"', '/%quote%/', $line);

            // Skips blank lines and comments
            if($line == "" || preg_match("/^;/i", $line))
                continue;

            if(preg_match($secpattern, $line, $matches)) {

                // It's a section
                $sec = $matches[1];

                if($changecase)
                    $sec = ucfirst(strtolower($sec));

                $section[$sec] = array();

            } elseif(preg_match($entrypattern, $line, $matches)) {

                // It's an entry
                $entry = $matches[1];

                if($changecase)
                    $entry = strtolower($entry);

                $value = preg_replace($entrypattern, "\\2", $line);

                // Restores double-quotes (")
                $value = str_replace('/%quote%/', '"', $value);

                // Convert some special words to their respective values
                if($convertwords) {
                    $index = array_search(strtolower($value), $words);
                    if($index !== false)
                        $value = $values[$index];
                }

                $section[$sec][$entry] = $value;

            } else {

                // It's a normal string
                $section[$sec][] = preg_replace($strpattern, "\\1", $line);

            }
        }

        return $section;
    }



  
}

/*
 *
function generate_ini($data, $comments="")
{
	if(!is_array($data)) {
		trigger_error(__FUNCTION__ . ": Cannot save INI file.");
		return null;
	}
	$text = $comments;
	foreach($data as $name=>$section) {
		$text .= "\r\n[$name]\r\n";

		foreach($section as $key=>$value) {
			$value = trim($value);
			if((string)((int)$value) == (string)$value)			// Integer: does nothing
				;
			elseif((string)((float)$value) == (string)$value)	// Floating point: does nothing
				;
			elseif($value === "")								// Empty string
				$value = '""';
			elseif(strstr($value, '"'))							// Escape double-quotes
				$value = '"' . str_replace('"', '\"', $value) . '"';
			else
				$value = '"' . $value . '"';

			$text .= "$key = " . $value . "\r\n";
		}
	}
	return $text;
}


 */

?>