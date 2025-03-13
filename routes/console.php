<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\ResetPastasCounter;

Schedule::job(new ResetPastasCounter())->everyFiveMinutes();
