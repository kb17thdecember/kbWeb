<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectReport extends Model
{
    protected $fillable = [
        'project_name',
        'project_code',
        'project_area',
        'project_location',
        'developer',
        'me_consultant',
        'me_contractor',
        'progress',
        'estimate_value',
        'contact_person',
        'project_update',
        'project_status',
    ];
}
