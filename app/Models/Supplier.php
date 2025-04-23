<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "allowed_discounts",
    ];

    protected $casts = [
        "allowed_discounts" => "array",
    ];

    public const ALLOWED_DISCOUNTS = [
        "Rabat na produkty" => "products_discount",
        "Rabat na znakowania" => "markings_discount",
        "Rabat na usługi dodatkowe" => "additional_services_discount",
    ];
}
