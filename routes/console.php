<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reporting:lock-expired')->dailyAt('00:30');
