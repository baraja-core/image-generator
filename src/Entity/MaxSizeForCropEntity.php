<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator\Entity;


/**
 * @internal
 */
final class MaxSizeForCropEntity
{
	public function __construct(
		private int $needleWidth,
		private int $needleHeight,
		private float $needleRatio,
	) {
	}


	public function getNeedleWidth(): int
	{
		return $this->needleWidth;
	}


	public function getNeedleHeight(): int
	{
		return $this->needleHeight;
	}


	public function getNeedleRatio(): float
	{
		return $this->needleRatio;
	}
}
