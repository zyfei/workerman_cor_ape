<?php

class Events{

    //任务线程任务
    public static function testMysql($data){
        //异步执行mysql操作,这只是随手写的例子，各位老爷能了解到可以很简单实现异步mysql就好
        $mysql = new \Cor\Extend\MySql("127.0.0.1","www","www","pingan_machine_oil");
        //查询三次
        $mysql->async_query("select * from t_admin");
        $mysql->async_query("select *  from t_admin");
        $mysql->async_query("select *   from t_admin");
        //注意这里采用协程方式访问mysql里面的协程方法
        $res = yield from $mysql->async_result();
        return $res;
    }

    public static function helloworld($data){
        return "helloworld cor";
        yield;
    }

}