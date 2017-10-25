<?php

namespace Cor\Net\Events;
use Cor\Net\CpuConnection;

/**
 * 协程版本的网络监听库
 * Class Select
 * @package Cor\Net
 */
class Select
{

    /**
     * Read event.
     * @var int
     */
    const EV_READ = 1;

    /**
     * Write event.
     * @var int
     */
    const EV_WRITE = 2;

    /**
     * 在异步链接时候检查通道正确性的时候见用过
     * Except event
     *
     * @var int
     */
    const EV_EXCEPT = 3;

    protected $_allEvents = array();
    protected $_readFds = array();
    protected $_writeFds = array();

    /**
     * 写入操作有可能发生挤压，前面的没执行，后面的覆盖
     */
    protected static $write_key = array();

    /**
     * 是否继续执行任务
     * @var bool
     */
    protected $is_loop = true;

    /**
     * 他的调度器
     */
    protected $cpu = null;

    public function __construct($cup)
    {
        $this->cpu = $cup;
    }


    /**
     * 添加一个socket监听任务
     * @param $fd
     * @param $flag 什么类型的监听，是读监听还是写监听
     * @param $func
     * @param null $args
     * @param $key //写操作可能不一样，每次可能有好几个写操作挤压，因为是异步的，所以为没个操作设定唯一key，写操作可以不设定
     */
    public function add($fd, $flag, $func, $args = array())
    {
        array_unshift($args, $fd);
        switch ($flag) {
            case self::EV_READ:
                $fd_key = (int)$fd;
                $this->_allEvents[$fd_key][$flag] = array($func, $args);
                $this->_readFds[$fd_key] = $fd;
                break;
            case self::EV_WRITE:
                $fd_key = (int)$fd;
                if (empty(self::$write_key[$fd_key])){
                    self::$write_key[$fd_key][0] = 1;//写操作唯一表示
                    self::$write_key[$fd_key][1] = 0;//代表读取到了哪里
                }else{
                    self::$write_key[$fd_key][0] = self::$write_key[$fd_key][0] + 1;
                }
                //写操作可能不一样，每次可能有好几个写操作挤压，因为是异步的
                array_unshift($args, self::$write_key[$fd_key][0]);
                $this->_allEvents[$fd_key][$flag][self::$write_key[$fd_key][0]] = array($func, $args);
                $this->_writeFds[$fd_key] = $fd;
                break;
        }
        return true;
    }

    /**
     * 从监听列表中移除一个监听任务
     * @param mixed $fd
     * @param int $flag
     * @return bool
     * $key 写入操作需要的唯一标识，指定哪个写入操作
     */
    public function del($fd, $flag , $key=null)
    {
        $fd_key = (int)$fd;
        switch ($flag) {
            case self::EV_READ:
                unset($this->_allEvents[$fd_key][$flag], $this->_readFds[$fd_key]);
                if (empty($this->_allEvents[$fd_key])) {
                    unset($this->_allEvents[$fd_key]);
                }
                return true;
            case self::EV_WRITE:
                unset($this->_allEvents[$fd_key][$flag][$key]);
                if(count($this->_allEvents[$fd_key][$flag])==0){
                    unset($this->_allEvents[$fd_key][$flag], $this->_writeFds[$fd_key]);
                }
                if (empty($this->_allEvents[$fd_key])) {
                    unset($this->_allEvents[$fd_key]);
                }
                return true;
        }
        return false;
    }

    /**
     * Main loop.
     *
     * @return void
     */
    public function loop()
    {
        $e = null;
        while ($this->is_loop) {
            //如果没有任务，自身也会一秒循环一次
            $timeout = 0;
            if ($this->cpu->taskQueueIsEmpty()) {
                $this->_check_workerman_live();
                $timeout = 2;
            }
            $read = $this->_readFds;
            $write = $this->_writeFds;
            $except = null;
            // Waiting read/write/signal/timeout events.
            if (!@stream_select($read, $write, $except, $timeout)) {
                yield;
                continue;
            }

            if ($read) {
                foreach ($read as $fd) {
                    $fd_key = (int)$fd;
                    if (isset($this->_allEvents[$fd_key][self::EV_READ])) {
                        $_e = reset($this->_allEvents[$fd_key][self::EV_READ]);
                        call_user_func_array($this->_allEvents[$fd_key][self::EV_READ][0],
                            $this->_allEvents[$fd_key][self::EV_READ][1]);
                    }
                }
            }

            if ($write) {
                foreach ($write as $fd) {
                    $fd_key = (int)$fd;
                    //获取下一个写入key
                    self::$write_key[$fd_key][1] = self::$write_key[$fd_key][1] + 1;
                    if (isset($this->_allEvents[$fd_key][self::EV_WRITE][self::$write_key[$fd_key][1]])) {

                        call_user_func_array($this->_allEvents[$fd_key][self::EV_WRITE][self::$write_key[$fd_key][1]][0],
                            $this->_allEvents[$fd_key][self::EV_WRITE][self::$write_key[$fd_key][1]][1]);
                    }
                }
            }

            /** 每次循环一次都将程序控制权交给调度器 */
            yield;
        }
    }

    /**
     * Destroy loop.
     *
     * @return mixed
     */
    public function destroy()
    {
        $this->is_loop = false;
    }

    public function _check_workerman_live(){
        $data = json_encode(array(1,""));
        $body_len = strlen($data);
        $bin_head = pack('S*', $body_len);
        $len = @fwrite($this->cpu->_mainSocket, $bin_head . $data);
        if ($len !== strlen($bin_head . $data)) {
            $this->cpu->thread->is_exit = false;
        }

    }

}