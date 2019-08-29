<?php
namespace pac;
use paws\
{ServerConnection, TextFrame};
class ChromiumInstance
{
	public $proc;
	public $pipes;
	public $socket;
	public $counter = 0;
	public $logging = false;
	public $callbacks = [];
	public $event_handlers = [];

	function __construct(string $executable, bool $headless = true, bool $disable_gpu = false)
	{
		$cmd = "\"$executable\" --remote-debugging-port=0";
		if($headless)
		{
			$cmd .= " --headless";
		}
		if($disable_gpu)
		{
			$cmd .= " --disable-gpu";
		}
		$this->proc = proc_open($cmd, [
			[
				"pipe",
				"r"
			],
			[
				"pipe",
				"w"
			],
			[
				"pipe",
				"w"
			]
		], $this->pipes);
		$out = fread($this->pipes[2], 1024);
		$this->socket = new ServerConnection(trim(explode("\n", substr($out, strpos($out, "ws://")))[0]));
	}

	function on(string $event, callable $function): ChromiumInstance
	{
		$this->event_handlers[$event][] = [
			$function,
			false
		];
		return $this;
	}

	function once(string $event, callable $function): ChromiumInstance
	{
		$this->event_handlers[$event][] = [
			$function,
			true
		];
		return $this;
	}

	function handle(): ChromiumInstance
	{
		while($frame = $this->socket->readFrame(0))
		{
			if($frame instanceof TextFrame)
			{
				if($this->logging)
				{
					echo "> {$frame->data}\n";
				}
				$json = json_decode($frame->data, true);
				if(isset($json["id"]))
				{
					if(array_key_exists($json["id"], $this->callbacks))
					{
						$this->callbacks[$json["id"]]($json["result"]);
						unset($this->callbacks[$json["id"]]);
					}
				}
				else if(isset($json["method"]))
				{
					$params = $json["params"] ?? [];
					if(array_key_exists($json["method"], $this->event_handlers))
					{
						foreach($this->event_handlers[$json["method"]] as $i => $handler)
						{
							$handler[0]($params);
							if($handler[1])
							{
								unset($this->event_handlers[$json["method"]][$i]);
							}
						}
					}
					if(isset($json["sessionId"]))
					{
						$key = $json["sessionId"].":".$json["method"];
						if(array_key_exists($key, $this->event_handlers))
						{
							foreach($this->event_handlers[$key] as $i => $handler)
							{
								$handler[0]($params);
								if($handler[1])
								{
									unset($this->event_handlers[$key][$i]);
								}
							}
						}
					}
				}
			}
		}
		return $this;
	}

	function newPage(callable $callback): ChromiumInstance
	{
		$this->exec("Target.createTarget", [
			"url" => "about:blank"
		], function($result) use (&$callback)
		{
			$targetId = $result["targetId"];
			$this->exec("Target.attachToTarget", [
				"targetId" => $result["targetId"],
				"flatten" => true
			], function($result) use (&$callback, &$targetId)
			{
				$page = new Page($this, $result["sessionId"], $targetId);
				$page->exec("Page.enable", [], function() use (&$callback, &$page)
				{
					$callback($page);
				});
			});
		});
		return $this;
	}

	function exec(string $method, array $params = [], $callback = null, string $sessionId = ""): ChromiumInstance
	{
		$json = [
			"id" => ++$this->counter,
			"method" => $method
		];
		if($sessionId != "")
		{
			$json["sessionId"] = $sessionId;
		}
		if(!empty($params))
		{
			$json["params"] = $params;
		}
		if(is_callable($callback))
		{
			$this->callbacks[$this->counter] = $callback;
		}
		$json = json_encode($json);
		if($this->logging)
		{
			echo "< {$json}\n";
		}
		$this->socket->writeFrame(new TextFrame($json))
					 ->flush();
		return $this;
	}

	function isRunning(): bool
	{
		return proc_get_status($this->proc)["running"];
	}

	function close($callback = null)
	{
		$this->exec("Browser.close", [], $callback);
	}
}
