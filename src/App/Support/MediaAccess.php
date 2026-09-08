<?php

namespace Kolydart\Laravel\App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Decides whether the current user may read a media file.
 *
 * The rule is a lookup table, `kolydart.media.gates`, keyed by the media's
 * owning model. A file belongs to a record, so the question worth asking is
 * whether the caller may read that record: an expense receipt is part of the
 * expense, a customer's identity document is part of the customer.
 *
 * Three keys are consulted, most specific first:
 *
 *   'App\Customer@attachment'  a single collection of one model
 *   'App\Customer'             every collection of that model
 *   '*'                        everything not named above
 *
 * A mapped ability of `null` means "any user the route middleware admits".
 * A model with no mapping and no '*' fallback is **denied**: a collection
 * added later is private until someone says otherwise, which is the failure
 * direction that does not leak.
 *
 * The abilities are consulted **without the owning record**, so they express a
 * class-level permission ("may this user view customers?"), not a record-level
 * one ("may this user view *this* customer?"). An application whose own listings
 * are scoped — multi-tenant, team-owned, explicitly granted — must say so here
 * too, by naming its own {@see MediaAccessContract} in `kolydart.media.access`.
 */
class MediaAccess implements MediaAccessContract
{
    public function allows(Media $media): bool
    {
        $gates = config('kolydart.media.gates', []);

        $keys = [
            $media->model_type.'@'.$media->collection_name,
            $media->model_type,
            '*',
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $gates)) {
                $ability = $gates[$key];

                return $ability === null
                    ? Auth::check()
                    : Gate::allows($ability);
            }
        }

        return false;
    }
}
