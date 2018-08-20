<?php

/**
 * ChatScript Client
 */

namespace BBot;

use Monolog\Registry as Log;

class ChatScriptClient
{

    public $host;
    public $port;
    public $userId;
    public $botId;
    public $cs_token = '#DO_SUBSTITUTE_SYSTEM | #DO_NUMBER_MERGE | #DO_PROPERNAME_MERGE | #DO_DATE_MERGE | #DO_SPELLCHECK | #DO_PARSE'; //default values from exeutor flow
    private static $lastText = array();

    public function __construct()
    {
        $this->host = Config::get('chatscriptServer')['host'];
        $this->port = Config::get('chatscriptServer')['port'];
        $this->userId = Config::get('chatscriptServer')['userId'];
        $this->botId = Config::get('chatscriptServer')['botId'];


        $this->setVariable('$cs_token', $this->cs_token);
    }

    /**
     * Sends and receive information to the ChatScript server
     * 
     * @param string $input Input text
     * @return string Output text
     * @throws Exception
     */
    public function send($input)
    {
        if (array_key_exists($input, self::$lastText)) {//runtime caching for now. useful in pattern match search
            return self::$lastText[$input];
        }

        $null = "\x00";

        //@TODO is it needed to send actual userId and botId to CS?
        $msg = $this->userId . $null . $this->botId . $null . $input . $null;

        $fp = fsockopen($this->host, $this->port, $errstr, $errno, 300);
        if (!$fp) {
            throw new \BBot\Exception('Error connecting to ChatScript server');
        }

        $ret = '';
        fputs($fp, $msg);
        while (!feof($fp)) {
            $ret .= fgets($fp, 512);
        }
        fclose($fp);

        self::$lastText[$input] = $ret;
        return $ret;
    }

    /**
     * testpattern ChatScript command
     * Used by the PatternMatchChatScriptAdapter to do CS matches
     * 
     * @TODO It should return true/false and debug information stored in another classvar
     * 
     * @param string $pattern Pattern to be tested
     * @param string $input User input
     * @return array ChatScript output
     */
    public function testPattern($pattern, $input)
    {
        $timeStart = microtime_float();

        $input = str_replace("\n", "", $input); //sanitizing to avoid cmd injection? @TODO check if there is more needed
        $pattern = str_replace("\n", "", $pattern); //malicious author?

        $csCmd = ":testpattern ($pattern) $input";

        $ret = $this->send($csCmd);

        $ret = explode("\n", $ret);
        $ret = array_map('trim', $ret);

        $timeEnd = microtime_float();
        $time = $timeEnd - $timeStart;
        Log::bbotcore()->debug("testpattern time: $time");

        return $ret;
    }

    /**
     * pos ChatScript command
     * Used by the PatternMatchChatScriptAdapter to preprocess text
     * 
     * @param string $str Text to be processed
     * @return ChatScriptClient output
     */
    public function pos($str)
    {
        $timeStart = microtime_float();

        $str = str_replace("\n", "", $str); //@TODO we'll have to do this always so move it

        $ret = $this->send(":pos " . $str);

        $ret = explode("\n", $ret);
        $ret = array_map('trim', $ret);

        $timeEnd = microtime_float();
        $time = $timeEnd - $timeStart;
        Log::bbotcore()->debug("pos time: $time");


        return $ret;
    }

    /**
     * do ChatScript command
     * Used to run any ChatScript language instruction
     * @param string $str ChatScript instruction
     * @return string ChatScript output
     */
    public function do($str)
    {
        $str = str_replace("\n", "", $str);

        $ret = $this->send(":do " . $str);

        return $ret;
    }

    /**
     * Returns ChatScript variable
     * 
     * @param string $varName Variable name
     * @return string Value
     */
    public function getVariable($varName)
    {
        return $this->do($varName);
    }

    /**
     * Sets ChatScript variable
     * 
     * @param string $varName Variable name
     * @param string $varValue Value
     */
    public function setVariable($varName, $varValue)
    {
        return $this->do($varName . ' = ' . $varValue);
    }

}

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float) $usec + (float) $sec);
}
