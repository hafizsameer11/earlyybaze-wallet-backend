<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use SerializesDatesInAppTimezone;
}
