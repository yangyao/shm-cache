<?php

namespace Yangyao\ShmCache;

class Reader
{
    /** @var $shmMasterHashMap ShmHashMap */
    private static $shmMasterHashMap = null;
    /** @var $shmMasterHashMap ShmHashMap */
    private static $shmSalverHashMap = null;
    private static $shmSalverKey = null;
    private static $shmMasterKey = null;

    private function __construct()
    {
    }

    /**
     * @param $shmMasterKey
     * @param $shmSalverKey
     * @return bool
     * @throws \Exception
     */
    public static function init($shmMasterKey, $shmSalverKey)
    {
        if (self::$shmMasterHashMap === null && self::$shmSalverHashMap === null) {
            self::$shmSalverKey = $shmSalverKey;
            self::$shmMasterKey = $shmMasterKey;
            self::$shmMasterHashMap = new ShmHashMap();
            try {
                self::$shmMasterHashMap->attach($shmMasterKey);
                return true;
            } catch (\Exception $e) {
                self::$shmMasterHashMap = null;
                return self::initSalver($shmSalverKey);
            }
        }
        return true;
    }

    /**
     * @param $keys
     * @return array|bool
     * @throws \Exception
     */
    public static function mget($keys)
    {
        if (self::$shmMasterHashMap != null) {
            $data = array();
            foreach ($keys as $key) {
                $value = self::get($key);
                if ($value) {
                    $k = explode('.', $key);
                    $data[array_pop($k)] = $value;
                }
            }
            return $data;
        }
        return false;
    }

    /**
     * @param $shmSalverKey
     * @return bool
     * @throws \Exception
     */
    public static function initSalver($shmSalverKey)
    {
        if (self::$shmSalverHashMap === null) {
            self::$shmSalverHashMap = new ShmHashMap();
            try {
                self::$shmSalverHashMap->attach($shmSalverKey);
                return true;
            } catch (\Exception $e) {
                self::$shmSalverHashMap = null;
                return false;
            }
        }
        return true;

    }

    /**
     * @param $key
     * @return bool|mixed
     * @throws \Exception
     */
    public static function get($key)
    {
        if (self::$shmMasterHashMap != null) {
            $data = self::$shmMasterHashMap->get($key);
            if (!$data) {
                if (self::initSalver(self::$shmSalverKey)) {
                    $data = self::$shmSalverHashMap->get($key);
                } else {
                    $data = false;
                }
            }
            return $data;
        } else if (self::$shmSalverHashMap != null) {
            return self::$shmSalverHashMap->get($key);
        }
        return false;
    }
}