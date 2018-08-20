<?php

namespace BBot\Plugins;

/**
 * Media plugin - defines .flow functions video, image and audio
 */
class Media
{

    private $bbot;

    public function __construct(\BBot\BBot $bbot)
    {
        $this->bbot = $bbot;
    }
    
    public static function init($bbot) {
        $bbot->registerDotFlowFunction('media', [__CLASS__, 'getResponse']);
        $bbot->registerDotFlowFunction('video', [__CLASS__, 'getResponse']);
        $bbot->registerDotFlowFunction('image', [__CLASS__, 'getResponse']);
        $bbot->registerDotFlowFunction('audio', [__CLASS__, 'getResponse']);
    }
    

    public function getResponse($node)
    {
        $this->bbot->setOutput('card', $node->info);
    }

}
