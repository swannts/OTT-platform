<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingDatasetManifest extends Model
{
    protected $fillable = [
        'dataset_id', 'profile', 'seed', 'reference_date', 'status', 'configuration',
        'record_counts', 'failure_summary', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'record_counts' => 'array',
            'reference_date' => 'date',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
