<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProdHeader;
use App\Models\ProdLabel;
use App\Jobs\SyncProdData;

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
        $syncedProdNos = [];

        try {
            // 1. Fetch data from ERP (SQL Server) with prod_index >= current period and sts = 60 or 70
            $erpData = \DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', '>=', $prodIndex)
                ->whereIn('sts', [60, 70])
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period >= $prodIndex with sts 60 or 70";
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
            \DB::transaction(function () use ($erpData, &$syncedCount, &$syncedProdNos) {
                foreach ($erpData as $item) {
                    $itemArray = (array) $item;
                    ProdHeader::updateOrCreate(
                        ['prod_no' => $itemArray['prod_no']],
                        $itemArray
                    );
                    $syncedProdNos[] = $itemArray['prod_no'];
                    $syncedCount++;
                }
            });

            $status = 'success';
            $message = "Prod Headers for period >= $prodIndex (sts 60/70) synced successfully.";

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
            'count' => $syncedCount,
            'synced_prod_nos' => $syncedProdNos
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
            // First, get the synced prod_nos from headers with sts 60 or 70
            $syncedProdNos = ProdHeader::where('prod_index', '>=', $prodIndex)
                ->whereIn('sts', [60, 70])
                ->pluck('prod_no')
                ->toArray();

            if (empty($syncedProdNos)) {
                $message = "No prod_no found in local headers with sts 60/70 for period >= $prodIndex";
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

            // SQL Server has a limit of 2100 parameters, so we need to chunk the prod_nos
            // We'll use chunks of 2000 to be safe
            $chunkSize = 2000;
            $allErpData = collect();

            foreach (array_chunk($syncedProdNos, $chunkSize) as $chunk) {
                $chunkData = \DB::connection('sqlsrv')
                    ->table('view_prod_label')
                    ->where('prod_index', '>=', $prodIndex)
                    ->whereIn('prod_no', $chunk)
                    ->get();

                $allErpData = $allErpData->merge($chunkData);
            }

            if ($allErpData->isEmpty()) {
                $message = "No data found in ERP view_prod_label for synced prod_nos";
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

            \DB::transaction(function () use ($allErpData, &$syncedCount) {
                foreach ($allErpData as $item) {
                    $itemArray = (array) $item;
                    ProdLabel::updateOrCreate(
                        ['lot_no' => $itemArray['lot_no']],
                        $itemArray
                    );
                    $syncedCount++;
                }
            });

            $status = 'success';
            $message = "Prod Labels for period >= $prodIndex (" . count($syncedProdNos) . " prod_nos) synced successfully.";

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

    /**
     * Trigger manual sync via queue job (non-blocking)
     * This method dispatches a job and returns immediately
     */
    public function syncManual(Request $request)
    {
        // Validate prod_index if provided
        $request->validate([
            'prod_index' => 'nullable|string|size:4', // Format: YYMM (e.g., 2512)
        ]);

        $prodIndex = $request->input('prod_index', date('ym'));

        try {
            // Dispatch the sync job to the queue
            SyncProdData::dispatch($prodIndex);

            return response()->json([
                'success' => true,
                'message' => "Sync job has been queued for period >= {$prodIndex} (with sts 60/70). The process will run in the background.",
                'prod_index' => $prodIndex,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue sync job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
