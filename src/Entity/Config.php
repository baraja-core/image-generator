<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


final class Config
{
	/**
	 * @param array<int, int> $defaultBackgroundColor
	 * @param array<int, array<int, int>> $cropPoints
	 */
	public function __construct(
		private array $defaultBackgroundColor,
		private array $cropPoints,
	) {
	}


	/**
	 * @return array<int, int>
	 */
	public function getDefaultBackgroundColor(): array
	{
		return $this->defaultBackgroundColor;
	}


	/**
	 * @return array<int, array<int, int>>
	 */
	public function getCropPoints(): array
	{
		return $this->cropPoints;
	}
}
