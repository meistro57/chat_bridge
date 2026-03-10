<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    private function resolveMaintenanceBanner(): array
    {
        try {
            if (Storage::disk('local')->exists('maintenance_banner.json')) {
                $data = json_decode(Storage::disk('local')->get('maintenance_banner.json'), true);
                if (is_array($data)) {
                    return [
                        'enabled' => (bool) ($data['enabled'] ?? false),
                        'message' => (string) ($data['message'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable) {
            // Silently ignore file read errors
        }

        return ['enabled' => false, 'message' => ''];
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
            ],
            'maintenanceBanner' => fn () => $this->resolveMaintenanceBanner(),
        ];
    }
}
