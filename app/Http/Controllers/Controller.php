<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Page size for a list endpoint.
     *
     * The mobile app pages through these lists, and a field agent with a large
     * book wants a bigger page than the office does. The cap stops a client
     * asking for the whole table in one request.
     */
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        $requested = (int) $request->input('per_page', $default);

        return max(1, min($requested, $max));
    }
}
