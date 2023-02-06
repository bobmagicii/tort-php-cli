<?php

namespace Local\Queue;

use React;
use Nether\Console;
use Nether\Common;

use Throwable;

class Server {

	const
	RunStateOn         = 1,
	RunStatePauseAfter = 2,
	RunStateQuitAfter  = 3;

	public int
	$RunState = 1;

	public Console\Client
	$CLI;

	public Common\Datastore
	$Queue;

	public Common\Datastore
	$Running;

	public int
	$MaxRunning = 1;

	public ?React\EventLoop\LoopInterface
	$Loop;

	public bool
	$Debug = FALSE;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected string
	$Host;

	protected int
	$Port;

	protected React\Socket\SocketServer
	$Server;

	protected Common\Datastore
	$Clients;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(
		Console\Client $CLI,
		string $Host='127.0.0.1',
		int $Port=42001,
		string $File='queue.phson',
		?React\EventLoop\LoopInterface $Loop=NULL
	) {

		$this->Host = $Host;
		$this->Port = $Port;
		$this->CLI = $CLI;
		$this->Loop = $Loop ?? React\EventLoop\Loop::Get();

		$this->Clients = new Common\Datastore;
		$this->Queue = new Common\Datastore;
		$this->Running = new Common\Datastore;

		////////

		if(!str_ends_with($File, '.phson'))
		$File = "{$File}.phson";

		$this->Queue->SetFilename($File);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Start(bool $Fresh=FALSE):
	void {

		$URI = sprintf('%s:%d', $this->Host, $this->Port);

		////////

		$this->Server = new React\Socket\SocketServer($URI, loop: $this->Loop);

		($this->Server)
		->On('connection', $this->OnConnect(...))
		->On('error', $this->OnError(...));

		////////

		if(!file_exists($this->Queue->GetFilename()) || $Fresh)
		$this->Queue->Write();

		$this->Queue->Read();
		$this->Queue->Each(
			fn(ServerJob $Job)
			=> $Job->Server = $this
		);

		$this->FormatLn(
			'%s %s (%d jobs(s))',
			$this->CLI->FormatSecondary('Loaded Queue File:'),
			$this->Queue->GetFilename(),
			$this->Queue->Count()
		);

		$this->Loop->AddTimer(0.25, $this->OnTimerKick(...));

		////////

		$this->FormatLn(
			'%s %s',
			$this->CLI->FormatSecondary('Server Started:'),
			$URI
		);

		return;
	}

	public function
	Stop():
	static {

		$this->Server->Close();
		$this->Queue->Write();

		return $this;
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
			$this->Stop();

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
		$this->Queue->Write();

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
		$this->Queue->Write();

		$this->FormatLn(
			'%s %s',
			$this->CLI->FormatSecondary('Job Queued:'),
			$Job->ID
		);

		$this->Kick();

		return $Job;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	OnConnect(React\Socket\ConnectionInterface $Connect):
	void {

		$RemoteAddr = $Connect->GetRemoteAddress();
		$Client = new ServerClient($this, $Connect);

		$this->Clients->Shove($RemoteAddr, $Client);
		//$this->FormatLn('client connected %s', $RemoteAddr);

		$Client->Send('sup');

		return;
	}

	public function
	OnDisconnect(React\Socket\ConnectionInterface $Connect):
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

	public function
	OnTimerKick(React\EventLoop\TimerInterface $Timer):
	void {

		$this->Kick();

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
