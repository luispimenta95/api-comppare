<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\ResetPastasCounter;

Schedule::job(new ResetPastasCounter())->monthlyOn(1,'00:00');
