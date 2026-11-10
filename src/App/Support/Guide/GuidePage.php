<?php

namespace Kolydart\Laravel\App\Support\Guide;

/**
 * One page of the in-app guide: a `<slug>.md` file with YAML front matter.
 *
 * See src/docs/guide.md for the front matter contract.
 */
class GuidePage
{
    /**
     * @param  list<string>  $source  Provenance: the documents that feed this page,
     *                                so a maintainer knows which page to update when
     *                                a feature changes. Never rendered.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $section,
        public readonly int $order,
        public readonly ?string $excerpt,
        public readonly string $visibility,
        public readonly array $source,
        public readonly string $body,
    ) {
    }

    /**
     * Pages marked `visibility: admin` are shown only to users who pass
     * `kolydart.guide.admin_gate`.
     */
    public function isAdminOnly(): bool
    {
        return $this->visibility === 'admin';
    }
}
