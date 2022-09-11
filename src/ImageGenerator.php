<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Baraja\ImageGenerator\Entity\MaxSizeForCropEntity;
use Baraja\ImageGenerator\Optimizer\DefaultOptimizer;
use Baraja\ImageGenerator\Optimizer\Optimizer;
use Baraja\Url\Url;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\ImageException;

final class ImageGenerator
{
	private ImageGeneratorRequest $request;

	private Optimizer $optimizer;

	private SmartCrop $smartCrop;

	private string $targetPath;


	public function __construct(
		private Config $config,
		?Optimizer $optimizer = null,
	) {
		$this->optimizer = $optimizer ?? new DefaultOptimizer;
		$this->smartCrop = new SmartCrop($this);
	}


	/**
	 * Fill in the URL address of the image (it can also be relative) with parameters
	 * so that the path is valid for ImageGenerator.
	 * If an absolute URL is passed from another domain, the image is automatically downloaded and cached.
	 * External images are retrieved via an internal proxy.
	 *
	 * @param array{
	 *    w?: int,
	 *    width?: int,
	 *    h?: int,
	 *    height?: int,
	 *    sc?: string,
	 *    cr?: string,
	 *    c?: string
	 * } $params
	 */
	public static function from(?string $url, array $params): string
	{
		if ($url === null || $url === '#INVALID_IMAGE#') {
			$url = sprintf('%s/placeholder.png', Url::get()->getBaseUrl());
		} elseif (preg_match('~^https?://.+\.([a-zA-Z]+)$~', $url, $parser) === 1) {
			$p = @parse_url($url); // @ - is escalated to exception
			if ($p === false) {
				throw new \InvalidArgumentException(sprintf('Malformed or unsupported URI "%s".', $url));
			}
			if (Url::get()->getUrlScript()->getHost() === rawurldecode($p['host'] ?? '')) {
				return sprintf('%s://%s%s', $p['scheme'], $p['host'], $p['port'] !== 80 ? ':' . $p['port'] : '')
					. self::from($p['path'] ?? '', $params);
			}
			$urlHash = md5($url);
			Proxy::save($url, $urlHash);
			$url = sprintf('%s/image-generator-proxy/%s.%s', Url::get()->getBaseUrl(), $urlHash, $parser[1] ?? 'png');
		} elseif (preg_match(
			'/^(?<prefix>.*[\/\\\\])(?<filename>.+?)(__[^_]*?_[a-z0-9]{6})(?<suffix>\.[^.]+)$/',
			$url,
			$parser,
		) === 1) {
			$url = sprintf('%s%s%s', $parser['prefix'], $parser['filename'], $parser['suffix']);
		}
		if (preg_match('/(?<prefix>.*\/)?(?<filename>[\w._-]+)\.(?<suffix>.+)$/', $url, $parser) === 1) {
			$param = Helper::paramsToString($params);

			return sprintf('%s%s%s.%s',
				$parser['prefix'] ?? '',
				$parser['filename'],
				$param !== '' ? sprintf('__%s_%s', $param, Helper::generateHash($param)) : '',
				$parser['suffix'],
			);
		}

		throw new \InvalidArgumentException(sprintf('Invalid URL "%s" given.', $url));
	}


	public function generate(ImageGeneratorRequest $request, string $sourceFile, string $targetFile): void
	{
		@ini_set('memory_limit', '256M');

		$this->request = $request;
		$this->targetPath = $targetFile;

		if (is_file($sourceFile) === false) {
			throw new \InvalidArgumentException('Source file does not exist "' . $sourceFile . '".');
		}
		if (is_file($targetFile) === true) {
			throw new \InvalidArgumentException('Target file exist "' . $targetFile . '".');
		}
		if ($this->isOk($sourceFile) === false) {
			ImageGeneratorRoute::renderPlaceholder('w' . $request->getWidth() . 'h' . $request->getHeight());
		}

		$this->copySourceFileToTemp(
			$sourceFile,
			$tempFile = (string) preg_replace('/(.+?)(\.\w+)$/', '$1_temp$2', $targetFile),
		);

		if ($this->request->isBreakPoint()) {
			$this->cropByBreakPoint($tempFile, $this->request->getWidth());
		} elseif ($this->request->getScale() !== null) {
			$this->scale(
				$tempFile,
				$this->request->getScale(),
				[
					$this->request->getWidth(),
					$this->request->getHeight(),
				],
			);
		} elseif ($this->request->getCrop() !== null) {
			if ($this->request->getCrop() === ImageGeneratorRequest::CROP_SMART) {
				$this->cropSmart($tempFile, $this->request->getWidth(), $this->request->getHeight());
			} else {
				$this->cropNette(
					$tempFile,
					$this->request->getCrop(),
					[
						$this->request->getWidth(),
						$this->request->getHeight(),
					],
				);
			}
		} elseif ($this->request->getPx() !== null && $this->request->getPy() !== null) {
			$this->percentagesShift(
				$tempFile,
				[
					'px' => $this->request->getPx(),
					'py' => $this->request->getPy(),
				],
				[
					$this->request->getWidth(),
					$this->request->getHeight(),
				],
			);
		} else {
			$this->cropSmart($tempFile, $this->request->getWidth(), $this->request->getHeight());
		}

		$this->optimizer->optimize(
			$tempFile,
			$this->request->getWidth() * $this->request->getHeight() > 479999 ? 85 : 95,
		);

		if ($this->isOk($tempFile)) {
			FileSystem::rename($tempFile, $targetFile);
		} else {
			@unlink($tempFile);
			ImageGeneratorRoute::renderPlaceholder(
				'w' . $request->getWidth()
				. 'h' . $request->getHeight(),
			);
		}
	}


	public function isOk(string $path, ?string $format = null): bool
	{
		if ($format === null) {
			$formatMap = [
				'image/gif' => 'gif',
				'image/png' => 'png',
				'image/jpeg' => 'jpg',
			];

			$fInfo = finfo_open(FILEINFO_MIME_TYPE);
			assert($fInfo !== false);
			$contentType = (string) finfo_file($fInfo, $path);
			if (isset($formatMap[$contentType]) === false) {
				return false;
			}
			$format = $formatMap[$contentType];
		}
		$formatToFunction = [
			'png' => 'imagecreatefrompng',
			'jpg' => 'imagecreatefromjpeg',
			'jpeg' => 'imagecreatefromjpeg',
			'gif' => 'imagecreatefromgif',
		];

		$format = strtolower($format);
		if (isset($formatToFunction[$format]) === false) {
			throw new \InvalidArgumentException(
				'Format "' . $format . '" is not supported. Did you mean "'
				. implode('", "', array_keys($formatToFunction))
				. '"?',
			);
		}
		$function = $formatToFunction[$format];
		if (Helper::functionIsAvailable($function) === false) {
			throw new \RuntimeException('Function "' . $function . '" is not available now.');
		}

		return (bool) @$function($path);
	}


	/**
	 * @param array{0: int|null, 1: int|null} $size
	 */
	public function cropNette(string $path, string $crop, array $size): void
	{
		[$width, $height] = $size;

		$this->cropByCorner(
			$image = $this->loadNetteImage($path),
			$crop,
			[$image->getWidth(), $image->getHeight()],
			[$width, $height],
		);
		$this->saveNetteImage($path, $image);
	}


	private function copySourceFileToTemp(string $sourceFile, string $tempFile): void
	{
		FileSystem::copy($sourceFile, $tempFile);
		clearstatcache();
	}


	/**
	 * +----------------> X <----------------+
	 * |  [A_x, A_y]                         |
	 * ˘    * =================== \          ˘
	 * Y    |                     |          Y
	 * ^    \ =================== *          ^
	 * |                         [B_x, B_y]  |
	 * +----------------> X <----------------+
	 */
	private function cropByBreakPoint(string $absolutePath, int $width): void
	{
		$breakPoint = null;
		$breakpoints = [0];
		$cropPoints = $this->config->getCropPoints();
		foreach ($cropPoints as $cropPoint => $values) {
			$breakpoints[] = $cropPoint;
		}
		sort($breakpoints);

		for ($i = 0; isset($breakpoints[$i]); $i++) {
			$beforeBreakpoint = $breakpoints[$i];
			$afterBreakpoint = $breakpoints[$i + 1] ?? INF;

			if (
				($width >= $beforeBreakpoint && $width < $afterBreakpoint)
				|| $afterBreakpoint === INF
			) {
				$breakPoint = ($afterBreakpoint !== INF ? $afterBreakpoint : $beforeBreakpoint);
				break;
			}
		}

		if ($breakPoint === null) {
			throw new \InvalidArgumentException(
				'Undefined breakpoint. '
				. 'Possible values: "' . implode(', ', $breakpoints) . '". Did you registered some points?',
			);
		}

		[$aX, $aY] = $cropPoints[$breakPoint];

		$bX = abs($cropPoints[$breakPoint][2] - $cropPoints[$breakPoint][0]);
		$bY = abs($cropPoints[$breakPoint][3] - $cropPoints[$breakPoint][1]);

		$this->saveNetteImage($absolutePath, $this->loadNetteImage($absolutePath)->crop($aX, $aY, $bX, $bY));
	}


	private function loadNetteImage(string $path): Image
	{
		try {
			return Image::fromFile($path !== '' ? $path : $this->targetPath);
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}


	private function saveNetteImage(string $path, Image $image): void
	{
		try {
			$image->save($path !== '' ? $path : $this->targetPath);
		} catch (ImageException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}


	/**
	 * @param array{0: int|null, 1: int|null} $size
	 */
	private function scale(string $absolutePath, string $scale, array $size): void
	{
		[$width, $height] = $size;
		if ($width === null && $height === null) {
			throw new \LogicException('Needle scale size must define width or height, but none is defined.');
		}

		if ($scale === ImageGeneratorRequest::SCALE_RATIO) {
			/** @var array{0: int, 1: int} $imageSize */
			$imageSize = getimagesize($absolutePath);
			if ($width === null) {
				$needleRatio = $imageSize[0] / $imageSize[1];
				$width = (int) ($needleRatio * $height);
			}
			if ($height === null) {
				$needleRatio = $imageSize[1] / $imageSize[0];
				$height = (int) ($needleRatio * $width);
			}

			if (
				($width / $imageSize[0] < 1.3 && $height / $imageSize[1] < 1.3)
				&& (
					($width === $imageSize[0] && $height >= $imageSize[1])
					|| ($height === $imageSize[1] && $width >= $imageSize[0])
				) === false
			) {
				$this->saveNetteImage(
					$absolutePath,
					$this->loadNetteImage($absolutePath)
						->resize($width, $height),
				);
			}
		} elseif ($scale === ImageGeneratorRequest::SCALE_COVER) {
			$this->saveNetteImage(
				$absolutePath,
				$this->loadNetteImage($absolutePath)
					->resize($width, $height, Image::EXACT),
			);
		} elseif ($scale === ImageGeneratorRequest::SCALE_ABSOLUTE) {
			$this->saveNetteImage(
				$absolutePath,
				$this->loadNetteImage($absolutePath)
					->resize(
						$width,
						$height,
						Image::SHRINK_ONLY | Image::STRETCH,
					),
			);
		}
	}


	private function cropSmart(string $path, ?int $width, ?int $height): void
	{
		$image = $this->loadNetteImage($path);
		$this->smartCrop->crop($path, $width, $height, $image);
	}


	/**
	 * @param array{0: int, 1: int} $original
	 * @param array{0: int|null, 1: int|null} $needle
	 */
	private function cropByCorner(Image $image, string $corner, array $original, array $needle): void
	{
		$corner = strtolower($corner);
		if (preg_match('/^([tmb])([lcr])$/', $corner, $cornerParser) === 1) {
			$leftCrop = $cornerParser[0] ?? 'm';
			$topCrop = $cornerParser[1] ?? 'c';
		} else {
			throw new \InvalidArgumentException(sprintf('Corner "%s" is not in valid format.', $corner));
		}

		[$originalWidth, $originalHeight] = $original;
		[$needleWidth, $needleHeight] = $needle;

		$resize = $this->getMaxSizeForCrop(
			[$originalWidth, $originalHeight],
			[$needleWidth, $needleHeight],
		);

		if (
			$needleWidth <= $originalWidth
			&& $needleHeight <= $originalHeight
		) {
			if ($leftCrop === 'm') {
				$top = (int) round(($originalHeight - $resize->getNeedleHeight()) / 2);
			} elseif ($leftCrop === 'b') {
				$top = (int) round($originalHeight - $resize->getNeedleHeight());
			} else {
				$top = 0;
			}

			if ($topCrop === 'c') {
				$left = (int) round(($originalWidth - $resize->getNeedleWidth()) / 2);
			} elseif ($topCrop === 'r') {
				$left = (int) round($originalWidth - $resize->getNeedleWidth());
			} else {
				$left = 0;
			}

			$image->crop($left, $top, $resize->getNeedleWidth(), $resize->getNeedleHeight())
				->resize(
					$needleWidth,
					$needleHeight,
				);
		}
	}


	/**
	 * Find best scale ratio of sizes for crop
	 *
	 * @param array{0: int, 1: int} $original
	 * @param array{0: int|null, 1: int|null} $needle
	 */
	private function getMaxSizeForCrop(array $original, array $needle): MaxSizeForCropEntity
	{
		[$originalWidth, $originalHeight] = $original;
		[$needleWidth, $needleHeight] = $needle;

		$needleWidthIsGreater = $needleWidth > $needleHeight;
		if ($needleWidth === null || $needleHeight === null) {
			if ($needleWidth === null && $needleHeight !== null) {
				$needleRatio = $originalWidth / $originalHeight;
				$needleWidth = (int) ($needleRatio * $needleHeight);
			} elseif ($needleHeight === null && $needleWidth !== null) {
				$needleRatio = $originalHeight / $originalWidth;
				$needleHeight = (int) ($needleRatio * $needleWidth);
			} else {
				throw new \LogicException('Needle size must define width or height, but none is defined.');
			}
		} else {
			$needleRatio = !$needleWidthIsGreater
				? $needleHeight / $needleWidth
				: $needleWidth / $needleHeight;

			while ($needleWidth < $originalWidth && $needleHeight < $originalHeight) {
				if ($needleWidthIsGreater) {
					$needleWidth += $needleRatio;
					$needleHeight++;
				} else {
					$needleHeight += $needleRatio;
					$needleWidth++;
				}
			}
		}
		if ($needleWidth > $originalWidth) {
			$needleWidth -= ($needleWidth - $originalWidth);
		}
		if ($needleHeight > $originalHeight) {
			$needleHeight -= ($needleHeight - $originalHeight);
		}

		return new MaxSizeForCropEntity(
			(int) $needleWidth,
			(int) $needleHeight,
			(float) $needleRatio,
		);
	}


	/**
	 * @param array{px: int, py: int} $xy
	 * @param array{0: int, 1: int} $needle
	 */
	private function percentagesShift(string $absolutePath, array $xy, array $needle): void
	{
		[$needleWidth, $needleHeight] = $needle;

		$this->saveNetteImage(
			$absolutePath,
			$this->loadNetteImage($absolutePath)
				->resize($needleWidth, $needleHeight, Image::FILL),
		);
		$image = $this->loadNetteImage($absolutePath);

		if ($this->request->getPx() !== null || $this->request->getPy() !== null) {
			$xy['px'] /= 100;
			$xy['py'] /= 100;

			$originalHeight = $image->getHeight();
			$originalWidth = $image->getWidth();
			if ($originalHeight * 2 < $originalWidth) {
				$left = (int) round(
					(($needleWidth * $originalHeight - $originalWidth * $needleHeight) / $needleHeight)
					* $xy['px'],
				);
				$top = 0;
			} else {
				$top = (int) round(
					(($originalWidth * $needleHeight - $needleWidth * $originalHeight) / $needleWidth)
					* $xy['py'],
				);
				$left = 0;
			}

			$image->crop($left, $top, $needleWidth, $needleHeight);
			$this->saveNetteImage($absolutePath, $image);
		}
	}
}
