<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReply extends Model
{
    protected $fillable = ['contact_id', 'message'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
