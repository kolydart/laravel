<?php
namespace Kolydart\Laravel\App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasLanguageFallback
{
    /**
     * Get title with English fallback.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function titleFallback(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['title'] ?? $this->attributes['title_en'] ?? null
        );
    }

    /**
     * Get description with English fallback.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function descriptionFallback(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['description'] ?? $this->attributes['description_en'] ?? null
        );
    }
}