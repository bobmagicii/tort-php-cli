<?php

namespace Local\Queue;

use React;
use Nether\Common;

use Exception;
use Local\TortJobStatus;

class ServerJob
extends Common\Prototype {

	const
	StatusPending = 0,
	StatusLoading = 1,
	StatusRunning = 2,
	StatusDone    = 3;

	const
	StatusWords = [
		self::StatusPending => 'Pending',
		self::StatusLoading => 'Loading',
		self::StatusRunning => 'Running',
		self::StatusDone    => 'Done'
	];

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public string
	$ID;

	public string
	$Type = 'none';

	public array|Common\Datastore
	$Payload = [];

	public int
	$Status = self::StatusPending;

	public ?TortJobStatus
	$StatusData = NULL;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected Server
	$Server;

	protected React\ChildProcess\Process
	$Process;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	OnReady(Common\Prototype\ConstructArgs $Args):
	void {

		if(!isset($this->ID))
		$this->ID = Common\UUID::V4();

		$this->Payload = match(TRUE) {
			isset($this->Payload) && is_array($this->Payload)
			=> new Common\Datastore($this->Payload),

			isset($this->Payload) && $this->Payload instanceof Common\Datastore
			=> $this->Payload,

			default
			=> new Common\Datastore([])
		};

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	GetStatusWord():
	string {

		if(array_key_exists($this->Status, static::StatusWords))
		return static::StatusWords[$this->Status];

		return 'Unknown';
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Run():
	static {

		// format the tort cli command.

		$Cmd = sprintf(
			'php %s %s',
			realpath($_SERVER['SCRIPT_FILENAME']),
			join(' ', $this->Payload['Args'])
		);

		// spin up the process.

		$Proc = new React\ChildProcess\Process(
			$Cmd, NULL, NULL,
			[ [ 'socket' ], [ 'socket' ], [ 'socket' ] ]
		);

		$Proc->Start($this->Server->Loop);

		if(!$Proc->IsRunning())
		throw new Exception('failed to launch process');

		// rig up the event handlers.

		$Proc->stdout->on('data', $this->OnProcessData(...));
		$Proc->on('exit', $this->OnProcessExit(...));

		// tell the server its running.

		$this->Status = static::StatusLoading;

		$this->Server->Running->Shove(
			$this->ID,
			new ServerProcess($this, $Proc)
		);

		$this->Server->FormatLn(
			'%s %s',
			$this->Server->CLI->FormatSecondary('Job Start:'),
			$this->ID
		);

		return $this;
	}

	public function
	OnProcessData(string $Data):
	void {

		$Data = trim($Data);

		if(str_starts_with($Data, '{"Step":')) {
			$this->OnProcessGenProgress($Data);
			return;
		}

		////////

		if($this->Server->Debug) {
			$this->Server->FormatLn(
				'%s %s',
				$this->Server->CLI->FormatSecondary('Job Data:'),
				$this->ID
			);

			$this->Server->FormatLn(' ^ %s', $Data);
		}

		return;
	}

	protected function
	OnProcessGenProgress($Data):
	void {

		if($this->Status === static::StatusLoading) {
			$this->Status = static::StatusRunning;
			$this->Server->FormatLn(
				'%s %s',
				$this->Server->CLI->FormatSecondary('Generation Begin:'),
				$this->ID
			);
		}

		$this->StatusData = new TortJobStatus(json_decode($Data));

		//$this->Server->PrintLn(json_encode($this->Status));

		return;
	}

	public function
	OnProcessExit():
	void {

		unset($this->Server->Running[$this->ID]);

		$this->Server->FormatLn(
			'%s %s',
			$this->Server->CLI->FormatSecondary('Job Done:'),
			$this->ID
		);

		$this->Status = static::StatusDone;
		$this->StatusData = NULL;

		$this->Server->Kick();

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromServerMessage(Server $Server, Message $Msg):
	static {

		return static::New(
			Server: $Server,
			ID: $Msg->ID,
			Type: $Msg->Type,
			Payload: $Msg->Payload
		);
	}

	static public function
	FromJSON(string $Data):
	static {

		$Data = json_decode($Data, TRUE);

		$Output = static::New(
			ID: $Data['ID'],
			Type: $Data['Type'],
			Payload: $Data['Payload'] ?? [],
			Status: $Data['Status'],
			StatusData: (
				$Data['StatusData']
				? new TortJobStatus($Data['StatusData'])
				: NULL
			)
		);

		return $Output;
	}

}
