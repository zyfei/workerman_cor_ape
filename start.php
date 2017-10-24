<?php
use Cor\CorWorker;

require_once __DIR__ . '/Workerman/Autoloader.php';

$worker = new CorWorker("http://0.0.0.0:1236");
$worker->count = 1;
//设置执行任务的类
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

// 日志
CorWorker::$logFile = __DIR__ . "/Log/log.log";
// 访问日志
CorWorker::$stdoutFile = __DIR__ . "/Log/stadout.log";

CorWorker::runAll();