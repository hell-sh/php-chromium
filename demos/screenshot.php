<?php
if(empty($argv[1]))
{
	die("Syntax: php screenshot.php <url>");
}
require "../vendor/autoload.php";
use Chromium\
{Chromium, Page};
use Asyncore\Asyncore;
$c = new Chromium();
iF(!$c->isAvailable())
{
	echo "Downloading Chromium...";
	$c->download();
	echo " Done.\n";
}
$i = $c->start(false);
//$i->logging = true;
$i->newPage(function(Page $page) use (&$i)
{
	global $argv;
	$page->once("Page.frameStoppedLoading", function() use (&$page)
	{
		$page->getLayoutMetrics(function($result) use (&$page)
		{
			$page->setDeviceMetrics($result["contentSize"]["width"], $result["contentSize"]["height"], 1, function() use (&$page)
			{
				$page->captureScreenshot("png", [], function($data)
				{
					global $i;
					file_put_contents("screenshot.png", base64_decode($data));
					echo "Screenshot saved to screenshot.png.\n";
					$i->close();
				});
			});
		});
	})
		 ->navigate($argv[1]);
});
Asyncore::loop();
