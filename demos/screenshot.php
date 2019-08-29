<?php
require "../vendor/autoload.php";
use pac\
{Chromium, Page};
$c = new Chromium();
iF(!$c->isAvailable())
{
	$c->download();
}
$i = $c->start(false);
$i->logging = true;
$i->newPage(function(Page $page) use (&$i)
{
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
					$i->close();
				});
			});
		});
	})
		 ->navigate("https://stackoverflow.com/");
});
while($i->isRunning())
{
	$i->handle();
}
