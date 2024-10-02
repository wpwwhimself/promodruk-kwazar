<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;

class DocumentOutputController extends Controller
{
    private const MM_TO_TWIP = 56.6929133858;

    public function downloadOffer(int $offer_id)
    {
        $offer = Offer::find($offer_id);

        $document = $this->initDocument();
        $section = $document->addSection([
            "paperSize" => "A4",
            "marginLeft" => 15 * self::MM_TO_TWIP,
            "marginRight" => 15 * self::MM_TO_TWIP,
            "marginTop" => 15 * self::MM_TO_TWIP,
            "marginBottom" => 15 * self::MM_TO_TWIP,
        ]);

        foreach ($offer->positions as $position) {
            $line = $section->addTextRun($this->style(["h_separated"]));
            $line->addText("$position[name] ($position[original_color_name]) ", $this->style(["h2"]));
            $line->addText($position["id"], $this->style(["ghost", "bold"]));

            $line = $section->addTextRun();
            $line->addText("Opis: ", $this->style(["bold"]));
            $line->addText(Str::words($position["description"], 25, "..."));

            $section->addText("Dostępne kolory:", $this->style(["bold"]), $this->style(["p_tight"]));
            $line = $section->addTextRun();
            Http::acceptJson()->get(env("MAGAZYN_API_URL") . "products/$position[product_family_id]/1")
                ->collect()
                ->map(fn ($p) => $p["color"])
                ->each(function ($color) use ($line) {
                    $line->addShape("rect", [
                        "roundness" => 0.2,
                        "frame" => [
                            "width" => 15,
                            "height" => 15,
                        ],
                        "fill" => ["color" => $color["color"]],
                    ]);
                    $line->addText(" ");
                });

            $line = $section->addTextRun();
            $line->addText("Szczegóły/więcej zdjęć: ", $this->style(["bold"]));
            $line->addLink(env("OFERTOWNIK_URL") . "produkty/$position[id]", "kliknij tutaj", $this->style(["link"]));

            $line = $section->addTextRun();
            collect($position["image_urls"])->take(3)->each(fn ($url) => $line->addImage($url, $this->style(["img"])));

            foreach ($position["calculations"] as $i => $calculation) {
                $section->addText(
                    count($position["calculations"]) > 1
                        ? "Kalkulacja ".($i + 1)
                        : "Kalkulacja",
                    $this->style(["h3"]),
                    $this->style(["h_separated"])
                );

                $section->addText("Znakowanie:", $this->style(["bold"]), $this->style(["p_tight", "h_separated"]));
                foreach ($calculation["items"] as $item_i => ["marking" => $marking]) {
                    $list = $section->addListItemRun(0, null, $this->style(["p_tight"]));
                    $list->addText("$marking[position]:", $this->style(["underline"]));
                    $list->addText(" $marking[technique]");
                }

                $images = $section->addTextRun();
                foreach ($calculation["items"] as $item_i => ["marking" => $marking]) {
                    foreach ($marking["images"] as $image) {
                        $images->addImage($image, $this->style(["img"]));
                    }
                }

                $section->addText("Cena (netto):", $this->style(["bold"]), $this->style(["p_tight"]));
                foreach ($calculation["summary"] as $qty => $sum) {
                    $list = $section->addListItemRun(0, null, $this->style(["p_tight"]));
                    $list->addText("$qty szt.: " . as_pln($sum));
                }
            }
        }

        $filename = Str::slug($offer->name) . ".docx";

        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        IOFactory::createWriter($document, "Word2007")
            ->save("php://output");
    }

    //////////////////

    private function initDocument()
    {
        $document = new PhpWord();
        $document->setDefaultFontName("Calibri");
        $document->setDefaultFontSize(11);
        $document->getSettings()->setThemeFontLang(new Language("pl-PL"));
        return $document;
    }

    private function style(array $styles): array
    {
        $definitions = collect([
            "h2" => [
                "size" => 16,
                "bold" => true,
            ],
            "h3" => [
                "size" => 13,
                "bold" => true,
            ],
            "h_separated" => [
                "spaceBefore" => 3 * self::MM_TO_TWIP,
            ],
            "p_tight" => [
                "spaceAfter" => 0,
            ],
            "ghost" => [
                "color" => "808080",
            ],
            "bold" => [
                "bold" => true,
            ],
            "underline" => [
                "underline" => "single",
            ],
            "link" => [
                "color" => "0000ff",
                "underline" => "single",
            ],
            "img" => [
                "width" => 500 / 3,
                "wrappingStyle" => "inline",
            ],
        ]);

        return $definitions->filter(fn ($s, $name) => in_array($name, $styles))
            ->flatMap(fn ($el) => $el)
            ->toArray();
    }
}