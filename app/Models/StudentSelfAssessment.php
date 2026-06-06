<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSelfAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'understanding_level', 'skills', 'people_groups', 'lessons',
        'tech_overall', 'dependency', 'problems', 'problems_other',
        'top_problem', 'student_role', 'total_people', 'success_rate', 'without_help',
    ];

    protected $casts = [
        'skills'        => 'array',
        'people_groups' => 'array',
        'problems'      => 'array',
        'total_people'  => 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
