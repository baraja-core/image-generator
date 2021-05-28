<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Baraja\Url\Url;
use Nette\Http\Request;

final class Helper
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/** Deterministically generates a 6-character long hash for parameter checking. */
	public static function generateHash(string $params, int $iterator = 0): string
	{
		$hash = sha1($params);
		if ($iterator > 3 || strlen($params) < 6) {
			return $hash;
		}

		$return = '';
		for ($i = 0; isset($hash[$i]) === true; $i++) {
			if ($i > 1) {
				$return .= chr((int) round((ord($hash[$i]) + ord($hash[$i - 1])) / 2));
			}
		}
		while (strlen($return) < 12) {
			$return .= strtolower($return);
		}

		return strtolower(substr(self::generateHash(strtolower($return), $iterator + 1), 0, 6));
	}


	/**
	 * Removes generated images from the cache from ImageGenerator.
	 *
	 * The relative path to the file or directory from the www directory is entered in $path,
	 * the basic directory is detected automatically from WWW_DIR by default,
	 * if it does not exist, the path will be detected automatically, or the second parameter can be set manually.
	 * Both the path to a specific file and the path to a directory can be inserted into the function.
	 *
	 * The path is always entered to the original file, the function itself monitors the location in the cache.
	 * The third parameter can be used by a bool to determine whether the directory should be searched recursively.
	 *
	 * The output of the function is the number of deleted files. If nothing is deleted, return zero.
	 * If the file or directory does not exist, it throws an exception.
	 *
	 * @sample '/images/forest.jpg'
	 */
	public static function invalidateCache(string $path, ?string $wwwDir = null, bool $recursive = false): int
	{
		if (preg_match('/\.\./', $path)) {
			throw new \InvalidArgumentException('Path "' . $path . '" could not contains \'..\'.');
		}
		if ($wwwDir !== null && preg_match('/\.\./', $wwwDir)) {
			throw new \InvalidArgumentException('Param $wwwDir "' . $wwwDir . '" could not contains \'..\'.');
		}

		$path = '/' . ltrim($path, '/');

		if ($wwwDir === null) {
			$wwwDir = dirname(__DIR__, 4) . '/www/';
		}
		if (!file_exists($wwwDir . $path)) {
			throw new \InvalidArgumentException('File or directory "' . $wwwDir . $path . '" does not exist.');
		}

		$cachePath = $wwwDir . '/_cache' . $path;
		$cachePathDirName = is_file($wwwDir . $path) ? dirname($cachePath) : $cachePath;
		$files = [];
		if (preg_match('/(?<baseName>[^\/]+)\.[^.]+$/', $cachePath, $cachePathWithoutSuffix)) {
			/** @phpstan-ignore-next-line */
			foreach (glob($cachePathDirName . '/' . $cachePathWithoutSuffix['baseName'] . '*') as $filePath) {
				$files[] = $filePath;
			}
		} elseif ($recursive) {
			foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cachePathDirName)) as $info) {
				if (is_file($info->getPathname())) {
					$files[] = $info->getPathname();
				}
			}
		} else {
			/** @phpstan-ignore-next-line */
			foreach (glob($cachePathDirName . '/*.*') as $filePath) {
				$files[] = $filePath;
			}
		}

		$countDeletedFiles = 0;
		foreach ($files as $file) {
			if (preg_match('/\.(jpg|jpeg|png|gif|md5)$/i', $file)) {
				unlink($file);
				$countDeletedFiles++;
			}
		}

		return $countDeletedFiles;
	}


	/**
	 * @param mixed[] $params
	 */
	public static function paramsToString(array $params): string
	{
		$w = $params['w'] ?? $params['width'] ?? null;
		$h = $params['h'] ?? $params['height'] ?? null;
		if ($w !== null && $w > 0 && $h && $h > 0) {
			$return = 'w' . $w . 'h' . $h;
			if (isset($params['sc']) === true) {
				$return .= '-sc' . $params['sc'];
			}
			if (isset($params['cr']) === true || isset($params['c']) === true) {
				$return .= '-c' . ($params['cr'] ?? $params['c']);
			}

			return $return;
		}

		return '';
	}


	/**
	 * Return current API path by current HTTP URL.
	 * In case of CLI return empty string.
	 */
	public static function processPath(Request $httpRequest): string
	{
		return trim(
			str_replace(
				rtrim($httpRequest->getUrl()->withoutUserInfo()->getBaseUrl(), '/'),
				'',
				Url::get()->getCurrentUrl(),
			),
			'/'
		);
	}


	public static function isLocalhost(): bool
	{
		static $is;
		if ($is === null) {
			if (self::userIp() === '127.0.0.1') {
				return $is = true;
			}
			$localHosts = ['localhost', '[^\/]+\.l', '127\.0\.0\.1'];
			$allowedPorts = ['80', '443', '3000'];
			$is = (bool) preg_match(
				'/^https?:\/\/(' . implode('|', $localHosts) . ')(:(?:' . implode(
					'|',
					$allowedPorts
				) . '))?(?:\/|$)/',
				Url::get()->getCurrentUrl(),
			);
		}

		return $is;
	}


	public static function userIp(): string
	{
		static $ip = null;
		if ($ip === null) {
			if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) { // Cloudflare support
				$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
			} elseif (isset($_SERVER['REMOTE_ADDR']) === true) {
				$ip = $_SERVER['REMOTE_ADDR'];
				if ($ip === '127.0.0.1') {
					if (isset($_SERVER['HTTP_X_REAL_IP'])) {
						$ip = $_SERVER['HTTP_X_REAL_IP'];
					} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
						$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
					}
				}
			} else {
				$ip = '127.0.0.1';
			}
			if (in_array($ip, ['::1', '0.0.0.0', 'localhost'], true)) {
				$ip = '127.0.0.1';
			}
			$filter = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
			if ($filter === false) {
				$ip = '127.0.0.1';
			}
		}

		return $ip;
	}


	public static function getLastErrorMessage(): ?string
	{
		$lastError = error_get_last();
		if ($lastError && isset($lastError['message'])) {
			return trim(
				(string) preg_replace(
					'/\s*\[<a[^>]+>[a-z0-9.\-_()]+<\/a>]\s*/i',
					' ',
					(string) $lastError['message'],
				),
			);
		}

		return null;
	}


	public static function functionIsAvailable(string $functionName): bool
	{
		static $disabled;
		if (\function_exists($functionName) === true) {
			$disableFunctions = ini_get('disable_functions');
			if ($disabled === null && $disableFunctions) {
				$disabled = explode(',', $disableFunctions);
			}

			return \in_array($functionName, $disabled, true) === false;
		}

		return false;
	}


	public static function setHttpStatus400(): void
	{
		self::checkHeaders(400);
		header('HTTP/1.1 400 Bad Request');
	}


	public static function setHttpStatus404(): void
	{
		self::checkHeaders(404);
		header('HTTP/1.1 404 Not Found');
	}


	public static function checkHeaders(int $statusCode): void
	{
		if (headers_sent($file, $line) === false) {
			return;
		}

		$fileCapture = '';
		if (is_file($file) === true) {
			$fileParser = explode("\n", str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($file)));
			$start = $line > 8 ? $line - 8 : 0;

			for ($i = $start; $i <= $start + 15; $i++) {
				if (isset($fileParser[$i]) === false) {
					break;
				}

				$fileCapture .= str_pad(' ' . ($i + 1) . ': ', 6, ' ')
					. str_replace("\t", '    ', $fileParser[$i])
					. ($line === $i + 1 ? ' <-------' : '') . "\n";
			}
		}

		throw new \RuntimeException(
			'Too late, headers already sent from "' . $file . '" on line #' . $line
			. "\n\n" . $fileCapture,
			$statusCode,
			new \RuntimeException($file, $line),
		);
	}
}
