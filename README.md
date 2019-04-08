# shm-cache
yet another php cache based on share memory

## how to use ?

install var composer 

```php
composer require yangyao/shm-cache
```

basic use .

```php
$shm = new Yangyao\ShmCache\ShmHashMap();
$shm->create(0x2222);
$shm->set('hello','world');
$shm->get('hello');

```

master and salver

```php

$masterKey = 0x2222;
$salverKey = 0x2223;
// add key to master
$shmMaster = new Yangyao\ShmCache\ShmHashMap();
$shmMaster->create($masterKey);
$shmMaster->set('hello','world');
// add key to salver
$shmSalver = new Yangyao\ShmCache\ShmHashMap();
$shmSalver->create($masterKey);
$shmSalver->set('hello','world');
// use the reader
Yangyao\ShmCache\Reader::init($masterKey,$salverKey);
Yangyao\ShmCache\Reader::get($key);

```
cluster consistent

```php

$server_1 = 0x2222;
$server_2 = 0x2223;

$key = 'hello';

$consistent = new Yangyao\ShmCache\ConsistentHash();
$consistent->addServer($server_1);
$consistent->addServer($server_2);

$server = $consistent->find($key);

$shm = new Yangyao\ShmCache\ShmHashMap();
$shm->attach($server);

// $shm->set($key,'world');

$data = $shm->get($key);


```

## what's the principle ?

- based on hash arithmetic
- write data into share memory use php shmop extension 

## what's the data structure ?

head 

![head](https://user-images.githubusercontent.com/5866775/55677531-2965bb00-591c-11e9-8527-cde171b53b28.png)

data 

![data](https://user-images.githubusercontent.com/5866775/55677532-29fe5180-591c-11e9-8a37-5eb8682fe46e.png)

all

![all](https://user-images.githubusercontent.com/5866775/55725786-f5d08100-5a40-11e9-805e-e2dfa3c6cdd6.png)







