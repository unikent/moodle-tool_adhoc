Adhoc Queue Systems
===

This subplugin directory can contain additional queuing systems for adhoc tasks, such
as beanstalk and redis.

It relies on a core hack (don't all of my plugins?!), you need to change this function in '/lib/classes/task/manager.php':
```
public static function queue_adhoc_task(adhoc_task $task) {
    ...

    \tool_adhoc\manager::queue_adhoc_task($result);

    return $result;
}
```
