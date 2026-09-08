<?php

namespace Kolydart\Laravel\App\Support;

use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The single place that turns a Media row into a URL.
 *
 * Call sites must never reach for `$media->getUrl()` again. That method asks
 * the media's disk for a public URL, and Laravel's local driver does not throw
 * when a disk has no `url` key — it silently returns `/storage/{path}`, which
 * corresponds to nothing. A collection moved to a private disk therefore does
 * not produce protected links, it produces dead ones, and nothing fails loudly
 * enough to notice.
 *
 * Everything not listed in `kolydart.media.public_collections` is addressed
 * through the protected route, whatever disk it currently sits on. That is
 * deliberate: during a disk migration the controller still resolves the file
 * from the media's own `disk` column, so links keep working while the files
 * move, in either order.
 */
class MediaUrl
{
    /**
     * @param  string|null  $conversion  a registered conversion name, or null
     *                                   for the original file
     */
    public static function url(Media $media, ?string $conversion = null): string
    {
        if (static::isPublic($media, $conversion)) {
            return $conversion === null
                ? $media->getUrl()
                : $media->getUrl($conversion);
        }

        $name = config('kolydart.media.routes.name', 'admin.').'media';

        return $conversion === null
            ? route($name, ['media' => $media->uuid])
            : route($name.'.conversion', [
                'media'      => $media->uuid,
                'conversion' => $conversion,
            ]);
    }

    /**
     * Is this media still served straight off a web-served disk?
     *
     * Entries take either form, matching how `gates` is keyed: a bare collection
     * name covers that collection on every model, `Model@collection` covers one
     * model's. The qualified form is what a mixed decision needs — an editor's
     * embedded images may be public while a photo collection on one model is
     * public and the same collection name on another is not.
     *
     * Being listed is necessary but not sufficient: the disk the files actually
     * sit on has the last word. A collection left in `public_collections` after
     * its files moved to a private disk would otherwise get `getUrl()` and the
     * dead `/storage/{path}` this class exists to prevent — the config being
     * stale is exactly the case it cannot afford to trust. Such media falls
     * through to the protected route, which resolves the file from the media's
     * own `disk` column and therefore still works.
     *
     * @param  string|null  $conversion  null for the original file; conversions
     *                                   may live on a disk of their own
     */
    public static function isPublic(Media $media, ?string $conversion = null): bool
    {
        $public = config('kolydart.media.public_collections', []);

        $listed = in_array($media->collection_name, $public, true)
            || in_array($media->model_type.'@'.$media->collection_name, $public, true);

        if (! $listed) {
            return false;
        }

        $disk = $conversion === null
            ? $media->disk
            : ($media->conversions_disk ?: $media->disk);

        if (static::diskIsWebServed($disk)) {
            return true;
        }

        Log::warning('kolydart.media: collection is listed in public_collections but its disk is not web-served; falling back to the protected route.', [
            'media'      => $media->getKey(),
            'model_type' => $media->model_type,
            'collection' => $media->collection_name,
            'disk'       => $disk,
        ]);

        return false;
    }

    /**
     * Only the local driver can answer a URL that leads nowhere.
     *
     * It falls back to `/storage/{path}` when the disk declares no `url` key,
     * rather than throwing. Every other driver builds its URL from its own
     * endpoint, so a missing `url` there is a default, not a dead end.
     *
     * A disk this application does not declare cannot be verified, so it is not
     * trusted — the answer stays on the side that produces a working link.
     */
    protected static function diskIsWebServed(?string $disk): bool
    {
        if ($disk === null || $disk === '') {
            return false;
        }

        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            return false;
        }

        return ($config['driver'] ?? null) !== 'local'
            || isset($config['url']);
    }
}
