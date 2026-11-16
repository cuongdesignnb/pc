<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the storefront contact page (/lien-he). */
class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = ['name', 'phone', 'email', 'subject', 'message', 'page_url', 'ip', 'user_agent', 'status'];
}
