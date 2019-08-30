<?php
namespace pac;
class Page
{
	public $ci;
	public $sessionId;
	public $targetId;

	function __construct(ChromiumInstance $ci, string $sessionId, string $targetId)
	{
		$this->ci = $ci;
		$this->sessionId = $sessionId;
		$this->targetId = $targetId;
	}

	function on(string $event, callable $function): Page
	{
		$this->ci->event_handlers[$this->sessionId.":".$event][] = [
			$function,
			false
		];
		return $this;
	}

	function once(string $event, callable $function): Page
	{
		$this->ci->event_handlers[$this->sessionId.":".$event][] = [
			$function,
			true
		];
		return $this;
	}

	function setDeviceMetrics(int $width, int $height, float $scale_factor = 1, $callback = null): Page
	{
		return $this->exec("Emulation.setDeviceMetricsOverride", [
			"mobile" => false,
			"width" => $width,
			"height" => $height,
			"deviceScaleFactor" => $scale_factor
		], $callback);
	}

	function exec(string $method, array $params = [], $callback = null): Page
	{
		$this->ci->exec($method, $params, $callback, $this->sessionId);
		return $this;
	}

	function navigate(string $url, $callback = null): Page
	{
		return $this->exec("Page.navigate", [
			"url" => $url
		], $callback);
	}

	/**
	 * Captures a screenshot of the page.
	 *
	 * @param string $format The image format. Either "png" or "jpeg:&lt;quality&gt;" where <code>&lt;quality&gt;</code> is an integer between 0 and 100.
	 * @param array $clip If provided, the screenshot will only include the given region. The array must have `x`, `y`, `width`, `height`, and `scale`.
	 * @param callable $callback The function to be called with the base64-encoded image as argument.
	 * @return Page $this
	 */
	function captureScreenshot(string $format, array $clip, callable $callback): Page
	{
		$args = [
			"format" => "png"
		];
		if(substr($format, 0, 5) == "jpeg:")
		{
			$quality = intval(substr($format, 5));
			if($quality >= 0 && $quality <= 100)
			{
				$args = [
					"format" => "jpeg",
					"quality" => $quality
				];
			}
		}
		if(!empty($clip))
		{
			$args["clip"] = $clip;
		}
		return $this->exec("Page.captureScreenshot", $args, function($result) use (&$callback)
		{
			$callback($result["data"]);
		});
	}

	function getLayoutMetrics(callable $callback): Page
	{
		return $this->exec("Page.getLayoutMetrics", [], $callback);
	}

	function setDocumentContent(string $html, $callback = null): Page
	{
		return $this->getFrameTree(function($result) use (&$html, &$callback)
		{
			$this->exec("Page.setDocumentContent", [
				"frameId" => $result["frame"]["id"],
				"html" => $html
			], $callback);
		});
	}

	function getFrameTree(callable $callback): Page
	{
		return $this->exec("Page.getFrameTree", [], function($result) use (&$callback)
		{
			$callback($result["frameTree"]);
		});
	}

	function getDocument(callable $callback): Page
	{
		return $this->exec("DOM.getDocument", [
			"depth" => 0
		], function($result) use (&$callback)
		{
			$callback(new DomNode($this, $result["root"]["nodeId"]));
		});
	}

	function close($callback = null)
	{
		$this->exec("Page.close", [], $callback);
	}
}
