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

        $this->info("Starting ERP sync for period >= {$prodIndex}");
        $this->newLine();

        // Sync Prod Headers (with sts filter 60 or 70)
        $this->info('Syncing Prod Headers (sts = 60 or 70)...');
        $headerResult = $this->syncProdHeaders($prodIndex);
        $this->line("  → {$headerResult['message']}");
        $this->line("  → Records synced: {$headerResult['count']}");
        $this->newLine();

        // Sync Prod Labels (only for synced prod_no from headers)
        $this->info('Syncing Prod Labels (based on synced headers)...');
        $labelResult = $this->syncProdLabels($prodIndex, $headerResult['synced_prod_nos'] ?? []);
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
        $syncedProdNos = [];

        try {
            // Fetch data with prod_index >= current period and sts = 60 or 70
            $erpData = \DB::connection('sqlsrv')
                ->table('view_prod_header')
                ->where('prod_index', '>=', $prodIndex)
                ->whereIn('sts', [60, 70])
                ->get();

            if ($erpData->isEmpty()) {
                $message = "No data found in ERP view_prod_header for period >= {$prodIndex} with sts 60 or 70";
                $status = 'success';
            } else {
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
                $message = "Prod Headers synced successfully (sts 60/70, prod_index >= {$prodIndex})";
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
            'synced_prod_nos' => $syncedProdNos,
        ];
    }

    private function syncProdLabels($prodIndex, $syncedProdNos = [])
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
                // Fetch labels with prod_index >= current period and prod_no in synced headers
                $erpData = \DB::connection('sqlsrv')
                    ->table('view_prod_label')
                    ->where('prod_index', '>=', $prodIndex)
                    ->whereIn('prod_no', $syncedProdNos)
                    ->get();

                if ($erpData->isEmpty()) {
                    $message = "No data found in ERP view_prod_label for synced prod_nos";
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
                    $message = "Prod Labels synced successfully (for " . count($syncedProdNos) . " prod_nos)";
                }
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
