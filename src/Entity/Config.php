<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


final class Config
{
	/** @var array{0: int, 1: int, 2: int} */
	private array $defaultBackgroundColor;


	/**
	 * @param array<int, int> $defaultBackgroundColor
	 * @param array<int, array{0: int, 1: int, 2: int, 3: int}> $cropPoints
	 */
	public function __construct(
		array $defaultBackgroundColor,
		private array $cropPoints,
	) {
		$defaultBackgroundColor += [255, 255, 255];
		assert(isset($defaultBackgroundColor[0], $defaultBackgroundColor[1], $defaultBackgroundColor[2]));
		$this->defaultBackgroundColor = $defaultBackgroundColor;
	}


	/**
	 * @return array{0: int, 1: int, 2: int}
	 */
	public function getDefaultBackgroundColor(): array
	{
		return $this->defaultBackgroundColor;
	}


	/**
	 * @return array<int, array{0: int, 1: int, 2: int, 3: int}>
	 */
	public function getCropPoints(): array
	{
		return $this->cropPoints;
	}
}
