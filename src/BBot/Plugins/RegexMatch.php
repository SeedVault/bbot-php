<?php

namespace BBot\Plugins;

/**
 * RegexMatch plugin - defined .flow function regexMatch to match with patterns based on regular expresions
 */
class RegexMatch
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    
    public static function init($bbot) {
        $bbot->registerDotFlowFunction('regexMatch', [__CLASS__, 'match']);
    }
    

    /**
     * Matches the 'pattern' with the user input
     * 
     * @param string $pattern Pattern rule
     * @param string $input User input
     * @return boolean TRUE if it match, FALSE if not
     */
    public function match($pattern, $input)
    {
        $cap = array();
        $r = preg_match($pattern, $input, $cap);

        if ($r === 1) {//matched
            //check for named capture and set var
            if ($cap && count($cap)) {
                foreach ($cap as $varName => $value) {
                    if (!is_numeric($varName)) {//only named captures. nonnamed captures are not going to be saved
                        $this->bbot->userData->setVar($varName, $value);
                    }
                }
            }
            return true;
        } else if ($r === 0) {//not match
            return false;
        } else if ($r === false) {//there is an error
            throw new \BBot\Exception("Bad regex pattern $pattern");
        }
        return false;
    }

}
