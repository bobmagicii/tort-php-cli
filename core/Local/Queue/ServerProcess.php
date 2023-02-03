<?php

namespace Local\Queue;

use React;

class ServerProcess {

	public ServerJob
	$Job;

	public React\ChildProcess\Process
	$Process;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	__Construct(ServerJob $Job, React\ChildProcess\Process $Proc) {

		$this->Job = $Job;
		$this->Process = $Proc;

		return;
	}

	public function
	Quit():
	void {

		$Pipe = NULL;

		foreach ($this->Process->pipes as $Pipe)
		$Pipe->close();

		$this->Process->Terminate();

		return;
	}

}
