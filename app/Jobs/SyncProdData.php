<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ProdHeader;
use App\Models\ProdLabel;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProdData implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 900; // 15 minutes timeout
    public $tries = 1; // Only try once

    protected $prodIndex;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $prodIndex = null)
    {
        $this->prodIndex = $prodIndex ?? date('ym');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting manual sync for prod_index >= {$this->prodIndex}");

        // Sync both prod_header and prod_label
        $syncedProdNos = $this->syncProdHeaders();
        $this->syncProdLabels($syncedProdNos);

        Log::info("Manual sync completed for prod_index >= {$this->prodIndex}");
    }

    /**
     * Sync prod_header data from ERP
     */
    protected function syncProdHeaders(): array
    {
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;
        $syncedProdNos = [];

        try {
            // Fetch data from ERP (SQL Server) with prod_index >= current period and sts = 60 or 70
            $erpData = DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', '>=', $this->prodIndex)
                ->whereIn('sts', [60, 70])
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period >= {$this->prodIndex} with sts 60 or 70";
                $status = 'success';
            } else {
                // Sync to Local Database
                DB::transaction(function () use ($erpData, &$syncedCount, &$syncedProdNos) {
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
                $message = "Prod Headers for period >= {$this->prodIndex} (sts 60/70) synced successfully.";
            }
        } catch (\Exception $e) {
            $message = 'Failed to sync prod_header data.';
            $errorDetails = $e->getMessage();
            Log::error("Prod Header Sync Error: " . $errorDetails);
        }

        // Log the sync operation
        SyncLog::create([
            'sync_type' => 'prod_header',
            'prod_index' => $this->prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);

        return $syncedProdNos;
    }

    /**
     * Sync prod_label data from ERP
     */
    protected function syncProdLabels(array $syncedProdNos = []): void
    {
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            // If no prod_nos were synced from headers, skip label sync
            if (empty($syncedProdNos)) {
                $message = "No prod_no to sync from headers, skipping label sync";
                $status = 'success';
            } else {
                // SQL Server has a limit of 2100 parameters, so we need to chunk the prod_nos
                // We'll use chunks of 2000 to be safe
                $chunkSize = 2000;
                $allErpData = collect();

                foreach (array_chunk($syncedProdNos, $chunkSize) as $chunk) {
                    $chunkData = DB::connection('sqlsrv')
                        ->table('view_prod_label')
                        ->where('prod_index', '>=', $this->prodIndex)
                        ->whereIn('prod_no', $chunk)
                        ->get();

                    $allErpData = $allErpData->merge($chunkData);
                }

                if ($allErpData->isEmpty()) {
                    $message = "No data found in ERP view_prod_label for synced prod_nos";
                    $status = 'success';
                } else {
                    DB::transaction(function () use ($allErpData, &$syncedCount) {
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
                    $message = "Prod Labels for period >= {$this->prodIndex} (" . count($syncedProdNos) . " prod_nos) synced successfully.";
                }
            }
        } catch (\Exception $e) {
            $message = 'Failed to sync prod_label data.';
            $errorDetails = $e->getMessage();
            Log::error("Prod Label Sync Error: " . $errorDetails);
        }

        // Log the sync operation
        SyncLog::create([
            'sync_type' => 'prod_label',
            'prod_index' => $this->prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncProdData job failed: " . $exception->getMessage());

        // Log the failure
        SyncLog::create([
            'sync_type' => 'manual_sync',
            'prod_index' => $this->prodIndex,
            'records_synced' => 0,
            'status' => 'failed',
            'message' => 'Job failed to execute',
            'error_details' => $exception->getMessage(),
            'synced_at' => now(),
        ]);
    }
}
