<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Nette\Utils\FileSystem;

final class Proxy
{
	private static ?string $basePath = null;


	public static function save(string $url, string $hash): void
	{
		$path = self::getStoragePath($hash, pathinfo($url)['extension'] ?? null);
		if (is_file($path)) {
			return;
		}
		FileSystem::write($path, FileSystem::read($url));
	}


	public static function getStoragePath(?string $hash = null, ?string $extension = null): string
	{
		$return = self::getBasePath();
		if ($hash !== null) {
			$return = sprintf('%s/%s/%s', $return, substr($hash, 0, 3), $hash);
			if ($extension !== null) {
				$return .= sprintf('.%s', $extension);
			}
		}

		return $return;
	}


	public static function getBasePath(): string
	{
		if (self::$basePath === null) {
			self::$basePath = sprintf('%s/_cache/_proxy', Helper::getWwwDir());
		}

		return self::$basePath;
	}
}
