<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity.
     *
     * @param string $action    created|updated|deleted
     * @param string $entityType auction|user|moderator|bid
     * @param int|null $entityId
     * @param string|null $entityName Human-readable name
     * @param array|null $changes  { "field": { "old": ..., "new": ... } }
     * @param array|null $metadata Extra context
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        ?array $changes = null,
        ?array $metadata = null,
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'changes' => $changes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Compute diff between old model attributes and new validated data.
     * Returns only changed fields in format { "field": { "old": ..., "new": ... } }
     */
    public static function computeChanges(Model $model, array $newData): ?array
    {
        $changes = [];
        $original = $model->getOriginal();

        foreach ($newData as $key => $newValue) {
            if (!array_key_exists($key, $original)) {
                continue;
            }

            $oldValue = $original[$key];
            
            // Handle Date Comparisons
             $casts = $model->getCasts();
             $isDate = isset($casts[$key]) && in_array($casts[$key], ['date', 'datetime', 'immutable_date', 'immutable_datetime']);

             if ($isDate && $oldValue && $newValue) {
                 try {
                     $d1 = \Carbon\Carbon::parse($oldValue);
                     $d2 = \Carbon\Carbon::parse($newValue);
                     if ($d1->equalTo($d2)) {
                         continue;
                     }
                     // Normalize values for consistent logging
                     $oldValue = $d1->toDateTimeString();
                     $newValue = $d2->toDateTimeString();
                 } catch (\Throwable $e) {
                     // Fallback to strict comparison if parsing fails
                 }
             }

            // Normalize for comparison
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }
}
