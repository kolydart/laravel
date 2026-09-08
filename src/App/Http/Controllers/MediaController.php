<?php

namespace Kolydart\Laravel\App\Http\Controllers;

use Illuminate\Routing\Controller;
use Kolydart\Laravel\App\Support\MediaAccessContract;
use Spatie\MediaLibrary\MediaCollections\Exceptions\InvalidConversion;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves media that does not sit on a web-served disk.
 *
 * The disk decides whether a file needs authorization at all: a file under the
 * document root answers a URL without PHP ever running, so no controller, model
 * scope or gate is consulted. This controller is the other half of moving a
 * collection off that disk — without it, the files are merely unreachable.
 *
 * **Local disks only.** Both actions read the file straight off the filesystem,
 * because `Media::getPath()` returns an absolute path only for the `local`
 * driver; for a cloud disk it returns a path relative to the bucket, which no
 * `is_file()` will ever find. Point a private collection at a local disk outside
 * the document root. A collection on S3 is protected by the bucket's own ACL and
 * a temporary URL, not by this route — and would 404 here on every request.
 */
class MediaController extends Controller
{
    /**
     * Mime types rendered in the browser rather than pushed as a download.
     *
     * Everything else is sent as an attachment. The list is deliberately short:
     * `text/html` and `image/svg+xml` are executable in the browser, and a file
     * uploaded by one user and rendered inline from the application's own origin
     * is stored XSS against every other user who opens it.
     */
    protected const INLINE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'application/pdf',
    ];

    /**
     * Conversion output formats, keyed by the extension the converter wrote.
     *
     * A conversion has no `mime_type` column of its own — the one on the row
     * describes the original, and a conversion may well be a different format.
     * This is a safe list rather than a guess: an extension that is not here is
     * served as an opaque download, never inline.
     */
    protected const CONVERSION_MIME_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function __construct(protected MediaAccessContract $access)
    {
    }

    /**
     * The original file.
     */
    public function media(Media $media): BinaryFileResponse
    {
        $this->authorizeMedia($media);

        // A row can outlive its file: a half-finished disk move, a manual
        // cleanup. That is a missing document, not a server fault, so it answers
        // 404 rather than 500. The check sits *after* the gate, so the response
        // never tells an unauthorised caller whether the file exists.
        $path = $media->getPath();
        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return $this->serve($path, $media->mime_type, $media->file_name);
    }

    /**
     * A generated conversion (thumb, preview).
     *
     * It asks exactly the same question the original does, so a thumbnail can
     * never be looser than the file it previews. Getting this wrong is the
     * common half-measure: the original moves to a private disk while its
     * conversions stay behind, and a 120px preview of an identity document is
     * still the document.
     */
    public function conversion(Media $media, string $conversion): BinaryFileResponse
    {
        $this->authorizeMedia($media);

        // The router constrains the name to `kolydart.media.conversions`, which
        // is a global list; whether *this* model registers that conversion is a
        // different question, and the media library answers it by throwing. A
        // conversion the model never declared is a missing file, not a fault, so
        // it joins the 404 below rather than surfacing as a 500.
        try {
            // The disk is the authority on what exists, not the
            // `generated_conversions` column: older versions of the package
            // never wrote it, so rows whose files are present on disk can still
            // report none.
            $path = $media->getPath($conversion);
        } catch (InvalidConversion) {
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->serve(
            $path,
            static::CONVERSION_MIME_TYPES[$extension] ?? null,
            "{$conversion}-{$media->file_name}"
        );
    }

    /**
     * Send a file with a content type the caller cannot talk the browser out of.
     *
     * The disposition and the `Content-Type` header must come from the same
     * value or the reasoning behind {@see INLINE_MIME_TYPES} does not survive
     * the trip: left to itself, `BinaryFileResponse` sets the header from a
     * fresh `finfo` guess over the file's *contents*, which need not agree with
     * the type this method just decided was safe to render. Setting it here
     * closes that gap, and `nosniff` stops the browser reopening it.
     */
    protected function serve(string $path, ?string $mimeType, string $downloadName): BinaryFileResponse
    {
        $mimeType = $mimeType ?: 'application/octet-stream';

        $response = response()->file($path, [
            'Content-Type'           => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setContentDisposition(
            in_array($mimeType, static::INLINE_MIME_TYPES, true) ? 'inline' : 'attachment',
            $downloadName
        );

        return $response;
    }

    protected function authorizeMedia(Media $media): void
    {
        abort_unless($this->access->allows($media), Response::HTTP_FORBIDDEN);
    }
}
