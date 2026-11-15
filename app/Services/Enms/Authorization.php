<?php

namespace App\Services\Enms;

use Carbon\CarbonImmutable;

/** The status of the supporting evidence and the current right are different facts. */
final class Authorization
{
    public function status(?array $evidence, ?array $verification): string
    {
        if (! $evidence) {
            return 'zgloszone';
        }
        if (isset($evidence['version'], $verification['evidence_version'])
            && $verification['evidence_version'] === $evidence['version']
            && ($verification['dostepnosc'] ?? null) === 'okazany'
            && ($verification['wiarygodnosc'] ?? null) === 'potwierdzona') {
            return 'zweryfikowane';
        }

        return 'udokumentowane';
    }

    /** Context, evidence and verification must be resolved from the scoped server records. */
    public function check(array $grant, string $status, array $context): array
    {
        $minimum = config('enms.minimum_authority.'.$context['class']);
        $ranks = ['zgloszone' => 0, 'udokumentowane' => 1, 'zweryfikowane' => 2];
        $fail = fn ($reason) => ['allowed' => false, 'status' => $status, 'minimum' => $minimum, 'reason' => $reason];
        if (! $minimum || ($grant['class'] ?? null) !== $context['class']) {
            return $fail('Uprawnienie nie obejmuje tej klasy działania.');
        }
        if (($grant['activity'] ?? null) !== $context['activity']) {
            return $fail('Wykonanie i zatwierdzenie rezultatu wymagają osobnych uprawnień.');
        }
        if (($ranks[$status] ?? -1) < $ranks[$minimum]) {
            return $fail('Wymagany status uprawnienia: '.$minimum.'.');
        }
        $date = CarbonImmutable::parse($context['date'])->startOfDay();
        $revocation = $grant['revocation'] ?? null;
        if ($revocation && $date->gte(CarbonImmutable::parse($revocation['date'])->startOfDay())) {
            return $fail('Uprawnienie cofnięto od '.$revocation['date'].'.');
        }
        if (empty($grant['valid_from']) || $date->lt(CarbonImmutable::parse($grant['valid_from'])->startOfDay())
            || (! empty($grant['valid_until']) && $date->gt(CarbonImmutable::parse($grant['valid_until'])->startOfDay()))) {
            return $fail('Czynność przypada poza okresem obowiązywania uprawnienia.');
        }
        $subject = (int) ($grant['user_id'] ?? 0);
        $substitute = $grant['substitute'] ?? null;
        if ($substitute && ! empty($substitute['evidence_id']) && $date->betweenIncluded(CarbonImmutable::parse($substitute['from'])->startOfDay(), CarbonImmutable::parse($substitute['until'])->startOfDay())) {
            $subjectMatches = in_array((int) $context['user_id'], [$subject, (int) $substitute['user_id']], true);
        } else {
            $subjectMatches = $subject === (int) $context['user_id'];
        }
        if (! $subjectMatches) {
            return $fail('Uprawnienie należy do innego użytkownika.');
        }
        if (($grant['scope_version'] ?? null) !== $context['scope_version']) {
            return $fail('Uprawnienie nie obejmuje aktualnej wersji zakresu EnMS.');
        }
        $area = $context['area'] ?? null;
        if (in_array($area, $grant['exclusions'] ?? [], true)
            || (! empty($grant['areas']) && ! in_array($area, $grant['areas'], true))) {
            return $fail('Obiekt lub proces nie jest objęty uprawnieniem.');
        }
        if (in_array($context['class'], ['NAKL', 'NAKL+'], true)) {
            if (! isset($context['amount_cents'], $grant['limit_cents']) || blank($context['currency'] ?? null) || blank($grant['currency'] ?? null) || ($context['currency'] ?? null) !== ($grant['currency'] ?? null)) {
                return $fail('Brakuje kwoty, waluty lub udokumentowanego limitu.');
            }
            $used = ($grant['limit_period'] ?? '') === 'year' ? ($context['spent_cents'] ?? null) : 0;
            if ($used === null || $context['amount_cents'] < 0 || $used + $context['amount_cents'] > $grant['limit_cents']) {
                return $fail('Kwota przekracza dostępny limit uprawnienia albo nie ustalono wykorzystania limitu rocznego.');
            }
        }
        if (count(array_unique($context['approved_steps'] ?? [])) < (int) ($grant['required_steps'] ?? 1)) {
            return $fail('Nie zebrano wszystkich wymaganych stopni zgody.');
        }

        return ['allowed' => true, 'status' => $status, 'minimum' => $minimum, 'reason' => null];
    }

    /** External employment does not remove a conflict (S-0.2 §2). */
    public function effectivenessConflicts(int $assessorId, array $task): array
    {
        $conflicts = [];
        foreach (['owner_id' => 'właściciel zadania', 'recommendation_author_id' => 'autor rekomendacji', 'executor_id' => 'wykonawca działania'] as $key => $label) {
            if ((int) ($task[$key] ?? 0) === $assessorId) {
                $conflicts[] = $label;
            }
        }
        if (in_array($assessorId, array_map('intval', $task['area_responsible_ids'] ?? []), true)) {
            $conflicts[] = 'osoba odpowiedzialna za obszar';
        }

        return $conflicts;
    }
}
