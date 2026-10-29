<?php

namespace Database\Seeders;

use App\Models\About;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Sample catalogue built from the studio artwork in storage/premium.
 *
 * The originals are 1254px PNGs of ~1.9MB each (78MB for the set) - far too
 * heavy to ship to a phone. This seeder derives web sized JPEGs from them:
 * 1000px squares for products, 1600x640 crops for home banners.
 *
 * Everything it writes is an ordinary Category / Product / Banner / Offer row,
 * so each one can be edited or deleted from the admin panel like any other
 * record. "Clear all data" does NOT bring them back - that only reruns the
 * chart of accounts and master data seeders.
 *
 *   php artisan db:seed --class=CatalogueDemoSeeder
 */
class CatalogueDemoSeeder extends Seeder
{
    /** Artwork source, outside the public disk so the originals are never served. */
    protected const SOURCE = 'premium';

    protected const PRODUCT_MAX = 1000;

    protected const BANNER_W = 1600;

    protected const BANNER_H = 640;

    protected const QUALITY = 82;

    /** slug => [name, category, weight in grams, price, description] */
    protected const PRODUCTS = [
        'antiquekada' => ['Antique Temple Kada', 'Bangles', 42.500, 342000, 'A broad 22K kada in an antique matte finish, its surface carved end to end with temple motifs and closed with a hinged clasp for an easy fit.'],
        'banglearch' => ['Arched Gold Bangle Set', 'Bangles', 38.200, 308000, 'A pair of 22K bangles whose arched profile catches light from every angle, polished to a mirror shine and light enough for all day wear.'],
        'black-silk' => ['Mango Kerala Necklace', 'Necklaces', 34.600, 279000, 'The classic Kerala manga pattern in 22K yellow gold, each mango cluster hand finished and edged with gold beads that move with you.'],
        'bridalcrown' => ['Bridal Nethi Chutti Crown', 'Bridal Sets', 26.400, 214000, 'A bridal headpiece in antique gold with a temple crown centre and fine chain drapes, made to sit comfortably through a full ceremony.'],
        'bridalheirloom' => ['Bridal Heirloom Haram', 'Bridal Sets', 58.900, 474000, 'A full length haram in antique 22K gold with layered temple panels and pearl drops, the kind of piece that gets handed down a generation at a time.'],
        'chandbali' => ['Chandbali Jhumka', 'Earrings', 14.300, 116000, 'Crescent chandbali earrings in 22K gold with a domed jhumka drop and a row of gold beads along the rim.'],
        'coinbangles' => ['Lakshmi Coin Bangles', 'Bangles', 40.100, 323000, 'Antique finish bangles ringed with Lakshmi kasu coins, a Kerala wedding staple that pairs as easily with a saree as with a set mundu.'],
        'crimson-bridal' => ['Crimson Bridal Choker Set', 'Bridal Sets', 52.700, 425000, 'A temple choker with matching jhumkas in antique gold, set with red and green stones and finished with emerald bead and pearl drops.'],
        'diamondhalo' => ['Diamond Halo Solitaire Ring', 'Rings', 4.100, 98000, 'A brilliant cut solitaire framed by a halo of pave diamonds, set in 18K gold with diamond shoulders running down the band.'],
        'diamondline' => ['Diamond Line Bracelet', 'Bracelets', 11.800, 265000, 'A single unbroken line of matched round diamonds in an 18K gold channel setting, with a concealed box clasp and safety catch.'],
        'diamondstuds' => ['Diamond Solitaire Studs', 'Earrings', 3.600, 74000, 'A matched pair of brilliant cut solitaires in a four prong 18K setting, the everyday studs that go with absolutely everything.'],
        'emeraldchoker' => ['Emerald Temple Choker', 'Necklaces', 46.300, 389000, 'A close fitting temple choker in antique gold, studded with emeralds and rubies and hung with a row of gold bead drops.'],
        'emeraldrops' => ['Emerald Drop Earrings', 'Earrings', 12.900, 118000, 'Carved emerald drops suspended from a gold and stone studded top, light on the ear despite their size.'],
        'filigree' => ['Filigree Bridal Necklace', 'Necklaces', 44.800, 361000, 'Fine gold wirework built up into a broad bib necklace, the filigree so dense it reads as lace until you look closely.'],
        'goldchains' => ['Classic Gold Chain', 'Chains', 16.500, 133000, 'A 22K yellow gold chain in a timeless link, solid enough for daily wear and sized to carry a pendant without sagging.'],
        'goldspheres' => ['Gold Sphere Mala', 'Necklaces', 48.200, 388000, 'Hollow filigree spheres strung into a graduated mala, closing on a temple jhumka pendant that swings as you move.'],
        'goldweave' => ['Woven Gold Bracelet', 'Bracelets', 18.700, 151000, 'A flexible 22K bracelet woven from fine gold strands, supple as fabric with a hidden clasp that disappears into the weave.'],
        'jasmine' => ['Jasmine Lakshmi Choker Set', 'Bridal Sets', 50.400, 406000, 'A Lakshmi temple choker with matching jhumkas in antique gold, trimmed with rubies and finished with a full row of pearl drops.'],
        'kasumala' => ['Kasu Mala', 'Necklaces', 45.600, 367000, 'The traditional Kerala coin mala, every kasu struck with the Lakshmi motif and strung on a 22K gold chain.'],
        'kundan' => ['Kundan Bridal Set', 'Bridal Sets', 54.100, 436000, 'Uncut kundan stones set in pure gold foil, backed with meenakari enamel and finished with pearl and bead drops.'],
        'lotus' => ['Lotus Bloom Necklace', 'Necklaces', 36.900, 297000, 'Lotus blooms worked in 22K gold open across the collarbone, each petal separately shaped and set with a stone centre.'],
        'lotusbangles' => ['Lotus Motif Bangles', 'Bangles', 37.400, 301000, 'A pair of 22K bangles carved with a repeating lotus motif, antique finished so the depth of the carving reads clearly.'],
        'lotuspendant' => ['Lotus Pendant', 'Pendants', 8.200, 67000, 'A single lotus in 22K gold with a stone set centre, sized to sit well on a thin chain or a heavier mala.'],
        'mango' => ['Manga Mala', 'Necklaces', 41.300, 333000, 'A long manga mala in antique gold, the mango motifs graduating in size towards a temple pendant at the centre.'],
        'midnightflora' => ['Midnight Flora Ruby Necklace', 'Necklaces', 43.700, 352000, 'Gold florals with ruby centres scattered across a broad collar, edged with leaf motifs and gold bead drops.'],
        'navaratna' => ['Navaratna Ring', 'Rings', 6.800, 58000, 'All nine auspicious stones set around a central ruby in 22K gold, the arrangement laid out the traditional way.'],
        'palakka' => ['Palakka Mala', 'Necklaces', 32.500, 262000, 'The Kerala palakka pattern, green stones in leaf shaped gold settings strung between fine gold links.'],
        'peacock' => ['Peacock Temple Necklace', 'Necklaces', 47.100, 379000, 'Facing peacocks in antique gold flank a Lakshmi centrepiece, with ruby accents and a full skirt of pearl drops.'],
        'pearldrizzle' => ['Pearl Drizzle Necklace', 'Necklaces', 29.800, 241000, 'Layered strands of matched pearls caught in gold stations, with a stone set gold clasp worn to the front or the back.'],
        'rosegold' => ['Rose Gold Couple Rings', 'Rings', 9.400, 132000, 'A rose gold pair: a diamond solitaire with pave shoulders, and a textured comfort fit band to match it.'],
        'royalbangles' => ['Royal Antique Bangles', 'Bangles', 44.600, 359000, 'Heavy antique finish bangles with raised temple work and stone set borders, made to be worn as a stack of two.'],
        'rubybracelet' => ['Ruby Studded Bracelet', 'Bracelets', 19.300, 167000, 'Rubies set in a row of antique gold links, flexible across the wrist and closed with a box clasp.'],
        'rubychoker' => ['Ruby Temple Choker', 'Necklaces', 45.200, 364000, 'A broad temple choker in antique gold, closely set with rubies and finished with gold bead and pearl drops.'],
        'rubyjhumka' => ['Ruby Jhumka', 'Earrings', 15.600, 126000, 'Domed jhumkas in antique gold, the bell set with rubies and rimmed with pearls that catch the light as they swing.'],
        'rubyring' => ['Ruby Statement Ring', 'Rings', 5.900, 52000, 'A cabochon ruby held in a carved 22K gold setting, the shoulders worked with fine granulation.'],
        'sapphire' => ['Blue Sapphire Ring', 'Rings', 5.400, 61000, 'A blue sapphire in an 18K gold setting with diamond accents running down each shoulder.'],
        'spiral' => ['Spiral Twist Earrings', 'Earrings', 10.700, 87000, 'A contemporary spiral in brushed and polished 22K gold, the two finishes twisting around each other down the drop.'],
        'swan' => ['Annam Temple Necklace', 'Necklaces', 49.500, 399000, 'Facing annam swans in antique gold around a Lakshmi centre, edged with rubies and a full row of pearls.'],
        'temple-chest' => ['Lakshmi Temple Haram', 'Necklaces', 62.300, 502000, 'A long temple haram in antique 22K gold, panel after panel of Lakshmi work closing on a large stone set pendant.'],
        'templebells' => ['Temple Bell Jhumka', 'Earrings', 16.800, 136000, 'Temple bell jhumkas in antique gold, the bell carved with deity motifs and finished with a gold bead fringe.'],
        'templechoker' => ['Temple Choker', 'Necklaces', 42.900, 346000, 'A classic antique gold temple choker, sitting high on the neck with stone set panels and gold bead drops.'],
        'templependant' => ['Lakshmi Temple Pendant', 'Pendants', 9.600, 78000, 'A Lakshmi pendant in antique gold with ruby accents, weighty enough to anchor a mala on its own.'],
        'weddingbands' => ['Wedding Band Pair', 'Rings', 12.200, 99000, 'A matched pair of 22K comfort fit wedding bands, one plain polished and one lightly textured.'],
    ];

    /**
     * Home banners, cropped out of the same artwork as the product of the same
     * name. Focus says where the subject sits vertically (0 = top, 1 = bottom);
     * the crop band is centred on it, which is what keeps the necklace in frame
     * instead of slicing it in half.
     *
     * slug => [title, focus]
     */
    protected const BANNERS = [
        'black-silk' => ['Kerala Bridal Collection', 0.45],
        'midnightflora' => ['Ruby and Gold Festive Edit', 0.45],
        'goldspheres' => ['Gold Savings Scheme', 0.52],
        'temple-chest' => ['Temple Jewellery Heirlooms', 0.62],
        'spiral' => ['New Arrivals', 0.50],
    ];

    public function run(): void
    {
        Storage::disk('public')->makeDirectory('products');
        Storage::disk('public')->makeDirectory('banners');

        $categories = $this->categories();
        $made = $this->products($categories);
        $this->banners();
        $this->about();

        $this->command?->info("Catalogue seeded: {$made} products, ".count(self::BANNERS).' banners.');
    }

    /** @return array<string,int> name => id */
    protected function categories(): array
    {
        $names = ['Rings', 'Necklaces', 'Bangles', 'Earrings', 'Chains', 'Bracelets', 'Pendants', 'Bridal Sets'];
        $ids = [];

        foreach ($names as $i => $name) {
            $ids[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['sortorder' => $i + 1, 'status' => true]
            )->id;
        }

        return $ids;
    }

    protected function products(array $categories): int
    {
        $i = 0;

        foreach (self::PRODUCTS as $slug => [$name, $category, $weight, $price, $description]) {
            $i++;
            $path = 'products/'.$slug.'.jpg';

            $this->square($slug, $path);

            $product = Product::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$category] ?? null,
                    'code' => 'PRD'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'description' => $description,
                    'image' => $path,
                    'weight' => $weight,
                    // Making charge is quoted separately in the app; 12% of value
                    // is a sane default the admin can edit per product.
                    'making_charge' => round($price * 0.12, 2),
                    'price' => $price,
                    'status' => true,
                ]
            );

            if ($slug === 'crimson-bridal') {
                Offer::updateOrCreate(
                    ['offer_code' => 'FESTIVE10'],
                    [
                        'product_id' => $product->id,
                        'title' => 'Festive Offer - 10% off making charges',
                        'description' => 'Valid on all 22K ornaments this season.',
                        'discount' => 10,
                        'discount_type' => 'PERCENT',
                        'start_date' => now()->toDateString(),
                        'end_date' => now()->addMonths(3)->toDateString(),
                        'image' => $path,
                        'status' => true,
                    ]
                );
            }
        }

        return $i;
    }

    protected function banners(): void
    {
        $i = 0;

        foreach (self::BANNERS as $slug => [$title, $focus]) {
            $i++;
            $path = 'banners/'.$slug.'.jpg';

            $this->wide($slug, $path, $focus);

            Banner::updateOrCreate(
                ['title' => $title],
                ['image' => $path, 'sortorder' => $i, 'status' => true]
            );
        }
    }

    protected function about(): void
    {
        About::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'About Us',
                'description' => 'We have been crafting trust in gold for over two decades. '
                    .'Our savings schemes let you build towards the jewellery you love, one '
                    .'affordable installment at a time, with fully transparent accounting and '
                    .'a passbook you can check any time from this app.',
            ]
        );
    }

    /* =====================================================================
     |  Artwork
     |
     |  None of this is required at seed time: a derived file already on the
     |  public disk is left alone, and if the originals were not deployed
     |  (78MB, so usually they are not) the row still gets its image path and
     |  the picture can be uploaded from the admin panel instead.
     ===================================================================== */

    /** Product tile: fit inside PRODUCT_MAX, keeping the aspect ratio. */
    protected function square(string $slug, string $path): void
    {
        $this->derive($slug, $path, function (\GdImage $src, int $w, int $h) {
            $scale = min(self::PRODUCT_MAX / $w, self::PRODUCT_MAX / $h, 1);
            $dw = max(1, (int) round($w * $scale));
            $dh = max(1, (int) round($h * $scale));

            $out = imagecreatetruecolor($dw, $dh);
            imagecopyresampled($out, $src, 0, 0, 0, 0, $dw, $dh, $w, $h);

            return $out;
        });
    }

    /** Banner: crop a BANNER_W:BANNER_H band centred on $focus, then scale it. */
    protected function wide(string $slug, string $path, float $focus): void
    {
        $this->derive($slug, $path, function (\GdImage $src, int $w, int $h) use ($focus) {
            // A source taller than the band is the normal case; clamp so the
            // crop can never run past either edge.
            $band = min((int) round($w * self::BANNER_H / self::BANNER_W), $h);
            $top = (int) round($h * $focus - $band / 2);
            $top = max(0, min($top, $h - $band));

            $out = imagecreatetruecolor(self::BANNER_W, self::BANNER_H);
            imagecopyresampled($out, $src, 0, 0, 0, $top, self::BANNER_W, self::BANNER_H, $w, $band);

            return $out;
        });
    }

    /** @param  callable(\GdImage,int,int):\GdImage  $transform */
    protected function derive(string $slug, string $path, callable $transform): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $source = storage_path(self::SOURCE.'/'.$slug.'.png');

        if (! is_file($source) || ! function_exists('imagecreatefrompng')) {
            return;
        }

        $src = @imagecreatefrompng($source);

        if (! $src) {
            return;
        }

        $out = $transform($src, imagesx($src), imagesy($src));
        imagedestroy($src);

        ob_start();
        imagejpeg($out, null, self::QUALITY);
        $disk->put($path, (string) ob_get_clean());
        imagedestroy($out);
    }
}
