Adhoc Queue Systems
===

This is for advanced users only!

This subplugin directory can contain additional queuing systems for adhoc tasks, such as beanstalk and redis.
The cron system will still run in the background as a backup unless you disable that.

It relies on a core hack (don't all of my plugins?!), you need to change this function in '/lib/classes/task/manager.php':
```
public static function queue_adhoc_task(adhoc_task $task) {
    ...

    \tool_adhoc\manager::queue_adhoc_task($result);

    return $result;
}
```

All plugins should implement "\tool_adhoc\queue" and must have a class "queue".
See 'cron' or 'https://github.com/unikent/moodle-queue_beanstalk' for example implementations.
