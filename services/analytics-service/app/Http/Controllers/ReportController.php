<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Get all reports
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|string|in:user_activity,auction_performance,revenue,system_health',
                'status' => 'nullable|string|in:pending,processing,completed,failed',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = DB::table('analytics_reports')
                ->select([
                    'id',
                    'type',
                    'title',
                    'status',
                    'parameters',
                    'file_path',
                    'file_size',
                    'created_at',
                    'updated_at',
                    'completed_at'
                ])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            $reports = $query->limit($limit)->offset($offset)->get();
            $total = $query->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'reports' => $reports,
                    'pagination' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total,
                    ],
                ],
                'message' => 'Reports retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve reports', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate a new report
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:user_activity,auction_performance,revenue,system_health',
                'title' => 'nullable|string|max:255',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'filters' => 'nullable|array',
                'format' => 'nullable|string|in:json,csv,pdf',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Validate date range
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            if ($dateFrom->gt($dateTo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date from must be before date to',
                ], 422);
            }

            // Create report record
            $reportId = DB::table('analytics_reports')->insertGetId([
                'type' => $request->type,
                'title' => $request->title ?? $this->generateReportTitle($request->type, $dateFrom, $dateTo),
                'status' => 'pending',
                'parameters' => json_encode([
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'filters' => $request->filters ?? [],
                    'format' => $request->format ?? 'json',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Queue report generation (in a real implementation, this would be a job)
            $this->processReport($reportId, $request->all());

            $report = DB::table('analytics_reports')->find($reportId);

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                ],
                'message' => 'Report generation started successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to generate report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get specific report details
     */
    public function show(int $reportId): JsonResponse
    {
        try {
            $report = DB::table('analytics_reports')->find($reportId);

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found',
                ], 404);
            }

            // If report has data, include it
            $reportData = null;
            if ($report->status === 'completed' && $report->file_path) {
                $reportData = $this->getReportData($report);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'data' => $reportData,
                ],
                'message' => 'Report retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'report_id' => $reportId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve report',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Download report file
     */
    public function download(int $reportId): Response
    {
        try {
            $report = DB::table('analytics_reports')->find($reportId);

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found',
                ], 404);
            }

            if ($report->status !== 'completed' || !$report->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report is not ready for download',
                ], 400);
            }

            $filePath = storage_path('app/' . $report->file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report file not found',
                ], 404);
            }

            $parameters = json_decode($report->parameters, true);
            $format = $parameters['format'] ?? 'json';
            
            $mimeTypes = [
                'json' => 'application/json',
                'csv' => 'text/csv',
                'pdf' => 'application/pdf',
            ];

            $extensions = [
                'json' => 'json',
                'csv' => 'csv',
                'pdf' => 'pdf',
            ];

            $filename = "report_{$report->id}_{$report->type}." . $extensions[$format];

            return response()->download(
                $filePath,
                $filename,
                [
                    'Content-Type' => $mimeTypes[$format],
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to download report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'report_id' => $reportId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download report',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate report title based on type and date range
     */
    private function generateReportTitle(string $type, Carbon $dateFrom, Carbon $dateTo): string
    {
        $typeNames = [
            'user_activity' => 'User Activity Report',
            'auction_performance' => 'Auction Performance Report',
            'revenue' => 'Revenue Report',
            'system_health' => 'System Health Report',
        ];

        $typeName = $typeNames[$type] ?? ucfirst(str_replace('_', ' ', $type));
        
        return "{$typeName} ({$dateFrom->format('M j, Y')} - {$dateTo->format('M j, Y')})";
    }

    /**
     * Process report generation (simplified implementation)
     */
    private function processReport(int $reportId, array $parameters): void
    {
        try {
            // Update status to processing
            DB::table('analytics_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            // Generate report data based on type
            $data = $this->generateReportData($parameters);

            // Save report data to file
            $filePath = $this->saveReportData($reportId, $data, $parameters['format'] ?? 'json');

            // Update report as completed
            DB::table('analytics_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => 'completed',
                    'file_path' => $filePath,
                    'file_size' => file_exists(storage_path('app/' . $filePath)) ? filesize(storage_path('app/' . $filePath)) : 0,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

        } catch (\Exception $e) {
            // Update report as failed
            DB::table('analytics_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            Log::error('Report generation failed', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'parameters' => $parameters,
            ]);
        }
    }

    /**
     * Generate report data based on type
     */
    private function generateReportData(array $parameters): array
    {
        $type = $parameters['type'];
        $dateFrom = $parameters['date_from'];
        $dateTo = $parameters['date_to'];
        $filters = $parameters['filters'] ?? [];

        return match ($type) {
            'user_activity' => $this->generateUserActivityData($dateFrom, $dateTo, $filters),
            'auction_performance' => $this->generateAuctionPerformanceData($dateFrom, $dateTo, $filters),
            'revenue' => $this->generateRevenueData($dateFrom, $dateTo, $filters),
            'system_health' => $this->generateSystemHealthData($dateFrom, $dateTo, $filters),
            default => ['error' => 'Unknown report type']
        };
    }

    /**
     * Generate user activity report data
     */
    private function generateUserActivityData(string $dateFrom, string $dateTo, array $filters): array
    {
        // Simplified implementation - in real scenario, this would query actual data
        return [
            'summary' => [
                'total_users' => rand(1000, 5000),
                'active_users' => rand(500, 2000),
                'new_registrations' => rand(50, 200),
                'user_sessions' => rand(2000, 8000),
            ],
            'daily_activity' => [
                // Sample data - would be real data from analytics_events table
                ['date' => $dateFrom, 'active_users' => rand(100, 500), 'sessions' => rand(200, 1000)],
                ['date' => $dateTo, 'active_users' => rand(100, 500), 'sessions' => rand(200, 1000)],
            ],
            'top_events' => [
                ['event_type' => 'page_view', 'count' => rand(5000, 15000)],
                ['event_type' => 'bid_placed', 'count' => rand(500, 2000)],
                ['event_type' => 'auction_viewed', 'count' => rand(1000, 5000)],
            ],
        ];
    }

    /**
     * Generate auction performance report data
     */
    private function generateAuctionPerformanceData(string $dateFrom, string $dateTo, array $filters): array
    {
        return [
            'summary' => [
                'total_auctions' => rand(100, 500),
                'completed_auctions' => rand(80, 400),
                'total_bids' => rand(1000, 5000),
                'average_bids_per_auction' => rand(10, 50),
            ],
            'performance_metrics' => [
                'completion_rate' => rand(80, 95) . '%',
                'average_auction_duration' => rand(24, 168) . ' hours',
                'bid_participation_rate' => rand(60, 85) . '%',
            ],
        ];
    }

    /**
     * Generate revenue report data
     */
    private function generateRevenueData(string $dateFrom, string $dateTo, array $filters): array
    {
        return [
            'summary' => [
                'total_revenue' => rand(10000, 100000),
                'commission_revenue' => rand(5000, 50000),
                'subscription_revenue' => rand(2000, 20000),
                'transaction_count' => rand(500, 2000),
            ],
            'revenue_breakdown' => [
                'auctions' => rand(60, 80) . '%',
                'subscriptions' => rand(15, 25) . '%',
                'fees' => rand(5, 15) . '%',
            ],
        ];
    }

    /**
     * Generate system health report data
     */
    private function generateSystemHealthData(string $dateFrom, string $dateTo, array $filters): array
    {
        return [
            'summary' => [
                'uptime_percentage' => rand(95, 100) . '%',
                'average_response_time' => rand(100, 500) . 'ms',
                'error_rate' => rand(0, 5) . '%',
                'total_requests' => rand(100000, 500000),
            ],
            'service_health' => [
                'auth_service' => 'healthy',
                'auction_service' => 'healthy',
                'payment_service' => 'healthy',
                'notification_service' => 'healthy',
            ],
        ];
    }

    /**
     * Save report data to file
     */
    private function saveReportData(int $reportId, array $data, string $format): string
    {
        $directory = 'reports/' . date('Y/m');
        $filename = "report_{$reportId}_{$format}_" . time() . ".{$format}";
        $filePath = "{$directory}/{$filename}";

        // Ensure directory exists
        if (!file_exists(storage_path('app/' . $directory))) {
            mkdir(storage_path('app/' . $directory), 0755, true);
        }

        match ($format) {
            'json' => file_put_contents(
                storage_path('app/' . $filePath),
                json_encode($data, JSON_PRETTY_PRINT)
            ),
            'csv' => $this->saveAsCsv(storage_path('app/' . $filePath), $data),
            'pdf' => file_put_contents(
                storage_path('app/' . $filePath),
                json_encode($data, JSON_PRETTY_PRINT) // For PDF, we'd use a library like TCPDF or DomPDF
            ),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}")
        };

        return $filePath;
    }

    /**
     * Save data as CSV format
     */
    private function saveAsCsv(string $filePath, array $data): void
    {
        $file = fopen($filePath, 'w');
        
        // Write headers
        if (!empty($data)) {
            $firstRow = reset($data);
            if (is_array($firstRow)) {
                fputcsv($file, array_keys($firstRow));
                
                // Write data rows
                foreach ($data as $row) {
                    if (is_array($row)) {
                        fputcsv($file, $row);
                    }
                }
            } else {
                // Simple key-value data
                fputcsv($file, ['Key', 'Value']);
                foreach ($data as $key => $value) {
                    fputcsv($file, [$key, is_array($value) ? json_encode($value) : $value]);
                }
            }
        }
        
        fclose($file);
    }

    /**
     * Get report data from file
     */
    private function getReportData($report): ?array
    {
        if (!$report->file_path) {
            return null;
        }

        $filePath = storage_path('app/' . $report->file_path);
        
        // Validate file path to prevent directory traversal
        $realPath = realpath($filePath);
        $storagePath = realpath(storage_path('app'));
        
        if (!$realPath || !$storagePath || strpos($realPath, $storagePath) !== 0) {
            return null;
        }
        
        if (!file_exists($realPath)) {
            return null;
        }

        $parameters = json_decode($report->parameters, true);
        $format = $parameters['format'] ?? 'json';

        if ($format === 'json') {
            $content = file_get_contents($realPath);
            return json_decode($content, true);
        }

        // For other formats, return file info
        return [
            'file_size' => filesize($realPath),
            'format' => $format,
            'download_available' => true,
        ];
    }
}
