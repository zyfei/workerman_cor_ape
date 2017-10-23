<?php
namespace Cor\Net;

class Task {
	//任务ID
	protected $taskId;
	//生成器,也就是那个执行的函数
	protected $coroutine;
	protected $sendValue = null;
	//是否是第一个
	protected $beforeFirstYield = true;
	
	public function __construct(&$taskId, \Generator $coroutine) {
		$this->taskId = $taskId;
		$this->coroutine = $coroutine;
	}
	
	public function getTaskId() {
		return $this->taskId;
	}
	
	public function setSendValue($sendValue) {
		$this->sendValue = $sendValue;
	}
	
	public function run() {
		if ($this->beforeFirstYield) {
			$this->beforeFirstYield = false;
			return $this->coroutine->current ();
		} else {
			////向生成器中传入一个值，当前yield接收值，然后继续执行下一个yield
			$retval = $this->coroutine->send ( $this->sendValue );
			$this->sendValue = null;
			return $retval;
		}
	}
	
	public function isFinished() {
		return ! $this->coroutine->valid ();
	}
}