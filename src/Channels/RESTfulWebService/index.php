<?php

/**
 * RESTful Webservice channel
 * 
 * POST data json example: {"botId":7,"userId":"2", "runBot: true, "input":{"text":"show me a video"}}
 * 
 * Response example: {"output":{"text":"here is the video","card":{"info":{"profiles":"hd","aspect":"16:9","autostart":true,"autoloop":true,"mute":false,"controls":true,"image_uri":"https:\/\/i.imgur.com\/Cxagv.jpg","media_uri":"http:\/\/techslides.com\/demos\/sample-videos\/small.mp4","alt_text":null},"type":"video","title":"video title","subtitle":"video subtitle"}}}
 * 
 */
require_once __DIR__ . '/../../../vendor/autoload.php';

try {

    //load configuration
    $cnf = include('../../../config.php');
    //get input
    $json = json_decode(file_get_contents('php://input'));
    //instatiate bbot
    $bot = new \BBot\BBot($json->botId, $json->orgId, $json->userId, $cnf);
    //set inputs
    if (isset($json->input)) {
        foreach ($json->input as $inputType => $inputValue) {
            $bot->setInput($inputType, $inputValue);
        }
    }
    //set commands
    if (isset($json->cmd)) {
        $cmdOutput = array();
        foreach ($json->cmd as $cmd) {
            if ($cmd[0] == 'setVar') {
                $cmdOutput[] = $bot->userData->setVar($cmd[1][0], $cmd[1][1]);
            }
            if ($cmd[0] == 'resetAll') {
                $cmdOutput[] = $bot->userData->resetAll();
            }
            //@TODO add more as needed
        }

        foreach ($cmdOutput as $co) {
            $bot->setOutput("cmd", $co);
        }
    }

    //run bot if asked to
    if (isset($json->runBot) && $json->runBot === true) {
        $bot->run();
    }
    echo json_encode($bot->getRawOutput(), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'error' => (string) $e, //@TODO filter exception data on production with a generic one with a code number 
        'output' => ['text' => 'Exception Error from BBot']
    ]);
}
   
