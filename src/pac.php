<?php
namespace pac;
class pac
{
	const REVS_DIRECTORY = __DIR__."/../revs/";

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
}
