<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * Microsoft congnitive services - Luis API client
 */
class MSCognitiveAPILuisClient
{
    /**
     * Returns Luis api response
     * 
     * @param string $query User input to be scored for intents
     * @param array $opt Some options
     * @return array API response
     */
    public static function request($query, $opt = null)
    {
        //this data scope is global to the platform
        $domainZone = Config::get('msCognitiveAPI')['domainZone'];
        $subscriptionKey = Config::get('msCognitiveAPI')['subscriptionKey'];
        
        $query = urlencode($query);
        $url = "https://$domainZone/luis/v2.0/apps/{$opt['appId']}?subscription-key=$subscriptionKey&verbose={$opt['verbose']}&timezoneOffset={$opt['timezoneOffset']}&q={$query}";
        $response = file_get_contents($url);
        return $response;
    }

}
