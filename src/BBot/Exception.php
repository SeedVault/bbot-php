<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * This will be used to distinguish bbot logical exceptions from platform exceptions
 */
class Exception extends \Exception
{

    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        Log::bbotcore()->alert($message);
        return parent::__construct($message, $code, $previous);                
    }

}
