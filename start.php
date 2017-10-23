<?php
use Cor\CorWorker;

require_once __DIR__ . '/Workerman/Autoloader.php';

$worker = new CorWorker("http://0.0.0.0:1236");
$worker->count = 1;
//设置执行任务的类
$worker->eventHandler = "Events";

$worker->onMessage = function ($connection,$data){
    //CorWorker::add_job("testMysql",$connection,$data);

    //你可以选择通过这种方式，让task线程执行,这会执行Events.php里面的teskJob方法
    CorWorker::add_job("testJob",$connection,$data);

    //你也可以选择这样的方式，也就是workerman的方式
    //$connection->send("Cor hello world");
};

//接收task进程传递的参数
$worker->jobReturn = function ($connection,$data){
    var_dump("jobReturn");
    var_dump($data);
};

// 日志
CorWorker::$logFile = __DIR__ . "/Log/log.log";
// 访问日志
CorWorker::$stdoutFile = __DIR__ . "/Log/stadout.log";

CorWorker::runAll();