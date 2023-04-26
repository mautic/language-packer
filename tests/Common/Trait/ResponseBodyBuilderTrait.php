<?php

declare(strict_types=1);

namespace App\Tests\Common\Trait;

trait ResponseBodyBuilderTrait
{
    private static function buildResourcesBody(string $slug, string $resource): string
    {
        return '{"data":[{"attributes":{"slug":"'.$slug.'","name":"'.$resource.'"}}]}';
    }

    private static function buildResourceLanguageStatsBody(string $resource, string $language): string
    {
        return '{"data":[{"id":"o:mautic:p:mautic:r:'.$resource.':l:'.$language.'","attributes":{"last_update":"2015-05-21T08:06:10Z"}}]}';
    }

    private static function buildLanguagesBody(string $language): string
    {
        return '{"data":{"attributes":{"code":"'.$language.'","name":"Afrikaans"}}}';
    }

    private static function buildTranslationsBody(string $uuid, string $status = 'succeeded'): string
    {
        return '{"data":{"id":"'.$uuid.'","attributes":{"status":"'.$status.'"},"links":{"self":"https://rest.api.transifex.com/resource_translations_async_downloads/'.$uuid.'"}}}';
    }

    private static function buildIniBody(string $ini = 'mautic.addon.notice.reloaded="%added% addons were added, %updated% updated, and %disabled% disabled."'): string
    {
        return $ini;
    }
}
