<?php

namespace App\Support;

class RolePermissionCatalog
{
    public const SYSTEM_ROLES = [
        'superadmin', 'admin', 'auditor_senior', 'auditor', 'client_admin', 'client_user',
    ];

    public const PROTECTED_ROLES = ['superadmin', 'client_admin', 'client_user'];

    public static function groups(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => [
                'dashboard.view' => 'Wejście do zakładki Dashboard',
            ]],
            'calendar' => ['label' => 'Kalendarz', 'permissions' => [
                'calendar.view' => 'Podgląd własnego kalendarza zadań',
                'calendar.team.view' => 'Podgląd kalendarzy wszystkich użytkowników',
            ]],
            'hr' => ['label' => 'HR', 'permissions' => [
                'hr.delegations.view' => 'Dostęp do własnych delegacji i samochodów',
                'hr.leaves.view' => 'Dostęp do własnych urlopów i zwolnień L4',
                'hr.attendance.view' => 'Dostęp do własnej listy obecności',
                'hr.team.view' => 'Podgląd i zarządzanie danymi HR innych użytkowników',
                'hr.vehicles.all.view' => 'Dostęp do wszystkich samochodów pracowników',
            ]],
            'crm' => ['label' => 'CRM i dostawcy', 'permissions' => [
                'crm.view' => 'Podgląd CRM, klientów i dostawców',
                'crm.companies.manage' => 'Dodawanie, edycja, archiwizacja i usuwanie firm',
                'crm.leads.manage' => 'Dodawanie i edycja leadów',
                'crm.tasks.own.manage' => 'Zarządzanie własnymi zadaniami CRM',
                'crm.tasks.team.manage' => 'Zarządzanie zadaniami innych użytkowników CRM',
            ]],
            'offers' => ['label' => 'Oferty', 'permissions' => [
                'offers.view' => 'Podgląd ofert i zapytań ofertowych',
                'offers.create' => 'Tworzenie i kopiowanie ofert',
                'offers.edit' => 'Edycja ofert i ich statusów',
                'offers.delete' => 'Usuwanie ofert',
                'offers.prices.view' => 'Podgląd cen w ofertach',
                'offers.templates.manage' => 'Zarządzanie formularzami i szablonami',
                'offers.catalog.manage' => 'Zarządzanie cennikiem usług',
            ]],
            'projects' => ['label' => 'Projekty', 'permissions' => [
                'projects.view' => 'Podgląd zakładki i projektów',
                'projects.create' => 'Tworzenie projektów',
                'projects.edit' => 'Edycja danych projektu',
                'projects.schedule.view' => 'Podgląd harmonogramu i zadań projektu',
                'projects.schedule.manage' => 'Zarządzanie harmonogramem i Ganttem',
                'projects.finances.view' => 'Podgląd finansów projektu',
                'projects.finances.manage' => 'Zarządzanie finansami projektu',
                'projects.requirements.view' => 'Podgląd materiałów i usług projektu',
                'projects.requirements.material_prices.view' => 'Podgląd cen materiałów w projekcie',
                'projects.requirements.service_prices.view' => 'Podgląd cen usług w projekcie',
                'projects.requirements.manage' => 'Zarządzanie materiałami i usługami',
                'projects.documents.view' => 'Podgląd dokumentów projektu',
                'projects.documents.manage' => 'Zarządzanie dokumentami projektu',
            ]],
            'audits' => ['label' => 'Audyty', 'permissions' => [
                'audits.view' => 'Podgląd modułu audytów',
                'audits.manage' => 'Tworzenie i edycja audytów',
                'audits.types.manage' => 'Zarządzanie typami i wersjami audytów',
                'audits.passports.view' => 'Podgląd paszportów energetycznych',
                'audits.passports.manage' => 'Dodawanie, edycja i usuwanie paszportów energetycznych',
            ]],
            'documents' => ['label' => 'Dokumenty', 'permissions' => [
                'documents.view' => 'Podgląd wszystkich dokumentów',
                'documents.upload' => 'Dodawanie dokumentów',
                'documents.delete' => 'Usuwanie dokumentów',
            ]],
            'client_zone' => ['label' => 'Strefa klienta', 'permissions' => [
                'client_zone.view' => 'Podgląd i przełączanie na strefę klienta',
                'client_zone.chat.manage' => 'Obsługa wiadomości klientów',
            ]],
            'settings' => ['label' => 'Ustawienia', 'permissions' => [
                'settings.users.view' => 'Podgląd użytkowników',
                'settings.users.manage' => 'Dodawanie, edycja i usuwanie użytkowników',
                'settings.roles.manage' => 'Pełne zarządzanie rolami i uprawnieniami',
                'settings.company.manage' => 'Zarządzanie danymi firmy i modułami',
                'settings.archive.view' => 'Podgląd archiwum',
            ]],
            'activity_log' => ['label' => 'Lista zmian', 'permissions' => [
                'activity_log.view' => 'Podgląd historii zmian i logowań użytkowników',
            ]],
            'advanced' => ['label' => 'Uprawnienia nadrzędne', 'permissions' => [
                'system.full_access' => 'Pełny dostęp operacyjny (pomija szczegółowe ograniczenia)',
            ]],
        ];
    }

    public static function names(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => array_keys($group['permissions']))
            ->values()
            ->all();
    }

    public static function roleLabel(string $role): string
    {
        return [
            'superadmin' => 'Super Admin',
            'admin' => 'Administrator',
            'auditor_senior' => 'Starszy audytor',
            'auditor' => 'Audytor',
            'client_admin' => 'Administrator klienta',
            'client_user' => 'Użytkownik klienta',
        ][$role] ?? $role;
    }
}
