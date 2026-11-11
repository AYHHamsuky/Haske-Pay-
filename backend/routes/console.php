<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('haske:requery')->everyTwentySeconds()->withoutOverlapping();
Schedule::command('haske:provider-balance')->everyFifteenMinutes();
Schedule::command('haske:audit-ledger')->dailyAt('01:00')->timezone('Africa/Lagos');
Schedule::command('queue:prune-failed --hours=168')->daily();
