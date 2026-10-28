<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('smashbook:release-holds')->everyMinute();
Schedule::command('smashbook:recurring-bookings')->dailyAt('00:30');
Schedule::command('smashbook:recurring-expenses')->monthlyOn(1, '01:00');
Schedule::command('smashbook:check-membership')->dailyAt('08:00');
Schedule::command('smashbook:backup')->dailyAt('02:00');
