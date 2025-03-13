<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pastas:reset') ->monthlyOn(1, '00:00');
/*Schedule::command('pastas:reset')->everyFiveMinutes();
crontab -e
* * * * * php /caminho/para/o/seu/projeto/artisan schedule:run >> /dev/null 2>&1


*/
