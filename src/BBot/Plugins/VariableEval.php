<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * VariableEval plugin - defines pseudo function variableEval. This will be called on each matched node to evaluate any conditional defined in the flow
 */
class VariableEval
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot) {
        $bbot->registerDotFlowFunction('variableEval', [__CLASS__, 'match']);
    }
    
    
    public function match($pre)
    {
        $varName = $pre->varName;
        $op = $pre->op;
        $value = $pre->value;

        $varValue = $this->bbot->userData->getVar($varName);
        
        Log::bbotcore()->debug("There is a conditional, evaluating varname: '$varName' with value: '$varValue' op: '$op' against value: '$value'");
        switch ($op) {
            case 'eq':
                $ret = strtolower(trim($varValue)) === strtolower(trim($value));
                break;
            case 'neq':
                $ret = strtolower(trim($varValue)) !== strtolower(trim($value));
                break;
            case 'gt':
                $ret = is_numeric($varValue) ? $varValue > $value : null;
                break;
            case 'lt':
                $ret = is_numeric($varValue) ? $varValue < $value : null;
                break;
        }
        Log::bbotcore()->debug("Conditional evaluated as " . ($ret ? "TRUE" : "FALSE"));
        return $ret;
    }

}
