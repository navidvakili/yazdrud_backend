<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== SliderProject Count ===\n";
$count = App\Models\SliderProject::count();
echo "$count projects found\n";

foreach (App\Models\SliderProject::all() as $p) {
    $slides = isset($p->project_data['slides']) ? count($p->project_data['slides']) : 0;
    echo "ID: {$p->id} | Title: {$p->title} | Active: " . ($p->is_active ? 'yes' : 'no') . " | Slides: $slides\n";
}

echo "\n=== Active HeroSlides Count ===\n";
$hsCount = App\Models\HeroSlide::where('is_active', true)->count();
echo "$hsCount active hero slides\n";

foreach (App\Models\HeroSlide::where('is_active', true)->get() as $s) {
    echo "ID: {$s->id} | Tag: {$s->tag} | Title: {$s->title}\n";
}

echo "\nDone.\n";
