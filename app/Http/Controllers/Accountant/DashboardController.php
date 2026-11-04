<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * عرض لوحة التحكم الرئيسية للمحاسب
     */
    public function index(): View
    {
        /** @var Accountant $accountant */
        $accountant = auth('accountant')->user()->loadMissing('office');
        $office = $accountant->office;

        return view('accountant.dashboard', compact('accountant', 'office'));
    }
}

