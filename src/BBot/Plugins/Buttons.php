<?php

namespace BBot\Plugins;

/**
 * Buttons plugin - This pseudo function will be called on each BBot->setResponse() and will provide buttons to the output if isButton attr is true
 * will not be needed with flow2
 */
class Buttons
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }

    public static function init($bbot) {
        $bbot->registerDotFlowFunction('buttons', [__CLASS__, 'getResponse']);
    }
    
    public function getResponse($node)
    {
        $output = $this->getButtons($node);
        foreach ($output as $o) {
            $this->bbot->setOutput('buttons', $o);
        }
    }

    public function getButtons($node)
    {
        $buttons = array();
        foreach ($node->connections as $c) {
            if (isset($c->isButton) && $c->isButton == true) {
                $buttons[] = array(
                    'label' => isset($c->name) && strlen($c->name) > 0 ? $c->name : $c->if->value[0],
                    'input' => $c->if->value[0]
                );
            }
        }
        return $buttons;
    }

}
