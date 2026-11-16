<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the storefront contact page (/lien-he). */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'subject', 'message', 'page_url', 'ip', 'user_agent', 'status'];
}
