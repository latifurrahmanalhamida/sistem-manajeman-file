<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model {
    protected $fillable = ['frequency', 'time', 'day_of_week', 'day_of_month'];
}
    