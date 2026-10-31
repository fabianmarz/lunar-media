<?php

namespace LunarMedia;

use Closure;
use Illuminate\Support\Facades\Route;
use Lunar\Panel\Navigation\NavigationItem;
use Lunar\Panel\Navigation\NavigationRegistry;
use Lunar\Panel\Sections\Section;
use LunarMedia\Http\Controllers\MediaController;

class MediaSection extends Section
{
    /**
     * Manifest permission gating routes (can: middleware) and the navigation
     * item together. Seeded by the permission migration on the staff guard.
     */
    public const PERMISSION = 'media:manage';

    public function key(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return __('media::media.nav_group');
    }

    public function navigation(NavigationRegistry $registry): void
    {
        $registry->group('content', __('media::media.nav_group'));
        $registry->addItem('content', new NavigationItem(
            key: 'media',
            label: 'media::media.nav_media',
            icon: 'image',
            route: 'panel.media.index',
            permission: self::PERMISSION,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function langNamespaces(): array
    {
        return ['media'];
    }

    public function routes(): ?Closure
    {
        return function (): void {
            Route::middleware('can:'.self::PERMISSION)
                ->prefix('media')
                ->name('panel.media.')
                ->group(function (): void {
                    Route::model('media', config('media-library.media_model'));

                    Route::get('/', [MediaController::class, 'index'])->name('index');
                    Route::post('/', [MediaController::class, 'store'])->name('store');
                    Route::get('{media}', [MediaController::class, 'show'])->name('show');
                    Route::put('{media}', [MediaController::class, 'update'])->name('update');
                });
        };
    }
}
