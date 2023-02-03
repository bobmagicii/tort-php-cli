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

}
