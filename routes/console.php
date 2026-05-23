<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('selo:version', function (): void {
    $this->info('SELO Laravel build is available.');
});
