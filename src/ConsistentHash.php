<?php

namespace Yangyao\ShmCache;


class ConsistentHash
{

    // server list
    private $serverList = array();
    // delay sort not in add server but in find
    private $sorted = false;

    /**
     * add server to server list
     * @param $server
     * @return $this
     */
    public function addServer($server)
    {
        $hash = crc32($server);
        // add server, need to sort,but later when find
        $this->sorted = false;
        // add server hash
        if (!isset($this->serverList[$hash])) {
            $this->serverList[$hash] = $server;
        }
        return $this;
    }

    /**
     * find server by key
     * @param $key
     * @return bool
     */
    public function find($key)
    {
        // sort server when find
        if (!$this->sorted) {
            asort($this->serverList);
            $this->sorted = true;
        }
        $hash = crc32($key);
        $len = sizeof($this->serverList);
        if ($len == 0) {
            return false;
        }
        $keys = array_keys($this->serverList);
        $values = array_values($this->serverList);
        // not between first and last ,then return the last server
        if ($hash <= $keys[0] || $hash >= $keys[$len - 1]) {
            return $values[$len - 1];
        }
        foreach ($keys as $key => $pos) {
            $nextPos = null;
            if (isset($keys[$key + 1])) {
                $nextPos = $keys[$key + 1];
            }
            // net node is null
            if (is_null($nextPos)) {
                return $values[$key];
            }
            // get server
            if ($hash >= $pos && $hash <= $nextPos) {
                return $values[$key];
            }
        }

    }

}