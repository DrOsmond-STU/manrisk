<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('manrisk:daily-sweep')->dailyAt('06:00');
Schedule::command('manrisk:scheduled-reports')->dailyAt('07:00');
Schedule::command('manrisk:snapshot')->monthlyOn(1, '00:30');
Schedule::command('manrisk:snapshot')->lastDayOfMonth('23:30');
