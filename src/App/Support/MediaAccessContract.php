<?php

namespace Kolydart\Laravel\App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The question the media routes ask before serving a byte.
 *
 * `kolydart.media.access` names the class that answers it. The contract is this
 * interface and nothing more, so a replacement resolver is free to express rules
 * a gate name cannot carry — an explicit per-record grant, an embargo date,
 * access inherited from a parent record — without inheriting anything from the
 * default implementation.
 */
interface MediaAccessContract
{
    /**
     * May the current user read this file?
     */
    public function allows(Media $media): bool;
}
