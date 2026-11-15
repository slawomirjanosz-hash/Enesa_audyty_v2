<?php

namespace App\Services\Enms;

use App\Models\IsoSystemReview;
use Ramsey\Uuid\Uuid;

/** Clause adapter: the shared task record stores only technical requirement IDs. */
final class TaskReferences
{
    public function external(): array
    {
        return [$this->id('6-2') => '6.2 · '.IsoSystemReview::TITLES['6-2']];
    }

    public function id(string $section): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://enesa.pl/enms/requirement/'.$section)->toString();
    }

    public function forSection(Workspace $w, string $section): array
    {
        $ids = $section === '5-1' ? array_keys(app(LeadershipLibrary::class)->positions()) : [$this->id($section)];

        return array_filter($w->records('task'), fn ($r) => count(array_intersect($ids, $r['payload']['requirements'] ?? [])) > 0);
    }
}
