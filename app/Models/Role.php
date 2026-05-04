<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name'];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_role')->withTimestamps();
    }
}
