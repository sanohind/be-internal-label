<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProdHeader;
use App\Models\ProdLabel;
use App\Models\SyncLog;

class SyncErpData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:sync {--prod_index= : Specific period to sync (YYMM format, e.g., 2512)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data from ERP (view_prod_header and view_prod_label) to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $prodIndex = $this->option('prod_index') ?? date('ym');

        $this->info("Starting ERP sync for period: {$prodIndex}");
        $this->newLine();

        // Sync Prod Headers
        $this->info('Syncing Prod Headers...');
        $headerResult = $this->syncProdHeaders($prodIndex);
        $this->line("  → {$headerResult['message']}");
        $this->line("  → Records synced: {$headerResult['count']}");
        $this->newLine();

        // Sync Prod Labels
        $this->info('Syncing Prod Labels...');
        $labelResult = $this->syncProdLabels($prodIndex);
        $this->line("  → {$labelResult['message']}");
        $this->line("  → Records synced: {$labelResult['count']}");
        $this->newLine();

        if ($headerResult['status'] === 'success' && $labelResult['status'] === 'success') {
            $this->info('✓ ERP sync completed successfully!');
            return Command::SUCCESS;
        } else {
            $this->error('✗ ERP sync completed with errors. Check sync_logs table for details.');
            return Command::FAILURE;
        }
    }

    private function syncProdHeaders($prodIndex)
    {
        $syncedCount = 0;
        $status = 'failed';
        $message = '';
        $errorDetails = null;

        try {
            $erpData = \DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', $prodIndex)
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period: {$prodIndex}";
                $status = 'success';
            } else {
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
                $message = "Prod Headers synced successfully";
            }
        } catch (\Exception $e) {
            $message = 'Failed to sync Prod Headers';
            $errorDetails = $e->getMessage();
            $this->error("  Error: {$errorDetails}");
        }

        // Log the sync operation
        SyncLog::create([
            'sync_type' => 'prod_header',
            'prod_index' => $prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'count' => $syncedCount,
        ];
    }

    private function syncProdLabels($prodIndex)
    {
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
                $message = "No data found in ERP view_prod_label for period: {$prodIndex}";
                $status = 'success';
            } else {
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
                $message = "Prod Labels synced successfully";
            }
        } catch (\Exception $e) {
            $message = 'Failed to sync Prod Labels';
            $errorDetails = $e->getMessage();
            $this->error("  Error: {$errorDetails}");
        }

        // Log the sync operation
        SyncLog::create([
            'sync_type' => 'prod_label',
            'prod_index' => $prodIndex,
            'records_synced' => $syncedCount,
            'status' => $status,
            'message' => $message,
            'error_details' => $errorDetails,
            'synced_at' => now(),
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'count' => $syncedCount,
        ];
    }
}
