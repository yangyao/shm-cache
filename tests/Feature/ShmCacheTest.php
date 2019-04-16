<?php

namespace ShmCacheTest\Unit;


use PHPUnit\Framework\TestCase;
use Yangyao\ShmCache\ConsistentHash;
use Yangyao\ShmCache\Exceptions\KeyExistException;
use Yangyao\ShmCache\ShmHashMap;
use Yangyao\ShmCache\Reader;

class ShmCacheTest extends TestCase
{

    private $masterKey = 0x2222;
    private $salverKey = 0x2223;

    /**
     * when shm key is zero
     * @throws \Exception
     */
    public function testShmKeyIsZero()
    {
        $shm = new ShmHashMap();
        $this->expectException(\Exception::class);
        $shm->create(0);
    }

    /**
     * basic set and get
     * @throws \Exception
     * @throws \Yangyao\ShmCache\Exceptions\KeyExistException
     * @throws \Yangyao\ShmCache\Exceptions\NoMemoryException
     */
    public function testCacheBasic()
    {
        $shm = new ShmHashMap();
        $shm->create($this->masterKey);
        $shm->set("hello","world");
        $this->assertEquals("world",$shm->get("hello"));
    }

    /**
     * when key exist
     * @throws KeyExistException
     * @throws \Exception
     * @throws \Yangyao\ShmCache\Exceptions\NoMemoryException
     */
    public function testKeyExist()
    {
        $this->expectException(KeyExistException::class);
        $shm = new ShmHashMap();
        $shm->create($this->masterKey);
        $shm->set("hello","world");
        $shm->set("hello","master");
    }

    /**
     * read from slaver when master is down
     * @throws KeyExistException
     * @throws \Exception
     * @throws \Yangyao\ShmCache\Exceptions\NoMemoryException
     */
    public function testMasterAndSlave()
    {
        // init master
        $shmMaster = new ShmHashMap();
        $shmMaster->create($this->masterKey);
        // add key to salver
        $shmSalver = new ShmHashMap();
        $shmSalver->create($this->salverKey);
        $shmSalver->set('hello','world');
        // use the reader
        Reader::init($this->masterKey,$this->salverKey);
        $this->assertEquals("world",Reader::get("hello"));
        // make some clean up
        $shmMaster->delete($this->masterKey);
        $shmSalver->delete($this->salverKey);
    }

    /**
     * @throws KeyExistException
     * @throws \Exception
     * @throws \Yangyao\ShmCache\Exceptions\NoMemoryException
     */
    public function testConsistent()
    {
        $key = 'name';
        // init two server
        $server_1 = (new ShmHashMap())->create($this->masterKey);
        $server_1->set($key,'tom');
        $server_2 = (new ShmHashMap())->create($this->salverKey);
        $server_2->set($key,'sam');
        // add server to consistent hash table
        $consistent = new ConsistentHash();
        $consistent->addServer($this->masterKey);
        $consistent->addServer($this->salverKey);
        // find server by key
        $server = (new ShmHashMap())->attach($consistent->find($key));
        // get value form key
        $this->assertEquals('sam',$server->get($key));

    }



}