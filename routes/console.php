<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:pgsql --cleanup')
    ->dailyAt('02:30')
    ->timezone('America/Sao_Paulo')
    ->environments(['production'])
    ->onOneServer()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('backup:mongodb --cleanup')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo')
    ->environments(['production'])
    ->onOneServer()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));
