<?php
/**
 * 作用就是模仿workerman的Connnection类，并且提供相同的close,send等方法
 */
namespace Cor\Net;

class Conn{

    public $id;
    public $remoteIp = null;
    public $remotePort = null;
    public $responseJobs = array();

    public function __construct($id,$remoteIp,$remotePort){
        $this->id = $id;
        $this->remoteIp = $remoteIp;
        $this->remotePort = $remotePort;
    }

    public function send($data){
        $job = array();
        $job[0] = "send";
        $job[1] = $this->id;
        $job[2] = $data;
        $this->responseJobs[] = $job;
    }

    /**
     * 获得该连接的客户端ip
     */
    public function getRemoteIp(){
        return $this->remoteIp;
    }

    /**
     * 获得该连接的客户端端口
     */
    public function getRemotePort(){
        return $this->remotePort;
    }

    /**
     * 安全的关闭连接.
     */
    public function close($data=''){
        $job = array();
        $job[0] = "close";
        $job[1] = $this->id;
        $job[2] = $data;
        $this->responseJobs[] = $job;
    }

    /**
     * 安全的关闭连接.
     */
    public function destroy(){
        $job = array();
        $job[0] = "destroy";
        $job[1] = $this->id;
        $this->responseJobs[] = $job;
    }

    /**
     * 使当前连接停止接收数据
     */
    public function pauseRecv(){
        $job = array();
        $job[0] = "pauseRecv";
        $job[1] = $this->id;
        $this->responseJobs[] = $job;
    }

    /**
     * 使当前连接继续接收数据
     */
    public function resumeRecv(){
        $job = array();
        $job[0] = "resumeRecv";
        $job[1] = $this->id;
        $this->responseJobs[] = $job;
    }

    /**
     * 将当前连接的数据流导入到目标连接。内置了流量控制。此方法做TCP代理非常有用
     * 正常传入TcpConnection对象，现在传入connectionId就可以
     */
    public function pipe($tcp_connection_id){
        $job = array();
        $job[0] = "pipe";
        $job[1] = $this->id;
        $job[2] = $tcp_connection_id;
        $this->responseJobs[] = $job;
    }

    /**
     * 默认方法
     * @param $method
     * @param $data
     */
    public function __call($name, $arguments){
        $job = array();
        $job[0] = $name;
        $job[1] = $this->id;
        $job[2] = $arguments[0];
        $this->responseJobs[] = $job;
    }

}