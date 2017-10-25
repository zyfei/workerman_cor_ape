<?php
namespace Cor\Net;

/**
 * 系统调用
 */
class SystemCall {
	protected $callback;
	
	//传入一个回调函数类型
	public function __construct(callable $callback) {
		$this->callback = $callback;
	}
	
	//可以将对象当做方法使用，默认就调用这个
	public function __invoke(Task $task, Cpu $cpu) {
		$callback = $this->callback;
		return $callback($task, $cpu);
	}
	
	//创建新任务
	public static function newTask(\Generator $coroutine) {
		return new SystemCall ( function (Task $task, Cpu $cpu) use ($coroutine) {
			$task->setSendValue ( $cpu->newTask ( $coroutine ) );
            $cpu->schedule ( $task );
		} );
	}

}