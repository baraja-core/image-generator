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

Full automatic ImageGenerator for creating dynamic image content by URL parameters.

- Easily generate thousands of image variants dynamically on-demand
- Set dozens of configuration parameters and customize the output
- Mature tools to work comfortably in Latte templates and on the backend
- All generated images are cached and protected by checksum validation

## :bulb: Key Principles

- **Dynamic image generation** - Images are generated based on URL parameters, no need to manually create variants
- **Automatic caching** - Generated images are stored in cache; subsequent requests are served directly without PHP overhead
- **Checksum protection** - Every request contains a hash that prevents unauthorized image generation
- **Original preservation** - Source images remain unchanged; all transformations are performed on copies
- **External URL support** - Images from external domains are automatically downloaded and cached locally
- **Nette Framework integration** - Native support for Latte macros and DIC extension
- **Output optimization** - Automatic image compression using jpegoptim and optipng

## :building_construction: Architecture and Components

### Main Components

| Component | Description |
|-----------|-------------|
| `ImageGenerator` | Core library for image transformation (resize, crop, scale) |
| `Image` | Request orchestrator - verifies hash, prepares paths, calls generator |
| `ImageGeneratorRoute` | Router for capturing dynamic image requests |
| `ImageGeneratorExtension` | DIC extension for Nette Framework integration |
| `Macros` | Latte macros for convenient template usage |
| `Proxy` | Downloads and caches external images |
| `SmartCrop` | Intelligent image cropping (detects important regions) |
| `Helper` | Static utility functions (hash, cache invalidation, environment detection) |
| `Config` | Configuration entity (background color, breakpoints) |
| `Optimizer` | Interface for image optimization with default implementation |

### System Architecture

```
+------------------+     +--------------------+     +------------------+
|   HTTP Request   |---->| ImageGeneratorRoute|---->|      Image       |
| (URL with params)|     | (pattern matching) |     |  (orchestrator)  |
+------------------+     +--------------------+     +------------------+
                                                            |
                         +----------------+                 |
                         |     Helper     |<----------------+
                         | (hash verify)  |                 |
                         +----------------+                 v
                                                   +------------------+
+------------------+     +--------------------+    |  ImageGenerator  |
|      Cache       |<----|     Optimizer      |<---|  (transformation)|
| (www/_cache/)    |     | (jpegoptim/optipng)|    +------------------+
+------------------+     +--------------------+             |
                                                            v
                         +--------------------+    +------------------+
                         |     SmartCrop      |<---|   Nette\Image    |
                         |   (intelligent)    |    |   (GD wrapper)   |
                         +--------------------+    +------------------+
```

### Request Processing Flow

```
1. Request URL: /images/cat__w200h150_abc123.jpg
                           |
2. ImageGeneratorRoute captures pattern and extracts:
   - dirname: images
   - basename: cat
   - params: w200h150
   - hash: abc123
   - extension: jpg
                           |
3. Image verifies hash (Helper::generateHash)
   - If mismatch and debug mode -> redirect to correct URL
   - If mismatch and production -> return error
                           |
4. Cache check (www/_cache/images/cat__w200h150_abc123.jpg)
   - Exists -> serve directly from cache
   - Not exists -> proceed to generation
                           |
5. ImageGenerator performs transformation:
   - Copy source file to temp
   - Apply requested transformations (crop/scale/resize)
   - Optimize output
   - Move to cache
                           |
6. Response to client with HTTP caching headers
```

## :package: Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/image-generator) and
[GitHub](https://github.com/baraja-core/image-generator).

To install, simply use the command:

```shell
$ composer require baraja-core/image-generator
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Requirements

- PHP 8.0+
- PHP extensions: `gd`, `session`, `json`, `fileinfo`, `curl`
- Nette Framework 3.0+

### Extension Registration (Nette)

```neon
extensions:
    imageGenerator: Baraja\ImageGenerator\ImageGeneratorExtension
```

### Configuration

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

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `debugMode` | bool | false | In debug mode, incorrect hash redirects to correct URL |
| `defaultBackgroundColor` | array | [255, 255, 255] | RGB background color for PNG images with transparency |
| `cropPoints` | array | predefined | Breakpoints for responsive cropping |

## :wrench: Extension via Linux Libraries

For advanced features, you can install the following libraries on the server (optional):

- [SmartCrop](https://github.com/jwagner/smartcrop.js/) - Intelligent cropping with detection of important image regions
- [OptiPNG](https://github.com/imagemin/imagemin-optipng) - PNG file optimization
- [Jpegoptim](https://github.com/tjko/jpegoptim) - JPEG file optimization

```bash
# Ubuntu/Debian
sudo apt-get install optipng jpegoptim

# SmartCrop (requires Node.js)
sudo npm install -g smartcrop-cli
```

## :rocket: Basic Usage

### URL Format

All images processed by ImageGenerator have the following format:

```
<basePath>/<dir>/<fileName>__<parameters>_<hash>.<format>
```

Example:
```
/images/monalisa__w200h128_abc123.jpg
```

This request loads image `/images/monalisa.jpg` and applies:
- Width: 200px
- Height: 128px
- Hash: abc123 (checksum verification)

### Usage in PHP Code

```php
use Baraja\ImageGenerator\ImageGenerator;

// Basic usage - resize
$url = ImageGenerator::from('/images/cat.png', ['w' => 200, 'h' => 150]);
// Result: /images/cat__w200h150_abc123.png

// With additional parameters
$url = ImageGenerator::from('/images/photo.jpg', [
    'w' => 800,
    'h' => 600,
    'sc' => 'r',  // scale ratio
    'c' => 'mc',  // crop middle-center
]);

// External image (automatically downloaded and proxied)
$url = ImageGenerator::from('https://example.com/image.jpg', ['w' => 300, 'h' => 200]);
```

### Usage in Latte Templates

ImageGenerator includes a native adapter for the Latte templating system.

#### Macro `{imageGenerator}`

Complete `<img>` tag rendering:

```latte
{imageGenerator '/images/cat.png', ['w' => 200, 'h' => 150]}
{* Result: <img src="/images/cat__w200h150_abc123.png" alt="Image"> *}

{* With alternative description *}
{imageGenerator '/images/cat.png', ['w' => 200, 'h' => 150, 'alt' => 'Cat']}
{* Result: <img src="/images/cat__w200h150_abc123.png" alt="Cat"> *}
```

#### Macro `{img}`

Returns URL only:

```latte
<img src="{img '/images/cat.png', ['w' => 200, 'h' => 150]}" alt="Cat">
```

#### Attribute `n:src`

For custom rendering logic:

```latte
<img n:src="/images/cat.png, [w => 200, h => 150]" alt="Cat">
```

## :gear: Transformation Parameters

Parameters are written after the filename following double underscores (`__`) and separated by hyphens.

### Width and Height

Parameters `w` and `h` set the image dimensions in pixels.

```
monalisa__w200h128_hash.jpg
```

- Minimum value: 16px
- Maximum value: 3000px
- If only one dimension is specified, the other is calculated based on aspect ratio

### Edge Cropping (crop)

Parameter `-c` determines from where the image will be cropped.

```
TL  TC  TR
ML  MC  MR
BL  BC  BR
```

| Value | Position |
|-------|----------|
| `tl` | Top-Left |
| `tc` | Top-Center |
| `tr` | Top-Right |
| `ml` | Middle-Left |
| `mc` | Middle-Center |
| `mr` | Middle-Right |
| `bl` | Bottom-Left |
| `bc` | Bottom-Center |
| `br` | Bottom-Right |
| `sm` | Smart Crop (intelligent cropping) |

Example:
```
cat__w200h150-ctl_hash.jpg   # Crop from top-left corner
cat__w200h150-csm_hash.jpg   # Smart crop
```

### Scale Mode

Parameter `-sc` determines how the image adapts to new dimensions.

| Value | Name | Description |
|-------|------|-------------|
| `r` | Ratio | Preserves aspect ratio, larger side determines main dimension |
| `c` | Cover | Fills as much area as possible in the given rectangle according to aspect ratio |
| `a` | Absolute | Image is stretched/compressed to exact dimensions (may cause distortion) |

Example:
```
photo__w800h600-scr_hash.jpg   # Scale ratio
photo__w800h600-scc_hash.jpg   # Scale cover
photo__w800h600-sca_hash.jpg   # Scale absolute
```

### Breakpoints

Parameter `-br` activates cropping according to predefined breakpoints. When using this parameter, others are ignored and the breakpoint is determined by width (`w`).

```
banner__w1920h800-br_hash.jpg
```

Default breakpoints:

| Breakpoint | Crop Area [x1, y1, x2, y2] |
|------------|----------------------------|
| 480 | [910, 30, 1845, 1150] |
| 600 | [875, 95, 1710, 910] |
| 768 | [975, 130, 1743, 660] |
| 1024 | [805, 110, 1829, 850] |
| 1280 | [615, 63, 1895, 800] |
| 1440 | [535, 63, 1975, 800] |
| 1680 | [410, 63, 2090, 800] |
| 1920 | [320, 63, 2240, 800] |
| 2560 | [0, 63, 2560, 800] |

### Percentage Cropping

Parameters `-px` and `-py` allow cropping based on percentage offset from the edge.

- `px` - percentage offset along X axis (0-100)
- `py` - percentage offset along Y axis (0-100)

```
main-page__w1680h800-px75-py0_hash.jpg
```

Image `main-page.jpg` will be cropped to 1680x800 with crop from top at 75% and from left at 0%.

### Parameter Combination

Parameters can be (almost) arbitrarily combined. Individual parameters are separated by hyphens.

```
/images/photo__w1680h800-px75-py0_hash.jpg
/images/banner__w800h600-scr-cmc_hash.jpg
```

## :arrows_counterclockwise: Format Conversion

If you need to change the image format (e.g., from PNG to JPG), simply change the extension in the URL. The generator automatically finds the source file and performs the conversion.

```php
// Source file: /images/logo.png
$url = ImageGenerator::from('/images/logo.jpg', ['w' => 200, 'h' => 100]);
// Result: PNG is converted to JPG and stored in cache
```

Supported formats: `jpg`, `jpeg`, `png`, `gif`, `webp`

## :floppy_disk: Cache

### Cache Location

The cache is located in the `/www/_cache/` directory and maintains the same directory structure as the source directories.

```
www/
├── images/
│   └── cat.png              # Source image
└── _cache/
    └── images/
        └── cat__w200h150_abc123.png   # Cached image
```

### Image Deduplication

If a generated image is content-identical to another already existing one, a symlink is created to save disk space.

### Cache Invalidation

```php
use Baraja\ImageGenerator\Helper;

// Invalidate specific image
$count = Helper::invalidateCache('/images/cat.png');

// Invalidate entire directory
$count = Helper::invalidateCache('/images/');

// Recursive invalidation (including subdirectories)
$count = Helper::invalidateCache('/images/', null, true);

// With explicit www directory path
$count = Helper::invalidateCache('/images/cat.png', '/var/www/html/www');
```

The method returns the number of deleted files.

## :globe_with_meridians: External Images (Proxy)

ImageGenerator supports processing images from external domains. Images are automatically downloaded, stored locally, and then processed.

```php
$url = ImageGenerator::from('https://example.com/photo.jpg', ['w' => 400, 'h' => 300]);
// Image is downloaded, stored in www/_cache/_proxy/ and URL is returned via internal proxy
```

External images are accessible via the `image-generator-proxy/*` endpoint.

### How Proxy Works

1. External URL is hashed using MD5
2. Image is downloaded and stored in `www/_cache/_proxy/{first-3-chars-of-hash}/{hash}.{format}`
3. All subsequent requests are served from local storage

## :chart_with_upwards_trend: Optimization

### Automatic Quality

The generator automatically applies quality optimization to all output images:

- Images larger than 480,000 pixels (e.g., 800x600): 85% quality
- Smaller images: 95% quality

### External Optimizers

If available, external tools are used:

- **jpegoptim** for JPEG files
- **optipng** for PNG files

### Custom Optimizer

You can implement your own optimizer:

```php
use Baraja\ImageGenerator\Optimizer\Optimizer;

class MyOptimizer implements Optimizer
{
    public function optimize(string $absolutePath, int $quality = 85): void
    {
        // Your optimization logic
    }
}
```

And register it in DIC:

```neon
services:
    - MyOptimizer

imageGenerator:
    optimizer: @MyOptimizer
```

## :shield: Security

### Hash Validation

Every request for a dynamic image contains a 6-character hash that is deterministically generated from parameters. This prevents:

- Generation of random parameter combinations (server attack)
- URL manipulation without knowledge of the hashing algorithm

### Debug Mode

In debug mode (local development only), incorrect hash redirects to the correct URL. In production environment, an error is returned.

### Dimension Limits

- Minimum dimension: 16px
- Maximum dimension: 3000px

Values outside these limits are automatically adjusted.

## :test_tube: Placeholder

If an error occurs during image generation, a placeholder is generated with information:

- Displays requested dimensions
- In debug mode displays error message
- Has gray background for easy identification

## :book: API Reference

### ImageGenerator::from()

```php
public static function from(?string $url, array $params): string
```

Generates URL for ImageGenerator.

**Parameters:**
- `$url` - Path to image (relative, absolute, or URL)
- `$params` - Array of parameters:
  - `w` or `width` - width in pixels
  - `h` or `height` - height in pixels
  - `sc` - scale mode (`r`, `c`, `a`)
  - `c` or `cr` - crop position

**Returns:** URL string with parameters and hash

### Helper::invalidateCache()

```php
public static function invalidateCache(
    string $path,
    ?string $wwwDir = null,
    bool $recursive = false
): int
```

Invalidates cache for given image or directory.

**Parameters:**
- `$path` - Relative path from www directory
- `$wwwDir` - Absolute path to www directory (optional, autodetected)
- `$recursive` - Recursive search in subdirectories

**Returns:** Number of deleted files

### Helper::generateHash()

```php
public static function generateHash(string $params, int $iterator = 0): string
```

Generates 6-character hash for parameter validation.

## :bulb: Tips and Tricks

### Responsive Images

For responsive websites, you can generate multiple variants:

```latte
<picture>
    <source media="(min-width: 1200px)" srcset="{img $image, [w => 1200, h => 800]}">
    <source media="(min-width: 768px)" srcset="{img $image, [w => 768, h => 512]}">
    <img src="{img $image, [w => 480, h => 320]}" alt="{$alt}">
</picture>
```

### Lazy Loading with Placeholders

```latte
<img
    src="{img $image, [w => 20, h => 15]}"
    data-src="{img $image, [w => 800, h => 600]}"
    class="lazyload"
    alt="{$alt}"
>
```

### URL Parameter Overview

| Parameter | Format | Example | Description |
|-----------|--------|---------|-------------|
| `w` | w{number} | w200 | Width in px |
| `h` | h{number} | h150 | Height in px |
| `-sc` | -sc{r\|c\|a} | -scr | Scale mode |
| `-c` | -c{position} | -cmc | Crop position |
| `-br` | -br | -br | Use breakpoints |
| `-px` | -px{0-100} | -px50 | Percentage offset X |
| `-py` | -py{0-100} | -py25 | Percentage offset Y |

## :bust_in_silhouette: Author

**Jan Barasek**
- Website: [https://baraja.cz](https://baraja.cz)
- GitHub: [@janbarasek](https://github.com/janbarasek)

## :page_facing_up: License

`baraja-core/image-generator` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/image-generator/blob/master/LICENSE) file for more details.
