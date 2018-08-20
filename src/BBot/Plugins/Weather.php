<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * Weather plugin - this defined .flow function weather
 */
class Weather
{

    private $bbot;
    
    private $cachedWeather;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot)
    {
        $bbot->registerDotFlowFunction('weather', [__CLASS__, 'getWeather']);
    }

    /**
     * Returns current weather
     * 
     * @param type $location
     * @return type
     * @throws \BBot\Exception
     */
    public function getWeather($location)
    {
        if (isset($this->cachedWeather[$location])) {//runtime cache, just in case the function is called multiple times from template (we might want to migrate to persistent cache with short ttl for this)
            return $this->cachedWeather[$location];
        }
        
        if ($location) {
            Log::bbotcore()->debug("Getting weather");
            $st = $this->searchText($location);
      
            //get locationkey needed to get weather
            $locationKey = $st[0]->Key;//get first result. flow1 doesnt allow us to handle desambiguation flow. will be sopported by flow2.0
   
            //building a simple canonical location
            $canonicalLocation = $st[0]->LocalizedName . ', ' . $st[0]->Country->LocalizedName;
            
            if(!$locationKey) {
                return ['text' => '<Invalid location>'];//flow1 do not support flow for handling invalid locations, we are forced to return like this for now. flow2 will fix this
            }
            
            $accuweatherApikey = \BBot\Config::get('accuweatherAPI')['apikey'];
            $aw = file_get_contents("http://dataservice.accuweather.com/currentconditions/v1/$locationKey?apikey=$accuweatherApikey&details=false");
            $awResponse = json_decode($aw);
            
            $r = array();
            $r['text'] = $awResponse[0]->WeatherText;
            $r['canonicalLocation'] = $canonicalLocation;
            
            $this->cachedWeather[$location] = $r;
            return $r;
        } else {
            throw new \BBot\Exception("API call error");
        }
    }

    /**
     * return accuweather location key
     * 
     * @param type $location
     * @return type
     */
    public function searchText($location)
    {
        //get locationkey based on provided location
        Log::bbotcore()->debug("Weather requested: getting location key");
        $location = urlencode($location);
        $accuweatherApikey = \BBot\Config::get('accuweatherAPI')['apikey'];
        $aw = file_get_contents("http://dataservice.accuweather.com/locations/v1/search?apikey=$accuweatherApikey&q=$location");
        $awResponse = json_decode($aw);
        //Log::bbotcore()->debug("Response: " . $aw);
        
           return $awResponse;
    }

}
