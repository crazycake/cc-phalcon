<?php
/**
 * CLI Task Controller: provides common functions for CLI tasks.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Core;

//imports
use Phalcon\CLI\Task;

class TaskController extends Task
{
    /**
     * Print Output with Colors
     * @access protected
     * @param  string $text
     * @param  string $status Can be OK, ERROR, WARNING OR NOTE
     * @return string
     */
    protected function _colorize($text, $status)
    {
        $open  = "";
        $close = "\033[0m";
        
        switch ($status) {
            case "OK":
                $open = "\033[92m";     //Green color
                break;
            case "ERROR":
                $open = "\033[91m";     //Red color
                break;
            case "WARNING":
                $open = "\033[93m";     //Yellow color
                break;
            case "NOTE":
                $open = "\033[94m";     //Blue color
                break;
            default:
                throw new Exception("CoreTask:_colorize -> invalid status: " . $status);
        }
        //return outout, chr(27 ) -> escape key
        return chr(27) . $open . $text . chr(27) . $close . "\n";
    }
}
