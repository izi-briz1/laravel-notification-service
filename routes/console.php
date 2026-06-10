<?php

use Illuminate\Support\Facades\Schedule;

// Outbox-свипер: добирает уведомления, не опубликованные в брокер
Schedule::command('notifications:dispatch-stuck')->everyMinute();
