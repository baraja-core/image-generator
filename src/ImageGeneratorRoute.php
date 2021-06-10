<?php

declare(strict_types=1);

namespace Baraja\ImageGenerator;


use Nette\Http\Request;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

final class ImageGeneratorRoute
{
	public const PATTERN = '/(?:(?<dirname>.+)\/)?'
	. '(?<basename>[^\/]+)'
	. '__(?<params>(?:w\d+|h\d+)[^\/]+)'
	. '_(?<hash>[a-z0-9]{6})'
	. '\.(?<extension>(?:[jJ][pP][eE]?[gG]|[pP][nN][gG]|[gG][iI][fF]))/';


	public static function renderPlaceholder(string $params, ?string $message = null): void
	{
		if (preg_match('/^(?:w(?<width>\d+))?(?:h(?<height>\d+))?/', $params, $paramsParser)) {
			$width = (int) ($paramsParser['width'] ?? 300);
			$height = (int) ($paramsParser['height'] ?? 300);
		} else {
			$width = 300;
			$height = 300;
		}
		if ($width < 64) {
			$width = 64;
		}
		if ($height < 64) {
			$height = 64;
		}

		$background = explode(',', self::hex2rgb('B'));
		$color = explode(',', self::hex2rgb('0'));
		header('Content-Type: image/png');

		if (function_exists('imagecreate') === false) {
			throw new \RuntimeException('Function imagecreate() does not exist. Did you install GD extension?');
		}
		$image = @imagecreate($width, $height);
		if ($image === false) {
			throw new \RuntimeException('Image must be "source": ' . Helper::getLastErrorMessage());
		}

		imagecolorallocate($image, (int) $background[0], (int) $background[1], (int) $background[2]);
		$textColor = (int) imagecolorallocate($image, (int) $color[0], (int) $color[1], (int) $color[2]);
		$text = $width . 'x' . $height;
		$x = ($width - strlen($text) * 9) / 2;
		$y = ($height - strlen($text) * 8) / 2;

		if ($message !== null && self::isDebugRequest(false)) {
			imagestring($image, 3, (int) $x, (int) ($y - 5), $text, $textColor);

			$message = Strings::toAscii($message);
			$line = (int) ($width / 7);

			for ($i = 0; preg_match('/^(.{' . $line . '})(.*)$/', $message, $messageParser); $i++) {
				imagestring($image, 2, 8, (int) ($y + 10 + $i * 15), $messageParser[1], $textColor);
				if ($i > 30 || trim($message = $messageParser[2]) === '') {
					break;
				}
			}
		} else {
			imagestring($image, 5, (int) $x, (int) $y, $text, $textColor);
			imagestring($image, 2, (int) $x, (int) ($y + 20), 'Image generator', $textColor);
		}

		imagepng($image);
		imagedestroy($image);
		die;
	}


	private static function hex2rgb(string $hex): string
	{
		$hex = str_replace('#', '', $hex);
		$len = strlen($hex);
		if ($len === 1) {
			$hex .= $hex;
		} elseif ($len === 2) {
			$r = hexdec($hex);
			$g = hexdec($hex);
			$b = hexdec($hex);
		} elseif ($len === 3) {
			$r = hexdec($hex[0] . $hex[0]);
			$g = hexdec($hex[1] . $hex[1]);
			$b = hexdec($hex[2] . $hex[2]);
		}
		if (!isset($r, $g, $b)) {
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
		}

		return implode(',', [$r, $g, $b]);
	}


	private static function isDebugRequest(bool $debugParam = true): bool
	{
		if (Debugger::$productionMode === Debugger::DEVELOPMENT) {
			return !($debugParam === true) || isset($_GET['debug']);
		}

		return false;
	}


	public function run(Request $request, Image $image): void
	{
		preg_match(
			self::PATTERN,
			$request->getUrl()->getRelativeUrl(),
			$routeParser,
		);

		[, $dirname, $basename, $params, $hash, $extension] = $routeParser;

		try {
			session_write_close();
			ignore_user_abort(true);
			$image->run(new ImageRequest($dirname, $basename, $params, $hash, $extension));
		} catch (\ErrorException $e) {
			Helper::setHttpStatus404();
			self::renderPlaceholder($params, $e->getMessage());
		} catch (\Throwable $e) {
			$error = self::isDebugRequest() === true
				? $e->getMessage()
				: (string) preg_replace('/^[^:]*?:\s+/', '', $e->getMessage());

			if (self::isDebugRequest()) {
				throw $e;
			}

			Debugger::log($e, ILogger::CRITICAL);

			$statusCode = $e->getCode();
			if ($statusCode === 404) {
				Helper::setHttpStatus404();
			} else {
				Helper::setHttpStatus400();
			}
			if (!self::isDebugRequest()) {
				self::renderPlaceholder($params, $e->getMessage());
			}
			if ($e instanceof \Exception) {
				throw new \ErrorException($error, $statusCode);
			}

			throw $e;
		}
	}
}
