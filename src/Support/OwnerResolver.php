<?php

namespace LunarMedia\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use LunarCms\Models\Page;

/**
 * Describes the entity a media row belongs to: a type label, a display label
 * and a panel link. `media.model_type` stores the morph alias for Lunar models
 * and the class name for models without one (e.g. the CMS page), so every
 * lookup goes through the resolved class.
 */
class OwnerResolver
{
    /**
     * Owner key => model class, in filter-dropdown order.
     *
     * @var array<string, class-string<Model>>
     */
    private const array OWNERS = [
        'product' => Product::class,
        'product_variant' => ProductVariant::class,
        'brand' => Brand::class,
        'collection' => Collection::class,
        'product_type' => ProductType::class,
        'product_option' => ProductOption::class,
        'product_option_value' => ProductOptionValue::class,
        'cms_page' => Page::class,
        'asset' => Asset::class,
    ];

    /**
     * @return array<string, string> owner key => label
     */
    public function typeOptions(): array
    {
        return array_map(fn (string $class): string => $this->typeLabel($class), self::OWNERS);
    }

    /**
     * Owners a library file can be copied onto, keyed like `typeOptions()`.
     *
     * @return array<string, class-string<Model>>
     */
    public function attachable(): array
    {
        return [
            'product' => Product::class,
            'brand' => Brand::class,
            'collection' => Collection::class,
        ];
    }

    /**
     * The panel permission that guards editing the given attachable owner.
     */
    public function attachPermission(string $key): ?string
    {
        return match ($key) {
            'product', 'brand' => 'catalog:manage-products',
            'collection' => 'catalog:manage-collections',
            default => null,
        };
    }

    /**
     * Every `model_type` form (alias and class name) stored for an owner key.
     *
     * @return list<string>
     */
    public function modelTypes(string $key): array
    {
        $class = self::OWNERS[$key] ?? null;

        if ($class === null) {
            return [];
        }

        $alias = array_search($class, Relation::morphMap(), true);

        return array_values(array_filter([$class, $alias === false ? null : $alias]));
    }

    /**
     * Relations to eager-load per owner class so listing stays one query per type.
     *
     * @return array<class-string<Model>, list<string>>
     */
    public function eagerLoads(): array
    {
        return [
            ProductVariant::class => ['product'],
            ProductOptionValue::class => ['option'],
        ];
    }

    /**
     * @return array{type_label: string, label: string, url: ?string}
     */
    public function describe(?Model $owner, string $modelType): array
    {
        if ($owner === null) {
            $class = Relation::getMorphedModel($modelType) ?? $modelType;

            return [
                'type_label' => $this->typeLabel($class),
                'label' => __('media::media.owner_deleted'),
                'url' => null,
            ];
        }

        return match (true) {
            $owner instanceof Asset => $this->row($owner, __('media::media.owner_library'), null),
            $owner instanceof ProductVariant => $this->row(
                $owner,
                trim(($owner->product?->translateAttribute('name') ?? '').' / '.$owner->sku, ' /'),
                $owner->product ? route('panel.products.variants.edit', [$owner->product, $owner]) : null,
            ),
            $owner instanceof Product => $this->row($owner, (string) $owner->translateAttribute('name'), route('panel.products.edit', $owner)),
            $owner instanceof Brand => $this->row($owner, (string) $owner->name, route('panel.brands.edit', $owner)),
            $owner instanceof Collection => $this->row($owner, (string) $owner->translateAttribute('name'), route('panel.collections.edit', $owner)),
            $owner instanceof ProductType => $this->row($owner, (string) $owner->name, route('panel.product-types.edit', $owner)),
            $owner instanceof ProductOption => $this->row($owner, (string) $owner->translate('name'), route('panel.settings.product-options.edit', $owner)),
            $owner instanceof ProductOptionValue => $this->row(
                $owner,
                (string) $owner->translate('name'),
                $owner->option ? route('panel.settings.product-options.edit', $owner->option) : null,
            ),
            $owner instanceof Page => $this->row($owner, (string) $owner->translate('title'), route('panel.cms.pages.edit', $owner)),
            default => $this->row($owner, '#'.$owner->getKey(), null),
        };
    }

    /**
     * @return array{type_label: string, label: string, url: ?string}
     */
    private function row(Model $owner, string $label, ?string $url): array
    {
        return [
            'type_label' => $this->typeLabel($owner::class),
            'label' => $label !== '' ? $label : '#'.$owner->getKey(),
            'url' => $url,
        ];
    }

    private function typeLabel(string $class): string
    {
        $key = array_search($class, self::OWNERS, true);

        return $key === false
            ? class_basename($class)
            : __('media::media.owner_types.'.$key);
    }
}
