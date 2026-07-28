<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    use HasFactory;

    protected $table = 'user_notification_settings';

    protected $fillable = [
        'notification_key',
        'title',
        'description',
        'app_enabled',
        'mail_enabled',
    ];

    protected $casts = [
        'app_enabled' => 'boolean',
        'mail_enabled' => 'boolean',
    ];

}
