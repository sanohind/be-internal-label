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
        Log::info("Starting manual sync for prod_index: {$this->prodIndex}");

        // Sync both prod_header and prod_label
        $this->syncProdHeaders();
        $this->syncProdLabels();

        Log::info("Manual sync completed for prod_index: {$this->prodIndex}");
    }

    /**
     * Sync prod_header data from ERP
     */
    protected function syncProdHeaders(): void
    {
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            // Fetch data from ERP (SQL Server)
            $erpData = DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', $this->prodIndex)
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period: {$this->prodIndex}";
                $status = 'success';
            } else {
                // Sync to Local Database
                DB::transaction(function () use ($erpData, &$syncedCount) {
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
                $message = "Prod Headers for period {$this->prodIndex} synced successfully.";
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
    }

    /**
     * Sync prod_label data from ERP
     */
    protected function syncProdLabels(): void
    {
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            $erpData = DB::connection('sqlsrv')
                ->table('view_prod_label')
                ->where('prod_index', $this->prodIndex)
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_label for period: {$this->prodIndex}";
                $status = 'success';
            } else {
                DB::transaction(function () use ($erpData, &$syncedCount) {
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
                $message = "Prod Labels for period {$this->prodIndex} synced successfully.";
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
