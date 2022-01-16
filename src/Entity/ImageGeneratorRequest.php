<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


final class ImageGeneratorRequest
{
	public const
		CROP_SMART = 'sm',
		CROP_RATIO = 'crop_ratio';

	public const
		SCALE_DEFAULT = null,
		SCALE_RATIO = 'r',
		SCALE_COVER = 'c',
		SCALE_ABSOLUTE = 'a';

	private ?int $width = null;

	private ?int $height = null;

	private bool $breakPoint = false;

	private ?string $scale = self::SCALE_DEFAULT;

	private ?string $crop = self::CROP_SMART;

	private ?int $px = null;

	private ?int $py = null;


	/**
	 * @param array{
	 *     width?: int,
	 *     height?: int,
	 *     breakPoint?: bool,
	 *     scale?: string|null,
	 *     crop?: string|null,
	 *     px?: int|null,
	 *     py?: int|null
	 * } $params
	 */
	public function __construct(array $params)
	{
		if (isset($params['width'], $params['height'])) {
			$this->setWidth($params['width']);
			$this->setHeight($params['height']);
		} else {
			throw new \InvalidArgumentException('Width or height params are required.');
		}
		$this->breakPoint = $params['breakPoint'] ?? false;
		$this->scale = $params['scale'] ?? null;
		$this->crop = $params['crop'] ?? null;
		$this->px = $params['px'] ?? null;
		$this->py = $params['py'] ?? null;
	}


	/**
	 * @param array{
	 *     width: int,
	 *     height: int,
	 *     breakPoint: bool,
	 *     scale: string|null,
	 *     crop: string|null,
	 *     px: int|null,
	 *     py: int|null
	 * }|string $params
	 */
	public static function createFromParams(string|array $params): self
	{
		return is_string($params)
			? self::createFromStringParams($params)
			: new self($params);
	}


	public static function createFromStringParams(string $params): self
	{
		preg_match('/^w(\d+)/i', $params, $w);
		preg_match('/^(w\d+)?h(\d+)/i', $params, $h);

		return new self([
			'width' => (isset($w[1]) && $w[1]) ? (int) $w[1] : 0,
			'height' => (isset($h[2]) && $h[2]) ? (int) $h[2] : 0,
			'breakPoint' => str_contains($params, '-br'),
			'scale' => preg_match('/-sc([rca])/i', $params, $sc) === 1
				? ((isset($sc[1]) && $sc[1]) ? (string) $sc[1] : null)
				: null,
			'crop' => preg_match('/-c([a-z]{2,5})/', $params, $c) === 1
				? ((isset($c[1]) && $c[1]) ? (string) $c[1] : null)
				: null,
			'px' => preg_match('/-px(\d+)/i', $params, $px) === 1
				? ((isset($px[1]) && $px[1]) ? (int) $px[1] : null)
				: null,
			'py' => preg_match('/-py(\d+)/i', $params, $py) === 1
				? ((isset($py[1]) && $py[1]) ? (int) $py[1] : null)
				: null,
		]);
	}


	private function setWidth(int $width): void
	{
		if ($width === 0) {
			throw new \InvalidArgumentException('Width can not be zero.');
		}
		if ($width < 16) {
			trigger_error('Minimal mandatory width is 16px, but "' . $width . '" given.');
			$width = 16;
		} elseif ($width > 3_000) {
			trigger_error('Image is so large. Maximal width is 3000px, but "' . $width . '" given.');
			$width = 3_000;
		}
		$this->width = $width;
	}


	private function setHeight(int $height): void
	{
		if ($height === 0) {
			throw new \InvalidArgumentException('Height can not be zero.');
		}
		if ($height < 16) {
			trigger_error('Minimal mandatory height is 16px, but "' . $height . '" given.');
			$height = 16;
		} elseif ($height > 3_000) {
			trigger_error('Image is so large. Maximal height is 3000px, but "' . $height . '" given.');
			$height = 3_000;
		}
		$this->height = $height;
	}


	public function getWidth(): int
	{
		return $this->width ?? 64;
	}


	public function getHeight(): int
	{
		return $this->height ?? 64;
	}


	public function isBreakPoint(): bool
	{
		return $this->breakPoint;
	}


	public function getScale(): ?string
	{
		return $this->scale;
	}


	public function getCrop(): ?string
	{
		return $this->crop;
	}


	public function getPx(): ?int
	{
		return $this->px;
	}


	public function getPy(): ?int
	{
		return $this->py;
	}
}
