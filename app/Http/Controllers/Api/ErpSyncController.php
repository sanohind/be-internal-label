<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProdHeader;
use App\Models\ProdLabel;

class ErpSyncController extends Controller
{
    public function syncProdHeaders(Request $request)
    {
        // Determine prod_index: use query param or default to current YYMM (e.g. 2512)
        $prodIndex = $request->input('prod_index', date('ym'));
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            // 1. Fetch data from ERP (SQL Server)
            $erpData = \DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', $prodIndex)
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period: $prodIndex";
                $status = 'success';

                // Log the sync attempt
                \App\Models\SyncLog::create([
                    'sync_type' => 'prod_header',
                    'prod_index' => $prodIndex,
                    'records_synced' => 0,
                    'status' => $status,
                    'message' => $message,
                    'synced_at' => now(),
                ]);

                return response()->json(['message' => $message]);
            }

            // 2. Sync to Local Database
            \DB::transaction(function () use ($erpData, &$syncedCount) {
                foreach ($erpData as $item) {
                    $itemArray = (array) $item;
                    ProdHeader::updateOrCreate(
                        ['prod_no' => $itemArray['prod_no']],
                        $itemArray
                    );
                    $syncedCount++;
                }
            });

            $status = 'success';
            $message = "Prod Headers for period $prodIndex synced successfully.";

        } catch (\Exception $e) {
            $message = 'Failed to sync ERP data.';
            $errorDetails = $e->getMessage();
        }

        // Log the sync operation
        \App\Models\SyncLog::create([
            'sync_type' => 'prod_header',
            'prod_index' => $prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);

        if ($status === 'failed') {
            return response()->json([
                'message' => $message,
                'error' => $errorDetails
            ], 500);
        }

        return response()->json([
            'message' => $message,
            'count' => $syncedCount
        ]);
    }

    public function syncProdLabels(Request $request)
    {
        // Determine prod_index: use query param or default to current YYMM (e.g. 2512)
        $prodIndex = $request->input('prod_index', date('ym'));
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            $erpData = \DB::connection('sqlsrv')
                ->table('view_prod_label')
                ->where('prod_index', $prodIndex)
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_label for period: $prodIndex";
                $status = 'success';

                // Log the sync attempt
                \App\Models\SyncLog::create([
                    'sync_type' => 'prod_label',
                    'prod_index' => $prodIndex,
                    'records_synced' => 0,
                    'status' => $status,
                    'message' => $message,
                    'synced_at' => now(),
                ]);

                return response()->json(['message' => $message]);
            }

            \DB::transaction(function () use ($erpData, &$syncedCount) {
                foreach ($erpData as $item) {
                    $itemArray = (array) $item;
                    ProdLabel::updateOrCreate(
                        ['lot_no' => $itemArray['lot_no']],
                        $itemArray
                    );
                    $syncedCount++;
                }
            });

            $status = 'success';
            $message = "Prod Labels for period $prodIndex synced successfully.";

        } catch (\Exception $e) {
            $message = 'Failed to sync ERP data.';
            $errorDetails = $e->getMessage();
        }

        // Log the sync operation
        \App\Models\SyncLog::create([
            'sync_type' => 'prod_label',
            'prod_index' => $prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);

        if ($status === 'failed') {
            return response()->json([
                'message' => $message,
                'error' => $errorDetails
            ], 500);
        }

        return response()->json([
            'message' => $message,
            'count' => $syncedCount
        ]);
    }
}
