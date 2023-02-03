<?php

namespace Local\Queue;

use Nether\Console;

use Throwable;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;
use Nether\Common\Datastore;

class Server {

	const
	RunStateOn         = 1,
	RunStatePauseAfter = 2,
	RunStateQuitAfter  = 3;

	public int
	$RunState = 1;

	public Console\Client
	$CLI;

	public Datastore
	$Queue;

	public Datastore
	$Running;

	public int
	$MaxRunning = 1;

	public ?LoopInterface
	$Loop;

	public bool
	$Debug = FALSE;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected string
	$Host;

	protected int
	$Port;

	protected SocketServer
	$Server;

	protected Datastore
	$Clients;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(Console\Client $CLI, string $Host='127.0.0.1', int $Port=42001, ?LoopInterface $Loop=NULL) {

		$this->Host = $Host;
		$this->Port = $Port;
		$this->CLI = $CLI;
		$this->Loop = $Loop ?? Loop::Get();

		$this->Clients = new Datastore;
		$this->Queue = new Datastore;
		$this->Running = new Datastore;

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Run():
	void {

		$URI = sprintf('%s:%d', $this->Host, $this->Port);

		////////

		$this->Server = new SocketServer($URI, loop: $this->Loop);

		($this->Server)
		->On('connection', $this->OnConnect(...))
		->On('error', $this->OnError(...));

		////////

		$this->FormatLn(
			'%s %s',
			$this->CLI->FormatSecondary('Server Started:'),
			$URI
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Kick():
	static {

		if($this->RunState === static::RunStatePauseAfter) {
			return $this;
		}

		if($this->RunState === static::RunStateQuitAfter) {
			if($this->Running->Count() === 0)
			$this->Server->Close();

			return $this;
		}

		if($this->Running->Count() < $this->MaxRunning)
		if($this->Queue->Count() > 0)
		$this->Next();

		return $this;
	}

	public function
	Next():
	static {

		$Job = $this->Queue->Shift();

		switch($Job->Type) {
			case 'cmd':
				$Job->Run();
			break;
		}

		return $this;
	}

	public function
	Push(Message $Msg):
	ServerJob {

		$Job = ServerJob::FromServerMessage($this, $Msg);

		$this->Queue->Push($Job);

		$this->FormatLn(
			'%s %s',
			$this->CLI->FormatSecondary('Job Queued:'),
			$Job->ID
		);

		$this->Kick();

		return $Job;
	}

	public function
	Quit():
	static {

		$this->Server->Close();

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	OnConnect(ConnectionInterface $Connect):
	void {

		$RemoteAddr = $Connect->GetRemoteAddress();
		$Client = new ServerClient($this, $Connect);

		$this->Clients->Shove($RemoteAddr, $Client);
		//$this->FormatLn('client connected %s', $RemoteAddr);

		$Client->Send('sup');

		return;
	}

	public function
	OnDisconnect(ConnectionInterface $Connect):
	void {

		$RemoteAddr = $Connect->GetRemoteAddress();

		unset($this->Clients[$RemoteAddr]);

		//$this->FormatLn('client disconnect %s', $RemoteAddr);

		return;
	}

	public function
	OnError(Throwable $Error):
	void {

		$this->CLI->FormatLn(
			'error: %s',
			$Error->GetMessage()
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	PrintLn($Message):
	static {

		$Message = sprintf(
			'%s %s',
			$this->CLI->FormatPrimary('[TortQS]'),
			$Message
		);

		$this->CLI->PrintLn($Message);
		return $this;
	}

	public function
	FormatLn($Format, ...$Argv):
	static {

		$Format = sprintf(
			'%s %s',
			$this->CLI->FormatPrimary('[TortQS]'),
			$Format
		);

		$this->CLI->FormatLn($Format, ...$Argv);
		return $this;
	}

}
