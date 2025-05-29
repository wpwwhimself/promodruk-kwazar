<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Comment;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Language;

class DocumentOutputController extends Controller
{
    private const MM_TO_TWIP = 56.6929133858;
    public const FORMATS = [
        "pdf" => [
            "writer" => "PDF",
            "font" => "DejaVu Sans",
        ],
        "docx" => [
            "writer" => "Word2007",
            "font" => "Calibri",
        ],
    ];
    private const TECHNIQUES_TO_SIMPLIFY = [
        "tampodruk",
        "tampon",
        "sitodruk",
    ];
    public const VAT_COEF = 1.23;

    public function processOffer(string $format, int $offer_id)
    {
        $offer = Offer::find($offer_id);

        $prepared_file = $offer->files?->firstWhere("type", $format);
        if ($prepared_file) {
            return response()->download(storage_path("app/public/".$prepared_file->file_path));
        }

        if (count($offer->positions) > $offer::FILE_QUEUE_LIMIT) {
            OfferFile::updateOrCreate(
                [
                    "offer_id" => $offer_id,
                    "type" => $format,
                ],
                [
                    "file_path" => null,
                ],
            );
            return back()->with("success", "Plik dodany do kolejki przetwarzania");
        }

        $this->downloadOffer($format, $offer_id);
    }

    public function processedOffers()
    {
        $files = OfferFile::orderByDesc("created_at")->paginate(25);

        return view("pages.offers.processed", compact("files"));
    }

    public function processedOffersDelete(?OfferFile $file = null)
    {
        if ($file) {
            if ($file->file_path)
                Storage::disk("public")->delete($file->file_path);
            $file->delete();
        } else {
            Storage::disk("public")->deleteDirectory("offers");
            OfferFile::truncate();
        }

        return back()->with("success", $file ? "Plik usunięty" : "Pliki usunięte");
    }

    #region creating document
    public function downloadOffer(string $format, int $offer_id, bool $save = false)
    {
        $offer = Offer::find($offer_id);

        $document = new PhpWord();
        $document->setDefaultFontName(self::FORMATS[$format]["font"]);
        $document->setDefaultFontSize(11);
        $document->getSettings()->setThemeFontLang(new Language("pl-PL"));

        $section = $document->addSection([
            "paperSize" => "A4",
            "marginLeft" => 15 * self::MM_TO_TWIP,
            "marginRight" => 15 * self::MM_TO_TWIP,
            "marginTop" => 15 * self::MM_TO_TWIP,
            "marginBottom" => 15 * self::MM_TO_TWIP,
        ]);

        $product_colors = Http::acceptJson()
            ->post(env("MAGAZYN_API_URL") . "products/colors", [
                "families" => array_map(
                    fn ($p) => $p["product_family_id"],
                    $offer->positions
                )
            ])
            ->collect("colors");

        $positions = collect($offer->positions)
            ->filter(fn ($pos) => !($pos["missing"] ?? false));

        foreach ($positions as $position) {
            $line = $section->addTextRun($this->style(["h_separated"]));
            $line->addText(htmlspecialchars($position["name"])."  (".($position["variant_name"] ?? $position["original_color_name"]).") ", $this->style(["h2"]));
            $line->addText($position["id"], $this->style(["ghost", "bold"]));

            $line = $section->addTextRun();
            $line->addText("Opis: ", $this->style(["bold"]));
            $line->addText(Str::words(htmlspecialchars($position["description"]), 12 * 3, "..."));

            $section->addText("Dostępne kolory:", $this->style(["bold"]), $this->style(["p_tight"]));
            $line = $section->addTextRun();
            collect($product_colors[$position["product_family_id"]])
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

            if ($position["show_ofertownik_link"] ?? false) {
                $line = $section->addTextRun();
                $line->addText("Szczegóły/więcej zdjęć: ", $this->style(["bold"]));
                $line->addLink(env("OFERTOWNIK_URL") . "produkty/$position[id]", "kliknij tutaj", $this->style(["link"]));
            }

            if (!request("no_product_thumbnails")) {
                $line = $section->addTextRun();
                collect($position["thumbnail_urls"])
                    ->transform(fn ($url, $i) => $url ?? $position["image_urls"][$i])
                    ->take(3)
                    ->each(function ($url) use ($line) {
                        try {
                            $img = Http::get($url)->body();
                            $dimensions = getimagesizefromstring($img);
                            $line->addImage($img, $this->style([
                                ($dimensions[0] < $dimensions[1]) ? "img_by_height" : "img"
                            ]));
                        } catch (\Exception $e) {
                            // skip
                        }
                    });
            }

            foreach ($position["calculations"] as $i => $calculation) {
                $section->addText(
                    (count($position["calculations"]) > 1
                        ? "Kalkulacja ".($i + 1)
                        : "Kalkulacja"
                    )
                    . (count($calculation["items"]) == 0
                        ? " – ceny BEZ ZNAKOWANIA"
                        : ""
                    ),
                    $this->style(["h2"]),
                    $this->style(["h_separated"])
                );

                if (array_reduce($calculation["items"], fn ($has_markings, $item) => $has_markings = $has_markings || !empty($item["marking"]), false)) {
                    $section->addText("Znakowanie:", $this->style(["bold"]), $this->style(["h_separated"]));
                    $table = $section->addTable($this->style(["table"]));
                    foreach ($calculation["items"] as $item_i => ["code" => $code, "marking" => $marking]) {
                        if ($item_i % 3 == 0)
                            $table->addRow();

                        $cell = $table->addCell(null, $this->style(["table_cell"]));
                        if ($marking["position"]) {
                            $cell->addText("$marking[position]:", $this->style(["underline"]), $this->style(["p_tight"]));
                        }
                        $technique_line = $cell->addTextRun($this->style(["p_tight"]));
                        $technique_name = $technique_line->addText($this->simplifyTechniqueName($marking["technique"]));
                        // if (Str::contains($marking["technique"], self::TECHNIQUES_TO_SIMPLIFY, true)) {
                        //     // if name was simplified, keep original name in the comment
                        //     $comment = new Comment(env("APP_COMPANY_NAME"));
                        //     $comment->addText($marking["technique"]);
                        //     $document->addComment($comment);
                        //     $technique_name->setCommentRangeStart($comment);
                        // }
                        if (Str::contains($code, "_")) { // modifier active, retrieving name
                            $technique_line->addText(" – " . Str::afterLast($code, "_"));
                        }
                        if ($marking["print_size"]) {
                            $cell->addText("Maks. obszar znak.: " . $marking["print_size"], $this->style(["ghost", "small"]));
                        }
                        if ($marking["images"] && $marking["images"][0]) {
                            try {
                                $img = Http::get($marking["images"][0])->body();
                                $dimensions = getimagesizefromstring($img);
                                $cell->addImage($img, $this->style([
                                    ($dimensions[0] < $dimensions[1]) ? "img_by_height" : "img",
                                    "h_separated",
                                ]));
                            } catch (\Exception $e) {
                                // skip
                            }
                        }
                    }
                }

                $section->addText("Cena netto".($offer->gross_prices_visible ? " (brutto)" : "").":", $this->style(["bold"]), $this->style(["p_tight", "h_separated"]));
                foreach ($calculation["summary"] as $qty => $sum) {
                    $list = $section->addListItemRun(0, null, $this->style(["p_tight"]));
                    $list->addText("$qty szt.: " . as_pln($sum) . ($offer->gross_prices_visible ? " (" . as_pln($sum * self::VAT_COEF) . ")" : ""));
                    if ($offer->unit_cost_visible) {
                        $list->addText(" "
                            . ($offer->gross_prices_visible
                                ? as_pln($sum / $qty) . "/szt. (".as_pln($sum / $qty * self::VAT_COEF)."/szt.)"
                                : "(".as_pln($sum / $qty)."/szt.)"
                            ),
                            $this->style(["ghost", "small"])
                        );
                    }
                }

                if ($calculation["additional_services"]) {
                    $section->addText("W cenie zawarte są dodatkowe usługi:", null, $this->style(["p_tight", "h_separated"]));
                    foreach ($calculation["additional_services"] as $service) {
                        $list = $section->addListItemRun(0, null, $this->style(["p_tight"]));
                        $list->addText($service["label"]);
                    }
                }
            }

            $section->addText(" ", null, $this->style(["h_separated"]));
            $section->addLine([
                "weight" => 1,
                "width" => 500,
                "height" => 0,
            ]);
        }

        $filename = Str::slug($offer->name) . "." . $format;
        if ($save && !Storage::disk("public")->exists("offers")) {
            Storage::disk("public")->makeDirectory("offers");
        }

        if ($format == "pdf") {
            Settings::setPdfRendererPath(".");
            Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
        }

        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        IOFactory::createWriter($document, self::FORMATS[$format]["writer"])
            ->save($save
                ? storage_path("app/public/offers/").$filename
                : "php://output"
            );

        if ($save) {
            return "offers/$filename";
        }
    }
    #endregion

    #region helpers
    private function simplifyTechniqueName(string $technique): string
    {
        foreach (self::TECHNIQUES_TO_SIMPLIFY as $word) {
            if (Str::contains($technique, $word, true))
                $technique = Str::replace(
                    $word,
                    "Nadruk-". strtoupper(substr($word, 0, 1)),
                    $technique,
                    false
                );
        }

        return $technique;
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
            "small" => [
                "size" => 10,
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
            "img_by_height" => [
                "height" => 400 / 3,
                "wrappingStyle" => "inline",
            ],
            "hr" => [
                "borderBottomSize" => 6,
                "marginLeft" => 30 * self::MM_TO_TWIP,
                "marginRight" => 30 * self::MM_TO_TWIP,
                "marginTop" => 60 * self::MM_TO_TWIP,
                "marginBottom" => 60 * self::MM_TO_TWIP,
            ],
            "table" => [
            ],
            "table_cell" => [
                "width" => 58 * self::MM_TO_TWIP,
            ],
        ]);

        return $definitions->filter(fn ($s, $name) => in_array($name, $styles))
            ->flatMap(fn ($el) => $el)
            ->toArray();
    }
    #endregion
}
