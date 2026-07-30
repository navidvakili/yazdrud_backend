<?php

namespace Database\Seeders;

use App\Models\SliderProject;
use App\Models\HeroSlide;
use Illuminate\Database\Seeder;

/**
 * Convert existing hero_slides into a SliderStudio project.
 *
 * This allows the public site slider to use Slider Studio content.
 * Run this after creating the slider_projects table.
 */
class ConvertHeroSlidesToSliderProject extends Seeder
{
    public function run(): void
    {
        $heroSlides = HeroSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($heroSlides->isEmpty()) {
            $this->command->warn('⚠️ No active hero slides found. Skipping conversion.');
            return;
        }

        $projectSlides = $heroSlides->map(function ($slide, $idx) {
            $bgGradient = match ($idx % 4) {
                0 => 'linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 50%, #1F3A5F 100%)',
                1 => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3443 100%)',
                2 => 'linear-gradient(135deg, #1b1b2f 0%, #1a3a4a 50%, #2a4a5a 100%)',
                default => 'linear-gradient(135deg, #0d1b2a 0%, #152843 50%, #1a3a3a 100%)',
            };

            $layers = [];
            $zIndex = 10;

            // Badge + Tag pill layer
            $layers[] = [
                'id' => "layer-{$slide->id}-badge",
                'name' => 'برچسب دسته',
                'type' => 'text',
                'x' => 50, 'y' => 120, 'width' => 400, 'height' => 45,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => "{$slide->badge_icon} {$slide->tag}",
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 14, 'fontWeight' => 'bold', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#F1E0C5',
                'backgroundColor' => 'rgba(21,40,67,0.8)',
                'borderRadius' => 999, 'borderWidth' => 1,
                'borderColor' => 'rgba(42,157,143,0.6)',
                'padding' => '8px 20px', 'shadow' => '0 4px 15px rgba(0,0,0,0.3)',
                'animation' => [
                    'inPreset' => 'fadeIn', 'inDuration' => 0.6, 'inDelay' => 0.2,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'none', 'parallaxDepth' => 0,
                ],
                'interactions' => [],
            ];

            // Title layer
            $layers[] = [
                'id' => "layer-{$slide->id}-title",
                'name' => 'عنوان اصلی',
                'type' => 'text',
                'x' => 50, 'y' => 200, 'width' => 700, 'height' => 80,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => $slide->title,
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 48, 'fontWeight' => 'black', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#FFFFFF',
                'backgroundColor' => 'transparent',
                'borderRadius' => 0, 'borderWidth' => 0, 'borderColor' => 'transparent',
                'padding' => '0px', 'shadow' => '0 4px 16px rgba(0,0,0,0.95)',
                'animation' => [
                    'inPreset' => 'slideUp', 'inDuration' => 0.8, 'inDelay' => 0.4,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'none', 'parallaxDepth' => 20,
                ],
                'interactions' => [],
            ];

            // Subtitle layer
            $layers[] = [
                'id' => "layer-{$slide->id}-subtitle",
                'name' => 'توضیحات',
                'type' => 'text',
                'x' => 50, 'y' => 300, 'width' => 700, 'height' => 60,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => $slide->subtitle,
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 20, 'fontWeight' => 'bold', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#F5E6CC',
                'backgroundColor' => 'transparent',
                'borderRadius' => 0, 'borderWidth' => 0, 'borderColor' => 'transparent',
                'padding' => '0px', 'shadow' => '0 2px 10px rgba(0,0,0,0.95)',
                'animation' => [
                    'inPreset' => 'fadeIn', 'inDuration' => 0.8, 'inDelay' => 0.6,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'none', 'parallaxDepth' => 10,
                ],
                'interactions' => [],
            ];

            // Badge stat layer
            $layers[] = [
                'id' => "layer-{$slide->id}-stat",
                'name' => 'نشان آمار',
                'type' => 'text',
                'x' => 280, 'y' => 390, 'width' => 240, 'height' => 40,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => "📊 {$slide->badge}",
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 14, 'fontWeight' => 'bold', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#FFFFFF',
                'backgroundColor' => 'rgba(42,157,143,0.9)',
                'borderRadius' => 12, 'borderWidth' => 1,
                'borderColor' => 'rgba(42,157,143,0.4)',
                'padding' => '8px 16px', 'shadow' => '0 4px 15px rgba(0,0,0,0.3)',
                'animation' => [
                    'inPreset' => 'zoomIn', 'inDuration' => 0.6, 'inDelay' => 0.8,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'none', 'parallaxDepth' => 0,
                ],
                'interactions' => [],
            ];

            // Primary CTA button
            $layers[] = [
                'id' => "layer-{$slide->id}-cta1",
                'name' => 'دکمه اصلی',
                'type' => 'button',
                'x' => 200, 'y' => 470, 'width' => 200, 'height' => 55,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => $slide->primary_cta_text,
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 16, 'fontWeight' => 'black', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#FFFFFF',
                'backgroundColor' => 'linear-gradient(135deg, #2A9D8F 0%, #218276 50%, #1F3A5F 100%)',
                'borderRadius' => 16, 'borderWidth' => 1,
                'borderColor' => 'rgba(42,157,143,0.4)',
                'padding' => '0px', 'shadow' => '0 8px 25px rgba(0,0,0,0.4)',
                'animation' => [
                    'inPreset' => 'slideUp', 'inDuration' => 0.6, 'inDelay' => 1.0,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'glow', 'parallaxDepth' => 0,
                ],
                'interactions' => [
                    [
                        'id' => "int-{$slide->id}-cta1",
                        'trigger' => 'click', 'action' => 'link',
                        'targetUrl' => $slide->primary_cta_target,
                        'targetSlideId' => null, 'targetLayerId' => null, 'customJs' => null,
                    ],
                ],
            ];

            // Secondary CTA button
            $layers[] = [
                'id' => "layer-{$slide->id}-cta2",
                'name' => 'دکمه فرعی',
                'type' => 'button',
                'x' => 450, 'y' => 470, 'width' => 200, 'height' => 55,
                'rotation' => 0, 'opacity' => 1, 'zIndex' => $zIndex++,
                'locked' => false, 'visible' => true,
                'content' => $slide->secondary_cta_text,
                'fontFamily' => 'Vazirmatn, sans-serif',
                'fontSize' => 16, 'fontWeight' => 'black', 'fontStyle' => 'normal',
                'textAlign' => 'center', 'color' => '#E7D3B1',
                'backgroundColor' => 'rgba(0,0,0,0.45)',
                'borderRadius' => 16, 'borderWidth' => 1,
                'borderColor' => 'rgba(232,197,178,0.4)',
                'padding' => '0px', 'shadow' => '0 8px 25px rgba(0,0,0,0.4)',
                'animation' => [
                    'inPreset' => 'slideUp', 'inDuration' => 0.6, 'inDelay' => 1.2,
                    'inEasing' => 'easeOut', 'outPreset' => 'none', 'outDuration' => 0.5,
                    'outDelay' => 5, 'hoverEffect' => 'lift', 'parallaxDepth' => 0,
                ],
                'interactions' => [
                    [
                        'id' => "int-{$slide->id}-cta2",
                        'trigger' => 'click', 'action' => 'link',
                        'targetUrl' => $slide->secondary_cta_target,
                        'targetSlideId' => null, 'targetLayerId' => null, 'customJs' => null,
                    ],
                ],
            ];

            return [
                'id' => (string) $slide->id,
                'title' => $slide->title,
                'duration' => 6.0,
                'transition' => 'fade',
                'background' => [
                    'type' => 'gradient',
                    'color' => '#0d1b2a',
                    'gradient' => $bgGradient,
                    'imageUrl' => $slide->bg_image,
                ],
                'layers' => $layers,
            ];
        });

        $projectData = [
            'id' => 'imported-hero-slides',
            'title' => 'اسلایدهای صفحه اصلی',
            'description' => 'اسلایدهای منتقل شده از بخش مدیریت اسلایدر کلاسیک',
            'width' => 1240,
            'height' => 720,
            'autoPlay' => true,
            'loop' => true,
            'scrollSnap' => false,
            'addonParticles' => false,
            'addonWave' => false,
            'addonTextMorph' => false,
            'slides' => $projectSlides->toArray(),
        ];

        // Use updateOrCreate so this seeder is idempotent
        SliderProject::updateOrCreate(
            ['title' => 'اسلایدهای صفحه اصلی'],
            [
                'title' => 'اسلایدهای صفحه اصلی',
                'description' => 'اسلایدهای منتقل شده از بخش مدیریت اسلایدر کلاسیک',
                'project_data' => $projectData,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $this->command->info('✅ Hero slides converted to SliderProject successfully.');
        $this->command->info("   Slides converted: {$heroSlides->count()}");
    }
}
