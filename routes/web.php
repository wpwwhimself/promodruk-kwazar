<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect("/", "/dashboard");

Route::controller(AuthController::class)->prefix("auth")->group(function () {
    Route::get("/login", "input")->name("login");
    Route::post("/login", "authenticate")->name("authenticate");
    Route::post("/change-password", "changePassword")->name("change-password");
    Route::middleware("auth")->get("/logout", "logout")->name("logout");
});

Route::middleware("auth")->group(function () {
    Route::get("/dashboard", function () {
        return view("pages.dashboard");
    })->name("dashboard");

    Route::controller(OfferController::class)->prefix("offers")->group(function () {
        Route::get("/", "list")->name("offers.list");
        Route::get("/show/{offer_id}", "show")->name("offers.show");
        Route::get("/edit/{offer_id?}", "edit")->name("offers.edit");
        Route::post("/process/", "update")->name("offers.process");
    });
});
