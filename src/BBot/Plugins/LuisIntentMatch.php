<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * luisIntentMatch plugin - defined luisIntentMatch .flow function
 */
class LuisIntentMatch
{

    private static $lastInput = array();
    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    
    public static function init($bbot) {
        $bbot->registerDotFlowFunction('luisIntentMatch', [__CLASS__, 'match']);
    }
    

    public function match($pattern, $input, $opts)
    {
        if (isset(self::$lastInput[$input])) {
            return self::$lastInput[$input];
        }


        $entityMatchingScore = 0.75;//@TODO hardcoded for now. flow2.0 will handle this
        $intentMatchingScore = 0.75;


        $opt = [
            'appId' => $this->bbot->dotBot['msCognitiveAPI']['appId'],
            'verbose' => 'true', //we need it true to get all at once, reducing calls
            'timezoneOffset' => 0 //this should be based on enduser tz .. @TODO should  be a tz setting 
        ];

        $r = \BBot\MSCognitiveAPILuisClient::request($input, $opt);
        $r = json_decode($r, true);
        Log::bbotcore()->debug("LUIS RESPONSSE: " . print_r($r, 1));

        $matchedIntentKey = array_search($opts['intentLabel'], array_column($r['intents'], 'intent'));
        $matchedIntent = $r['intents'][$matchedIntentKey];
        Log::bbotcore()->debug("SCORE: " . $matchedIntent['score']);

        //check for entities. set vars
        //@TODO this works only with custom entities. Check what to do with bundled entities from luis (like date)
        Log::bbotcore()->debug(print_r($r['entities'], 1));
        if (count($r['entities'])) {

            foreach ($r['entities'] as $e) {
                if (!isset($e['score']) || $e['score'] >= $entityMatchingScore) {
                    //set var
                    $this->bbot->userData->setVar($e['type'], $e['entity']);
                }
            }
        }

        //check if it has good score
        if ($matchedIntent['score'] >= $intentMatchingScore) {

            //check if it's the topScore
            if ($r['topScoringIntent']['intent'] == $opts['intentLabel']) {//it's the top scoring, so we can return true, no need to search for more higher scores
                self::$lastInput[$input] = true;
                return true; //@TODO we will need to get score value for debugging.. so may be we should store it somewhere to get it with another method
            }
        }
        
        //it's not the top score so return score
        self::$lastInput[$input] = false;
        return false;
    }

}
