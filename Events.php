<?php

class Events{

    public static function testJob($connection,$data){
        //和workerman的send方法一样，colse等方法也可以用，你懂得
        $connection->send("Cor hello world");
        //返回主线程，调用worker->jobReturn方法。并且可以传递参数
        $connection->jobReturn("参数");
        yield;
    }

    public static function testMysql($connection,$data){
        $mysql = new \Cor\Extend\MySql("127.0.0.1","www","www","数据库");
        $mysql->async_query("select * from t_admin");
        $mysql->async_query("select * from t_admin");
        $mysql->async_query("select * from t_admin");

        $res = yield from $mysql->async_result();
        $connection->send(json_encode($res));
        yield;
    }

}