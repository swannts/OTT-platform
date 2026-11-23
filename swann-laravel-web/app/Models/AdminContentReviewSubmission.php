<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminContentReviewSubmission extends Model
{
    protected $table = 'admin_content_review_submissions';

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
        'submitted_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
