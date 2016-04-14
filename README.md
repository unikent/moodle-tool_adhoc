Adhoc task tool
==================

Adhoc task management interface for Moodle 2.7+.
This plugin allows you to manage failing adhoc tasks and to monitor/test adhoc tasks during development.
It also provides CLI utilities for running large batches of adhoc tasks at once to offload from cron, for example during annual rollover.

![Image of page] (https://cloud.githubusercontent.com/assets/4242976/9224318/55e644d6-40f9-11e5-9b02-8d7446b914d5.png)

Queue plugins
====

The plugin also provides support for alternate queuing systems, as defined by subplugins under the "queue" folder.
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
