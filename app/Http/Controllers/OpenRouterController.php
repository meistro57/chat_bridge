<?php

namespace App\Http\Controllers;

use App\Services\OpenRouterService;
use Illuminate\Http\JsonResponse;

class OpenRouterController extends Controller
{
    public function __construct(protected OpenRouterService $openRouter) {}

    public function stats(): JsonResponse
    {
        return response()->json($this->openRouter->getDashboardStats());
    }
}
