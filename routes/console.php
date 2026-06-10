<?php

use Illuminate\Support\Facades\Schedule;

// Outbox-свипер: добирает уведомления, не опубликованные в брокер
Schedule::command('notifications:dispatch-stuck')->everyMinute();

// Dead-worker-свипер: возвращает в очередь уведомления, зависшие в sending
Schedule::command('notifications:requeue-stuck-sending')->everyFiveMinutes();
