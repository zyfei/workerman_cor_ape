<?php

namespace Cor\Net;

use Cor\Net\Events\Select;

class CpuConnection
{
    const READ_BUFFER_SIZE = 65535;

    // 当前分配的ID号
    public static $max_id = 0;
    // 唯一ID
    public $id = 0;
    public $worker = null;
    public $conn = null;
    public $onMessage = null;

    public $old_buffer = '';

    public function __construct($worker, $socket)
    {
        self::$max_id++;
        $this->id = self::$max_id;
        $conn = stream_socket_accept($socket, 0);
        $this->worker = $worker;
        $this->conn = $conn;
    }

    // 动起来，跟着我的世界一起喝彩
    public function read()
    {
        $buffer = @fread($this->conn, self::READ_BUFFER_SIZE);
        if ($buffer === '' || $buffer === false) {
            if (feof($this->conn) || !is_resource($this->conn) || $buffer === false) {
                $this->destroy();
                return;
            }
        }

        $this->old_buffer = $this->old_buffer . $buffer;
        while(strlen($this->old_buffer)>2){
            $len = substr($this->old_buffer, 0, 2);
            $body_len = unpack('Sbin_len', $len)['bin_len'];
            if(strlen($this->old_buffer)<($body_len+2)){
                break;
            }
            $data = substr($this->old_buffer, 2, $body_len);
            $data = json_decode($data,true);
            $this->old_buffer = substr($this->old_buffer,($body_len+2),strlen($this->old_buffer)-($body_len+2));
            $conn = null;
            $conn = new Conn($data[1][0], $data[1][2], $data[1][3]);

            if (is_callable($this->worker->thread->eventHandler . '::' . $data[0])) {
                $gen = $this->worker->thread->eventHandler . '::' . $data[0];
                //模拟workerman的TcpConnection类
                $response = yield from $gen ($conn, $data[1][1]);

                //默认发送
                if ($response != "") {
                    //调用conn的发送
                    $conn->send($response);
                }
                /** 在这里查看执行了什么操作，然后统一返回给workerman线程执行 */
                $responseJobs = $conn->responseJobs;
                $conn->responseJobs = array();
                if (count($responseJobs) > 0) {
                    //$this->_write($this->conn,json_encode($responseJobs));
                    $this->worker->_event->add($this->conn, Select::EV_WRITE, array($this, '_write'), array(json_encode($responseJobs)));
                }
                unset($conn);
            }
        }
        yield;
    }


    public function destroy()
    {
        if ($this->worker) {
            unset ($this->worker->connections [(int)$this->conn]);
        }
        $this->worker->_event->del($this->conn,Select::EV_READ);
        $this->worker->_event->del($this->conn,Select::EV_WRITE);
    }

    /**
     * 读回调
     */
    public function _read($socket){
        //将读取操作添加进任务队列中
        //尽量缩小不必要的任务切换，将任务切换使用在阻塞操作当中
        $this->worker->newTask ( $this->read() );
    }

    /**
     * 写回调
     */
    public function _write($key,$socket,$data){
        $body_len = strlen($data);
        $bin_head = pack('S*', $body_len);
        $len = @fwrite($socket, $bin_head . $data);

        if ($len === strlen($bin_head . $data)) {
            $this->worker->_event->del($socket, Select::EV_WRITE,$key);
        }else{
            $this->destroy();
        }

    }
}