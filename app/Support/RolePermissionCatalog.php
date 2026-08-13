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
            'crm' => ['label' => 'CRM i dostawcy', 'permissions' => [
                'crm.view' => 'Podgląd CRM, klientów i dostawców',
                'crm.companies.manage' => 'Dodawanie, edycja, archiwizacja i usuwanie firm',
                'crm.leads.manage' => 'Dodawanie i edycja leadów',
                'crm.tasks.manage' => 'Dodawanie i edycja zadań CRM',
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
                'projects.delete' => 'Usuwanie projektów',
                'projects.schedule.manage' => 'Zarządzanie harmonogramem i Ganttem',
                'projects.finances.manage' => 'Zarządzanie finansami projektu',
                'projects.requirements.manage' => 'Zarządzanie materiałami i usługami',
                'projects.documents.manage' => 'Zarządzanie dokumentami projektu',
            ]],
            'audits' => ['label' => 'Audyty', 'permissions' => [
                'audits.view' => 'Podgląd modułu audytów',
                'audits.manage' => 'Tworzenie i edycja audytów',
                'audits.types.manage' => 'Zarządzanie typami i wersjami audytów',
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
