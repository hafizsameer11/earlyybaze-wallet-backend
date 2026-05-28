<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminRegionScope
{
    /**
     * Resolve region filter for admin users from role name or explicit query param.
     */
    public static function resolve(Request $request): ?string
    {
        $explicit = $request->query('region');
        if ($explicit && $explicit !== 'all') {
            return $explicit;
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        $role = strtolower((string) ($user->role ?? ''));

        if (str_contains($role, 'nigeria') || str_contains($role, 'ng_admin')) {
            return 'nigeria';
        }

        if (str_contains($role, 'south_africa') || str_contains($role, 'za_admin') || str_contains($role, 'zar')) {
            return 'south_africa';
        }

        return null;
    }
}
