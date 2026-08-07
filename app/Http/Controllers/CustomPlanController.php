<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CustomPlanService;

class CustomPlanController extends Controller
{
    protected CustomPlanService $customPlanService;

    public function __construct()
    {
        $this->customPlanService = app(CustomPlanService::class);
    }

    public function store(Request $request)
    {
        $plan = $this->customPlanService->create($request->all());

        return redirect()
            ->back()
            ->with('success', 'Custom plan created successfully.');
    }
}
