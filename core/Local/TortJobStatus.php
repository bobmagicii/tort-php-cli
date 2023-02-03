<?php

namespace Local;

use React;
use Nether\Common;

use Exception;

class TortJobStatus
extends Common\Prototype {

	public int
	$Step;

	public int
	$StepMax;

	public int
	$IterCur;

	public float
	$IterPerSec;

	public string
	$TimeSpent;

	public string
	$TimeETA;

}
