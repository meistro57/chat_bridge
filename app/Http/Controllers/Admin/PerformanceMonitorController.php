<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PerformanceMonitorService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceMonitorController extends Controller
{
    public function __construct(private readonly PerformanceMonitorService $performanceMonitorService) {}

    public function index(): Response
    {
        return Inertia::render('Admin/PerformanceMonitor', [
            'snapshot' => $this->performanceMonitorService->snapshot(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->performanceMonitorService->snapshot());
    }
}
