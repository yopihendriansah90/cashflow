<?php

namespace App\Support;

final class WalletBrand
{
    public static function find(string $type, ?string $key): ?array
    {
        return config('wallet-brands')[$type][$key] ?? null;
    }

    public static function logo(string $key): ?string
    {
        $path = "assets/images/wallet-brands/{$key}.png";

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
