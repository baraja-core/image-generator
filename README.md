Image generator
===============

- Easily generate thousands of image types dynamically,
- Set dozens of configuration parameters and customize the output,
- Mature tools to work comfortably in Latte template and on the backend.

The image generator is a simple way to work with images on the frontend using only calls to specific parameters in the URL. To prevent the generator from being easily overloaded, all images are treated with a checksum.

Base idea
---------

Before the introduction of ImageGenerator, managing images and their dimensions was a very complex task that required a lot of complex code.

ImageGenerator introduces a simple way to instantly create a new image based on a source image and configuration parameters and cache it. Only the application can send a request to generate a new image variant (handled by a checksum). If the requested variant does not exist, it will be automatically generated.

This approach allows you to easily create thousands of image variants, and easily precede from one dimension to a new one. The original source image will never be changed and is read-only.

📦 Installation
---------------

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/image-generator) and
[GitHub](https://github.com/baraja-core/image-generator).

To install, simply use the command:

```shell
$ composer require baraja-core/image-generator
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

Extension via Linux libraries
-----------------------------

To use the advanced features, you need to install the following Linux libraries on the server (optional):

- SmartCrop | [Stáhnout](https://github.com/jwagner/smartcrop.js/)
- OptiPNG | [Stáhnout](https://github.com/imagemin/imagemin-optipng)
- Jpegoptim | [Stáhnout](https://github.com/tjko/jpegoptim)

Using the generator in a template
---------------------------------

ImageGenerator includes a native adapter for the Latte templating system.

```
{imageGenerator '/animal/cat.png', ['w' => 200]}
```

Pass an array of parameters to the `imageGenerator` macro in the usual way. In addition, it is possible to pass the `alt` parameter, which carries information about an alternative image description.

Alternatively, we can just add the path to the image via the `n:src` parameter and figure out the entire rendering logic ourselves:

```
<img n:src="/animal/cat.png, [w => 200]">
```

Request (internal/external) image variant URL
---------------------------------------------

You can request an image to be rendered simply by calling the `ImageGenerator::from()` method, passing the disk path (relative, absolute, or URL) and the image editing parameters.

The URL can also lead to an external domain. In this case, the image will be automatically downloaded by the robot. A copy of the downloaded image is cached on the local disk and manipulated in the usual way. In order to render the external image to your domain, the resulting image must be returned via an internal PHP Proxy, which is available by default at the URI `image-generator-proxy/*`.

For example:

```php
$newUrl = ImageGenerator::from($url, ['w' => 100, 'h' => 380]);
```

How to process image request
----------------------------

All images received by ImageGenerator have this format:

```
	<basePath>/<dir>/<fileName>__<parameters>_<hash>.<format>
```

When the image is called for the first time, a specialized PHP script is run to prepare and cache the image according to the set parameters. The next time the image is called, it is already cached and is called directly from there without the assistance of the PHP script.

Cache
-----

The cache is located directly in the `/www/_cache` directory and contains the same directory structure as all other source directories from which images are loaded. This structure is maintained automatically during image generation.

Should an image be generated with the same content as an existing image, it will be automatically tracked down and a symlink created, saving disk space.

Cache invalidation
------------------

Under normal circumstances, the cache will never be invalidated and a complete history of all images will be maintained. If you want to invalidate the cache, just call the `Helper::invalidateCache()` function.

- The first parameter passes the path to the original image (relative from `/www`),
- Then the absolute path to the `/www` directory (if `NULL`, it will be detected automatically),
- The last parameter (a boolean) can be used to specify if images should be invalidated recursively in subdirectories as well.

Parameters
----------

The behavior of the generator can be influenced using parameters directly in the URL when calling the image file. All parameters are written after a pair of underscores and none of the parameters are mandatory (all have a default value). If no parameter is passed, the original image is returned without any changes directly from the source location.

Parameters are automatically validated before being passed to the generator. Invalid input is ignored (skipped) and a new image is invented according to the other available parameters.

Example of calling an image:

```
	<basePath>/<dir>/monalisa__w200h128_ABCDEF.jpg
```

This path reads the image from the path **/dir/monalisa.jpg** and sets the following filters:

- Width: 200px
- Height: 128px
- Centering method (crop): 'smart' (the default filter was used)
- Scale (aspect ratio): *none* (not needed, the image will not be distorted)
- Hash: 'ABCDEF' (checksum parameters will be verified)

Then the source image is called, filters are applied and the result is cached.

List of all parameters
----------------------

We distinguish the following parameters:

### Height and width (width & height)

The pair of parameters 'w' and 'h' usually follow each other. If one of them is not specified, it will be calculated according to the aspect ratio.

The parameter is immediately followed by the dimension in pixels as an integer.

Example notation: `w200h128`

### Crop by edge (crop)

The 'c' parameter tells where the image will be cropped to the set size from the 'w' and 'h' parameters. It does not perform any further optimizations (aspect ratio, ...), it just performs a rough crop to the set size.

It has 9 possible states depending on the position:

```
TL TC TR
ML MC MR
BL BC BR
```

And one special state: `sm`, which means 'smart-crop', or "I don't know where to crop the image, make an intelligent decision yourself".

Example notation: `-ctl` says that the image will be cropped from the top-left edge.

> TIP: If you need to crop a group of images according to the same rules that change for different resolutions, it is better to use breakpoints (details in the next chapter).

### A way to treat different side lengths (scale)

Sometimes it's useful to change the deformation and cropping behavior for different page lengths - whether you need to fill a specified space or, conversely, oversize.

There are 3 possible states for this:

- 'r' - ratio (the aspect ratio will be kept, the larger side gives the main dimension),
- 'c' - cover (tries to fill as much area as possible in the specified rectangle according to the aspect ratio),
- 'a' - absolute (image will be compressed / stretched to exact dimensions regardless of aspect ratio, may cause deformation).

Example notation: `-scr` means 'scale ratio'

### Break points

Because of the large photos on the AirBank site, we have implemented the ability to set custom breakpoints by which the image is cropped. When using the `-br` parameter, all others are ignored and the breakpoint used is determined by the width of the desired image (`w` parameter).

The breakpoints are defined in the `neon` configuration file and it is not possible to change the parameters.

The use of breakpoints is a way to control the way images are cropped according to unstructured rules. If a dimension is required that is not listed in the breakpoints, the next larger one is used.

Default setting:

```
Breakpoint: [upper left corner, lower right corner]
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

### Proportional cropping determined by percentage

Quite often (especially on responsive sites) it is not a good idea to cut to a specific edge, but to cut based on the aspect ratio as a percentage of the source image, for which we don't know the original dimensions to determine the best crop area. This is exactly what proportional percentage cropping solves.

First the exact aspect ratio is analyzed to determine which parameter to cut by (and the other will be ignored), then the crop area is calculated based on the specified percentages and the cut is made.

> ATTENTION: Both values are not used for cropping, but the better one is always automatically determined according to the current aspect ratio!

If you want to use this parameter, you must specify both mandatory values:

- 'px' - percentage cropping according to the 'X' axis,
- 'py' - percentage crop according to the 'Y' axis.

Example entry: `-px52-py70` says that the image crop will be shifted 52% from the top edge and 70% from the left edge. The edge corresponding to the source aspect ratio will always be used, and the other dimension will be stretched over the entire remaining image area.

The values given after `px` and `py` are percentages as integers in the range 0 - 100. A negative value is not possible.

Combination of parameters
-------------------

Parameters can be (almost) arbitrarily combined. Individual unrelated parameters are separated by a hyphen.

Example of calling an image: `/data/cms/main-page__w1680h800-px75-py0_7fd364.jpg`

The image `main-page.jpg` will be called, cropped to a size of 1680x800 with the crop from the top at 75% and from the left at 0%.

Format conversion
----------------

Often we have an image on disk in the `png` format, for example, which is uneconomical for data transfer and the `jpg` format would suffice. If there is no image with a `.jpg` extension with the same name, it is possible to change the extension when calling the image (and call the non-existent image) and let the generator automatically track down the source file and convert the format,

For example, if there is a `cat.png` file and we are interested in its `jpg` version, we just call `cat.jpg` and the format will be converted (it doesn't affect the source image, the converted file will be cached).

Data optimization
-------------------

Many images (especially large photos) have unnecessarily too large a capacity, which slows down network transfer. The image generator automatically applies data optimization to all images in the output. It currently converts the quality to 85%. This value is set manually directly in the generator and cannot be influenced by any parameter.

📄 License
-----------

`baraja-core/image-generator` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/template/blob/master/LICENSE) file for more details.
