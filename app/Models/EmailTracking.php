<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

final class EmailTracking extends Model
{
    protected $fillable = [
        'message_id',
        'user_email',
        'user_id',
        'event_type',
        'event_data',
        'occurred_at',
        'ip_address',
        'user_agent',
        'country',
        'city',
        'device_type',
        'campaign_id',
        'email_type',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the user associated with this tracking event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_email', 'email');
    }

    /**
     * Scope for specific event types
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for specific campaigns
     */
    public function scopeCampaign($query, string $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted event type
     */
    protected function eventTypeFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->event_type) {
                'delivered' => 'Delivered',
                'opened' => 'Opened',
                'clicked' => 'Clicked',
                'bounced' => 'Bounced',
                'complained' => 'Complained',
                'unsubscribed' => 'Unsubscribed',
                default => ucfirst($this->event_type)
            }
        );
    }

    /**
     * Get event icon
     */
    protected function eventIcon(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->event_type) {
                'delivered' => '✅',
                'opened' => '👀',
                'clicked' => '🖱️',
                'bounced' => '↩️',
                'complained' => '🚫',
                'unsubscribed' => '❌',
                default => '📧'
            }
        );
    }

    /**
     * Get event color class
     */
    protected function eventColorClass(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->event_type) {
                'delivered' => 'text-green-600',
                'opened' => 'text-blue-600',
                'clicked' => 'text-purple-600',
                'bounced' => 'text-orange-600',
                'complained' => 'text-red-600',
                'unsubscribed' => 'text-red-500',
                default => 'text-gray-600'
            }
        );
    }

    /**
     * Check if this is a positive engagement event
     */
    protected function isPositiveEvent(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->event_type, ['delivered', 'opened', 'clicked'])
        );
    }

    /**
     * Check if this is a negative event
     */
    protected function isNegativeEvent(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->event_type, ['bounced', 'complained', 'unsubscribed'])
        );
    }

    /**
     * Get analytics summary for a date range
     */
    public static function getAnalyticsSummary($startDate = null, $endDate = null): array
    {
        $query = static::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('occurred_at', [$startDate, $endDate]);
        }

        $events = $query->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $total = array_sum($events);
        
        return [
            'total_events' => $total,
            'delivered' => $events['delivered'] ?? 0,
            'opened' => $events['opened'] ?? 0,
            'clicked' => $events['clicked'] ?? 0,
            'bounced' => $events['bounced'] ?? 0,
            'complained' => $events['complained'] ?? 0,
            'unsubscribed' => $events['unsubscribed'] ?? 0,
            'open_rate' => $total > 0 ? round((($events['opened'] ?? 0) / $total) * 100, 2) : 0,
            'click_rate' => $total > 0 ? round((($events['clicked'] ?? 0) / $total) * 100, 2) : 0,
            'bounce_rate' => $total > 0 ? round((($events['bounced'] ?? 0) / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get user engagement score
     */
    public static function getUserEngagementScore(string $userEmail): float
    {
        $events = static::where('user_email', $userEmail)->get();
        
        if ($events->isEmpty()) {
            return 0.0;
        }

        $score = 0;
        foreach ($events as $event) {
            $score += match ($event->event_type) {
                'opened' => 1,
                'clicked' => 3,
                'bounced' => -2,
                'complained' => -5,
                'unsubscribed' => -3,
                default => 0
            };
        }

        return max(0, min(100, $score));
    }
}
