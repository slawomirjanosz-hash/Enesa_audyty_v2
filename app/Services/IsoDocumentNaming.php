<?php

namespace App\Services;

use App\Models\IsoPlantProfile;
use Illuminate\Support\Str;

/** Display the site before the document type without changing frozen PDF content. */
final class IsoDocumentNaming
{
    public static function title(IsoPlantProfile|string|null $profile, string $type): string
    {
        $site = $profile instanceof IsoPlantProfile ? ($profile->answers['site.name']['value'] ?? '') : ($profile ?? '');
        $site = trim(preg_replace('/[\s\p{C}]+/u', ' ', $site));
        if ($site === '') {
            $site = $profile instanceof IsoPlantProfile ? 'Profil zakładu #'.$profile->id : 'Zakład';
        }
        $type = Str::limit(trim(preg_replace('/[\s\p{C}]+/u', ' ', $type)), 180, '…');

        $siteLimit = 240 - mb_strlen($type) - 3;
        $site = mb_strlen($site) > $siteLimit ? mb_substr($site, 0, $siteLimit - 1).'…' : $site;

        return $site.' — '.$type;
    }

    public static function filename(string $title): string
    {
        $safe = preg_replace('/[\\\\\/<>:"|?*%\x00-\x1F\x7F]/u', '_', $title);

        return rtrim(Str::limit($safe, 240, ''), '. ').'.pdf';
    }
}
