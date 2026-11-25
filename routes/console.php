<?php

use Illuminate\Support\Facades\Schedule;

/*
 * TAREFAS AGENDADAS
 * Para rodarem sozinhas, o servidor precisa deste cron (a cada minuto):
 *   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
 * No Windows (teste local): php artisan schedule:work
 */
Schedule::command('ecotech:expirar')->everyMinute()->withoutOverlapping();
