<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UsageAnalyticsService
{
    public function getOverallStats(string $period = 'today'): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        $total = $query->count();
        $successful = $query->clone()->successful()->count();
        $errors = $query->clone()->errors()->count();
        $avgResponseTime = $query->avg('response_time_ms');

        return [
            'total_requests' => $total,
            'successful_requests' => $successful,
            'error_requests' => $errors,
            'success_rate' => $total > 0 ? ($successful / $total) * 100 : 0,
            'avg_response_time_ms' => round($avgResponseTime ?? 0, 2),
            'period' => $period,
        ];
    }

    public function getUserStats(User $user, string $period = 'today'): array
    {
        $query = $user->usageLogs();
        $this->applyPeriodFilter($query, $period);

        $total = $query->count();
        $successful = $query->clone()->successful()->count();
        $errors = $query->clone()->errors()->count();
        $avgResponseTime = $query->avg('response_time_ms');

        return [
            'user_id' => $user->id,
            'total_requests' => $total,
            'successful_requests' => $successful,
            'error_requests' => $errors,
            'success_rate' => $total > 0 ? ($successful / $total) * 100 : 0,
            'avg_response_time_ms' => round($avgResponseTime ?? 0, 2),
            'period' => $period,
        ];
    }

    public function getTopEndpoints(string $period = 'today', int $limit = 10): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        return $query->select('endpoint', DB::raw('COUNT(*) as request_count'))
            ->groupBy('endpoint')
            ->orderBy('request_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTopUsers(string $period = 'today', int $limit = 10): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        return $query->select('user_id', DB::raw('COUNT(*) as request_count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('request_count', 'desc')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($log) {
                return [
                    'user_id' => $log->user_id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'user_email' => $log->user->email ?? 'Unknown',
                    'request_count' => $log->request_count,
                ];
            })
            ->toArray();
    }

    public function getErrorAnalysis(string $period = 'today'): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        $errorsByStatus = $query->clone()
            ->where('status_code', '>=', 400)
            ->select('status_code', DB::raw('COUNT(*) as error_count'))
            ->groupBy('status_code')
            ->orderBy('error_count', 'desc')
            ->get()
            ->toArray();

        $errorsByEndpoint = $query->clone()
            ->where('status_code', '>=', 400)
            ->select('endpoint', DB::raw('COUNT(*) as error_count'))
            ->groupBy('endpoint')
            ->orderBy('error_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'errors_by_status' => $errorsByStatus,
            'errors_by_endpoint' => $errorsByEndpoint,
        ];
    }

    public function getTimeSeriesData(string $period = 'today', string $interval = 'hour'): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        $dateFormat = $this->getDateFormat($interval);
        
        return $query->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as time_period"),
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as successful_count'),
                DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count'),
                DB::raw('AVG(response_time_ms) as avg_response_time')
            )
            ->groupBy('time_period')
            ->orderBy('time_period')
            ->get()
            ->map(function ($item) {
                return [
                    'time_period' => $item->time_period,
                    'request_count' => $item->request_count,
                    'successful_count' => $item->successful_count,
                    'error_count' => $item->error_count,
                    'success_rate' => $item->request_count > 0 ? 
                        ($item->successful_count / $item->request_count) * 100 : 0,
                    'avg_response_time_ms' => round($item->avg_response_time ?? 0, 2),
                ];
            })
            ->toArray();
    }

    public function getResponseTimePercentiles(string $period = 'today'): array
    {
        $query = ApiUsageLog::query();
        $this->applyPeriodFilter($query, $period);

        $responseTimes = $query->pluck('response_time_ms')->sort()->values();
        
        if ($responseTimes->isEmpty()) {
            return [
                'p50' => 0,
                'p90' => 0,
                'p95' => 0,
                'p99' => 0,
            ];
        }

        $count = $responseTimes->count();
        
        return [
            'p50' => $responseTimes[intval($count * 0.5)] ?? 0,
            'p90' => $responseTimes[intval($count * 0.9)] ?? 0,
            'p95' => $responseTimes[intval($count * 0.95)] ?? 0,
            'p99' => $responseTimes[intval($count * 0.99)] ?? 0,
        ];
    }

    private function applyPeriodFilter($query, string $period): void
    {
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'yesterday':
                $query->whereDate('created_at', yesterday());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'last_30_days':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
        }
    }

    private function getDateFormat(string $interval): string
    {
        switch ($interval) {
            case 'minute':
                return '%Y-%m-%d %H:%i';
            case 'hour':
                return '%Y-%m-%d %H:00';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            default:
                return '%Y-%m-%d %H:00';
        }
    }
}