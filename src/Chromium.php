<?php
namespace Chromium;
use RuntimeException;
use ZipArchive;
class Chromium
{
	const REVS_DIRECTORY = __DIR__."/../revs/";
	const LATEST_REVISION = 706915;
	const DOWNLOAD_URL_BASE = "https://storage.googleapis.com/chromium-browser-snapshots/";
	/**
	 * @var int $revision
	 */
	private $revision;

	function __construct(int $revision = self::LATEST_REVISION)
	{
		$this->revision = $revision;
	}

	function canBeDownloaded(): bool
	{
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $this->getDownloadUrl(),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => "HEAD",
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => true
		]);
		if(defined("PHP_WINDOWS_VERSION_MAJOR"))
		{
			curl_setopt($ch, CURLOPT_CAINFO, __DIR__."/cacert.pem");
		}
		curl_exec($ch);
		$res = (curl_getinfo($ch)["http_code"] === 200);
		curl_close($ch);
		return $res;
	}

	function getDownloadUrl(): string
	{
		if(stristr(PHP_OS, "LINUX"))
		{
			return self::DOWNLOAD_URL_BASE."Linux_x64/{$this->revision}/".$this->getArchiveName().".zip";
		}
		else if(stristr(PHP_OS, "DAR"))
		{
			return self::DOWNLOAD_URL_BASE."Mac/{$this->revision}/".$this->getArchiveName().".zip";
		}
		else if(defined("PHP_WINDOWS_VERSION_MAJOR"))
		{
			return self::DOWNLOAD_URL_BASE.(PHP_INT_SIZE == 8 ? "Win_x64" : "Win")."/{$this->revision}/".$this->getArchiveName().".zip";
		}
		throw new RuntimeException("Couldn't identify operating system");
	}

	function getArchiveName(): string
	{
		if(stristr(PHP_OS, "LINUX"))
		{
			return "chrome-linux";
		}
		else if(stristr(PHP_OS, "DAR"))
		{
			return "chrome-mac";
		}
		else if(defined("PHP_WINDOWS_VERSION_MAJOR"))
		{
			return "chrome-".($this->revision > 591479 ? "win" : "win32");
		}
		throw new RuntimeException("Couldn't identify operating system");
	}

	function download()
	{
		$dir = self::REVS_DIRECTORY.$this->revision;
		if(is_dir(self::REVS_DIRECTORY))
		{
			if(is_dir($dir))
			{
				self::recursivelyDelete($dir);
			}
		}
		else
		{
			mkdir(self::REVS_DIRECTORY);
		}
		mkdir($dir);
		$zip_file = self::REVS_DIRECTORY.$this->revision."/".$this->getArchiveName().".zip";
		$fh = fopen($zip_file, "w");
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $this->getDownloadUrl(),
			CURLOPT_FILE => $fh
		]);
		if(defined("PHP_WINDOWS_VERSION_MAJOR"))
		{
			curl_setopt($ch, CURLOPT_CAINFO, __DIR__."/cacert.pem");
		}
		if(!curl_exec($ch))
		{
			throw new RuntimeException("Chromium download failed: ".curl_error($ch)." (".curl_errno($ch).")");
		}
		curl_close($ch);
		$zip = new ZipArchive();
		$res = $zip->open($zip_file);
		if($res !== true)
		{
			throw new RuntimeException("Failed to open Chromium zip: Error code ".$res);
		}
		if(!$zip->extractTo($dir))
		{
			throw new RuntimeException("Failed to extract Chromium");
		}
		$zip->close();
		unlink($zip_file);
	}

	/**
	 * Recursively deletes a folder.
	 *
	 * @param string $path
	 */
	static function recursivelyDelete(string $path)
	{
		if(substr($path, -1) == "/")
		{
			$path = substr($path, 0, -1);
		}
		if(!file_exists($path))
		{
			return;
		}
		if(is_dir($path))
		{
			foreach(scandir($path) as $file)
			{
				if(!in_array($file, [
					".",
					".."
				]))
				{
					self::recursivelyDelete($path."/".$file);
				}
			}
			rmdir($path);
		}
		else
		{
			unlink($path);
		}
	}

	function isAvailable(): bool
	{
		return is_dir(self::REVS_DIRECTORY.$this->revision."/".$this->getArchiveName());
	}

	function start(bool $headless = true, bool $disable_gpu = false): Instance
	{
		return new Instance($this->getExecutable(), $headless, $disable_gpu);
	}

	function getExecutable(): string
	{
		if(stristr(PHP_OS, "LINUX"))
		{
			return realpath(self::REVS_DIRECTORY.$this->revision."/".$this->getArchiveName()."/chrome");
		}
		else if(stristr(PHP_OS, "DAR"))
		{
			return realpath(self::REVS_DIRECTORY.$this->revision."/".$this->getArchiveName()."/Chromium.app/Contents/MacOS/Chromium");
		}
		else if(defined("PHP_WINDOWS_VERSION_MAJOR"))
		{
			return realpath(self::REVS_DIRECTORY.$this->revision."/".$this->getArchiveName()."/chrome.exe");
		}
		throw new RuntimeException("Couldn't identify operating system");
	}
}
