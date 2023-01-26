<?php

namespace Local\Queue;

use React;
use Nether\Common;

use Exception;

class ServerJob
extends Common\Prototype {

	public string
	$ID;

	public string
	$Type = 'none';

	public array|Common\Datastore
	$Payload = [];

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

		$this->Server->Running->Shove(
			$this->ID,
			[ 'Job'=> $this, 'Process'=> $Proc ]
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

		$this->Server->FormatLn(
			'%s %s',
			$this->Server->CLI->FormatSecondary('Job Data:'),
			$this->ID
		);

		$this->Server->FormatLn(' ^ %s', $Data);

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

		$this->Server->Kick();

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromMessage(Server $Server, Message $Msg):
	static {

		return static::New(
			Server: $Server,
			ID: $Msg->ID,
			Type: $Msg->Type,
			Payload: $Msg->Payload
		);
	}

}
