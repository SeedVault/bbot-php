<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * Main BBot class. This is the bot engine.
 */
class BBot
{

    /**
     * Input data
     *
     * @var array 
     */
    public $in;

    /**
     * Output data
     *
     * @var array
     */
    public $ou;

    /**
     * .Bot data - config data about the bot, etc
     *
     * @var array 
     */
    public $dotBot;

    /**
     * Internal platform configuration data - api, databse credentials, etc
     *
     * @var array
     */
    public $config;

    /**
     * User persistent session data
     *
     * @var array
     */
    public $userData;

    /**
     * Map flow functions to plugin functions
     *
     * @var array 
     */
    public $flowFunctionsMap;

    /**
     * Cached plugin objects
     *
     * @var array
     */
    private $pluginsObjects;

    const BBOT_CORE_LOGGER = 'bbotcore';

    /**
     * BBot constructor
     * 
     * @param string|int $botId Bot ID
     * @param string|int $orgId Organization ID
     * @param string|int $userId User ID
     * @param array $config Platform custom configuration
     */
    public function __construct($botId, $orgId, $userId, $config = null)
    {
        $this->botId = $botId;
        $this->orgId = $orgId;
        $this->userId = $userId;

        //handle some php errors to be captured as exceptions 
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            Log::bbotcore()->warning("PHP Warning: $errstr ($errno) in $errfile on line $errline");
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        //initialize monolog
        if (!Log::hasLogger(self::BBOT_CORE_LOGGER)) {//@TODO this should be configured by the config file
            $coreLogger = new \Monolog\Logger(self::BBOT_CORE_LOGGER);
            $coreLogger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/Logs/bbot.log', \Monolog\Logger::DEBUG));
            Log::addLogger($coreLogger);
        }

        //initialize in/output obj
        $this->ou = new \stdClass();
        $this->in = new \stdClass();

        //initilize custom platform config
        if ($config) {
            Config::set($config);
        }

        $this->loadDotBot();
        $this->sessionStart();

        //initialize plugins. This calls init static method which will do whatever plugins think has to do to initialize (like flow $function to class->method mapping)
        foreach (Config::get('plugins') as $plugin) {
            $pluginClassName = '\\BBot\\Plugins\\' . $plugin; //@TODO may be we shouldn't hardcode this and allow plugins to be outside bbot path (plugins as independent packages?)
            $pluginClassName::init($this);
        }
    }

    /**
     * Register .flow $function mapped to its plugin method
     * 
     * @param string $flowFunction .flow function name
     * @param array $pluginFunction callable array class/method of plugin method
     */
    public function registerDotFlowFunction($flowFunction, $pluginFunction)
    {
        $this->flowFunctionsMap[$flowFunction] = $pluginFunction;
    }

    /**
     * Executes the plugin function based on .flow function name
     * 
     * @param string $flowFunction .flow function name to be executed
     * @param array $args Array of arguments
     * @return mixed Any value returned by the function
     */
    public function callDotFlowFunction($flowFunction, $args)
    {
        //get plugin pointer
        $pluginFunction = $this->flowFunctionsMap[$flowFunction];
        //check if there is object stored
        if (!isset($this->pluginsObjects[$pluginFunction[0]])) {
            $this->pluginsObjects[$pluginFunction[0]] = new $pluginFunction[0]($this);
        }

        return call_user_func([$this->pluginsObjects[$pluginFunction[0]], $pluginFunction[1]], ...$args);
    }

    /**
     * Initialize user session
     * 
     * @param string $scope The scope of user data.
     * It can be 'bot': data is not shared anywhere. user data remains on the bot
     * 'org': data is shared between all bot from the same organization. All bots will have access to the same user data. All bots will recognice the same user
     */
    public function sessionStart($scope = null)
    {

        //if not scope defined on args, check if it's defined on dotBot
        if (!$scope) {
            if (isset($this->dotBot->userSessionScope)) {
                $scope = $this->dotBot->userSessionScope;
            } else {
                //not defined in dotBot, set default
                $scope = UserData::BOT;
            }
        }

        if (isset($this->userId) && isset($this->botId) && isset($this->orgId)) {
            //setting userdata to not be shared (UserData::BOT)
            $this->userData = new UserData($this, $scope, $this->userId, $this->botId, $this->orgId);
            //user data will not be shared between organization's bots
        } else {
            throw new Exception('Data missing');
        }
    }

    /**
     * Return whole output object
     * 
     * @return array
     */
    public function getRawOutput()
    {
        return $this->ou;
    }

    /**
     * Executes the current volley
     *
     */
    public function run()
    {

        Log::bbotcore()->debug('botId: ' . $this->botId . ' - userId: ' . $this->userId);

        //load current flow which the user is at
        $this->loadBotFlows();
        
        //check for command
        if ($this->isCommand($this->getInput())) {
            $output = $this->runCommand($this->getInput());
            $this->setOutput('text', $output);
            return true;
        }
        $cNode = $this->getCurrentNode(); //get node on which the user is at
        
        //look for match and output text
        $output = '';
        $mNode = null;
        if (!$cNode) {//no current node, it's initial welcome message
            Log::bbotcore()->debug("There is no current node. This is first welcome mesage. Ignoring input.");
            $cNode = $this->getFirstNode(); //getting first node will provide the global user intents
            $this->setCurrentNodeId($cNode->id);
            $this->setResponseByNode($cNode);
            return true;
        } else { //not first,  look for matchings on context current node and if it fails go for global user intents in the first node
            $match = $this->findFlowMatch($cNode); //this returns matched connection object, not node

            if ($match) {//there is a match
                //check if we need to store input value in persistent storage
                //only store value if the match is in context, not on global intents. doesn't matter if there is no node pointed from the matched connection
                if ($this->lastMatchedNodeFoundIn == 'context' && isset($cNode->fieldId)) {
                    $formId = isset($cNode->formId) ? $cNode->formId : null;
                    $this->userData->setVar($cNode->fieldId, $this->in->input->text);

                    //add question/answer pair to the form if formId is defined
                    if ($formId) {
                        $pText = new Plugins\Text($this); //get text output from previous node to get the question                 
                        $cNodeOutput = $pText->getOutput($cNode);
                        $this->userData->set("formVars.{$formId}.{$cNode->fieldId}", ['question' => $cNodeOutput, 'answer' => $this->in->input->text]);
                    }

                    Log::bbotcore()->debug("Variable '$cNode->fieldId' with value '{$this->in->input->text}' on formId '$formId'");
                }
                
                //we have a match so set current node to keep the user on the rejoinder
                if ($this->lastMatchedNodeId) {//mNode might be null if there is no node set for the matched connection
                    $mNode = $this->getNode($this->lastMatchedNodeId);
                    $newCurrentNode = $mNode;
                    $this->setCurrentNodeId($newCurrentNode->id);

                    $this->setResponseByNode($mNode); //this returns all output types from the node
                } else {
                    //there is a match but there is no node defined. set current node to root. output will be the same as it there is no match
                    $newCurrentNode = $this->getFirstNode();
                    $this->setCurrentNodeId($newCurrentNode->id);
                    Log::bbotcore()->debug("There is no node defined for the matched connection. Set current node to root");
                }

                Log::bbotcore()->debug("New current node id {$newCurrentNode->id}");
            }


            if (!$mNode) {//if there is no match..
                //botdev should take care of no matches. Add a wildcard intent to handle it
                $this->setOutput('text', ''); 
            }
        }
    }

    /**
     * This will match current user input with the speified node
     * If it doesnt match, it will try to match user intent with global user intents from toplevel node
     * 
     * @param array $contextNode Node to try first match (usually the current node there the user is as context)
     * @return string Node id that the flow should go as it matched the user intent
     */
    public function findFlowMatch($contextNode = null)
    {
        if (!$contextNode) {
            $contextNode = $this->getCurrentNode();
        }


        $m = false;
        Log::bbotcore()->debug("Find match for user text \"{$this->in->input->text}\" on context intent node id {$contextNode->id}...");

        //first try to match current node
        $m = $this->findContextPatternMatch($contextNode);

        if ($m) {
            $this->lastMatchedNodeFoundIn = 'context';
            Log::bbotcore()->debug("Match found on context intent node to node id {$this->lastMatchedNodeId}");

            return $m;
        } else {
            //second try to match toplevel node
            $fNode = $this->getFirstNode();
            if ($fNode != $contextNode) {
                Log::bbotcore()->debug("Match not found, try to match toplevel intent id ($fNode->id)");

                $m = $this->findContextPatternMatch($fNode);
                if ($m) {
                    $this->lastMatchedNodeFoundIn = 'global';
                    Log::bbotcore()->debug("Match found on toplevel intent to node id {$this->lastMatchedNodeId}");
                    return $m;
                } else {
                    Log::bbotcore()->debug("Match not found on toplevel node");
                }
            } else {
                Log::bbotcore()->debug("Match not found, this is toplevel node, there is nothing more to do");
            }
        }


        return false;
    }

    /**
     * Convenience method to iterate through the connection's node to find match
     * 
     * @param array $node
     * @return boolean TRUE if match, FALSE if not
     */
    public function findContextPatternMatch($node)
    {
        $scores = array();

        foreach ($node->connections as $c) {
            if (isset($c->if)) {
                foreach ($c->if->value as $pattern) {

                    //eval conditional first as it's faster
                    //with flow 2.0 this will not be hardcoded. flow 2.0 will have a nested conditional criteria functions 
                    if ($this->evalConditional($c)) {
                        //@TODO it might happen we need do this type of conditionals after matching as ML like luis will provide entities setting variables
                        //which we will want to evaluate in the conditionals intent 

                        $match = $this->findPatternMatch($pattern, $this->getInput(), $c);
                 

                        if ($match === true) {//match first TRUE
                            //@TODO would be better if current method returns an array/object with this data instead of setting it on $this
                            $this->lastMatchedNodeId = $c->if->then != 'end' ? $c->if->then : null;
                            $this->lastMatchedConnection = $c;
                            $this->lastMatchedPattern = $pattern;
                            return true;
                        } else if (is_numeric($match)) {//if it's numeric then it's a score. we need to collect all scores from node. then match high score                            
                            Log::bbotcore()->debug("Pattern match score $match");

                            //if value is already set, then ignore. we want the first node with the value, so first node is matched
                            if (!isset($scores[$match])) {
                                $scores[$match] = [
                                    'lastMatchedNodeId' => $c->if->then != 'end' ? $c->if->then : null,
                                    'lastMatchedConnection' => $c,
                                    'lastMatchedPattern' => $pattern
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        //get high score is any and return true
        if (is_array($scores) && count($scores)) {//there are matched intents with score. return higher score
            $bestScore = max(array_keys($scores));
            $highScoreMatch = $scores[$bestScore];
            $this->lastMatchedNodeId = $highScoreMatch['lastMatchedNodeId'];
            $this->lastMatchedConnection = $highScoreMatch['lastMatchedConnection'];
            $this->lastMatchedPattern = $highScoreMatch['lastMatchedPattern'];
            Log::bbotcore()->debug("Best score match: $bestScore - pattern: {$highScoreMatch['lastMatchedPattern']}");
            return true;
        }

        return false;
    }

    /**
     * Executes evalConditional .flow function
     * (with .flow 2.0 this function wont be needed)
     * 
     * @param object $conn Connection object from the node
     * @return boolean True if evalues as true
     */
    public function evalConditional($conn)
    {
        if (isset($conn->if->pre)) {
            return $this->callDotFlowFunction('variableEval', [$conn->if->pre]);
        } else {//if there is no conditional, just eval true
            return true;
        }
    }

    /**
     * Finds pattern match based on the defined match function in .flow node connection
     * 
     * @param string $pattern Pattern defined in connection node
     * @param string $input User input
     * @return boolean True if is a match
     */
    public function findPatternMatch($pattern, $input, $connection)
    {

        if (isset($connection->if->op)) {
            $opts = [
                'op' => $connection->if->op,
                'intentLabel' => isset($connection->name) ? $connection->name : null
            ];

            return $this->callDotFlowFunction($opts['op'], [$pattern, $input, $opts]);
        }


        //if there is no conditions, return true
        return true;
    }

    /**
     * Returns output text from the specified node
     * If the node has multiple strings it will return a random pick
     * 
     * @param array $node Node to get output text message
     * @return type string Output text message
     */
    public function setResponseByNode($node)
    {

        //with .flow 2.0 this speacial treatment won't be needed
        //check if there are more outputs
        if (isset($node->info)) {
            if ($node->type == 'process' || $node->type == 'card') {

                $this->callDotFlowFunction($node->subType, [$node]);
            } else {
                $this->callDotFlowFunction($node->type, [$node]);
            }
        }

        //check and send buttons to output
        $this->callDotFlowFunction('buttons', [$node]);

        //regular msg
        if (isset($node->msg)) {
            $this->callDotFlowFunction('text', [$node]);
        }
    }

    /**
     * Load the user input to the object
     * 
     * @param string $type Input type
     * @param string $userInput User input
     */
    public function setInput($type, $input)
    {
        if (!isset($this->in->input)) {
            $this->in->input = new \stdClass();
        }

        $this->in->input->$type = $input;
    }

    /**
     * Returns user input
     * 
     * @return string User input
     */
    public function getInput($type = 'text')
    {
        return $this->in->input->$type;
    }

    /**
     * Returns the current node where the user is. this is the volley position which brings context to the user intent
     * 
     * @return array Node data
     */
    public function getCurrentNode()
    {

        $cNodeId = $this->getCurrentNodeId();

        if ($cNodeId) {
            return $this->getNode($cNodeId);
        } else {
            return null;
        }
    }

    /**
     * Returns node object by connection
     * 
     * @param object $connection 
     * @return object Node object
     */
    public function getNodeByConnection($connection)
    {
        $nodeId = $this->getNodeIdByConnection($connection);
        if ($nodeId) {
            return $this->getNode($nodeId);
        }
        return null;
    }

    /**
     * Returns node id by connection
     * 
     * @param object $connection
     * @return string|int Node id
     */
    public function getNodeIdByConnection($connection)
    {
        return $connection->if->then != 'end' ? $connection->if->then : null;
    }

    /**
     * Returns node array by its id
     * 
     * @param string $nodeId
     * @param bool $returnResolvedNode True to return node pointed by flowId if it's the case
     * @return object Node object
     */
    public function getNode($nodeId, $returnResolvedNode = true)
    {
        foreach ($this->botFlowsNodes as $node) {
            if ($node->id == $nodeId || $node->name == 'flow' . $nodeId) {//ugly thing I have to do because author's tool is not putting the right name on the flow
                //@TODO fix this on author's tool!
                break;
            }
        }

        if ($node) {
            if ($returnResolvedNode) {
                if ($node->type == 'flowId') {
                    if (isset($node->flowId)) {//check if it's a flow or node connection
                        Log::bbotcore()->debug("It's a connection flow. Finding actual node...");
                        //look for the real node
                        $flowId = $node->flowId;
                        $rootNode = $this->getNode($flowId);
                        $mNodeId = $rootNode->connections[0]->default;
                        if ($mNodeId == 'end') {
                            $mNodeId = null;
                            $node = null;
                            Log::bbotcore()->debug("Flow is empty, discarding");
                        } else {
                            Log::bbotcore()->debug("Found node id $mNodeId");
                            $node = $this->getNode($mNodeId);
                        }
                    } else {
                        Log::bbotcore()->debug("It's a connection node. ");
                        $mNodeId = $node->connections[0]->default;
                        $node = $this->getNode($mNodeId);
                    }
                }
            }
        } else {
            Log::bbotcore()->debug('node not found in flow');
            throw new \BBot\Exception('node not found in flow');
        }
        return $node;
    }

    /**
     * Returns the first node in which we have all global user intents
     * 
     * @return array First node
     */
    public function getFirstNode()
    {
        foreach ($this->botFlowsNodes as $node) {
            if ($node->type != 'root') {
                return $node;
            }
        }
        //throw new \BBot\Exception('first node not found');
    }

    /**
     * Returns user current node id
     * 
     * @return string Node uuid
     */
    public function getCurrentNodeId()
    {
        return $this->userData->get('currentNodeId');
    }

    /**
     * Sets current node uuid
     * 
     * @param string $nodeId Node uuid
     */
    public function setCurrentNodeId($nodeId)
    {
        $this->userData->set('currentNodeId', $nodeId);
    }

    /**
     * Sets bot output
     * 
     * @param string $output Bot output
     */
    public function setOutput($type, $output = null)
    {
        if ($output === null) {
            //1st arg is array
            $this->ou->output = $type;
        } else {
            $this->ou->output[$type][] = $output;
        }
    }

    /**
     * Returns bot output
     * 
     * @return string Bot output
     */
    public function getOutput($type = 'text')
    {
        return $this->ou->output[$type];
    }

    public function getOutputTypeList()
    {
        return array_keys($this->ou->output);
    }

    /**
     * Interpolate tagger variable names with its value
     * 
     * @param strig $subject String to interpolate variables
     * @param array $cb Callable function callback
     * @param type $escapeChar 
     * @param type $errPlaceholder
     * @return string Interpolated response
     */
    function interpolate($subject, $cb = null, $escapeChar = "\\", $errPlaceholder = '')
    {
        $esc = preg_quote($escapeChar);
        $expr = "/
        $esc$esc(?=$esc*+{)
      | $esc{
      | {([\.\w\{\}]+)}
    /x";

        $callback = function($match) use($escapeChar, $errPlaceholder, $cb, $subject) {
            switch ($match[0]) {
                case $escapeChar . $escapeChar:
                    return $escapeChar;

                case $escapeChar . '{':
                    return '{';

                default:
                    $m = $match[1];
                    if (strpos($m, '{') !== false) {//if it still has { then reprocess as it might have interpolation within interpolation (like using dynamic attrs {weather.{location}})
                        $m = $this->interpolate($m);
                    }
                    $ret = $this->userData->getVar($m);

                    if ($ret === false) {
                        throw new \BBot\Exception("User variable '$m' is undefined. (It's being injected in '$subject')");
                        //return null; //@TODO may be this should be set in a configuration from author? (exception is better.. it avoids using it in api calls)
                        //return $m;
                    }

                    if (!is_string($ret) && !is_numeric($ret)) {
                        $ret = json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); //bbot will display json if it's an object or array 
                        //@TODO this leaks too much information to end user. It should be restricted to author. It should go to error attr not outout. Also aggregator should not send too much to bbot
                    }

                    if ($cb) {
                        if (is_callable($cb)) {
                            $ret = call_user_func($cb, $ret);
                        }
                    }
                    return $ret;
                
            }
        };

        return preg_replace_callback($expr, $callback, $subject);
    }

    /**
     * Check if input has a command 
     * 
     * @param string $str User input
     * @return bool TRUE if it's a cheatcode, FALSE if it isn't
     */
    public function isCommand($str)
    {
        return strpos($str, ":") === 0;
    }

    /**
     * Executes command
     * 
     * @param string $str command (including double colon)
     * @return string
     */
    public function runCommand($str)
    {
        //@TODO at some point this needs authorization
        if ($str == ':reset all') {
            $this->userData->resetAll();
            return "All user data is deleted.";
            //
        //
        } else if ($str == ':help') {
            return "Commands:
 :reset all       Reset user data of all users on the system
 ";
            //
        //
        } else {
            return "Unknown command. Type :help";
        }
    }

    /**
     * loads all flows from the project. 
     * @TODO it should load just the current flow
     */
    public function loadBotFlows()
    {
        $f = Config::getRepositoryData('flows', $this->botId);
        $this->botFlowsNodes = $f;
    }

    /**
     * Loads .bot 
     */
    public function loadDotBot()
    {
        $db = Config::getRepositoryData('dotBot', $this->botId);
        $this->dotBot = $db;
    }

}
