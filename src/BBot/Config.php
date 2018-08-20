<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * Standalone configuration class
 * Handles platform configuration
 */
class Config {
    
    /**
     * Holds configuration
     *
     * @var type 
     */
    private static $config;
        
    /**
     * Returns configuration value
     * 
     * @param string $key Entry from configuration
     * @return mixed Any value from config
     * @throws \BBot\Exception
     */
    public static function get($key) {
        if (!self::$config) {
            self::load();
        }
        
        if (isset(self::$config[$key])) {            
            return self::$config[$key];
        }
        
        throw new \BBot\Exception("Missing config key $key");
        
    }
    
    /**
     * Sets whole configuration
     * 
     * @param array $config
     */
    public static function set($config) {
        self::$config = $config;
    }
    
    /**
     * Loads config from default location. currently config.php file 
     * It will be migrated to db at some point and leaving just db credentials in text file and other stuff
     * 
     * @return boolean
     * @throws \BBot\Exception
     */
    private static function load() {
        self::$config = include('../../config.php');
        
        if (!is_array(self::$config)) {
            throw new \BBot\Exception("Bad config file");
        }
        return true;
    }
    
    /**
     * Loads resources based on config 
     * 
     * @param string $entry entry from configuration
     * @param string|int $resourceId will be used to interpolate id from the uri defined in config
     */
    public static function getRepositoryData($entry, $resourceId)
    {
        $uri = Config::get($entry)['uri'];
        $parsedUri = parse_url($uri);
        
        if ($parsedUri['scheme'] == 'file' || $parsedUri == 'http') {
            //using restful adapter 
            $uri = str_replace("{id}", $resourceId, $uri);
            Log::bbotcore()->debug('Loading ' . $entry . ' from: ' . $uri);
            
            if ($parsedUri['scheme'] == 'file') {//file_get_contents doesnt work with file scheme
                $uri = str_replace('file://', '', $uri);
            }
         
            $f = json_decode(file_get_contents($uri));
            if (!$f) {
                throw new \BBot\Exception('Empty uri: ' . $uri);
            }
        }


        return $f;
    }

    
}
