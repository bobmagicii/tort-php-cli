<?php

namespace Local\Queue;

use React;

use Throwable;

class ServerClient {

	const
	MsgTypeMap = [
		'cmd'    => 'OnMsgQueueCommand',
		'status' => 'OnMsgQueryStatus',
		'list'   => 'OnMsgQueryList'
	];

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected Server
	$Server;

	protected React\Socket\Connection
	$Socket;

	protected string
	$Buffer = '';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(Server $Server, React\Socket\Connection $Connect) {

		$this->Server = $Server;
		$this->Socket = $Connect;

		($this->Socket)
		->On('data', $this->OnData(...))
		->On('close', $this->OnClose(...))
		->On('error', $this->OnError(...));

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Send(string $Type, array $Payload=[]):
	static {

		$this->Socket->Write(sprintf(
			'%s%s',
			json_encode([ 'Type'=> $Type, 'Payload'=> $Payload ]),
			"\n"
		));

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	OnData(string $Data):
	void {

		// the default for linux clients seems to be sending entire
		// lines at once. the default for windows clients seem to be
		// sending each character at once. so fill a buffer.

		// one goofy thing about this is that if you ever use the backspace
		// from the telnet client it will end up being malformed but i do
		// not think i care rn.

		$this->Buffer .= $Data;

		// then try to drain the buffer.

		while(str_contains($this->Buffer, "\n")) {
			$Cmd = NULL;
			$Data = NULL;
			$Msg = NULL;

			// handle the chopping of a command and skip anything that
			// ended up empty.

			list($Cmd, $this->Buffer) = explode("\n", $this->Buffer, 2);
			$Cmd = trim($Cmd);

			if(!$Cmd)
			continue;

			// try to parse the command as one we want to handle and skip
			// anything that came across as garbage.

			$Data = json_decode($Cmd, TRUE);

			if(!is_array($Data)) {
				$this->Server->FormatLn(
					'client cmd malformed: %s',
					$Cmd
				);

				continue;
			}

			// then finally we can try to handle the message that came
			// in as something that needs doneoing.

			$Msg = new Message($Data);

			if(array_key_exists($Msg->Type, static::MsgTypeMap)) {
				$this->{static::MsgTypeMap[$Msg->Type]}($Msg);
				continue;
			}

			$this->OnMsgUnhandled($Msg);
		}

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	OnClose():
	void {

		$this->Server->OnDisconnect($this->Socket);
		return;
	}

	public function
	OnError(Throwable $Error):
	void {

		$this->Server->FormatLn(
			'client err: %s',
			$Error->GetMessage()
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	OnMsgQueueCommand(Message $Msg):
	void {

		$Job = $this->Server->Push($Msg);

		$this->Send('job', [
			'ID'=> $Job->ID,
			'Payload'=> $Job->Payload
		]);

		return;
	}

	protected function
	OnMsgQueryStatus(Message $Msg):
	void {

		$Running = (
			$this->Server->Running
			->Map(fn(array $Entry)=> [ 'ID'=> $Entry['Job']->ID, 'Payload'=> $Entry['Job']->Payload ])
			->Values()
		);

		$Queued = (
			$this->Server->Queue
			->Map(fn(ServerJob $Job)=> [ 'ID'=> $Job->ID, 'Payload'=> $Job->Payload ])
			->Values()
		);

		$this->Send('status', [
			'Running' => $Running,
			'Queued'  => $Queued
		]);

		return;
	}

	protected function
	OnMsgQueryList(Message $Msg):
	void {

		$this->Send('list', [
			'Running' => $this->Server->Running->GetData(),
			'Queue' => $this->Server->Queue->GetData()
		]);

		return;
	}

	protected function
	OnMsgUnhandled(Message $Msg):
	void {

		$this->Server->FormatLn(
			'unhandled msg: %s %s',
			$Msg->Type,
			json_encode($Msg->Payload)
		);

		$this->Send('unhandled');
		return;
	}

}
