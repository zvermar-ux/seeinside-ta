<?php

namespace App\Support;

/**
 * Central list of modules and the permissions that can be granted per user.
 * Each module has "view" and (optionally) "manage" permission: e.g. objects.view, objects.manage.
 */
class Permissions
{
    public const SUPER_ADMIN = 'super_admin';
    public const AGENCY_ADMIN = 'agency_admin';
    public const OPERATOR = 'operator';

    /** module => has manage permission */
    public const MODULES = [
        'tours' => true,
        'events' => true,
        'accommodations' => true,
        'visas' => true,
        'bookings' => true,
        'visa_applications' => true,
        'catalog_settings' => true,
        'integrations' => true,
        'objects' => true,
        'groups' => true,
        'charters' => true,
        'tour_operators' => true,
        'subagents' => true,
        'slider' => true,
        'reviews' => true,
        'ai' => false,
        'live_chat' => true,
        'media' => true,
        'reports' => true,
        'finance' => true,
        'clients' => true,
        'users' => true,
        'agency' => true,
        'imports' => false,
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::MODULES as $module => $hasManage) {
            $out[] = "{$module}.view";
            if ($hasManage) {
                $out[] = "{$module}.manage";
            }
        }

        return $out;
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            'tours' => __('Organized tours'),
            'events' => __('Events'),
            'accommodations' => __('Hotels & apartments'),
            'visas' => __('Visa services'),
            'bookings' => __('Bookings'),
            'visa_applications' => __('Visa applications'),
            'catalog_settings' => __('Departure places, airlines, amenities'),
            'integrations' => __('Integrations & website API'),
            'objects' => __('Imported offer (Aries)'),
            'groups' => __('Groups & destinations'),
            'charters' => __('Charters'),
            'tour_operators' => __('Tour operators'),
            'subagents' => __('Subagents'),
            'slider' => __('Homepage slider'),
            'reviews' => __('Reviews'),
            'ai' => __('AI assistant (texts and images)'),
            'live_chat' => __('Live chat & chatbot'),
            'media' => __('Media library'),
            'reports' => __('Reports, costs & profit'),
            'finance' => __('Invoices, cash register & supplier invoices'),
            'clients' => __('Clients & companies'),
            'users' => __('Users'),
            'agency' => __('Agency settings'),
            'imports' => __('Imports'),
            default => $module,
        };
    }

    public static function label(string $permission): string
    {
        [$module, $action] = explode('.', $permission);

        return self::moduleLabel($module).' — '.($action === 'view' ? __('view') : __('manage'));
    }

    /** Options for a CheckboxList, grouped label => permission */
    public static function options(?array $only = null): array
    {
        $out = [];
        foreach (self::all() as $p) {
            if ($only === null || in_array($p, $only, true)) {
                $out[$p] = self::label($p);
            }
        }

        return $out;
    }

    public static function roleDefaults(string $role): array
    {
        return match ($role) {
            self::AGENCY_ADMIN => self::all(),
            self::OPERATOR => [
                'tours.view', 'tours.manage', 'events.view', 'events.manage', 'accommodations.view', 'accommodations.manage',
                'visas.view', 'visas.manage', 'bookings.view', 'bookings.manage', 'visa_applications.view', 'visa_applications.manage',
                'catalog_settings.view', 'objects.view', 'objects.manage', 'groups.view', 'groups.manage', 'charters.view', 'charters.manage',
                'tour_operators.view', 'subagents.view',
                'reviews.view', 'reviews.manage', 'ai.view', 'live_chat.view', 'live_chat.manage', 'media.view', 'media.manage', 'finance.view', 'finance.manage', 'clients.view', 'clients.manage',
            ],
            default => [],
        };
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            self::SUPER_ADMIN => __('Super admin'),
            self::AGENCY_ADMIN => __('Agency admin'),
            self::OPERATOR => __('Operator'),
            default => '—',
        };
    }
}
