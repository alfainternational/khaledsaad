<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\AgencyPortfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * محفظة الوكالة عبر الـAPI — نظير `App\Http\Controllers\App\PortfolioController`.
 *
 * النطاق مساحة العمل كلها لا مشروعًا بعينه (§٥.٢): وكالة تدير عشرة عملاء تفتح
 * شاشة واحدة. لا حساب هنا — يُقرأ ما حسبته `Diagnosis` عبر `AgencyPortfolio`.
 */
class PortfolioController extends Controller
{
    public function __construct(private readonly AgencyPortfolio $portfolio) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->portfolio->for($request->user()->primaryWorkspace()),
        ]);
    }
}
