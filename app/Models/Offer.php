<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        "creator_user_id",
        "receiver_data",
        "positions",
        "notes",
    ];

    protected function casts(): array
    {
        return [
            "receiver_data" => "array",
            "positions" => "array",
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, "creator_user_id");
    }
}
