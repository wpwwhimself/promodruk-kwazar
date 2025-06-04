<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class OfferTest extends DuskTestCase
{
    public function testShouldSeeOfferList(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(1)
                ->visitRoute("offers.list")
                ->assertSee("Lista ofert")
                // offer list
                ->with("table", fn ($table) => $table
                    ->assertSee("Edytuj")
                    ->assertSee("Pobierz")
                )
            ;
        });
    }

    public function testShouldInitANewOffer(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(1)
                ->visitRoute("offers.offer")
                ->assertSee("Konfiguracja")
                //todo wyklikać wybieranie produktu
            ;
        });
    }

    public function test_should_see_missing_products_on_old_offer(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(1)
                ->visitRoute("offers.offer", ["id" => 47])
                ->assertSee("USUNIĘTY")
            ;
        });
    }

    public function test_should_see_stocks_on_offer(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(1)
                ->visitRoute("offers.offer", ["id" => 46])
                ->assertSee("📦")
            ;
        });
    }
}
