<?php
/**
 * Created by PhpStorm.
 * User: zhangyufei
 * Date: 2017/10/23
 * Time: 17:49
 */

namespace Cor\Extend;

/**
 * 非常简单的异步mysql例子
 */
class MySql
{

    public $conns = array();

    public $host;
    public $user;
    public $password;
    public $database;

    public function __construct($host, $user, $password, $database)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
    }

    function async_query($sql)
    {
        $con = new \mysqli($this->host, $this->user, $this->password, $this->database);
        $con->query($sql, MYSQLI_ASYNC);
        $this->conns[$con->thread_id] = $con;
    }

    function async_result()
    {
        $res = array();
        $e = array();
        $e2 = array();
        while (count($this->conns)>0) {
            $reads = $this->conns;

            if (!mysqli_poll($reads, $e, $e2, 10)) {
                yield;
                continue;
            }
            foreach ($reads as $obj) {
                unset($this->conns[$obj->thread_id]);
                $sql_result = $obj->reap_async_query();
                $sql_result_array = $sql_result->fetch_all();
                $sql_result->free();
                $res[] = $sql_result_array;
            }
            yield;
        }
        return $res;
    }

}
