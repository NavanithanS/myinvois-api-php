<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'api_key_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'request_size',
        'response_size',
        'ip_address',
        'user_agent',
        'request_headers',
        'response_headers',
        'error_message',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isError(): bool
    {
        return $this->status_code >= 400;
    }

    public function isServerError(): bool
    {
        return $this->status_code >= 500;
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }
}