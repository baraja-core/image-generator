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
		private ImageGenerator $imageGenerator,
		private Config $config,
	) {
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
		$absoluteSourceFilePath = $request->getSourceFileAbsolutePath();
		if ($absoluteSourceFilePath === null) {
			throw new \InvalidArgumentException(
				sprintf('Source file "%s" does not exist.', $request->getFilePath())
				. "\n" . Helper::getLastErrorMessage(),
				404,
			);
		}

		$fileSuffix = $request->getFileSuffix(true);

		$this->sourcePath = $absoluteSourceFilePath;
		$this->tempPath = sprintf('%s/_cache/_temp/%s', Helper::getWwwDir(), $fileSuffix);
		$this->cachePath = sprintf('%s/_cache/%s', Helper::getWwwDir(), $fileSuffix);
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
