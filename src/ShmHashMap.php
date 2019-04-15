<?php

namespace Yangyao\ShmCache;

use Exception;
use Yangyao\ShmCache\Exceptions\KeyExistException;
use Yangyao\ShmCache\Exceptions\NoMemoryException;

class ShmHashMap
{
    private $shmId = null;
    private $head = [];

    /**
     * @param $shmKey
     * @param int $buckets
     * @param int $size
     * @return ShmHashMap
     * @throws Exception
     */
    public function create($shmKey, $buckets = 12281, $size = 10000000)
    {
        if ($shmKey == 0) {
            throw new Exception('shm key can not be zero !');
        }
        $this->shmId = @shmop_open($shmKey, 'a', 0, 0);
        if ($this->shmId != false) {
            if (!shmop_delete($this->shmId)) {
                throw new Exception('delete exist shm 0x' . dechex($shmKey) . ' error');
            }
        }
        $this->shmId = @shmop_open($shmKey, 'c', 0777, $size);
        if ($this->shmId == false) {
            throw new Exception('create shm 0x' . dechex($shmKey) . ' error');
        }
        $head = [];
        $head['bsize'] = $size;
        $head['size'] = 0;
        $head['buckets'] = $buckets;
        $head['start'] = 24;
        $head['data'] = $buckets * 4 + 24;
        $head['free'] = $buckets * 4 + 24;
        $this->setHead($head);
        return $this;
    }

    /**
     * @param $shmKey
     * @return $this|bool
     * @throws Exception
     */
    public function attach($shmKey)
    {
        if ($this->shmId != null) {
            return true;
        }
        if ($shmKey == 0) {
            throw new Exception('shm key can not be zero !');
        }
        $this->shmId = @shmop_open($shmKey, 'a', 0, 0);
        if ($this->shmId == false) {
            throw new Exception("attach 0x" . dechex($shmKey) . " shm error");
        }
        $this->getHead();
        return $this;
    }

    /**
     * delete cache
     * @param $shmKey
     * @throws Exception
     */
    public function delete($shmKey)
    {
        if (!shmop_delete($this->shmId)) {
            throw new Exception('delete exist shm 0x' . dechex($shmKey) . ' error');
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getHead()
    {
        $data = shmop_read($this->shmId, 0, 24);
        if ($data === false) {
            throw new Exception('read head error');
        }
        $this->head = unpack("Ibsize/Isize/Ibuckets/Istart/Idata/Ifree", $data);
        return $this->head;
    }

    /**
     * @param $head
     * @return bool
     * @throws Exception
     */
    private function setHead($head)
    {
        $this->head = $head;
        $data = pack('IIIIII', $head['bsize'], $head['size'], $head['buckets'], $head['start'], $head['data'], $head['free']);
        if (!shmop_write($this->shmId, $data, 0)) {
            throw new Exception('write head error');
        }
        return true;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     * @throws Exception
     * @throws NoMemoryException
     * @throws KeyExistException
     */
    public function set($key, $value)
    {
        $head = $this->head;
        $intKey = crc32($key);
        $vardata = json_encode($value);
        $newkeylen = strlen($key);
        $newvarlen = strlen($vardata);
        $wlen = 12 + $newkeylen + $newvarlen;
        if ($head['free'] + $wlen > $head['bsize']) {
            throw new NoMemoryException('no memory');
        }
        $index = $intKey % $head['buckets'];
        $buf = shmop_read($this->shmId, $head['start'] + (4 * $index), 4);
        $anext = unpack('I', $buf);
        $next = $anext[1];
        $offset = 0;
        if ($next == null || $next == 0) {
            $next = $head['free'];
            $offset = $head['start'] + (4 * $index);
        } else {
            while ($next != null && $next != 0) {
                $buf = shmop_read($this->shmId, $next, 12);
                $offset = $next;
                $adata = unpack('I3', $buf);
                $nnext = $adata[1];
                $keylen = $adata[2];
                $varlen = $adata[3];
                $buf = shmop_read($this->shmId, $next + 12, $keylen + $varlen);
                $adata = unpack('a' . $keylen . 'key/a' . $varlen . "value", $buf);
                $nextkey = $adata['key'];
                if ($nextkey == $key) {
                    throw new KeyExistException($key . ' exsit');
                }
                $next = $nnext;
            }
            $next = $head['free'];
        }
        if (!shmop_write($this->shmId, pack('IIIa' . $newkeylen . 'a' . $newvarlen, 0, $newkeylen, $newvarlen, $key, $vardata), $head['free'])) {
            throw new Exception('write node error');
        }
        if (!shmop_write($this->shmId, pack('I', $next), $offset)) {
            throw new Exception('write nlen error');
        }
        $head['free'] += $wlen;
        $head['size']++;
        return $this->setHead($head);
    }

    /**
     * @param $key
     * @return bool|mixed
     * @throws Exception
     */
    public function get($key)
    {
        if ($this->shmId == null) {
            throw new Exception('shm not init');
        }
        $head = $this->head;
        $intKey = crc32($key);
        $index = $intKey % $head['buckets'];
        $buf = shmop_read($this->shmId, $head['start'] + (4 * $index), 4);
        $anext = unpack('I', $buf);
        $next = $anext[1];
        if ($next == null || $next == 0) {
            return false;
        }
        while ($next != null && $next != 0) {
            $buf = shmop_read($this->shmId, $next, 12);
            $adata = unpack('I3', $buf);
            $nnext = $adata[1];
            $keylen = $adata[2];
            $varlen = $adata[3];
            $buf = shmop_read($this->shmId, $next + 12, $keylen + $varlen);
            $adata = unpack('a' . $keylen . "key/a" . $varlen . "value", $buf);
            if ($adata['key'] == $key) {
                return json_decode($adata['value']);
            }
            $next = $nnext;
        }
        return false;
    }

    public function __destruct()
    {
        if ($this->shmId != null) {
            @shmop_close($this->shmId);
        }
    }
}
