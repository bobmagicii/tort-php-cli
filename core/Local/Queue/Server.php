<?php

namespace Local\Queue;

use Throwable;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use React\ChildProcess\Process;
use React\Socket\ConnectionInterface;
use Nether\Common\Datastore;
use Nether\Console\Client;

class Server {

	protected string
	$Host;

	protected int
	$Port;

	protected Client
	$CLI;

	protected SocketServer
	$Server;

	protected Datastore
	$Clients;

	protected Datastore
	$Queue;

	protected int
	$Running = 0;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected ?LoopInterface
	$Loop;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(Client $CLI, string $Host='127.0.0.1', int $Port=42001, ?LoopInterface $Loop=NULL) {

		$this->Host = $Host;
		$this->Port = $Port;
		$this->CLI = $CLI;
		$this->Loop = $Loop;

		$this->Clients = new Datastore;
		$this->Queue = new Datastore;

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
			'started %s',
			$URI
		);

		return;
	}

	public function
	GetQueueStatus():
	array {

		return [
			'Pending' => $this->Queue->Count()
		];
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Kick():
	static {

		if($this->Running < 1)
		if($this->Queue->Count() > 0) {
			$this->FormatLn('%d things need doed', $this->Queue->Count());
			$this->Next();
		}

		return $this;
	}

	public function
	Next():
	static {

		$Job = $this->Queue->Pop();
		/** @var Message $Job */

		$this->FormatLn(
			'starting job: %s',
			json_encode($Job)
		);


		switch($Job->Type) {
			case 'cmd':
				$Cmd = sprintf(
					'php %s %s',
					realpath($_SERVER['SCRIPT_FILENAME']),
					join(' ', $Job->Payload['Args'])
				);

				$Proc = new Process(
					$Cmd,
					NULL,
					NULL,
					[ [ 'socket' ], [ 'socket' ], [ 'socket' ] ]
				);

				$Proc->Start($this->Loop);

				if($Proc->IsRunning()) {
					$this->Running += 1;
					$this->PrintLn('job start');

					$Proc->stdout->on('data', function(string $Data){
						$Data = trim($Data);
						$this->PrintLn("data: {$Data}");
						return;
					});

					$Proc->on('exit', function(){
						$this->Running -= 1;
						$this->PrintLn('done');
						$this->Kick();
						return;
					});
				}

			break;
		}

		return $this;
	}

	public function
	Push(mixed $Job):
	static {

		$this->Queue->Push($Job);

		$this->FormatLn(
			'added job: %s (queue size: %d)',
			json_encode($Job),
			$this->Queue->Count()
		);

		$this->Kick();

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
