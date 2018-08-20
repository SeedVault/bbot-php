<?php

namespace BBot;

use Monolog\Registry as Log;

/**
 * Session user data
 */
class UserData
{

    const ORGANIZATION = 'orgId';
    const BOT = 'botId';

    public $bbot;
    public $orgId;
    public $botId;
    public $userId;
    public $namespace;

    /**
     * @param BBot $bbot BBot object
     * @param string $namespace defined scoop of user session (bot or org)
     * @param string|int $userId User ID
     * @param string|int $botId Bot ID
     * @param string|int $orgId Organization ID
     */
    public function __construct($bbot, $namespace, $userId, $botId, $orgId)
    {
        $this->bbot = $bbot;
        $this->namespace = $namespace;
        $this->orgId = $orgId;
        $this->botId = $botId;
        $this->userId = $userId;

        //do connection
        $uri = Config::get('userData')['uri'];
        $this->conn = new \MongoDB\Client($uri);

        $db = str_replace("/", "", parse_url($uri, PHP_URL_PATH)); //get db name from uri
        $this->userDataCol = $this->conn->$db->userData;
    }

    /**
     * Sets permanent user data
     * 
     * @param string $k Variable name
     * @param string $v Value
     */
    public function set($k, $v, $operator = '$set')
    {
        $q = $this->getQuery();

        $updateResult = $this->userDataCol->updateOne(
                $q, [$operator => [$k => $v]], ['upsert' => true]
        );
        return $updateResult;
    }

    /**
     * Pushes values
     * 
     * @param string $k Variable name
     * @param string $v Value to be pushed
     * @return bool
     */
    public function push($k, $v)
    {
        return $this->set($k, $v, '$push');
    }

    /**
     * Add to set
     * 
     * @param string $k Variable name
     * @param string $v Vallue to be added to the set
     * @return bool
     */
    public function addToSet($k, $v)
    {
        return $this->set($k, $v, '$addToSet');
    }

    /**
     * Returns session user data
     * 
     * @param string $k Variable name
     * @return string Value
     */
    public function get($k)
    {
        $q = $this->getQuery();

        //this will fix projection with element arrays in dot notation:
        //ex. weather.response.data.0.current_weather .. 0 is the key of array data but mongodb doesnt support it for projections, so we convert it to
        // weather.response.data.current_weather
        $lvls = explode('.', $k);
        $fixedK = array();
        foreach ($lvls as $lvl) {
            if (!is_numeric($lvl)) {
                $fixedK[] = $lvl;
            }
        }
        $fixedK = implode('.', $fixedK);

        $result = $this->userDataCol->findOne($q, ['projection' => ['_id' => 0, $fixedK => 1]]);

        if ($result && count($result) > 0) {
            if (strpos($k, ".") !== false) {//asked a nested value, get the requested value (mongodb php lib is returning the whole document structure with the filtered projection)      
                $lvls = explode('.', $k);
                $ret = $result;
                foreach ($lvls as $lvl) {
                    if (!is_numeric($lvl)) {
                        if (!property_exists($ret, $lvl)) {
                            return false;
                        }
                        $ret = $ret->$lvl;
                    } elseif (is_numeric($lvl)) {
                        if (!array_key_exists($lvl, $ret)) {
                            return false;//@TODO Should bbot throw exception here? (for now caller is doing it)
                        }
                        $ret = $ret[$lvl]; //if it is a number inside a dot notation, it's pointing to an array element (in fact it's a BSONDocument object but acts like an array if original data were array)
                    }
                }
                return $ret;
            }

            return $result && property_exists($result, $k) ? $result->$k : null;
        }

        //there is no value defined yet
        return false;
    }

    /**
     * Store any user data for later use on .flow
     * 
     * @param string $k Variable name
     * @param string $v Value
     * @return bool
     */
    public function setVar($k, $v)
    {
        $k = 'userVars.' . $k;

        Log::bbotcore()->debug("Setting setVar: $k = " . json_encode($v));
        return $this->set($k, $v);
    }

    /**
     * Returns user data 
     * 
     * @param string $k Variable name
     * @return string Stored value
     */
    public function getVar($k)
    {
        //@TODO we should at least cache it here with static var?the var can be interpolated several times in the same response so we avoid api call on the same response
        $k = 'userVars.' . $k;
        return $this->get($k);
    }

    /**
     * Completely resets all session user data and .flow variable data
     */
    public function resetAll()
    {
        $updateResult = $this->userDataCol->deleteMany(['userId' => $this->userId]);
    }

    /**
     * Returns query to be used by get method
     * 
     * @return array 
     */
    public function getQuery()
    {
        $q = array();
        if ($this->namespace == self::ORGANIZATION) {
            $q[self::ORGANIZATION] = $this->orgId;
        } else if ($this->namespace == self::BOT) {
            $q[self::BOT] = $this->botId;
        }

        $q['userId'] = $this->userId;

        return $q;
    }

}
