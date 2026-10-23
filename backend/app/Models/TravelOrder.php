<?php

namespace App\Models;

use App\Enums\TravelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelOrder extends Model
{
    use HasFactory;

    protected $fillable = ['destination', 'departure_date', 'return_date'];

    protected function casts(): array
    {
        return [
            'status' => TravelStatus::class,
            'departure_date' => 'date',
            'return_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
