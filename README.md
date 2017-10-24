# Workerman_cor_ape


## 这是什么
Workerman_cor_ape是知名php框架 [Workerman](https://github.com/walkor/Workerman) 的强化版，在不影响任何使用方式，稳定性，性能前提下，增加了异步任务组件。
## 原理是什么
Workerman每个工作进程只有一个线程，这个线程既负责收发网络消息，又负责处理业务，在业务阻塞比较多的情况下，比较浪费性能。   
   
Workerman_cor_ape框架将Workerman每个进程扩展为两个线程，分别是workerman线程，以及任务线程。   
`workerman线程:和原有的单线程workerman线程是相同的，无论是使用方式，还是性能。`    
`任务线程:负责接收workerman线程异步提交的任务，执行结束后可以将执行结果主动推送给workerman线程`
   
   
另外任务线程采用协程调度方式实现，自己控制线程等待/执行时机，最大限度压榨性能。并且方便自己扩展异步组件
   

## Requires
线程安全版本的 PHP7 or 更高  
A POSIX compatible operating system (Linux, OSX, BSD)  
POSIX PCNTL and **PTHREDS** extensions for PHP  

## Workerman的使用方法

中文主页:[http://www.workerman.net](http://www.workerman.net)

中文文档: [http://doc.workerman.net](http://doc.workerman.net)

Documentation:[https://github.com/walkor/workerman-manual](https://github.com/walkor/workerman-manual/blob/master/english/src/SUMMARY.md)


## 使用异步任务功能

### workerman线程
```php
use Cor\CorWorker;

require_once __DIR__ . '/Workerman/Autoloader.php';

$worker = new CorWorker("http://0.0.0.0:1236");
$worker->count = 1;
//设置任务线程执行的位置,不设置默认根目录下Events
$worker->eventHandler = "Events";

//以前怎么使用workerman，现在还可以怎么使用，毫无区别
$worker->onMessage = function ($connection,$data){
    //你也可以选择这样的方式，也就是workerman的方式,我们先注释掉，使用任务线程的send方法返回数据
    //$connection->send("hello workerman_cor_ape");
    //这段代码会异步任务线程Evnets类里面的testMysql方法
    CorWorker::add_job("testMysql",$connection,$data);

};

//任务线程可以将结果主动通知给workerman线程
$worker->jobReturn = function ($connection,$data){
    var_dump($data);
};

CorWorker::runAll();
```

### 任务线程
```php
class Events{
	

    //任务线程任务
    public static function testMysql($connection,$data){
        //异步执行mysql操作,这只是随手写的例子，各位老爷能了解到可以很简单实现异步mysql就好
        $mysql = new \Cor\Extend\MySql("127.0.0.1","账号","密码","数据库");
        //查询三次
        $mysql->async_query("select * from t_admin");
        $mysql->async_query("select * from t_admin");
        $mysql->async_query("select * from t_admin");
        //注意这里采用协程方式访问mysql里面的协程方法
        $res = yield from $mysql->async_result();
        //任务线程内也可以使用$connection的绝大多数方法(pipe方法略有不同，需要传递$connection的id);
        $connection->send(json_encode($res));
        //当访问connection没有内置的方法的时候，会触发workerman线程的$worker->方法
        $connection->jobReturn("hello worker_cor_ape!");
        //方法内最少包含一个yield字段
        yield;
    }

}
```


## 如何启动
```php start.php start  ```  
```php start.php start -d  ```  
```php start.php status  ```   
```php start.php connections```  
```php start.php stop  ```  
```php start.php restart  ```  
```php start.php reload  ```  

# Workerman性能测试
```
CPU:      Intel(R) Core(TM) i3-3220 CPU @ 3.30GHz and 4 processors totally
Memory:   8G
OS:       Ubuntu 14.04 LTS
Software: ab
PHP:      5.5.9
```

**Codes**
```php
<?php
use Workerman\Worker;
$worker = new Worker('tcp://0.0.0.0:1234');
$worker->count=3;
$worker->onMessage = function($connection, $data)
{
    $connection->send("HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nServer: workerman\r\nContent-Length: 5\r\n\r\nhello");
};
Worker::runAll();
```
**Result**

```shell
ab -n1000000 -c100 -k http://127.0.0.1:1234/
This is ApacheBench, Version 2.3 <$Revision: 1528965 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient)
Completed 100000 requests
Completed 200000 requests
Completed 300000 requests
Completed 400000 requests
Completed 500000 requests
Completed 600000 requests
Completed 700000 requests
Completed 800000 requests
Completed 900000 requests
Completed 1000000 requests
Finished 1000000 requests


Server Software:        workerman/3.1.4
Server Hostname:        127.0.0.1
Server Port:            1234

Document Path:          /
Document Length:        5 bytes

Concurrency Level:      100
Time taken for tests:   7.240 seconds
Complete requests:      1000000
Failed requests:        0
Keep-Alive requests:    1000000
Total transferred:      73000000 bytes
HTML transferred:       5000000 bytes
Requests per second:    138124.14 [#/sec] (mean)
Time per request:       0.724 [ms] (mean)
Time per request:       0.007 [ms] (mean, across all concurrent requests)
Transfer rate:          9846.74 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.0      0       5
Processing:     0    1   0.2      1       9
Waiting:        0    1   0.2      1       9
Total:          0    1   0.2      1       9

Percentage of the requests served within a certain time (ms)
  50%      1
  66%      1
  75%      1
  80%      1
  90%      1
  95%      1
  98%      1
  99%      1
 100%      9 (longest request)

```

## 联系我

QQ群: 342016184   
任何人都可以通过QQ群联系到我。
