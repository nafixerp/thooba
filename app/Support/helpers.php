<?php

if (! function_exists('money_in')) {
    /**
     * Indian number grouping: 1234567.5 -> 12,34,567.50
     */
    function money_in(float|int|string|null $n, int $decimals = 2): string
    {
        $n = (float) ($n ?? 0);
        $neg = $n < 0;
        $n = abs($n);

        $parts = explode('.', number_format($n, $decimals, '.', ''));
        $int = $parts[0];
        $dec = $parts[1] ?? '';

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }

        return ($neg ? '-' : '').$int.($decimals ? '.'.$dec : '');
    }
}

if (! function_exists('cur')) {
    function cur(float|int|string|null $n, int $decimals = 2): string
    {
        return config('scheme.symbol', '₹').' '.money_in($n, $decimals);
    }
}

if (! function_exists('drcr_label')) {
    function drcr_label(float $signed): string
    {
        if (abs($signed) < 0.005) {
            return '—';
        }

        return money_in(abs($signed)).' '.($signed > 0 ? 'Dr' : 'Cr');
    }
}

if (! function_exists('storage_url')) {
    /**
     * Public URL for a file on the "public" disk, built from the HOST THE
     * CLIENT ACTUALLY USED rather than APP_URL.
     *
     * asset() pins everything to APP_URL/ASSET_URL, which is normally
     * http://localhost:8081/schemeerp. A phone or an Android emulator cannot
     * resolve "localhost" to this machine, so every banner, product photo and
     * payment screenshot silently fell back to a placeholder.
     *
     * The path segment (/schemeerp) still comes from the configured URL,
     * because index.php strips it before Laravel sees the request.
     */
    function storage_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $configured = rtrim((string) (config('app.asset_url') ?: config('app.url')), '/');
        $base = $configured;

        if ($request = request()) {
            $host = $request->getSchemeAndHttpHost();          // http://10.0.2.2:8081
            $prefix = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');  // /schemeerp
            $base = $host.$prefix;
        }

        return $base.'/storage/'.ltrim($path, '/');
    }
}
