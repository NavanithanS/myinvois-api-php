<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'max_retries',
        'timeout_seconds',
        'last_triggered_at',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    public function getSuccessRate(): float
    {
        $total = $this->success_count + $this->failure_count;
        
        if ($total === 0) {
            return 0;
        }

        return ($this->success_count / $total) * 100;
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
    }

    public function getRecentDeliveries(int $limit = 10)
    {
        return $this->deliveries()
            ->latest()
            ->limit($limit)
            ->get();
    }
}