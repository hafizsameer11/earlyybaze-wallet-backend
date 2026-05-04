<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenanceService extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name', 'status'];
}
