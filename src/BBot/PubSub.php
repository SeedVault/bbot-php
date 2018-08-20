<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * This will be used by plugins to hook into bbot events
 */
class PubSub
{
    
    private static $subs = [];
        
    public static function subscribe($eventName, $callback)
    {        
        if (!isset(self::$subs[$eventName])) {
            self::$subs[$eventName] = [];
        }
        self::$subs[$eventName][] = $callback;
    }
    
    public static function publish($eventName, ...$args)
    {                
        Log::bbotcore()->debug("Event call: $eventName");
        
        if (!isset(self::$subs[$eventName])) {
            //Magic variables will fire events with no subscriptors
            //throw new \BBot\Exception("Event '{$eventName}' with no subscriptors");  
            Log::bbotcore()->debug("Event '{$eventName}' with no subscriptors");        
            return false;
        }
        
        $response = array();
        foreach (self::$subs[$eventName] as $callback) {
            $response[get_class($callback[0])] = $callback(...$args);            
        }
        
        return $response;
    }
}