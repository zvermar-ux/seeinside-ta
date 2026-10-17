<?php

namespace App\Models\Concerns;

use App\Models\DeletedRecord;
use App\Services\Website\WebsiteNotifier;

/**
 * Every change of a catalog item is pushed to the connected websites (WordPress) right after the response,
 * and deletions are remembered so websites can catch up with /api/v1/changes.
 */
trait SyncsToWebsite
{
    abstract public static function websiteType(): string;

    public static function bootSyncsToWebsite(): void
    {
        static::saved(fn ($model) => WebsiteNotifier::changed($model));

        static::deleted(function ($model) {
            DeletedRecord::create([
                'agency_id' => $model->agency_id,
                'type' => static::websiteType(),
                'record_id' => $model->getKey(),
                'slug' => $model->slug,
                'deleted_at' => now(),
            ]);
            WebsiteNotifier::deleted($model);
        });
    }
}
