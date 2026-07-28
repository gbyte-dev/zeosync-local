<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'notification_key',
        'title',
        'description',
        'email_enabled',
        'in_app_enabled',
    ];
}
