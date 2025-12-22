<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProdHeader;
use App\Models\ProdLabel;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    /**
     * Get list of prod headers that are ready for printing
     * Filter: sts IN (60, 70) AND has at least 1 prod_label with status='NS' and print_status=0
     */
    public function listProdHeaders(Request $request)
    {
        try {
            // Optional filter by prod_index (period)
            $prodIndex = $request->input('prod_index');

            $query = ProdHeader::whereIn('sts', [60, 70])
                ->whereHas('prodLabels', function ($query) {
                    $query->where('status', 'NS')
                        ->where('print_status', 0);
                });

            if ($prodIndex) {
                $query->where('prod_index', $prodIndex);
            }

            $headers = $query->orderBy('prod_no', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Prod headers retrieved successfully',
                'count' => $headers->count(),
                'data' => $headers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve prod headers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get labels ready for printing based on prod_no
     * Filter: status = 'NS' AND print_status = 0
     */
    public function getPrintableLabels(Request $request)
    {
        $request->validate([
            'prod_no' => 'required|string'
        ]);

        try {
            $prodNo = $request->input('prod_no');

            // Get the prod_header data
            $prodHeader = ProdHeader::where('prod_no', $prodNo)
                ->whereIn('sts', [60, 70])
                ->first();

            if (!$prodHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prod header not found or not ready for printing (sts must be 60 or 70)'
                ], 404);
            }

            // Get printable labels
            $labels = ProdLabel::where('prod_no', $prodNo)
                ->where('status', 'NS')
                ->where('print_status', 0)
                ->orderBy('lot_no', 'asc')
                ->get();

            if ($labels->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No printable labels found for this prod_no'
                ], 404);
            }

            // Prepare data for PDF generation
            $labelData = $labels->map(function ($label) use ($prodHeader) {
                // Extract year from prod_index (e.g., 2512 -> 2025)
                $year = '';

                // Calculate qty: qty_order / snp
                $qty = $prodHeader->snp > 0
                    ? round($prodHeader->qty_order / $prodHeader->snp, 2)
                    : 0;

                // Prepare semicolon-separated data for printing above QR code
                // Format: old_partno;qty;lot_no;customer;prod_no
                $partNo = $prodHeader->old_partno ?? '';
                $customer = $prodHeader->customer ?? '';
                $printData = implode(';', [
                    $partNo,
                    $qty,
                    $label->lot_no,
                    $customer,
                    $prodHeader->prod_no
                ]);

                return [
                    'label_id' => $label->id,
                    'lot_no' => $label->lot_no,
                    'customer' => $prodHeader->customer,
                    'model' => $prodHeader->model,
                    'unique_no' => $prodHeader->unique_no,
                    'part_no' => $prodHeader->old_partno, 
                    'description' => $prodHeader->description,
                    'date' => $year,
                    'qty' => $qty,
                    'lot_date' => $label->lot_date,
                    'lot_qty' => $label->lot_qty,
                    'back_no' => $prodHeader->back_no,
                    'tmmin_id' => $prodHeader->common_id, // common_id dijadikan tmmin_id
                    'karakteristik' => $prodHeader->karakteristik,
                    'print_data' => $printData, // data untuk ditampilkan di atas QR code
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Printable labels retrieved successfully',
                'count' => $labelData->count(),
                'prod_header' => [
                    'prod_no' => $prodHeader->prod_no,
                    'prod_index' => $prodHeader->prod_index,
                    'sts' => $prodHeader->sts,
                ],
                'data' => $labelData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve printable labels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark labels as printed
     * Update print_status from 0 to 1
     */
    public function markAsPrinted(Request $request)
    {
        $request->validate([
            'label_ids' => 'required|array',
            'label_ids.*' => 'integer|exists:prod_label,id'
        ]);

        try {
            $labelIds = $request->input('label_ids');

            $updated = ProdLabel::whereIn('id', $labelIds)
                ->where('print_status', 0)
                ->update([
                    'print_status' => 1
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Labels marked as printed successfully',
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark labels as printed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
