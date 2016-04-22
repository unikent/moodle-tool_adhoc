Cron task system
===

You can either leave this to run Moodle-side or disable the task and run cli/cron.php separately so adhoc tasks do not interfere with regular tasks.
You should also disable the default adhoc task runner:

In /lib/cronlib.php, there is a comment "// Run all adhoc tasks.", after which you will find a while() loop... Delete that loop.
