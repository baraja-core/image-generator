<div align='center'>
  <picture>
    <source media='(prefers-color-scheme: dark)' srcset='https://cdn.brj.app/images/brj-logo/logo-regular.png'>
    <img src='https://cdn.brj.app/images/brj-logo/logo-dark.png' alt='BRJ logo'>
  </picture>
  <br>
  <a href="https://brj.app">BRJ organisation</a>
</div>
<hr>

# Image Generator

Full automatic ImageGenerator for creating dynamic content by URL.

- Easily generate thousands of image types dynamically
- Set dozens of configuration parameters and customize the output
- Mature tools to work comfortably in Latte templates and on the backend
- All generated images are cached and protected by checksum validation

## :bulb: Hlavni principy

- **Dynamicke generovani obrazku** - Obrazky se generuji na zaklade parametru v URL, bez potreby rucniho vytvareni variant
- **Automaticke cachovani** - Vygenerovane obrazky se ukladaji do cache, pri dalsim pozadavku se servuji primo bez zateze PHP
- **Ochrana checksumem** - Kazdy pozadavek obsahuje hash, ktery zabranu neautorizovanemu generovani obrazku
- **Zachovani originalu** - Zdrojovy obrazek zustava nezmeneny, vsechny transformace se provadeji na kopiich
- **Podpora externich URL** - Obrazky z externich domen jsou automaticky stahovany a cachovany lokalne
- **Integrace s Nette Framework** - Nativni podpora Latte maker a DIC extension
- **Optimalizace vystupu** - Automaticka komprese obrazku pomoci jpegoptim a optipng

## :building_construction: Architektura a komponenty

### Hlavni komponenty

| Komponenta | Popis |
|------------|-------|
| `ImageGenerator` | Jadro knihovny pro transformaci obrazku (resize, crop, scale) |
| `Image` | Orchestrator pozadavku - overuje hash, pripravuje cesty, vola generator |
| `ImageGeneratorRoute` | Router pro zachyceni pozadavku na dynamicke obrazky |
| `ImageGeneratorExtension` | DIC extension pro integraci s Nette Framework |
| `Macros` | Latte makra pro pohodlne pouziti v sablonacha |
| `Proxy` | Stahovani a cachovani externich obrazku |
| `SmartCrop` | Inteligentni orezavani obrazku (detekce dulezitych oblasti) |
| `Helper` | Staticke utility funkce (hash, cache invalidace, detekce prostredi) |
| `Config` | Konfiguracni entita (barva pozadi, breakpointy) |
| `Optimizer` | Rozhrani pro optimalizaci obrazku s vychozi implementaci |

### Architektura systemu

```
+------------------+     +-------------------+     +------------------+
|   HTTP Request   |---->| ImageGeneratorRoute|---->|      Image       |
| (URL s parametry)|     | (pattern matching)|     | (orchestrator)   |
+------------------+     +-------------------+     +------------------+
                                                           |
                         +----------------+                |
                         |     Helper     |<---------------+
                         | (hash verify)  |                |
                         +----------------+                v
                                                  +------------------+
+------------------+     +-------------------+    |  ImageGenerator  |
|      Cache       |<----|    Optimizer      |<---|  (transformace)  |
| (www/_cache/)    |     | (jpegoptim/optpng)|    +------------------+
+------------------+     +-------------------+             |
                                                           v
                         +-------------------+    +------------------+
                         |     SmartCrop     |<---|   Nette\Image    |
                         | (inteligentni)    |    |   (GD wrapper)   |
                         +-------------------+    +------------------+
```

### Tok zpracovani pozadavku

```
1. Pozadavek na URL: /images/cat__w200h150_abc123.jpg
                           |
2. ImageGeneratorRoute zachyti pattern a extrahuje:
   - dirname: images
   - basename: cat
   - params: w200h150
   - hash: abc123
   - extension: jpg
                           |
3. Image overuje hash (Helper::generateHash)
   - Pokud nesouhlasi a je debug mode -> redirect na spravnou URL
   - Pokud nesouhlasi a je production -> vrati chybu
                           |
4. Kontrola cache (www/_cache/images/cat__w200h150_abc123.jpg)
   - Existuje -> servuje primo z cache
   - Neexistuje -> pokracuje ke generovani
                           |
5. ImageGenerator provede transformaci:
   - Zkopiruje zdrojovy soubor do temp
   - Aplikuje pozadovane transformace (crop/scale/resize)
   - Optimalizuje vystup
   - Presune do cache
                           |
6. Odpoved klientovi s HTTP hlavickami pro cachovani
```

## :package: Instalace

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/image-generator) and
[GitHub](https://github.com/baraja-core/image-generator).

To install, simply use the command:

```shell
$ composer require baraja-core/image-generator
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Pozadavky

- PHP 8.0+
- PHP extensions: `gd`, `session`, `json`, `fileinfo`, `curl`
- Nette Framework 3.0+

### Registrace extension (Nette)

```neon
extensions:
    imageGenerator: Baraja\ImageGenerator\ImageGeneratorExtension
```

### Konfigurace

```neon
imageGenerator:
    debugMode: false
    defaultBackgroundColor: [255, 255, 255]
    cropPoints:
        480: [910, 30, 1845, 1150]
        600: [875, 95, 1710, 910]
        768: [975, 130, 1743, 660]
        1024: [805, 110, 1829, 850]
        1280: [615, 63, 1895, 800]
        1440: [535, 63, 1975, 800]
        1680: [410, 63, 2090, 800]
        1920: [320, 63, 2240, 800]
        2560: [0, 63, 2560, 800]
```

| Parametr | Typ | Vychozi | Popis |
|----------|-----|---------|-------|
| `debugMode` | bool | false | V debug modu se pri chybnem hashi presmeruje na spravnou URL |
| `defaultBackgroundColor` | array | [255, 255, 255] | RGB barva pozadi pro PNG obrazky s pruhlednosti |
| `cropPoints` | array | prednastavene | Breakpointy pro responsivni orezavani |

## :wrench: Rozsireni pomoci Linux knihoven

Pro pokrocile funkce je mozne nainstalovat na server nasledujici knihovny (volitelne):

- [SmartCrop](https://github.com/jwagner/smartcrop.js/) - Inteligentni orezavani s detekci dulezitych casti obrazku
- [OptiPNG](https://github.com/imagemin/imagemin-optipng) - Optimalizace PNG souboru
- [Jpegoptim](https://github.com/tjko/jpegoptim) - Optimalizace JPEG souboru

```bash
# Ubuntu/Debian
sudo apt-get install optipng jpegoptim

# SmartCrop (vyzaduje Node.js)
sudo npm install -g smartcrop-cli
```

## :rocket: Zakladni pouziti

### Format URL

Vsechny obrazky zpracovane ImageGeneratorem maji nasledujici format:

```
<basePath>/<dir>/<fileName>__<parameters>_<hash>.<format>
```

Priklad:
```
/images/monalisa__w200h128_abc123.jpg
```

Tento pozadavek nacte obrazek `/images/monalisa.jpg` a aplikuje:
- Sirka: 200px
- Vyska: 128px
- Hash: abc123 (overeni checksumu)

### Pouziti v PHP kodu

```php
use Baraja\ImageGenerator\ImageGenerator;

// Zakladni pouziti - resize
$url = ImageGenerator::from('/images/cat.png', ['w' => 200, 'h' => 150]);
// Vysledek: /images/cat__w200h150_abc123.png

// S dalsimi parametry
$url = ImageGenerator::from('/images/photo.jpg', [
    'w' => 800,
    'h' => 600,
    'sc' => 'r',  // scale ratio
    'c' => 'mc',  // crop middle-center
]);

// Externi obrazek (automaticky se stahne a zproxuje)
$url = ImageGenerator::from('https://example.com/image.jpg', ['w' => 300, 'h' => 200]);
```

### Pouziti v Latte sablonach

ImageGenerator obsahuje nativni adapter pro Latte templating system.

#### Makro `{imageGenerator}`

Kompletni vykresleni `<img>` tagu:

```latte
{imageGenerator '/images/cat.png', ['w' => 200, 'h' => 150]}
{* Vysledek: <img src="/images/cat__w200h150_abc123.png" alt="Image"> *}

{* S alternativnim popisem *}
{imageGenerator '/images/cat.png', ['w' => 200, 'h' => 150, 'alt' => 'Kocka']}
{* Vysledek: <img src="/images/cat__w200h150_abc123.png" alt="Kocka"> *}
```

#### Makro `{img}`

Vraci pouze URL adresu:

```latte
<img src="{img '/images/cat.png', ['w' => 200, 'h' => 150]}" alt="Kocka">
```

#### Atribut `n:src`

Pro vlastni logiku vykresleni:

```latte
<img n:src="/images/cat.png, [w => 200, h => 150]" alt="Kocka">
```

## :gear: Parametry transformace

Parametry se zapisuji za nazev souboru za dvojite podtrzitko (`__`) a oddeluji se pomlckou.

### Sirka a vyska (width & height)

Parametry `w` a `h` nastavuji rozmery obrazku v pixelech.

```
monalisa__w200h128_hash.jpg
```

- Minimalni hodnota: 16px
- Maximalni hodnota: 3000px
- Pokud je zadana pouze jedna dimenze, druha se dopocita podle pomeru stran

### Orezavani podle hrany (crop)

Parametr `-c` urcuje, odkud bude obrazek orezan.

```
TL  TC  TR
ML  MC  MR
BL  BC  BR
```

| Hodnota | Pozice |
|---------|--------|
| `tl` | Top-Left (levy horni roh) |
| `tc` | Top-Center (horni stred) |
| `tr` | Top-Right (pravy horni roh) |
| `ml` | Middle-Left (levy stred) |
| `mc` | Middle-Center (stred) |
| `mr` | Middle-Right (pravy stred) |
| `bl` | Bottom-Left (levy dolni roh) |
| `bc` | Bottom-Center (dolni stred) |
| `br` | Bottom-Right (pravy dolni roh) |
| `sm` | Smart Crop (inteligentni orezani) |

Priklad:
```
cat__w200h150-ctl_hash.jpg   # Orez z leveho horniho rohu
cat__w200h150-csm_hash.jpg   # Inteligentni orez
```

### Zpusob zmeny rozmeru (scale)

Parametr `-sc` urcuje, jak se obrazek prizpusobi novym rozmerum.

| Hodnota | Nazev | Popis |
|---------|-------|-------|
| `r` | Ratio | Zachova pomer stran, vetsi strana urcuje hlavni rozmer |
| `c` | Cover | Vyplni co nejvetsi plochu v danem obdelniku podle pomeru stran |
| `a` | Absolute | Obrazek se roztahne/stlaci na presne rozmery (muze zpusobit deformaci) |

Priklad:
```
photo__w800h600-scr_hash.jpg   # Scale ratio
photo__w800h600-scc_hash.jpg   # Scale cover
photo__w800h600-sca_hash.jpg   # Scale absolute
```

### Breakpointy

Parametr `-br` aktivuje orezani podle preddefinovanych breakpointu. Pri pouziti tohoto parametru jsou ostatni ignorovany a breakpoint se urcuje podle sirky (`w`).

```
banner__w1920h800-br_hash.jpg
```

Vychozi breakpointy:

| Breakpoint | Oblast orezani [x1, y1, x2, y2] |
|------------|----------------------------------|
| 480 | [910, 30, 1845, 1150] |
| 600 | [875, 95, 1710, 910] |
| 768 | [975, 130, 1743, 660] |
| 1024 | [805, 110, 1829, 850] |
| 1280 | [615, 63, 1895, 800] |
| 1440 | [535, 63, 1975, 800] |
| 1680 | [410, 63, 2090, 800] |
| 1920 | [320, 63, 2240, 800] |
| 2560 | [0, 63, 2560, 800] |

### Procentualni orezavani

Parametry `-px` a `-py` umoznuji orezani podle procentualniho posunu od okraje.

- `px` - procentualni posun podle osy X (0-100)
- `py` - procentualni posun podle osy Y (0-100)

```
main-page__w1680h800-px75-py0_hash.jpg
```

Obrazek `main-page.jpg` bude orezan na 1680x800 s orezem shora na 75% a zleva na 0%.

### Kombinace parametru

Parametry lze (temer) libovolne kombinovat. Jednotlive parametry se oddeluji pomlckou.

```
/images/photo__w1680h800-px75-py0_hash.jpg
/images/banner__w800h600-scr-cmc_hash.jpg
```

## :arrows_counterclockwise: Konverze formatu

Pokud potrebujete zmenit format obrazku (napr. z PNG na JPG), staci zmenit priponu v URL. Generator automaticky najde zdrojovy soubor a provede konverzi.

```php
// Zdrojovy soubor: /images/logo.png
$url = ImageGenerator::from('/images/logo.jpg', ['w' => 200, 'h' => 100]);
// Vysledek: PNG se prevede na JPG a ulozi do cache
```

Podporovane formaty: `jpg`, `jpeg`, `png`, `gif`, `webp`

## :floppy_disk: Cache

### Umisteni cache

Cache se nachazi v adresari `/www/_cache/` a zachovava stejnou adresarovou strukturu jako zdrojove adresare.

```
www/
├── images/
│   └── cat.png              # Zdrojovy obrazek
└── _cache/
    └── images/
        └── cat__w200h150_abc123.png   # Cachovany obrazek
```

### Deduplikace obrazku

Pokud je vygenerovany obrazek obsahove shodny s jinym jiz existujicim, vytvori se symlink pro usporu diskoveho prostoru.

### Invalidace cache

```php
use Baraja\ImageGenerator\Helper;

// Invalidace konkretniho obrazku
$count = Helper::invalidateCache('/images/cat.png');

// Invalidace celeho adresare
$count = Helper::invalidateCache('/images/');

// Rekurzivni invalidace (vcetne podadresaru)
$count = Helper::invalidateCache('/images/', null, true);

// S explicitnim zadanim www adresare
$count = Helper::invalidateCache('/images/cat.png', '/var/www/html/www');
```

Metoda vraci pocet smazanych souboru.

## :globe_with_meridians: Externi obrazky (Proxy)

ImageGenerator podporuje zpracovani obrazku z externich domen. Obrazky jsou automaticky stazeny, ulozeny lokalne a dale zpracovany.

```php
$url = ImageGenerator::from('https://example.com/photo.jpg', ['w' => 400, 'h' => 300]);
// Obrazek se stahne, ulozi do www/_cache/_proxy/ a vrati se URL pres interni proxy
```

Externi obrazky jsou dostupne pres endpoint `image-generator-proxy/*`.

### Jak proxy funguje

1. Externi URL se zahashuje pomoci MD5
2. Obrazek se stahne a ulozi do `www/_cache/_proxy/{prvni-3-znaky-hash}/{hash}.{format}`
3. Vsechny dalsi pozadavky se servuji z lokalniho uloziste

## :chart_with_upwards_trend: Optimalizace

### Automaticka kvalita

Generator automaticky aplikuje optimalizaci kvality na vsechny vystupni obrazky:

- Obrazky vetsi nez 480 000 pixelu (napr. 800x600): kvalita 85%
- Mensi obrazky: kvalita 95%

### Externi optimalizatory

Pokud jsou k dispozici, pouziji se externi nastroje:

- **jpegoptim** pro JPEG soubory
- **optipng** pro PNG soubory

### Vlastni optimizer

Muzete implementovat vlastni optimizer:

```php
use Baraja\ImageGenerator\Optimizer\Optimizer;

class MyOptimizer implements Optimizer
{
    public function optimize(string $absolutePath, int $quality = 85): void
    {
        // Vase optimalizacni logika
    }
}
```

A zaregistrovat ho v DIC:

```neon
services:
    - MyOptimizer

imageGenerator:
    optimizer: @MyOptimizer
```

## :shield: Bezpecnost

### Hash validace

Kazdy pozadavek na dynamicky obrazek obsahuje 6-znakovy hash, ktery se generuje deterministicky z parametru. Toto zabranuje:

- Generovani nahodnych kombinaci parametru (utok na server)
- Manipulaci s URL bez znalosti hashovaciho algoritmu

### Debug mode

V debug modu (pouze lokalni vyvoj) se pri chybnem hashi provede presmerovani na spravnou URL. V produkcnim prostredi se vrati chyba.

### Limity rozmeru

- Minimalni rozmer: 16px
- Maximalni rozmer: 3000px

Hodnoty mimo tyto limity jsou automaticky upraveny.

## :test_tube: Placeholder

Pokud dojde k chybe pri generovani obrazku, vygeneruje se placeholder s informacemi:

- Zobrazuje pozadovane rozmery
- V debug modu zobrazuje chybovou zpravu
- Ma sedy podklad pro snadnou identifikaci

## :book: API Reference

### ImageGenerator::from()

```php
public static function from(?string $url, array $params): string
```

Generuje URL pro ImageGenerator.

**Parametry:**
- `$url` - Cesta k obrazku (relativni, absolutni nebo URL)
- `$params` - Pole parametru:
  - `w` nebo `width` - sirka v pixelech
  - `h` nebo `height` - vyska v pixelech
  - `sc` - scale mode (`r`, `c`, `a`)
  - `c` nebo `cr` - crop position

**Vraci:** URL string s parametry a hashem

### Helper::invalidateCache()

```php
public static function invalidateCache(
    string $path,
    ?string $wwwDir = null,
    bool $recursive = false
): int
```

Invaliduje cache pro dany obrazek nebo adresar.

**Parametry:**
- `$path` - Relativni cesta od www adresare
- `$wwwDir` - Absolutni cesta k www adresari (volitelne, autodetekce)
- `$recursive` - Rekurzivni prohledavani podadresaru

**Vraci:** Pocet smazanych souboru

### Helper::generateHash()

```php
public static function generateHash(string $params, int $iterator = 0): string
```

Generuje 6-znakovy hash pro validaci parametru.

## :bulb: Tipy a triky

### Responzivni obrazky

Pro responzivni web muzete generovat vice variant:

```latte
<picture>
    <source media="(min-width: 1200px)" srcset="{img $image, [w => 1200, h => 800]}">
    <source media="(min-width: 768px)" srcset="{img $image, [w => 768, h => 512]}">
    <img src="{img $image, [w => 480, h => 320]}" alt="{$alt}">
</picture>
```

### Lazy loading s placeholdery

```latte
<img
    src="{img $image, [w => 20, h => 15]}"
    data-src="{img $image, [w => 800, h => 600]}"
    class="lazyload"
    alt="{$alt}"
>
```

### Prehled parametru v URL

| Parametr | Format | Priklad | Popis |
|----------|--------|---------|-------|
| `w` | w{cislo} | w200 | Sirka v px |
| `h` | h{cislo} | h150 | Vyska v px |
| `-sc` | -sc{r\|c\|a} | -scr | Scale mode |
| `-c` | -c{pozice} | -cmc | Crop pozice |
| `-br` | -br | -br | Pouzit breakpointy |
| `-px` | -px{0-100} | -px50 | Procentualni posun X |
| `-py` | -py{0-100} | -py25 | Procentualni posun Y |

## :bust_in_silhouette: Autor

**Jan Barasek**
- Website: [https://baraja.cz](https://baraja.cz)
- GitHub: [@janbarasek](https://github.com/janbarasek)

## :page_facing_up: License

`baraja-core/image-generator` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/image-generator/blob/master/LICENSE) file for more details.
