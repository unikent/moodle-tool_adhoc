Adhoc Queue Systems
===

This subplugin directory can contain additional queuing systems for adhoc tasks, such
as beanstalk and redis.

It relies on a core hack (don't all of my plugins?!), you need to change this function in '/lib/classes/task/manager.php':
```
public static function queue_adhoc_task(adhoc_task $task, $nextruntime = null, $priority = 1024) {
    global $DB;

    if ($nextruntime === null) {
        // Schedule it immediately.
        $nextruntime = time() - 1;
    }

    $record = self::record_from_adhoc_task($task);
    // Schedule it immediately.
    $record->nextruntime = $nextruntime;
    $id = $DB->insert_record('task_adhoc', $record);

    \tool_adhoc\manager::queue_adhoc_task($id, $priority);

    return $id;
}
```
