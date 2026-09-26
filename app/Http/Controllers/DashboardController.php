<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Navigation\NavItem;
use App\Services\SpendingService;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly SpendingService $spendingService,
    ) {}

    #[NavItem(label: 'app.dashboard', route: 'dashboard', icon: 'HomeIcon', order: 10)]
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'dashboard' => $this->spendingService->dashboard(),
        ]);
    }
}
