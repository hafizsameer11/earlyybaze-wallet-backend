<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\SystemSettingHelper;
use Illuminate\Http\Request;

class AutoFlushController extends Controller
{
    private const AUTO_FLUSH_KEY = 'auto_flush_enabled';

    public function config()
    {
        return ResponseHelper::success([
            'enabled' => SystemSettingHelper::getBool(self::AUTO_FLUSH_KEY, false),
            'default' => false,
        ], 'Auto flush config fetched', 200);
    }

    public function updateConfig(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SystemSettingHelper::setBool(self::AUTO_FLUSH_KEY, (bool) $validated['enabled']);

        return ResponseHelper::success([
            'enabled' => SystemSettingHelper::getBool(self::AUTO_FLUSH_KEY, false),
        ], 'Auto flush config updated', 200);
    }
}
