<?php

return [
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
