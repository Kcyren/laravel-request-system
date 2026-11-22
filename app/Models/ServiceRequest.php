<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $table = 'requests';

    /**
     * Only the controller's explicit, validated allowlist is ever passed to
     * create()/update(); user_id and status are always set by the server.
     */
    protected $fillable = [
        'user_id',
        'requester_name',
        'requester_email',
        'item_name',
        'quantity',
        'purpose',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
