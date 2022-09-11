<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Nette\Utils\Image as NetteImage;

final class SmartCrop
{
	public function __construct(
		private ImageGenerator $imageGenerator,
	) {
	}


	public function crop(string $path, ?int $width, ?int $height, NetteImage $image): void
	{
		if ($width === null) {
			$width = $height < 1 ? 150 : $height;
		}
		if ($height === null) {
			$height = $width < 1 ? 150 : $width;
		}

		if ($width <= $image->getWidth() || $height <= $image->getHeight()) {
			$smartCropPath = null;
			foreach (['/usr/bin/smartcrop', '/usr/sbin/smartcrop', '/usr/local/node/bin/smartcrop'] as $s) {
				if (@\is_file($s)) { // may do not have permissions
					$smartCropPath = $s;
					break;
				}
			}

			if ($smartCropPath !== null) {
				$commandResult = $this->shellExec(sprintf('%s %s%s%s %s',
					$smartCropPath,
					$path,
					$width > 0 ? ' --width ' . $width : '',
					$height > 0 ? ' --height ' . $height : '',
					$path,
				));

				if (
					$commandResult !== ''
					&& (
						str_contains($commandResult, 'Error')
						|| str_contains($commandResult, 'Exception')
					)
				) {
					throw new \RuntimeException('SmartCrop unable to generate image.');
				}
			} else {
				$this->imageGenerator->cropNette($path, 'mc', [$width, $height]);
				// TODO: Implement SmartCrop!
			}
		}
	}


	private function shellExec(string $command): string
	{
		if (Helper::functionIsAvailable('shell_exec') === false) {
			return '';
		}

		/** @phpstan-ignore-next-line */
		return (string) @shell_exec($command . ' 2>&1');
	}
}
