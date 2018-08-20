<?php

namespace BBot\Plugins;

/**
 * PlainTextEqMatch plugin - defined .flow function plainTextEqMatch to match based on plain text equal
 */
class PlainTextEqMatch
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }
    
    public static function init($bbot) {
        $bbot->registerDotFlowFunction('plainTextEqMatch', [__CLASS__, 'match']);
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
        if (strtolower($pattern) === strtolower(trim($input))) {
            return true;
        }
        return false;
    }

}
