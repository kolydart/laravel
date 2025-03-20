<?php
namespace Kolydart\Laravel\App\Traits;

trait HasLanguageFallback
{
    /**
     * Get title with English fallback.
     *
     * @return string|null
     */
    public function getTitleFallbackAttribute()
    {
        return $this->title ?? $this->title_en ?? null;
    }

    /**
     * Get description with English fallback.
     *
     * @return string|null
     */
    public function getDescriptionFallbackAttribute()
    {
        return $this->description ?? $this->description_en ?? null;
    }
}