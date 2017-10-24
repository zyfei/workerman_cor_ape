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
    //子线程
    public $taskThread = null;
    public $taskAsyncConn = null;
    //工作类路径
    public $eventHandler;
    protected $_onConnect;
    protected $_onWorkerStart;
    protected $_onWorkerStop;
    protected $_onMessage;
    protected $_onClose;
    protected $_onError;
    protected $_onBufferFull;
    protected $_onBufferDrain;
    protected $cpu_unix_name;
    /**
     * 析构函数
     * @param string $socket_name
     * @param array $context_option
     */
    public function __construct($socket_name, $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
    }

    public function run()
    {

        // 查看php版本
        if (( int )substr(PHP_VERSION, 0, 1) < 7) {
            printf("error : php version must > 7");
            exit ();
        }

        if (! extension_loaded ( 'pthreads' )) {
            exit ( "Please install pthreads extension. See http://doc3.workerman.net/install/install.html\n" );
        }

        if (!$this->eventHandler) {
           $this->eventHandler = "Events";
        }
        //管道位置
        $this->cpu_unix_name = 'unix:///tmp/cor_'.posix_getpid() . ".sock";
        //创建任务线程
        $this->taskThread = new CorThread($this->cpu_unix_name,$this->eventHandler);
        $this->taskThread->start();
        //设置工作类路径


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
        Timer::add(0.3,function() use (&$worker){
            $worker->taskAsyncConn = new AsyncTcpConnection($worker->taskThread->cpu_unix_name);
            $worker->taskAsyncConn->onMessage = function ($conn, $data) use ($worker) {
                //游标
                $i = 0;
                while (true) {
                    $len = substr($data, $i, $i + 2);
                    $body_len = unpack('Sbin_len', $len)['bin_len'];
                    $body = substr($data, $i + 2, $body_len);
                    $body = json_decode($body, true);
                    foreach ($body as $n) {
                        $method = $n[0];
                        $connection = $worker->connections[$n[1]];
                        switch ($method) {
                            case "send":
                                $connection->send($n[2]);
                                break;
                            case "close":
                                $connection->close($n[2]);
                                break;
                            case "destroy":
                                $connection->send();
                                break;
                            case "pauseRecv":
                                $connection->pauseRecv();
                                break;
                            case "resumeRecv":
                                $connection->resumeRecv();
                                break;
                            case "pipe":
                                $connection->pipe($worker->connections[$n[2]]);
                                break;
                            default:
                                call_user_func($worker->$method, $connection,$n[2]);
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
            $worker->taskAsyncConn->onClose = function ($con) {
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
        },array(),false);
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
    }

    /**
     *  封装传入数据
     */
    public static function add_job($method,$connection,$data = '')
    {
        if($method=='' || !$connection){
            return false;
        }
        $arr = array();
        $arr[0] = $method;
        $obj = array();
        $obj[0] = $connection->id;
        $obj[1] = $data;
        $obj[2] = $connection->getRemoteIp();
        $obj[3] = $connection->getRemotePort();

        $arr[1] = $obj;
        $buffer = json_encode($arr);
        $body_len = strlen($buffer);
        $bin_head = pack('S*', $body_len);

        $connection->worker->taskAsyncConn->send($bin_head . $buffer);
    }

}