<?php
namespace Cor;

/**
 * Created by PhpStorm.
 * User: zhangyufei
 * Date: 2017/10/18
 * Time: 16:33
 * 异步线程的协程调度器
 * Generator类型数据不可线程共享
 */
class CorThread extends \Thread
{
    public $cpu_unix_name = "none.sock";
    public $is_exit;
    public $eventHandler = "Events";

    public function __construct($cpu_unix_name,$eventHandler)
    {
        $this->is_exit = false;
        $this->cpu_unix_name = $cpu_unix_name;
        $this->eventHandler = $eventHandler;
    }

    public function run()
    {
        //分离子线程和父线程
        spl_autoload_register(function ($name) {
            $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $name);
            $class_file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . "$class_path.php";
            if (is_file($class_file)) {
                require_once($class_file);
                if (class_exists($name, false)) {
                    return true;
                }
            }
            return false;
        });
        global $cpu;
        $cpu = new \Cor\Net\Cpu($this->cpu_unix_name,$this);
        $cpu->run();
    }

}
