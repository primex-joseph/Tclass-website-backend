<?php

use App\Support\EnrollmentPeriodRollover;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('enrollment:rollover', function () {
    try {
        $result = EnrollmentPeriodRollover::rolloverToNextPeriod();
        $this->info('Enrollment period rollover complete.');
        $this->line('From: ' . $result['from']['name']);
        $this->line('To:   ' . $result['to']['name']);
    } catch (\RuntimeException $e) {
        $this->error($e->getMessage());
        return 1;
    } catch (\Throwable $e) {
        $this->error('Failed to roll over enrollment period.');
        return 1;
    }

    return 0;
})->purpose('Advance active enrollment period to the next term and auto-create next AY periods.');
