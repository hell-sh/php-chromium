<?php
namespace Chromium;
class DomNode
{
	public $page;
	public $id;

	function __construct(Page $page, int $id)
	{
		$this->page = $page;
		$this->id = $id;
	}

	function querySelector(string $selector, callable $callback): DomNode
	{
		return $this->exec("DOM.querySelector", [
			"selector" => $selector
		], function($result) use (&$callback)
		{
			$callback(new DomNode($this->page, $result["nodeId"]));
		});
	}

	function exec(string $method, array $params = [], $callback = null): DomNode
	{
		$params["nodeId"] = $this->id;
		$this->page->exec($method, $params, $callback);
		return $this;
	}

	function querySelectorAll(string $selector, callable $callback): DomNode
	{
		return $this->exec("DOM.querySelectorAll", [
			"selector" => $selector
		], function($result) use (&$callback)
		{
			$nodes = [];
			foreach($result["nodeIds"] as $nodeId)
			{
				array_push($nodes, new DomNode($this->page, $nodeId));
			}
			$callback($nodes);
		});
	}

	function getBoxModel(callable $callback): DomNode
	{
		return $this->exec("DOM.getBoxModel", [], function($result) use (&$callback)
		{
			$callback($result["model"]);
		});
	}
}
