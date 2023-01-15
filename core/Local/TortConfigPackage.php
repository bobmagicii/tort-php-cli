<?php

namespace Local;

class TortConfigPackage {

	public function
	__Construct(?string $AppPath=NULL, ?string $ExecPath=NULL, ?string $VoicePath=NULL) {

		if($AppPath)
		$this->App = TortConsoleConfig::FromFile($AppPath);

		////////

		if($ExecPath && file_exists($ExecPath))
		$this->Exec = TortExecConfig::FromFile($ExecPath);
		else
		$this->Exec = new TortExecConfig;

		////////

		if($VoicePath && file_exists($VoicePath))
		$this->Voice = TortVoiceConfig::FromFile($VoicePath);
		else
		$this->Voice = new TortVoiceConfig;

		return;
	}

	public ?TortConsoleConfig
	$App = NULL;

	public ?TortExecConfig
	$Exec = NULL;

	public ?TortVoiceConfig
	$Voice = NULL;

}
