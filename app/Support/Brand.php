<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Brand
{
    public const NAME = 'Cashflow Rumah Tangga Muslim';
    public const LOGO = 'assets/images/logo/logo-icon.svg';
    public const FAVICON = 'favicon.ico';

    public readonly string $name;
    public readonly string $logo;
    public readonly string $favicon;

    public function __construct()
    {
        $settings = DB::table('system_settings')->find(1);
        $this->name = $settings->app_name ?? __(self::NAME);
        $this->logo = self::url('logo', $settings->logo_path ?? null, self::LOGO);
        $this->favicon = self::url('favicon', $settings->favicon_path ?? null, self::FAVICON);
    }

    private static function url(string $kind, ?string $path, string $fallback): string
    {
        return $path && Storage::disk('local')->exists($path)
            ? route('identity.asset', [$kind, basename($path)], absolute: false)
            : asset($fallback);
    }
}
