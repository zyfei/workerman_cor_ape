<?php

namespace Cor;

use Cor\CorThread;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;

/**
 * Class CoroutineWorker
 * @package Workerman
 * 协程版本的Worker
 */
class CorWorker extends Worker
{
    // 当前分配的ID号,前100系统保留
    protected static $_job_key_recorder = 100;
    public $_job_funs = array();

    //子线程
    public $taskThread = null;
    public $taskAsyncConn = null;
    //当前正在执行的connection
    protected $_connection;

    //工作类路径
    public $eventHandler;
    protected $_onWorkerStart;
    protected $_onWorkerStop;

    public static function runAll()
    {
        // 查看php版本
        if (( int )substr(PHP_VERSION, 0, 1) < 7) {
            printf("error : php version must > 7");
            exit ();
        }

        if (!extension_loaded('pthreads')) {
            exit ("Please install pthreads extension. See http://doc3.workerman.net/install/install.html\n");
        }
        // 运行父方法
        parent::runAll();
    }

    public function run()
    {
        //设置工作类路径
        if (!$this->eventHandler) {
            $this->eventHandler = "Events";
        }
        //管道位置
        $this->cpu_unix_name = 'unix:///tmp/cor_' . posix_getpid() . ".sock";
        //创建任务线程
        $this->taskThread = new CorThread($this->cpu_unix_name, $this->eventHandler);
        $this->taskThread->start();

        //重新设置方法
        $this->_onWorkerStart = $this->onWorkerStart;
        $this->onWorkerStart = array($this, "onWorkerStart");
        $this->_onWorkerStop = $this->onWorkerStop;
        $this->onWorkerStop = array($this, "onWorkerStop");

        // 运行父方法
        parent::run();
    }

    /**
     * 进程start
     */
    public function onWorkerStart($worker)
    {
        Timer::add(0.3, function () use (&$worker) {
            $worker->taskAsyncConn = new AsyncTcpConnection($worker->taskThread->cpu_unix_name);
            $worker->taskAsyncConn->onMessage = function ($conn, $data) use ($worker) {
                //游标
                $i = 0;
                while (true) {
                    $len = substr($data, $i, $i + 2);
                    $body_len = unpack('Sbin_len', $len)['bin_len'];
                    $body = substr($data, $i + 2, $body_len);
                    $body = json_decode($body, true);
                    if($body[0]!=1){
                        if (array_key_exists($body[0], $worker->_job_funs)) {
                            call_user_func($worker->_job_funs[$body[0]][1], $body[1]);
                        }
                    }
                    $i = $i + 2 + $body_len;
                    if ($i >= strlen($data)) {
                        break;
                    }
                }
            };
            $worker->taskAsyncConn->onConnect = function ($con) {
                var_dump("job thread is ready!");
            };
            $worker->taskAsyncConn->onClose = function ($con)use($worker) {
                $this->taskThread->is_exit = true;
                posix_kill(posix_getppid(), SIGUSR1);
            };
            $worker->taskAsyncConn->onError = function ($con, $code, $msg) {
                $this->taskThread->is_exit = true;
                posix_kill(posix_getppid(), SIGUSR1);
            };
            $worker->taskAsyncConn->connect();

            if ($worker->_onWorkerStart) {
                call_user_func($worker->_onWorkerStart, $worker);
            }
        }, array(), false);

        /**
         * 10秒钟检查一次是否有任务超时
         */
        Timer::add(10, function () use (&$worker) {
            $time = time();
            foreach ($worker->_job_funs as $k => $n) {
                if ($time >= $n[0]) {
                    unset($worker->_job_funs[$k]);
                }
            }
        });
    }

    public function onWorkerStop($worker)
    {
        $this->taskThread->is_exit = true;
        /**
         * 退出的时候删除unix文件
         */
        if (strpos($worker->cpu_unix_name, "unix://") === 0) {
            $unix_file = str_replace("unix://", "", $worker->cpu_unix_name);
            if (file_exists($unix_file)) {
                unlink($unix_file);
            }
        }
        if ($worker->_onWorkerStop) {
            call_user_func($worker->_onWorkerStop, $worker);
        }
    }

    /**
     * @param $job 任务名称
     * @param $data 传递到任务中的数据
     * @param null $function 回调方法
     * @param int $timeout 超时时间，超过这个时间，主线程不会收到返回通知
     */
    public function ajax($job, $data, $function = null, $timeout = 10000)
    {
        /** @var 当key等于0的时候，代表不需要异步返回 $key */
        if ($function == null) {
            $key = 0;
        } else {
            if (PHP_INT_MAX === self::$_job_key_recorder) {
                self::$_job_key_recorder = 100;
            }
            self::$_job_key_recorder = self::$_job_key_recorder + 1;
            $key = self::$_job_key_recorder;
            //保存当前回调函数
            $this->_job_funs[$key] = array(time() + $timeout, $function);
        }

        /**  创建访问线程的消息体  */
        $arr = array();
        $arr[0] = $job;//任务名
        $arr[1] = $key;//任务唯一key
        $arr[2] = $data;//传递消息体
        $buffer = json_encode($arr);
        $body_len = strlen($buffer);
        $bin_head = pack('S*', $body_len);
        $this->taskAsyncConn->send($bin_head . $buffer);
    }

    /**
     * 重启整个进程
     */
    public function reload_cor(){
        $this->taskThread->is_exit = true;
        posix_kill(posix_getppid(), SIGUSR1);
    }
}