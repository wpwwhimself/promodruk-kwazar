<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Wildside\Userstamps\Userstamps;

class Offer extends Model
{
    use HasFactory, Userstamps;

    public const FILE_QUEUE_LIMIT = 20;

    protected $fillable = [
        "name", "notes",
        "unit_cost_visible", "gross_prices_visible",
        "positions",
    ];

    protected $casts = [
        "positions" => "array",
        "unit_cost_visible" => "boolean",
        "gross_prices_visible" => "boolean",
    ];

    #region relations
    public function files()
    {
        return $this->hasMany(OfferFile::class);
    }
}
