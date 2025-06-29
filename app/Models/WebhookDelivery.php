<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'status_code',
        'response_body',
        'response_time_ms',
        'attempt_number',
        'status',
        'error_message',
        'delivered_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && 
               $this->attempt_number < $this->webhook->max_retries &&
               $this->next_retry_at &&
               $this->next_retry_at->isPast();
    }

    public function markAsDelivered(int $statusCode, string $responseBody, int $responseTime): void
    {
        $this->update([
            'status' => 'delivered',
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_time_ms' => $responseTime,
            'delivered_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage, ?int $statusCode = null, ?string $responseBody = null): void
    {
        $nextRetryAt = null;
        
        if ($this->attempt_number < $this->webhook->max_retries) {
            // Exponential backoff: 1min, 5min, 30min
            $delayMinutes = pow(5, $this->attempt_number);
            $nextRetryAt = now()->addMinutes($delayMinutes);
        }

        $this->update([
            'status' => 'failed',
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'next_retry_at' => $nextRetryAt,
        ]);
    }

    public function scheduleRetry(): void
    {
        $this->increment('attempt_number');
        
        if ($this->attempt_number <= $this->webhook->max_retries) {
            $delayMinutes = pow(5, $this->attempt_number - 1);
            $this->update([
                'status' => 'pending',
                'next_retry_at' => now()->addMinutes($delayMinutes),
            ]);
        }
    }
}