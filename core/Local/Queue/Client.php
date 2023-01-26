<?php

namespace Local\Queue;

use Exception;

class Client {

	protected string
	$Host;

	protected int
	$Port;

	protected mixed
	$Socket;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(string $Host, int $Port) {

		$this->Host = $Host;
		$this->Port = $Port;

		return;
	}

	public function
	__Destruct() {

		if(is_resource($this->Socket))
		fclose($this->Socket);

		$this->Socket = NULL;

		return;
	}

	public function
	Connect():
	static {

		$this->Socket = @fsockopen($this->Host, $this->Port);

		if(!is_resource($this->Socket))
		throw new Error\ClientConnectFail($this->Host, $this->Port);

		// expect the welcome message.

		$Msg = Message::FromJSON(fgets($this->Socket));

		// ok go.

		return $this;
	}

	public function
	Send(string $Type, array $Payload=[]):
	Message {

		$Msg = Message::New(Type: $Type, Payload: $Payload);
		$Data = json_encode($Msg);

		fwrite($this->Socket, "{$Data}\n");
		$Resp = Message::FromJSON(fgets($this->Socket));

		return $Resp;
	}

}
