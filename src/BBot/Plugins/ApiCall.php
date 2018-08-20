<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * ApiCall plugin - defined apiCall .flow function letting the botdev to do any http request to API restful web services
 */
class ApiCall
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot) {
        $bbot->registerDotFlowFunction('apiCall', [__CLASS__, 'getResponse']);
    }
    
    public function getResponse($node)
    {
        $response = $this->getApiCallResponse($node);
        $this->bbot->userData->setVar($node->info->responseVarName, $response ? $response : '');
        return $response;
    }

    public function getApiCallResponse($node)
    {
        
        $ch = curl_init();
        //curl_setopt($curl, CURLOPT_VERBOSE, true);
        if ($node->info->method == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else if ($node->info->method == 'get') {
            
        }

        $url = $this->bbot->interpolate($node->info->uri, function($t) {
            return urlencode($t);
        }); //botdev can interpolate variables. it will urlencode each var


        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


        Log::bbotcore()->debug("Running API call to uri: " . $url);

        $r = curl_exec($ch);

        Log::bbotcore()->debug("Response: " . $r);

        //@TODO check status repsonse 200/etc and show custom messages?
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode == 200) {
            $r = json_decode($r);
            return $r;
        } else {
            throw new \BBot\Exception("API call error (http code $httpCode) - $curlError");
        }

    }

}
