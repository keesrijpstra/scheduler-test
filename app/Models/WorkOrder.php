<?php

namespace App\Models;

use App\Enums\StatusType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Enum;

class WorkOrder extends Model
{

    protected $casts = [
        'status' => StatusType::class,
    ];

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class, 'workorder_id');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'work_order_user');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
