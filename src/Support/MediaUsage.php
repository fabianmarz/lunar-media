<?php

namespace LunarMedia\Support;

use Illuminate\Support\Collection;
use LunarCms\Models\Page;

/**
 * Finds where a media file is referenced from CMS block JSON, which no foreign
 * key protects. A PHP scan is fine at CMS scale; keep every caller on this one
 * method so a query-based lookup can replace it later.
 */
class MediaUsage
{
    /**
     * @return Collection<int, array{id: int, title: string, url: string}>
     */
    public function pagesUsing(int $mediaId): Collection
    {
        return Page::query()
            ->get(['id', 'title', 'slug', 'blocks'])
            ->filter(fn (Page $page): bool => $this->references($page, $mediaId))
            ->map(fn (Page $page): array => [
                'id' => $page->id,
                'title' => (string) $page->translate('title'),
                'url' => route('panel.cms.pages.edit', $page),
            ])
            ->values();
    }

    private function references(Page $page, int $mediaId): bool
    {
        foreach (($page->blocks ?? []) as $blocks) {
            foreach ((array) $blocks as $block) {
                if (($block['type'] ?? null) === 'image' && (int) ($block['data']['media_id'] ?? 0) === $mediaId) {
                    return true;
                }
            }
        }

        return false;
    }
}
