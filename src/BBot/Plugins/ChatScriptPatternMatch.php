<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * ChatScriptPatternMatch pluging - defines chatscript .flow function
 * 
 */
class ChatScriptPatternMatch
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }
    
    public static function init($bbot) {
        $bbot->registerDotFlowFunction('chatscript', [__CLASS__, 'match']);
    }    
    
    /**
     * Try to match user input with pattern rule
     * 
     * @param string $pattern ChatScript pattern rule from json flow
     * @param string $input User input
     * @return boolean TRUE if it match, FALSE if not
     */
    public function match($pattern, $input)
    {
        if (strlen($input) == 0) {
            return false;
        }

        $cs = new \BBot\ChatScriptClient();

        $ret = $cs->testPattern($pattern, $input);//@TODO this is slow. Should be sending user input and test matching with ^match. Also we get values from patterns with it
        Log::bbotcore()->debug("Trying pattern match: $pattern");
        //check if it matched
        if ($ret[1] == 'Matched') {
            Log::bbotcore()->debug("Pattern $pattern Matched!");
            return true;
        }
        $debug = $ret;
        unset($debug[0]);
        unset($debug[1]);

        //@TODO add :trace all flag
        Log::bbotcore()->debug("Match failed: pattern $pattern\n" . implode("\n", $debug));

        return false;
    }

}
