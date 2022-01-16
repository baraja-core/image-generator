<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Baraja\Url\Url;
use Nette\Application\BadRequestException;
use Nette\Utils\FileSystem;

final class Image
{
	private bool $debugMode = false;

	private string $sourcePath;

	private string $tempPath;

	private ?string $canonicalPath = null;

	private string $cachePath;


	public function __construct(
		private string $rootDir,
		private ImageGenerator $imageGenerator,
		private Config $config,
	) {
		$this->rootDir = rtrim($rootDir, '/');
	}


	/** @internal used by DIC */
	public function setDebugMode(bool $debugMode): void
	{
		$this->debugMode = $debugMode;
	}


	/**
	 * @throws BadRequestException|\ErrorException
	 */
	public function run(ImageRequest $request): void
	{
		if ($this->hashVerify($request->getHash(), $request->getParams()) === false) {
			throw new \LogicException('Invalid request.');
		}
		$this->setupPaths($request);

		if (is_file($this->tempPath) === true) { // is file in temp?
			$this->waitForFileInCache();
		}
		clearstatcache();
		if (is_file($this->cachePath) === true) { // is file in cache?
			$this->sendFileFromCache();
		}

		$this->prepareCacheAndTempDir($this->tempPath, $this->cachePath);
		$this->copySourceFileToTemp($this->sourcePath, $this->tempPath);
		$this->removeTransparentBackground($this->tempPath);

		$this->imageGenerator->generate(
			ImageGeneratorRequest::createFromParams($request->getParams()),
			$this->tempPath,
			$this->cachePath,
		);

		if (str_contains(PHP_OS_FAMILY, 'WIN')) { // Win bug
			$this->removeFileFromTemp();
		}

		$this->sendFileFromCache();
	}


	private function hashVerify(string $hashFromUser, string $params): bool
	{
		$hashFromParams = Helper::generateHash($params);
		if ($hashFromParams !== $hashFromUser) {
			if ($this->debugMode === true) {
				$url = preg_replace(
					'/_(.{6})(\..+)$/',
					'_' . $hashFromParams . '$2',
					Url::get()->getCurrentUrl(),
				);

				Helper::checkHeaders(301);
				header('HTTP/1.1 301 Moved Permanently');
				header('Location: ' . $url);
				header('Connection: close');
				echo 'Redirecting to <a href="' . $url . '">' . $url . '</a>.';
				die;
			}

			return false;
		}

		return true;
	}


	private function setupPaths(ImageRequest $request): void
	{
		$fileName = $request->getBasename()
			. '__' . $request->getParams()
			. '_' . $request->getHash()
			. '.' . $request->getExtension();

		$filePath = $request->getDirname()
			. '/' . $request->getBasename()
			. '.' . $request->getExtension();

		$absoluteFileDirPath = (string) preg_replace(
			'@/+@',
			'/',
			$this->rootDir . '/www/' . str_replace(
				$request->getBasename() . '.' . $request->getExtension(),
				'',
				$filePath,
			),
		);

		$absoluteFilePath = null;
		if (is_dir($absoluteFileDirPath) === true) {
			$fileList = scandir($absoluteFileDirPath, 1);
			foreach (is_array($fileList) ? $fileList : [] as $item) {
				if (
					((string) preg_replace('@\.(?<extension>[a-zA-Z]+)$@', '', $item) === $request->getBasename())
					&& ($absoluteFilePath === null || str_ends_with($item, $request->getExtension()))
				) {
					$absoluteFilePath = (string) preg_replace(
						'@/+@',
						'/',
						$this->rootDir . '/www/'
						. str_replace($request->getBasename() . '.' . $request->getExtension(), '', $filePath)
						. '/' . $item,
					);
				}
			}
		}
		if (
			$absoluteFilePath === null
			&& $request->getParams() !== ''
			&& str_contains($request->getDirname(), '..') === false
		) {
			$urls = [];
			foreach (['jpg', 'png', 'gif'] as $ext) {
				$urls[$ext] = sprintf('%s/%s/%s/%s',
					Url::get()->getBaseUrl(),
					$request->getDirname(),
					$request->getBasename(),
					$ext,
				);
			}

			foreach ($urls as $url) {
				$cacheDownloadFilePath = sprintf('%s/www/_cache/_downloaded/%s/%s',
					$this->rootDir,
					$request->getDirname(),
					basename($url),
				);
				$this->createDir(dirname($cacheDownloadFilePath));

				try {
					if (is_file($cacheDownloadFilePath) === false) {
						if (@file_put_contents($cacheDownloadFilePath, $this->getImageContentFromUrl($url, 2)) === false) {
							throw new \RuntimeException(sprintf('Image has been downloaded from URL "%s", but unable to save into cache: %s',
								$url,
								Helper::getLastErrorMessage(),
							));
						}
						$absoluteFilePath = $cacheDownloadFilePath;
					} elseif (filesize($cacheDownloadFilePath) > 1) {
						$absoluteFilePath = $cacheDownloadFilePath;
						break;
					}
				} catch (\Throwable) {
					@file_put_contents($cacheDownloadFilePath, '');
					continue;
				}
			}
		}

		$fileSuffix = $request->getDirname()
			. '/' . $request->getBasename()
			. '.' . $request->getExtension();

		$cachePath = '_cache/' . $fileSuffix;
		$tempPath = '_cache/_temp/' . $fileSuffix;
		$absoluteTempPath = $this->rootDir . '/www/'
			. str_replace(
				$request->getBasename() . '.' . $request->getExtension(),
				'',
				$tempPath,
			);
		$absoluteTempFilePath = $absoluteTempPath . '/' . $fileName;
		$absoluteCachePath = $this->rootDir . '/www/'
			. str_replace(
				$request->getBasename() . '.' . $request->getExtension(),
				'',
				$cachePath,
			);

		$absoluteCacheFilePath = $absoluteCachePath . '/' . $fileName;

		if ($absoluteFilePath !== null) {
			$this->sourcePath = $absoluteFilePath;
			$this->tempPath = (string) preg_replace('@/+@', '/', $absoluteTempFilePath);
			$this->cachePath = (string) preg_replace('@/+@', '/', $absoluteCacheFilePath);
		} else {
			throw new \ErrorException(
				'Source file "' . $filePath . '" does not exist.'
				. "\n" . Helper::getLastErrorMessage(),
				404,
			);
		}
	}


	/**
	 * Load and return binary image data from the specified URL, or throw an exception.
	 *
	 * @param int $minSize Minimum allowed image size in Bytes (when the code is 200, but the image is still smaller, it throws an exception).
	 */
	private function getImageContentFromUrl(string $url, int $timeout = 1, int $minSize = 20): string
	{
		curl_setopt_array(
			$ch = curl_init(),
			[
				CURLOPT_URL => $url,
				CURLOPT_HEADER => false,
				CURLOPT_MAXREDIRS => 2,
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT']
					?? 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 '
					. '(KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11',
			],
		);

		$return = (string) curl_exec($ch);
		$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$httpStatusCode = is_numeric($httpStatusCode) ? (int) $httpStatusCode : 500;
		curl_close($ch);

		if ($httpStatusCode !== 200 || $return === '' || \strlen($return) < $minSize) {
			throw new \RuntimeException(
				sprintf('Image on URL does not exist (HTTP code: #%d, responseSize: %d)',
					$httpStatusCode,
					strlen($return),
				),
				$httpStatusCode === 0 ? 404 : $httpStatusCode,
			);
		}

		return $return;
	}


	private function waitForFileInCache(): void
	{
		for ($i = 0; $i <= 5; $i++) {
			clearstatcache();
			if ($i > 0) {
				sleep(1);
			}
			if (@is_file($this->cachePath)) {
				return;
			}
		}

		clearstatcache();
		if (is_file($this->tempPath) === true) {
			$tempFileTime = filemtime($this->tempPath);
			if ($tempFileTime !== false && abs(time() - $tempFileTime) > 30) {
				@unlink($this->tempPath);
				clearstatcache();
			}
		}
	}


	/**
	 * @throws BadRequestException
	 */
	private function sendFileFromCache(): void
	{
		clearstatcache();
		$loadPath = $this->canonicalPath ?? $this->cachePath;
		if (is_file($loadPath) === true) {
			header('Pragma: public');
			header('Cache-Control: max-age=86400' . (Helper::isLocalhost() ? '' : ', immutable, public'));
			header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime('12 hours')));
			header('Content-type: ' . $this->getContentTypeByFilename($this->cachePath));
			usleep(1000);

			$file = file_get_contents($loadPath);
			if ($file !== false) {
				echo $file;
				die;
			}

			throw new BadRequestException(
				'Can not load file on path "' . $file . '". '
				. 'Loaded path: "' . $loadPath . '".' . "\n" . Helper::getLastErrorMessage(),
			);
		}

		throw new BadRequestException(sprintf('File "%s" is not in cache path. %s',
			$loadPath,
			Helper::getLastErrorMessage(),
		));
	}


	/** @return string (image/jpeg, image/png, image/gif) */
	private function getContentTypeByFilename(string $cachePath): string
	{
		if (str_ends_with($cachePath, '.png')) {
			$contentType = 'image/png';
		} elseif (str_ends_with($cachePath, '.gif')) {
			$contentType = 'image/gif';
		} else {
			$contentType = 'image/jpeg';
		}

		return $contentType;
	}


	private function prepareCacheAndTempDir(string $tempPath, string $cachePath): void
	{
		$this->createDir((string) preg_replace('/\/[a-zA-Z0-9-_.]+$/', '', $tempPath));
		$this->createDir((string) preg_replace('/\/[a-zA-Z0-9-_.]+$/', '', $cachePath));
	}


	private function copySourceFileToTemp(string $sourcePath, string $tempPath): void
	{
		$this->createDir(dirname($tempPath));
		if (!@copy($sourcePath, $tempPath)) {
			throw new \RuntimeException(
				'File can not be copped to temp. '
				. '"' . $sourcePath . '" => "' . $tempPath . '": '
				. Helper::getLastErrorMessage(),
			);
		}
		clearstatcache();
	}


	private function removeTransparentBackground(string $path): void
	{
		if (substr($path, -4) !== '.png') {
			return;
		}

		@ini_set('memory_limit', '256M');
		$defaultColor = $this->config->getDefaultBackgroundColor();

		/** @var \GdImage $src */
		$src = imagecreatefrompng($path);
		$width = imagesx($src);
		$height = imagesy($src);
		/** @var \GdImage $bg */
		$bg = imagecreatetruecolor($width, $height);
		$backgroundColor = (int) imagecolorallocate($bg, $defaultColor[0], $defaultColor[1], $defaultColor[2]);
		imagefill($bg, 0, 0, $backgroundColor);
		imagecopyresampled($bg, $src, 0, 0, 0, 0, $width, $height, $width, $height);
		imagepng($bg, $path, 0);

		clearstatcache();
	}


	private function removeFileFromTemp(): void
	{
		$this->findSimilarImageBySameHash();
		unlink($this->tempPath);
	}


	/**
	 * Different parameter values can produce the same output.
	 * This method tries to find the same image and create a symlink to save disk space.
	 */
	private function findSimilarImageBySameHash(): void
	{
		$md5CacheFile = md5_file($this->cachePath);
		$pathInfo = pathinfo($this->cachePath);
		$pathInfoList = scandir($pathInfo['dirname'], 1);
		foreach (is_array($pathInfoList) ? $pathInfoList : [] as $item) {
			if (
				preg_match(
					'/^(?<filename>.+)(?<suffix>_(?<md5>.{32})\.md5)$/',
					$item,
					$parser,
				) === 1
				&& $parser['md5'] === $md5CacheFile
			) {
				unlink($this->cachePath);
				$this->canonicalPath = $pathInfo['dirname'] . '/' . $parser['filename'];
				symlink(basename($this->canonicalPath), $this->cachePath);

				return;
			}
		}

		FileSystem::write($this->cachePath . '_' . $md5CacheFile . '.md5', '');
	}


	/** Safe create dir and fix permissions. */
	private function createDir(string $path): void
	{
		if (is_dir($path) === true) {
			$this->fixDirPerms($path);

			return;
		}

		$parts = [];
		$partPath = $path;
		while (true) {
			if (is_dir($partPath) === false) {
				$parts[] = $partPath;
				$partPath = dirname($partPath);
			} else {
				break;
			}
		}
		foreach (array_reverse($parts) as $part) {
			FileSystem::createDir($part);
			$this->fixDirPerms($part);
		}
	}


	/** Minimal permission is 5 or 7 for group, because other process must write some image data. */
	private function fixDirPerms(string $path): void
	{
		$p = decoct(fileperms($path) & 0777)[1] ?? '';
		if ($p !== '5' && $p !== '7' && @chmod($path, 0664) === false) {
			throw new \RuntimeException(
				'Can not set directory permission. '
				. 'Directory "' . $path . '" given: ' . Helper::getLastErrorMessage(),
			);
		}
	}
}
