<?php

namespace Cor\Net;

use Cor\Net\Events\Select;

class Cpu
{
    // 当前分配的ID号
    public static $_idRecorder = 0;
    public $id = 0;
    //当前运行cpu的线程
    public $thread = null;
    //当前的event库
    public $_event = "";
    // 当前连接通道数组
    public $connections = [];
    // 监听的路径
    protected $host;
    // 任务队列
    protected $taskQueue;
    // 当前任务ID进行到哪里
    protected $_taskId = 0;
    // 任务map
    protected $taskMap = []; // taskId => task

    // 监听端口的socket
    public $_mainSocket = null;


    public function __construct($host, $thread)
    {
        // 设置监听路径
        $this->host = $host;
        $this->thread = $thread;
        // 初始化唯一ID
        if (PHP_INT_MAX === self::$_idRecorder) {
            self::$_idRecorder = 0;
        }
        $this->id = self::$_idRecorder++;
        // 实例化数据结构队列
        $this->taskQueue = new \SplQueue ();
        // 初始化系统
        $this->init();
        $this->_event = new Select($this);
        $this->_event->add($this->_mainSocket, Select::EV_READ, array($this, '_accept'));
        $this->newTask($this->_event->loop());
    }


    /**
     * 传入协程对象，创建新任务，并且把任务放入任务队列
     */
    public function newTask(\Generator $coroutine)
    {
        if (PHP_INT_MAX === $this->_taskId) {
            self::$_idRecorder = 0;
        }
        $tid = $this->_taskId;

        // 创建一个任务
        $task = new Task ($tid, $coroutine);
        // 将任务放入任务表中
        $this->taskMap [$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    /**
     * 将任务放入任务队列底部
     */
    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    /**
     * 任务队列是否为空
     */
    public function taskQueueIsEmpty(){
        return $this->taskQueue->isEmpty();
    }

    /**
     * 开始调度任务，类似于cpu
     */
    public function run()
    {
        // 如果有任务队列中还有任务,那么继续执行
        while (!$this->taskQueue->isEmpty() && !$this->thread->is_exit) {
            // 取出队列头部的成员
            $task = $this->taskQueue->dequeue();
            // 执行一个刻度的任务
            $retval = $task->run();

            // 如果任务返回的是一个系统调用，那么执行这个调用,并且把这个任务移除，这是一次性任务
            if ($retval instanceof SystemCall) {
                $retval ($task, $this);
                continue;
            }

            // 如果任务运行完毕了
            if ($task->isFinished()) {
                // 那么把这个任务从任务表中移除
                unset ($this->taskMap [$task->getTaskId()]);
            } else {
                // 如果没完成，将他放入任务链表最后
                $this->schedule($task);
            }
        }
    }

    /**
     * 有新链接回调
     */
    public function _accept($socket){
        $connection = new CpuConnection ($this, $socket);
        $this->connections [( int )$connection->conn] = $connection;
        $this->_event->add($connection->conn, Select::EV_READ, array($connection, '_read'));
    }

    /**
     * 初始化相关数据
     */
    protected function init()
    {
        //如果是unix类型，那么启动之前先把之前的删除了
        if (strpos($this->host, "unix://") === 0) {
            $unix_file = str_replace("unix://", "", $this->host);
            if (file_exists($unix_file)) {
                unlink($unix_file);
            }
        }
        // 创建socket套接字,用来监听这个端口
        $socket = stream_socket_server($this->host, $errNo, $errStr);
        // 如果创建失败抛出异常
        if (!$socket) {
            throw new \Exception ($errStr, $errNo);
            exit ();
        }
        // 设置监听连接socket
        $this->_mainSocket = $socket;
        // 设置为非阻塞IO,当尝试读一个网络流，并且未读取字节的时候，立即告诉调用者结构
        stream_set_blocking($socket, 0);
    }

}

