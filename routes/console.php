<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\ResetPastasCounter;
use App\Jobs\ResetRanking;
use App\Jobs\GetRanking;

Schedule::job(new ResetPastasCounter())->monthlyOn(1,'00:00');
Schedule::job(new ResetRanking())->monthlyOn(1,'00:01');
Schedule::job(new GetRanking())->lastDayOfMonth('23:59');
