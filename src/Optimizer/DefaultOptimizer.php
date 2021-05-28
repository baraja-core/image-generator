<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator\Optimizer;


use Baraja\ImageGenerator\Helper;

final class DefaultOptimizer implements Optimizer
{
	public function optimize(string $absolutePath, int $quality = 85): void
	{
		if (str_ends_with($absolutePath, '.jpg')) {
			$command = 'jpegoptim -s -f -m' . $quality . ' ' . escapeshellarg($absolutePath);
			$this->shellExec($command);
		} elseif (str_ends_with($absolutePath, '.png')) {
			$command = 'optipng -o2 ' . escapeshellarg($absolutePath);
			$this->shellExec($command);
		}
	}


	private function shellExec(string $command): string
	{
		if (Helper::functionIsAvailable('shell_exec') === false) {
			return '';
		}

		return (string) @shell_exec($command . ' 2>&1');
	}
}
