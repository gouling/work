<?php

/**
 * Twitter Snowflake
 */

namespace Application\Library;

class Generate
{
    private $__machineID;

    public function __construct($machineID = 0)
    {
        $this->__machineID = $machineID;
    }

    public function createID()
    {
        $base = decbin($this->__getMillisecond());
        $machineID = str_pad(decbin($this->__machineID), 10, '0', STR_PAD_LEFT);
        $random = str_pad(decbin(rand(0, 4096)), 12, '0', STR_PAD_LEFT);

        return bindec($base . $machineID . $random);
    }

    public function getTimestampByID($id)
    {
        return floor(bindec(substr(decbin($id), 0, 41))) / 1000;
    }

    public function createOrderID()
    {
        $id = $this->createID();
        $serial = rtrim(chunk_split($id, 5, '-'), '-');

        return $serial;
    }

    private function __getMillisecond()
    {
        return floor(microtime(true) * 1000);
    }
}
