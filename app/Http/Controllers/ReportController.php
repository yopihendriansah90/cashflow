<?php

namespace App\Http\Controllers;

use App\Support\MonthlyLedger;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = MonthlyLedger::fromRequest($request);

        return view('pages.cashflow.report', [
            'membership' => $request->attributes->get('membership'),
            'report' => $report,
            'summary' => $report->summary(),
        ]);
    }

    public function export(Request $request)
    {
        $report = MonthlyLedger::fromRequest($request);

        return response()->streamDownload(function () use ($report) {
            $output = fopen('php://output', 'wb');
            try {
                $report->writeCsv($output);
            } finally {
                fclose($output);
            }
        }, 'transaksi-'.$report->month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
