<?php

namespace BBot\Plugins;

use Monolog\Registry as Log;

/**
 * Text plugin - defines .flow function text
 */
class Text
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot)
    {
        $bbot->registerDotFlowFunction('text', [__CLASS__, 'pluginGetOutput']);
    }

    public function pluginGetOutput($node)
    {
        $output = $this->getOutput($node);
        foreach ($output as $o) {
            $this->bbot->setOutput('text', $o);
        }
    }

    public function getOutput($node)
    {
        $output = array();
        //regular msg
        if (isset($node->msg)) {
            if (count($node->msg) > 1) {
                $output[] = $node->msg[rand(0, count($node->msg))]; //@TODO avoid repetitive output on random (flow2.0)
            } else if (count($node->msg)) {
                $output[] = $node->msg[0];
            }
        }

        //run template engine          
        $tData = (array) $this->bbot->userData->get('userVars'); //load all user variables into the engine
        foreach ($output as &$o) {

            $loader = new \Twig_Loader_Array(array('output' => $o)); //@TODO find a better way to handle this. template engine should be defined with its added functions on constructor method not here
            $twig = new \Twig_Environment($loader);

            //getting functions defined by plugins to inject them as functions in template engine
            //@TODO some should be filters instead of functions. we will have to add a flag
            $flowFunctions = array_keys($this->bbot->flowFunctionsMap);
            foreach ($flowFunctions as $flowFunction) {
                $twig->addFunction(new \Twig_Function($flowFunction, function (...$args) use ($flowFunction) {
                    Log::bbotcore()->debug("Calling $flowFunction() with args: " . json_encode($args));
                    return $this->bbot->callDotFlowFunction($flowFunction, $args);
                }));
            }

            $o = $twig->render('output', $tData);
        }



        return $output;
    }

}
