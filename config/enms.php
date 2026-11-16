<?php

return [
    // 16.11.2026: fresh 0.4 data in QA audit 9 only; no migration of 0.3 answers or approvals.
    'audit_ids' => array_values(array_filter(array_map('trim', explode(',', env('ENMS_04_AUDIT_IDS', '9'))), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'library' => '2026-11-15',
    'action_classes' => ['USTR' => 'Ustrojowa', 'NAKL+' => 'Nakładowa ponad limit', 'NAKL' => 'Nakładowa w limicie', 'ORG' => 'Organizacyjna', 'OPER' => 'Operacyjna', 'SPRAW' => 'Sprawozdawcza'],
    'action_types' => ['governance' => 'Polityka, zakres, karta lub powołanie zespołu', 'investment' => 'Decyzja finansowa / inwestycja', 'organization' => 'Procedura, czas lub zakres obowiązków', 'operation' => 'Czynność techniczna / pomiar / przegląd', 'reporting' => 'Raport, zestawienie lub odczyt'],
    'action_type_classes' => ['governance' => 'USTR', 'investment' => 'NAKL', 'organization' => 'ORG', 'operation' => 'OPER', 'reporting' => 'SPRAW'],
    // S-0.2 §1: these are ENESA rules, not inferred ISO requirements.
    'minimum_authority' => ['USTR' => 'zweryfikowane', 'NAKL+' => 'zweryfikowane', 'NAKL' => 'zweryfikowane', 'ORG' => 'zweryfikowane', 'OPER' => 'udokumentowane', 'SPRAW' => 'udokumentowane'],
    'effectiveness' => [
        'proste' => ['niskie' => 'samoocena', 'srednie' => 'samoocena', 'wysokie' => 'niezalezna'],
        'istotne' => ['niskie' => 'niezalezna', 'srednie' => 'niezalezna', 'wysokie' => 'niezalezna'],
    ],
];
