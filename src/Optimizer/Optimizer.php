<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator\Optimizer;


interface Optimizer
{
	public function optimize(string $absolutePath, int $quality = 85): void;
}
