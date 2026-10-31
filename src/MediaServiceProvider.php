<?php

namespace LunarMedia;

use Illuminate\Support\ServiceProvider;
use Lunar\Panel\Facades\Panel;
use Lunar\Panel\PanelManager;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the package: translations, migrations, panel section, and the
     * Vite module wiring that makes the panel emit this add-on's bundle.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(dirname(__DIR__).'/resources/lang', 'media');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        // Registration must land during boot — the panel processes sections
        // once at app booted and silently ignores anything later.
        Panel::section(new MediaSection);

        $this->app->make(PanelManager::class)->vite('media', [
            'input' => 'resources/js/addon.ts',
            'hotFile' => null,
            'buildDirectory' => 'vendor/lunar-panel/media',
            '__buildSourcePath' => dirname(__DIR__).'/build',
        ]);
    }
}
