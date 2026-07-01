<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Course;

$courses = Course::whereIn('id', [515, 572])->get(['id','title','active','start_date','end_date','registration_start_date','registration_end_date','sections']);
foreach ($courses as $c) {
    echo 'ID=' . $c->id
        . ' | title=' . $c->title
        . ' | active=' . ($c->active ? 'true' : 'false')
        . ' | start=' . ($c->start_date ? $c->start_date->toDateString() : '-')
        . ' | end=' . ($c->end_date ? $c->end_date->toDateString() : '-')
        . ' | reg_start=' . ($c->registration_start_date ? $c->registration_start_date->toDateString() : '-')
        . ' | reg_end=' . ($c->registration_end_date ? $c->registration_end_date->toDateString() : '-')
        . ' | sections=' . json_encode($c->sections)
        . PHP_EOL;
}
