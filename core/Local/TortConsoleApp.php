<?php

namespace Local;

use React;
use Nether\Console;
use Nether\Common;

use Phar;
use DateTime;
use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class TortConsoleApp
extends Console\Client {

	const
	AppName    = 'Tort',
	AppDesc    = 'PHP-CLI Wrapper for TorToiSe TTS',
	AppVersion = '1.0.2-dev',
	AppDebug   = TRUE;

	const
	RunModeNone = 0,
	RunModeText = 1,
	RunModeFile = 2;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	#[Console\Meta\Command('gen')]
	#[Console\Meta\Info('Generate text-to-speech.')]
	#[Console\Meta\Arg('text-in-quotes', 'What it should try to say.')]
	#[Console\Meta\Value('--outdir', 'Where to save generated files to. You can use placeholders as well: {hostname}, {voice}, {date}, {file}, and {label}')]
	#[Console\Meta\Value('--text', 'Text to say if not wanting to do it as a quoted argument.')]
	#[Console\Meta\Value('--file', 'A text file of stuff to say instead of CLI input.')]
	#[Console\Meta\Value('--lines', 'Runs each line of the --file as its own process. You can also do --lines=4 or --lines=2,4-6 to be picky.')]
	#[Console\Meta\Value('--seed', 'The seed for replicating runs.')]
	#[Console\Meta\Value('--voice', 'Which voice to use. The name of the directory within the voices folder.')]
	#[Console\Meta\Value('--count', 'How many sound files to render to experience different variations.')]
	#[Console\Meta\Value('--label', 'Just a text label to give this run to help remember. Gets added to the generated filenames and can be used in the outdir as well.')]
	#[Console\Meta\Value('--ppre', 'A prompt to prefix each prompt with using TorToiSe built-in prompt engineering.')]
	#[Console\Meta\Value('--iters', '[voice.conf] How many iterations to force the AI to suffer before rendering.')]
	#[Console\Meta\Value('--quality', '[voice.conf] One of the presets: ultra_fast, fast, standard, high_quality')]
	#[Console\Meta\Value('--top-p', '[voice.conf] Feels like it controls the range of pitch inflections allowed. Larger being a larger range. (0.0-1.0)')]
	#[Console\Meta\Value('--temper', '[voice.conf] Feels like it controls how wild of pitch inflections can be walked through the range. (0.0-1.0)')]
	#[Console\Meta\Value('--cond-str', '[voice.conf] Strength of the vocal conditioning by the model. (0.0-inf)')]
	#[Console\Meta\Value('--rep-pen', '[voice.conf] Higher means it tries harder to squash repeating sound and silences. (0.0-inf)')]
	#[Console\Meta\Value('--len-pen', '[voice.conf] Higher means it should try harder to be brief. (0.0-inf)')]
	#[Console\Meta\Value('--diff-temp', '[voice.conf] Claimed it controls how mushy something might sound. (0.0-1.0)')]
	#[Console\Meta\Toggle('--dry', 'Just pretend to do it.')]
	#[Console\Meta\Toggle('--keep-combined', 'Do not delete seemingly pointless _combined.wav file.')]
	#[Console\Meta\Toggle('--dont-rename', 'Do not try to save you from overwriting yourself.')]
	#[Console\Meta\Toggle('--derp', 'Just do it do not confirm if we are ready to run.')]
	#[Console\Meta\Error(1, 'file not found (%s)')]
	#[Console\Meta\Error(2, 'no --file or --text specified.')]
	#[Console\Meta\Error(3, 'no valid voices selected')]
	#[Console\Meta\Error(4, 'no conda env detected')]
	#[Console\Meta\Error(5, 'file not readable (%s)')]
	#[Console\Meta\Error(6, 'fun aborted')]
	#[Console\Meta\Error(7, 'unable to create output dir (%s)')]
	#[Console\Meta\Error(8, 'invalid conda env active: %s')]
	public function
	CmdGenerate():
	int {

		$Config = new TortConfigPackage(
			$this->GetLocalPath('tort.json'),
			$this->GetOption('job')
		);

		////////

		// fill in config values from cli input or defaulting them back
		// to what they were or what would be reasonable.

		$Config->Exec->TortoiseTTS = $this->GetOption('tbin') ?? $Config->Exec->TortoiseTTS;
		$Config->Exec->VoiceDir = $this->GetOption('vodir') ?? $Config->Exec->VoiceDir;
		$Config->Exec->OutputDir = $this->GetOption('outdir') ?? $Config->Exec->OutputDir;

		$Config->Exec->Text = $this->GetOption('text') ?? $this->GetInput(1) ?? $Config->Exec->Text;
		$Config->Exec->File = $this->GetOption('file') ?? $Config->Exec->File;
		$Config->Exec->Lines = $this->GetOption('lines') ?? $Config->Exec->Lines;
		$Config->Exec->Seed = $this->GetOption('seed') ?? $Config->Exec->Seed;
		$Config->Exec->Voice = $this->GetOption('voice') ?? $Config->App->DefaultVoice;
		$Config->Exec->GenCount = $this->GetOption('count') ?? $Config->Exec->GenCount;
		$Config->Exec->TextLenMin = $this->GetOption('len-min') ?? $Config->Exec->TextLenMin;
		$Config->Exec->TextLenMax = $this->GetOption('len-max') ?? $Config->Exec->TextLenMax;
		$Config->Exec->KeepCombined = $this->GetOption('keep-combined') ?? $Config->Exec->KeepCombined;
		$Config->Exec->DontRename = $this->GetOption('dont-rename') ?? $Config->Exec->DontRename;
		$Config->Exec->Label = $this->GetOption('label') ?? $Config->Exec->Label;
		$Config->Exec->PromptPre = $this->GetOption('ppre') ?? $Config->Exec->PromptPre;
		$Config->Exec->DryRun = $this->GetOption('dry') ?? $Config->Exec->DryRun;
		$Config->Exec->Derp = $this->GetOption('derp') ?? $Config->Exec->Derp;

		////////

		// inputs designed to be controlled by the voice json config unless
		// specifically overwritten by cli inputs.

		$Config->Voice->Quality = $this->GetOption('quality') ?? $Config->Voice->Quality;
		$Config->Voice->Iters = $this->GetOption('iters') ?? $Config->Voice->Iters;
		$Config->Voice->TopP = $this->GetOption('top-p') ?? $Config->Voice->TopP;
		$Config->Voice->CondStr = $this->GetOption('cond-str') ?? $Config->Voice->CondStr;
		$Config->Voice->RepPen = $this->GetOption('rep-pen') ?? $Config->Voice->RepPen;
		$Config->Voice->LenPen = $this->GetOption('len-pen') ?? $Config->Voice->LenPen;
		$Config->Voice->Temper = $this->GetOption('temper') ?? $Config->Voice->Temper;
		$Config->Voice->DiffTemper = $this->GetOption('diff-temper') ?? $Config->Voice->DiffTemper;

		////////

		// local use variables.

		$Verbose = $this->GetOption('verbose') ?? FALSE;
		$OutputJSON = $this->GetOption('--jsonout');
		$Commands = new Common\Datastore;
		$TortoiseTTS = NULL;
		$VoiceDir = NULL;
		$RunMode = static::RunModeText;
		$Text = NULL;
		$File = NULL;
		$Lines = NULL;

		$Cmd = NULL;
		$CmdIter = NULL;
		$Txt = NULL;
		$TxtIter = NULL;

		////////

		// handle reminding me that i need to use conda to make it work
		// else it will constantly be a pain.

		$this->HandleCheckingForConda($Config);

		// handle me constantly forgetting the batch flag when running
		// them from batch scripts.

		if(!$this->IsUserInteractable())
		$Config->Exec->Derp = TRUE;

		////////

		// handle generating default paths and data we can process
		// without changing the save state of the exec config.

		$TortoiseTTS = $this->GetLocalPath($Config->Exec->TortoiseTTS);
		$VoiceDir = $this->GetLocalPath($Config->Exec->VoiceDir);

		$OutputDir = $this->ReplacePathTokens(
			$this->GetLocalPath(
				$this->GetOption('outdir')
				?? $Config->Exec->OutputDir
				?? $Config->App->OutputDir
			),
			$Config
		);

		////////

		// check that we have something we want to render. we can take
		// text from the cli or text from a file.

		if($Config->Exec->Text === NULL)
		if($Config->Exec->File === NULL)
		$this->Quit(2);

		////////

		// process and mutate the text and file inputs for singular,
		// file, or per line running.

		list($Text, $File, $Lines) = $this->ProcessInputs($Config);

		if($File && !$Lines) {
			$RunMode = static::RunModeFile;
			$Text = new Common\Datastore([ $File ]);
		}

		////////

		// handle sanity checking for voices a little just to make life
		// nicer if you cant type. if we asked for a single voice it is
		// easy to check if a config file exists in its folder and merge
		// it into the current.

		$Voice = $this->ProcessVoiceChoice(
			$Config->Exec->VoiceDir,
			$Config->Exec->Voice
		);

		if($Voice->Count() === 0)
		$this->Quit(3);

		if($Voice->Count() === 1)
		$this->MergeArgsIntoVoiceConf($Config, $Voice);

		////////

		// sanity check some values that have been problematic with the
		// voice/run configuration. the configuration objects have been
		// taught about what has been found problematic so they can fix
		// temselves. exceptions will be raised about any warnings that
		// should be explained.

		try {
			$Config->Voice->TryToPreventDeath();
			$Config->Exec->TryToPreventDeath();
		}

		catch(Error\TextLengthWarning $Err) {
			$this->FormatLn(
				'%s%s%s',
				PHP_EOL,
				$this->Formatter->BrightRed('WARNING: '),
				$Err->GetMessage()
			);
		}

		////////

		// dump out a bunch of info about what is going to be done and
		// how it is going to do so.

		if($Text && !$File)
		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Input Text:'),
			$Text->Join(" / ")
		);

		if($File)
		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Input File:'),
			$File
		);

		$this
		->FormatLn('%s %s', $this->FormatPrimary('Output:'), $OutputDir)
		->FormatLn('%s %s', $this->FormatPrimary('Iteration Count:'), $Config->Voice->Iters)
		->FormatLn('%s %s', $this->FormatPrimary('Generation Count:'), $Config->Exec->GenCount)
		->FormatLn('%s %s', $this->FormatPrimary('Voice:'), $Voice->Join(', '))
		->FormatLn('%s %.2f', $this->FormatPrimary(' - TopP:'), $Config->Voice->TopP)
		->FormatLn('%s %.2f', $this->FormatPrimary(' - Temper:'), $Config->Voice->Temper)
		->FormatLn('%s %.2f', $this->FormatPrimary(' - DiffTemper:'), $Config->Voice->DiffTemper)
		->FormatLn('%s %.2f', $this->FormatPrimary(' - CondStr:'), $Config->Voice->CondStr)
		->FormatLn('%s %.2f', $this->FormatPrimary(' - LenPen:'), $Config->Voice->LenPen)
		->FormatLn('%s %.2f', $this->FormatPrimary(' - RepPen:'), $Config->Voice->RepPen)
		->PrintLn();

		////////

		// generate the super long crazy commands to the cli app given
		// what we can be smart about and what was asked for. note if an
		// tortoise arg has a short and long form option, only the short
		// form seems to work.

		foreach($Text as $TxtIter => $Txt) {
			if(!trim($Txt))
			continue;

			$Cmd = sprintf(
				(
					'python "%s" '.
					'-V "%s" -v "%s" '.
					'-O "%s" '.
					'--candidates %d '.
					'--text-split %d,%d '.
					'-p "%s" '.
					'--num-autoregressive-samples %d '.
					'--cond-free %d --cond-free-k %0.2f '.
					'--top-p %.2f '.
					'--temperature %.2f '.
					'--diffusion-temperature %.2f '.
					'--repetition-penalty %.2f '.
					'--length-penalty %.2f '
				),
				$TortoiseTTS,
				$VoiceDir, $Voice->Join(','),
				$OutputDir,
				$Config->Exec->GenCount,
				$Config->Exec->TextLenMin, $Config->Exec->TextLenMax,
				$Config->Voice->Quality,
				$Config->Voice->Iters,
				$Config->Voice->Cond, $Config->Voice->CondStr,
				$Config->Voice->TopP,
				$Config->Voice->Temper,
				$Config->Voice->DiffTemper,
				$Config->Voice->RepPen,
				$Config->Voice->LenPen
			);

			if($Config->Exec->Seed)
			$Cmd .= sprintf('--seed %s ', $Config->Exec->Seed);

			switch($RunMode) {
				case static::RunModeFile: {
					$Cmd .= sprintf('2>&1 < %s', $Txt);
					break;
				}
				default: {
					$Cmd .= sprintf('"%s" 2>&1', $Txt);
					break;
				}
			}

			$Commands[$TxtIter] = $Cmd;
		}

		////////

		// if we have a map of lines that we wanted generated then filter
		// out the skips leaving the indexing intact.

		if($Lines instanceof Common\Datastore)
		$Commands->Filter(
			fn(string $Cmd, int $CmdIter)
			=> $Lines->HasValue($CmdIter + 1)
		);

		////////

		// if we only wanted to pretend that we were going to do this
		// then pretend print it out and bail. also handle if we did not
		// pass in the skip confirm flag.

		if($Config->Exec->DryRun || !$Config->Exec->Derp) {

			if($Config->Exec->DryRun) {
				$this
				->PrintLn($this->Formatter->BrightRed('DRY RUN'))
				->PrintLn();
			}

			if($Config->Exec->DryRun || $Verbose) {
				$CurIter = 0;

				foreach($Commands as $CmdIter => $Cmd) {
					$CurIter++;

					$this->PrintLn($this->GetStatusPrefixedLine(
						'DimYellow',
						$CurIter,
						$Commands->Count(),
						($CmdIter + 1),
						$Config->Exec->Label,
						$Cmd
					));

					$this->PrintLn();
				}
			}

			if(!$Config->Exec->DryRun)
			if(!$Config->Exec->Derp) {
				$this->FormatLn(
					'%s%s',
					PHP_EOL,
					$this->FormatPrimary('Are you ready to rumble?')
				);

				if(strtolower($this->Prompt(NULL, '[y/n]')) !== 'y')
				$this->Quit(6);
			}

			if($Config->Exec->DryRun)
			return 0;
		}

		////////

		// make sure the output dir actually exists because of the lulz
		// mentioned earlier.

		if(!is_dir($OutputDir))
		mkdir($OutputDir, 0777, TRUE);

		if(!is_dir($OutputDir))
		$this->Quit(7, $OutputDir);

		////////

		// write the job file to disk so that the task can be replayed at
		// a later date if needed.

		$Config->Exec->Write(Common\Filesystem\Util::Pathify(
			$OutputDir,
			'job.json'
		));

		////////

		// now we can finally commit tortoise to doing some work having
		// hopefully increased the chances of success.

		$CurIter = 0;
		foreach($Commands as $CmdIter => $Cmd) {
			$CurIter++;

			$this->PrintLn($this->GetStatusPrefixedLine(
				'BrightCyan',
				$CurIter,
				$Commands->Count(),
				($CmdIter+1),
				$Config->Exec->Label,
				$Cmd
			));

			$this->PrintLn();
			$this->ExecuteTortoise(
				$CmdIter,
				$Cmd,
				$this->GetStatusPrefixedLine(
					NULL,
					$CurIter,
					$Commands->Count(),
					($CmdIter + 1),
					$Config->Exec->Label
				)
			);

			if(!$Config->Exec->KeepCombined)
			$this->DeleteCombinedFile($OutputDir);

			if(!$Config->Exec->DontRename)
			$this->RenameFilesFromRun($OutputDir, $CmdIter, $Config);

			$this->PrintLn();
			$this->PrintLn($this->FormatPrimary('Generation Done'));
			$this->PrintLn();
		}

		////////

		return 0;
	}

	#[Console\Meta\Command('voices')]
	#[Console\Meta\Info('Lists all the installed voices.')]
	public function
	CmdVoiceList():
	int {

		$Config = new TortConfigPackage;
		$VoiceDir = $this->GetLocalPath($Config->Exec->VoiceDir);
		$List = $this->GetVoices($Config->Exec->VoiceDir);
		$Voice = NULL;

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Voice Dir:'),
			$VoiceDir
		);

		$this->PrintLn($this->FormatPrimary(sprintf(
			'Installed Voices (%d):',
			$List->Count()
		)));

		foreach($List as $Voice) {
			$this->FormatLn(
				' %s %s',
				$this->FormatSecondary('*'),
				$Voice
			);
		}

		return 0;
	}

	#[Console\Meta\Command('voiceconf')]
	#[Console\Meta\Info('Generate a default voice.json file for a voice.')]
	#[Console\Meta\Arg('voice')]
	public function
	CmdVoiceConf():
	int {

		$Config = new TortConfigPackage;

		$Voice = $this->GetInput(1);
		$VoiceFile = $this->GetLocalPath(
			$Config->Exec->VoiceDir,
			$Voice,
			'voice.json'
		);

		if(!is_dir(dirname($VoiceFile)))
		$this->Quit(1);

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Voice Config:'),
			$VoiceFile
		);

		if(file_exists($VoiceFile))
		if(!$this->PromptEquals('Overwrite Existing?', '[y/n]', 'y')) {
			$this->PrintLn('Existing file has been spared.');
			return 0;
		}

		$VoiceConf = new TortVoiceConfig;
		$VoiceConf->Write($VoiceFile);

		return 0;
	}

	#[Console\Meta\Command('seqdir')]
	#[Console\Meta\Info('Resequence a directory of lines into folders one of each line in the folder. Without any toggle options it will just print a summary.')]
	#[Console\Meta\Arg('dir')]
	#[Console\Meta\Toggle('--sort', 'Sort the summary by how many files for each line there are.')]
	#[Console\Meta\Toggle('--copy', 'Copy files into sequenced set directories.')]
	#[Console\Meta\Toggle('--move', 'Move files into sequenced set sirectories.')]
	#[Console\Meta\Toggle('--shuffle', 'Shuffles the lines before distributing them into sequenced folders.')]
	#[Console\Meta\Error(1, 'no --dir specified')]
	#[Console\Meta\Error(2, 'directory not found %s')]
	public function
	CmdSequenceDir():
	int {

		$Key = NULL;
		$Count = NULL;

		$Path = $this->GetInput(1) ?? $this->GetOption('path');
		$Copy = $this->GetOption('copy') ?? FALSE;
		$Move = $this->GetOption('move') ?? FALSE;
		$Sort = $this->GetOption('sort') ?? FALSE;
		$Shuffle = $this->GetOption('shuffle') ?? FALSE;
		$Check = (!$Copy && !$Move);

		if(!$Path)
		$this->Quit(1);

		////////

		$Path = $this->GetLocalPath($Path);

		if(!is_dir($Path))
		$this->Quit(2, $Path);

		////////

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Sequencing Directory:'),
			$Path
		);

		$Tool = new TortDirectorySequencer(
			$Path,
			($Copy && !$Move),
			$Shuffle
		);

		$Files = $Tool->GetFiles();
		$Max = $Tool->CheckMaxSets($Files);
		$Counts = $Files->Map(fn($Line)=> $Line->Count());
		$Key = NULL;
		$Count = NULL;

		if($Sort)
		$Counts->Sort();

		$this->FormatLn(
			'%s %d',
			$this->FormatPrimary('Max Sets:'),
			$Max
		);

		foreach($Counts as $Key => $Count)
		$this->FormatLn('- %s: %s', $Key, $Count);

		////////

		if(!$Check)
		$Tool->Run();

		return 0;
	}

	#[Console\Meta\Command('auditdir')]
	#[Console\Meta\Info('Play all the sound files in a directory asking if you want to keep them. Requires the PlayerCmd to be set to a player capable of CLI playing in your tort.json.')]
	#[Console\Meta\Error(1, 'no PlayerCmd found in tort.json')]
	#[Console\Meta\Error(2, 'supplied path not valid.')]
	public function
	CmdAudit():
	int {

		$Config = new TortConfigPackage($this->GetLocalPath('tort.json'));
		$Path = $this->GetInput(1);

		$Audit = NULL;
		$Files = NULL;
		$Remnants = NULL;
		$Input = NULL;
		$File = NULL;
		$Iter = NULL;

		////////

		if(!$Config->App->PlayerCmd)
		$this->Quit(1);

		if(!$Path)
		$this->Quit(2);

		$Path = $this->GetLocalPath($Path);

		if(!is_dir($Path))
		$this->Quit(2);

		////////

		$Audit = new TortAudioAuditor($Config, $Path);
		$Files = $Audit->GetFiles();

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Directory:'),
			$Path
		);

		$this->FormatLn(
			'%s %d',
			$this->FormatPrimary('Found Files:'),
			$Files->Count()
		);

		$this->PrintLn();

		////////

		$Input = $this->Ask('Begin Auditing?', '[y/n]', FALSE);

		if($Input !== 'y')
		return 0;

		////////

		$Iter = 0;

		while($Iter < $Files->Count()) {
			$Input = NULL;
			$File = $Files[$Iter];

			$this->FormatLn(
				'%s %s',
				$this->FormatPrimary('Playing:'),
				$File
			);

			$Audit->Play($File);

			while(!in_array($Input, ['y', 'n', 'r', 'q'])) {
				$Input = $this->Ask('Keep?', '[y/n/r/q/?]', FALSE);

				if($Input === '?') {
					$this->FormatLn('y = yes, keep this file.');
					$this->FormatLn('n = no, do not keep this file.');
					$this->FormatLn('r = repeat this file.');
					$this->FormatLn('q = quit auditing.');
					$this->PrintLn();
				}
			}

			// user wants to repeat file.

			if($Input === 'r')
			continue;

			// user wants to bail.

			if($Input === 'q')
			$Iter = $Files->Count();

			// user wants to delete file.

			if($Input === 'n') {
				$this->FormatLn(
					'%s %s',
					$this->Formatter->DimMagenta('Delete:'),
					$File
				);

				$Audit->Delete($File);
				$this->PrintLn();
			}

			$Iter++;
		}

		$Remnants = $Audit->GetFiles();

		$this->FormatLn(
			'%s %d',
			$this->FormatPrimary('Remaining Files:'),
			$Remnants->Count()
		);

		////////

		return 0;
	}

	////////////////////////////////////////////////////////////////
	// queue management commands ///////////////////////////////////

	#[Console\Meta\Command('qs')]
	#[Console\Meta\Info('Run the queue server allowing for setting up many jobs to run in sequence. This is not needed to use this tool to run things via command or script. It is needed for the Web UI.')]
	#[Console\Meta\Value('--bind', 'Interface to bind to. Defaults to 127.0.0.1. Set to 0.0.0.0 for any.')]
	#[Console\Meta\Value('--fresh', 'Ignore what was in the queue file starting fresh.')]
	public function
	QueueServer():
	int {

		$Host = $this->GetOption('bind') ?? '127.0.0.1';
		$File = $this->GetLocalPath($this->GetOption('file') ?? 'queue.phson');
		$Fresh = $this->GetOption('fresh') ?? FALSE;
		$Config = new TortConfigPackage($this->GetLocalPath('tort.json'));

		$this->HandleCheckingForConda($Config);

		////////

		$Loop = React\EventLoop\Loop::Get();

		$Server = new Queue\Server(
			$this,
			Host: $Host,
			File: $File,
			Loop: $Loop
		);

		$Server->Start($Fresh);
		$Loop->Run();

		////////

		$Server->PrintLn('END OF LINE');

		return 0;
	}

	#[Console\Meta\Command('qstatus')]
	#[Console\Meta\Info('Ask the queue server for status update.')]
	#[Console\Meta\Toggle('--list', 'List the IDs of the jobs in the queue.')]
	#[Console\Meta\Toggle('--full', 'List the full info of jobs in the queue.')]
	#[Console\Meta\Value('--host', 'Queue server host name or IP.')]
	#[Console\Meta\Value('--port', 'Queue server port.')]
	#[Console\Meta\Error(1, 'unable to connect to queue server')]
	public function
	QueueStatus():
	int {

		$Job = NULL;
		$Client = $this->GetQueueClient();
		$ShowFull = $this->GetOption('full') ?? FALSE;
		$ShowList = $this->GetOption('list') ?? $ShowFull;

		if(!$Client)
		$this->Quit(1);

		////////

		$Msg = $Client->Send('status');
		$NumRunning = count($Msg->Payload['Running']);
		$NumQueued = count($Msg->Payload['Queued']);

		////////

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Status:'),
			$NumRunning ? 'Running' : 'Idle'
		);

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Running:'),
			$NumRunning
		);

		$this->FormatLn(
			'%s %s',
			$this->FormatPrimary('Queued:'),
			$NumQueued
		);

		if($NumRunning || $NumQueued)
		$this->PrintLn();

		foreach($Msg->Payload['Running'] as $Job) {
			$Job = Queue\ServerJob::FromJSON($Job);

			$this->FormatLn(
				'%s%s%s',
				$this->Formatter->BrightCyan("[{$Job->GetStatusWord()}] "),
				$this->Formatter->BrightGreen(
					$Job->StatusData instanceof TortJobStatus
					? sprintf(
						'[%d/%d %s it/s] ',
						$Job->StatusData->Step,
						$Job->StatusData->StepMax,
						$Job->StatusData->IterPerSec
					)
					: ''
				),
				$Job->ID
			);

			if(!$ShowFull)
			continue;

			$this->FormatLn(
				' - Payload: %s',
				json_encode($Job->Payload)
			);

			$this->FormatLn(
				' - Status: %s',
				json_encode($Job->StatusData)
			);

			$this->PrintLn();
		}

		$this->PrintLn();

		if(!$ShowList)
		return 0;

		foreach($Msg->Payload['Queued'] as $Job) {
			$Job = Queue\ServerJob::FromJSON($Job);

			$this->FormatLn(
				'%s %s',
				$this->Formatter->DimCyan("[{$Job->GetStatusWord()}]"),
				$Job->ID
			);

			if(!$ShowFull)
			continue;

			$this->FormatLn(
				' - Payload: %s',
				json_encode($Job->Payload)
			);

			$this->PrintLn();
		}

		return 0;
	}

	#[Console\Meta\Command('qpause')]
	#[Console\Meta\Info('Tell the queue to pause on running any new jobs.')]
	#[Console\Meta\Value('--host', 'Queue server host name or IP.')]
	#[Console\Meta\Value('--port', 'Queue server port.')]
	#[Console\Meta\Error(1, 'unable to connect to queue server')]
	public function
	QueuePause():
	int {

		$Client = $this->GetQueueClient();

		if(!$Client)
		$this->Quit(1);

		$Client->Send('pause');

		return 0;
	}

	#[Console\Meta\Command('qresume')]
	#[Console\Meta\Info('Tell the queue to resume running jobs.')]
	#[Console\Meta\Value('--host', 'Queue server host name or IP.')]
	#[Console\Meta\Value('--port', 'Queue server port.')]
	#[Console\Meta\Error(1, 'unable to connect to queue server')]
	public function
	QueueResume():
	int {

		$Client = $this->GetQueueClient();

		if(!$Client)
		$this->Quit(1);

		$Client->Send('resume');

		return 0;
	}

	#[Console\Meta\Command('qquit')]
	#[Console\Meta\Info('Tell the queue to terminate itself.')]
	#[Console\Meta\Toggle('--now', 'Do not wait for the current job to finish. Do it now.')]
	#[Console\Meta\Toggle('--abandon', 'Do not push any interuptted jobs back into the queue.')]
	public function
	QueueQuit():
	int {

		$Client = $this->GetQueueClient();
		$Force = $this->GetOption('now') ?? FALSE;
		$Abandon = $this->GetOption('abandon') ?? FALSE;

		if(!$Client)
		$this->Quit(1);

		$Client->Send('quit', [
			'force'   => $Force,
			'abandon' => $Abandon
		]);

		return 0;
	}

	#[Console\Meta\Command('qremove')]
	#[Console\Meta\Info('Remove the specified job from the queue.')]
	#[Console\Meta\Arg('job-id')]
	public function
	QueryRemove():
	int {

		return 0;
	}

	#[Console\Meta\Command('qgen')]
	#[Console\Meta\Info('Add a generation command to the queue.')]
	#[Console\Meta\Error(1, 'unable to connect to queue server')]
	#[Console\Meta\Value('--host', 'Queue server host name or IP.')]
	#[Console\Meta\Value('--port', 'Queue server port.')]
	public function
	QueueGenerate():
	int {

		$Client = $this->GetQueueClient();

		$Cmd = new Common\Datastore([ 'gen', '--derp', '--jsonout' ]);
		$Cmd->MergeRight(array_slice($_SERVER['argv'], 2));

		if(!$Client)
		$this->Quit(1);

		$Client->Send('cmd', [
			'Args' => $Cmd->GetData()
		]);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	// just devvy things ///////////////////////////////////////////

	#[Console\Meta\Command('phar', TRUE)]
	#[Console\Meta\Info('Compile a tort.phar for easy use/distribution.')]
	public function
	BuildPhar():
	int {

		$Item = NULL;

		$BaseDir = dirname(__FILE__, 3);
		$Index = $this->GetPharIndex();
		$OutFile = Common\Filesystem\Util::Pathify(
			$BaseDir, 'build', 'tort',
			'tort.phar'
		);

		if(!is_dir(dirname($OutFile)))
		mkdir(dirname($OutFile), 0777, TRUE);

		if(file_exists($OutFile))
		unlink($OutFile);

		////////

		$Phar = new Phar($OutFile);
		$Phar->StartBuffering();
		$Phar->SetDefaultStub('tort.php');
		$Phar->BuildFromIterator($Index, $BaseDir);
		$Phar->StopBuffering();

		////////

		$Check = new Phar($OutFile);

		foreach(new RecursiveIteratorIterator($Check) as $Item)
		$this->PrintLn(preg_replace(
			'#^phar:\/\/(?:.+).phar#', 'pharout://',
			$Item->GetPathName()
		));

		////////

		copy(
			Common\Filesystem\Util::Pathify($BaseDir, 'tort.json'),
			Common\Filesystem\Util::Pathify($BaseDir, 'build', 'tort', 'tort.json')
		);

		copy(
			Common\Filesystem\Util::Pathify($BaseDir, 'README.md'),
			Common\Filesystem\Util::Pathify($BaseDir, 'build', 'tort', 'README.md')
		);

		copy(
			Common\Filesystem\Util::Pathify($BaseDir, 'LICENSE.md'),
			Common\Filesystem\Util::Pathify($BaseDir, 'build', 'tort', 'LICENSE.md')
		);

		return 0;
	}

	#[Console\Meta\Command('qcmd', TRUE)]
	#[Console\Meta\Info('Send any Tort command to the queue.')]
	#[Console\Meta\Error(1, 'unable to connect to queue server')]
	#[Console\Meta\Value('--host', 'Queue server host name or IP.')]
	#[Console\Meta\Value('--port', 'Queue server port.')]
	public function
	QueueCommand():
	int {

		$Client = $this->GetQueueClient();

		if(!$Client)
		$this->Quit(1);

		$Msg = $Client->Send('cmd', [
			'Args' => array_slice($_SERVER['argv'], 2)
		]);

		print_r($Msg);

		return 0;
	}

	#[Console\Meta\Command('testlongtime', TRUE)]
	public function
	TestLongTime():
	int {

		$this->PrintLn('workin hard for 60sec');
		sleep(60);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Ask(string $Msg, string $Choices=NULL, ?bool $Case=FALSE):
	string {

		$Prompt = $this->Formatter->BrightMagenta($Msg);

		if($Choices)
		$Prompt .= " {$Choices}";

		////////

		$Input = $this->Prompt($Prompt, '>');

		$Input = match(TRUE) {
			$Case === TRUE
			=> strtoupper($Input),

			$Case === FALSE
			=> strtolower($Input),

			default
			=> $Input
		};

		////////

		return $Input;
	}

	protected function
	GetLocalPath(...$Argv):
	string {

		$Path = (
			str_starts_with(__FILE__, 'phar://')
			? dirname(Phar::Running(FALSE), 1)
			: dirname(__FILE__, 3)
		);

		$More = join('/', $Argv);

		////////

		// do not mess with unix absolute paths.

		if(str_starts_with($More, '/'))
		return $More;

		// do not mess with windows absolute paths.

		if(str_starts_with($More, '\\'))
		return Common\Filesystem\Util::Repath($More);

		if(preg_match('#^[A-Za-z]:\\\\#', $More))
		return Common\Filesystem\Util::Repath($More);

		////////

		$Path = Common\Filesystem\Util::Repath(
			Common\Filesystem\Util::Pathify($Path, $More)
		);

		return $Path;
	}

	protected function
	GetFileBasicName(string $File):
	string {

		$Base = basename($File);

		if(str_contains($Base, '.'))
		return explode('.', $Base, 2)[0];

		return $Base;
	}

	protected function
	GetFileBasicWithLinesName(?string $File, string|bool $Lines):
	?string {

		if(!$File)
		return NULL;

		if($File && is_string($Lines))
		return sprintf(
			'%s(%s)',
			$this->GetFileBasicName($File),
			$Lines
		);

		return $this->GetFileBasicName($File);
	}

	protected function
	ReplacePathTokens(string $Input, TortConfigPackage $Config):
	string {

		$Output = $Input;
		$Old = NULL;
		$New = NULL;

		$Map = [
			'{hostname}'  => strtolower(gethostname()),
			'{datestamp}' => (new DateTime)->Format($Config->App->OutputDatestamp),
			'{voice}'     => $Config->Exec->Voice,
			'{label}'     => $Config->Exec->Label,
			'{file}'      => $this->GetFileBasicWithLinesName(
				$Config->Exec->File,
				$Config->Exec->Lines
			)
		];

		foreach($Map as $Old => $New) {
			if(!$New) {
				$Output = preg_replace(
					sprintf('#[\-\_\.]*%s#', preg_quote($Old, '#')),
					'',
					$Output
				);
				continue;
			}

			$Output = str_replace($Old, $New, $Output);
		}

		return $Output;
	}

	protected function
	IsUserInteractable():
	bool {

		// if run from a script i want to disable interactions. this is how
		// bash like shells seem to do it.

		if(isset($_SERVER['SHLVL']) && $_SERVER['SHLVL'])
		return FALSE;

		// if run from a cron i want to disable interactions.

		if(!stream_isatty(STDIN))
		return FALSE;

		////////

		return TRUE;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	MergeArgsIntoVoiceConf(TortConfigPackage $Config, Common\Datastore $Voice):
	void {

		$VoiceConf = TortVoiceConfig::FromFile($this->GetLocalPath(
			$Config->Exec->VoiceDir,
			$Voice[0],
			'voice.json'
		));

		if($VoiceConf !== NULL) {
			if(!$this->HasOption('top-p'))
			$Config->Voice->TopP = $VoiceConf->TopP;

			if(!$this->HasOption('diff-temper'))
			$Config->Voice->DiffTemper = $VoiceConf->DiffTemper;

			if(!$this->HasOption('rep-pen'))
			$Config->Voice->RepPen = $VoiceConf->RepPen;

			if(!$this->HasOption('len-pen'))
			$Config->Voice->LenPen = $VoiceConf->LenPen;

			if(!$this->HasOption('iters'))
			$Config->Voice->Iters = $VoiceConf->Iters;

			if(!$this->HasOption('quality'))
			$Config->Voice->Quality = $VoiceConf->Quality;

			if(!$this->HasOption('cond-str'))
			$Config->Voice->CondStr = $VoiceConf->CondStr;

			if(!$this->HasOption('temper'))
			$Config->Voice->Temper = $VoiceConf->Temper;
		}

		return;
	}

	protected function
	ProcessInputs(TortConfigPackage $Config):
	array {

		$File = $Config->Exec->File;
		$Lines = $Config->Exec->Lines;
		$Text = $Config->Exec->Text;

		if($File) {
			$File = $this->ReplacePathTokens(
				$this->GetLocalPath($File),
				$Config
			);

			if(!file_exists($File))
			$this->Quit(1, $File);

			if(!is_readable($File))
			$this->Quit(5, $File);

			if(!$Lines)
			return [ NULL, $File, FALSE ];

			// if we asked for line processing then dump the contents
			// of that file into the text buffer.

			$Text = trim(file_get_contents($File));
		}

		if(is_string($Text)) {
			$Text = new Common\Datastore(explode("\n", $Text));

			// trim up the input.

			$Text->Remap(
				fn(string $Line)
				=> trim($Line)
			);

			// setup prompt engineering.

			if($Config->Exec->PromptPre)
			$Text->Remap(
				fn(string $Line)
				=> "[{$Config->Exec->PromptPre}] {$Line}"
			);

			// process line selections.

			if(is_string($Lines))
			$Lines = $this->ProcessLines($Lines);

			return [ $Text, $File, $Lines ];
		}

		return [ NULL, NULL, FALSE ];
	}

	protected function
	ProcessLines(string $Lines):
	Common\Datastore {

		$Output = new Common\Datastore;
		$Groups = explode(',', $Lines);
		$Title = '';
		$Group = NULL;
		$Min = NULL;
		$Max = NULL;

		foreach($Groups as $Group) {
			$Title .= sprintf(',%s', $Group);

			if(str_contains($Group, '-')) {
				list($Min, $Max) = explode('-', $Group, 2);
				$Output->MergeRight(range($Min, $Max));
				continue;
			}

			if(is_numeric($Group)) {
				$Output[] = (int)$Group;
				continue;
			}
		}

		$Output->SetTitle(trim($Title, ','));

		return $Output;
	}

	protected function
	ProcessVoiceChoice(string $VoiceDir, string $Voice):
	Common\Datastore {

		// handle digesting what was asked for.

		$Output = match($Voice) {
			'all'
			=> $this->GetVoices($VoiceDir),

			default
			=> new Common\Datastore(explode(',', $Voice))
		};

		// generate a dataset and do some minimal amount of sanity
		// checking on the voice folders.

		$Output
		->Remap(fn(string $Vo)=> trim($Vo))
		->Filter(
			fn(string $Vo)
			=> (
				FALSE
				|| str_contains($Vo, '&')
				|| is_dir($this->GetLocalPath($VoiceDir, $Vo))
			)
		);

		return $Output;
	}

	protected function
	DeleteCombinedFile(string $OutputDir):
	void {

		// for some reason if you set an output dir he saves a copy of
		// the first file generated as a "combined" file even though it
		// also saved all the candidates.

		$File = NULL;

		$Found = glob(Common\Filesystem\Util::Pathify(
			$OutputDir,
			'*_combined.wav'
		));

		foreach($Found as $File) {
			$this->FormatLn(
				'%s %s',
				$this->FormatPrimary('Cleanup:'),
				basename($File)
			);

			unlink($File);
		}

		return;
	}

	protected function
	RenameFilesFromRun(string $OutputDir, int $CmdIter, TortConfigPackage $Conf):
	void {

		$File = NULL;

		$New = NULL;
		$Dirname = NULL;
		$Basename = NULL;
		$OutputDir = $this->ReplacePathTokens($OutputDir, $Conf);
		$Found = glob(Common\Filesystem\Util::Pathify($OutputDir, '*.wav'));

		foreach($Found as $File) {
			$Dirname = dirname($File);
			$Basename = basename($File);

			if(preg_match('/^line\d+_/', $Basename))
			continue;

			////////

			$New = preg_replace(
				'/^(.+?)\.wav$/',
				sprintf(
					'line%\'03d%s_$1.wav',
					($CmdIter + 1),
					($Conf->Exec->Label
						? "_{$Conf->Exec->Label}"
						: ''
					)
				),
				$this->ReplacePathTokens($Basename, $Conf)
			);

			$this->FormatLn(
				'%s %s => %s',
				$this->FormatPrimary('Renaming:'),
				$Basename,
				$New
			);

			rename($File, Common\Filesystem\Util::Pathify(
				$Dirname,
				$New
			));
		}

		return;
	}

	protected function
	ExecuteTortoise(int $CmdIter, string $Cmd, string $Prefix):
	void {

		// this is a bunch of bullshit depending on the redirection of
		// stderr to stdout because windows is the worst and still does
		// not support non-blocking proc_open pipes in 2023. to be fair
		// the proc_open based code was three times as long but it was
		// better. this more or less only works because tortoise is
		// blasting its output in full bursts.

		$Data = NULL;
		$TortStep = 0;
		$TortStepMax = 3;
		$OutputJSON = $this->GetOption('jsonout');

		$PBarReg = '#(\d+)+/(\d+) \[([\d\:]+)<([\d\:]+), ([^\]]+)\]#';
		$PBarData = NULL;

		$PrefixData = $this->Formatter->DimCyan($Prefix);
		$PrefixTime = $this->Formatter->BrightCyan($Prefix);
		$PrefixProg = '';

		$PP = popen($Cmd, 'r');

		while($Data = fread($PP, 4096)) {
			$Data = trim($Data);

			$PrefixProg = $this->Formatter->BrightGreen(sprintf(
				'[%d/%d] ',
				$TortStep,
				$TortStepMax
			));

			// handle if we got something that smells like progress.
			if(preg_match($PBarReg, $Data, $PBarData)) {
				if($OutputJSON) {
					$Status = new TortJobStatus([
						'Step'        => $TortStep,
						'StepMax'     => $TortStepMax,
						'IterCur'     => $PBarData[1],
						'IterMax'     => $PBarData[2],
						'IterPerSec'  => $PBarData[5],
						'TimeSpent'   => $PBarData[3],
						'TimeETA'     => $PBarData[4]
					]);

					$this->PrintLn(json_encode($Status));
					continue;
				}

				printf(
					"\r%s%sIter %s of %s @ %s [%s, ETA %s]",
					$PrefixTime,
					$PrefixProg,
					$PBarData[1],
					$PBarData[2],
					$PBarData[5],
					$PBarData[3],
					$PBarData[4]
				);

				if($PBarData[1] === $PBarData[2])
				echo PHP_EOL;

				continue;
			}

			// else drain the data.
			while($Data) {
				if(str_contains($Data, "\n"))
				list($Next, $Data) = explode("\n", $Data, 2);
				else
				list($Next, $Data) = [ $Data, NULL ];

				$Next = trim($Next ?? '');

				// ignore that annoying errant progress.
				if(str_contains($Next, '?it/s'))
				continue;

				////////

				if(str_starts_with(strtolower($Next), 'loading tts.'))
				$TortStep = 0;

				if(str_starts_with(strtolower($Next), 'generating '))
				$TortStep = 1;

				if(str_starts_with(strtolower($Next), 'computing '))
				$TortStep = 2;

				if(str_starts_with(strtolower($Next), 'transforming '))
				$TortStep = 3;

				////////

				$PrefixProg = $this->Formatter->Green(sprintf(
					'[%d/%d] ',
					$TortStep,
					$TortStepMax
				));

				// treat anything else as printable.
				printf(
					'%s%s%s%s',
					$PrefixData,
					$PrefixProg,
					$Next,
					PHP_EOL
				);
			}
		}

		pclose($PP);

		return;
	}

	protected function
	GetVoices(string $VoiceDir):
	Common\Datastore {

		$List = new Common\Datastore(glob($this->GetLocalPath(
			$VoiceDir, '*'
		)));

		$List
		->Remap(fn(string $Path)=> basename($Path))
		->Sort();

		return $List;
	}

	protected function
	GetPharIndex():
	Common\Datastore {

		$Dirs = [ 'core', 'vendor' ];
		$Files = [ 'tort.php', 'composer.json', 'composer.lock' ];
		$Index = new Common\Datastore;
		$DS = DIRECTORY_SEPARATOR;

		$Dir = NULL;
		$File = NULL;
		$Iter = NULL;
		$Item = NULL;
		$ItemPath = NULL;

		foreach($Dirs as $Dir) {
			$Iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($Dir)
			);

			foreach($Iter as $Item) {
				/** @var SplFileInfo $Item */

				if(!$Item->IsFile())
				continue;

				$ItemPath = $Item->GetPathName();
				$ItemName = $Item->GetBasename();

				if(str_contains($ItemPath, "{$DS}tests{$DS}"))
				continue;

				if(str_contains($ItemPath, "{$DS}vendor{$DS}bin{$DS}"))
				continue;

				if(str_contains($ItemPath, 'php_codesniffer'))
				continue;

				if(str_contains($ItemPath, '.git'))
				continue;

				if(str_contains($ItemPath, '.vscode'))
				continue;

				if(str_starts_with($ItemName, 'phpunit'))
				continue;

				if(str_starts_with($ItemName, 'phpcs'))
				continue;

				$Index->Push($Item);
			}
		}

		foreach($Files as $File) {
			$Index->Push(new SplFileInfo($File));
		}

		return $Index;
	}

	protected function
	GetStatusPrefixedLine(?string $Colour, int $Current, int $Total, int $LineNum, ?string $Label=NULL, ?string $Info=NULL):
	string {

		$Output = sprintf(
			'%s[%d/%d] [Line %d] ',
			($Label ? "[{$Label}] ": ''),
			$Current,
			$Total,
			$LineNum
		);

		if($Colour)
		$Output = $this->Formatter->{$Colour}($Output);

		if($Info)
		$Output .= $Info;

		return $Output;
	}

	protected function
	GetQueueClient():
	?Queue\Client {

		$Host = $this->GetOption('host') ?? '127.0.0.1';
		$Port = $this->GetOption('port') ?? 42001;

		$Client = new Queue\Client($Host, $Port);

		try { $Client->Connect(); }
		catch(Queue\Error\ClientConnectFail $Err) {
			return NULL;
		}

		return $Client;
	}

	protected function
	HandleCheckingForConda(TortConfigPackage $Config):
	void {

		if(!$Config->App->CheckForConda)
		return;

		// check that any conda env is active.

		if(!isset($_ENV['CONDA_DEFAULT_ENV']))
		if(!isset($_SERVER['CONDA_DEFAULT_ENV']))
		$this->Quit(4);

		// check that a specific conda env is active.

		if(!is_string($Config->App->CheckForConda))
		return;

		if(isset($_ENV['CONDA_DEFAULT_ENV']))
		if($_ENV['CONDA_DEFAULT_ENV'] !== $Config->App->CheckForConda)
		$this->Quit(8, $_ENV['CONDA_DEFAULT_ENV']);

		if(isset($_SERVER['CONDA_DEFAULT_ENV']))
		if($_SERVER['CONDA_DEFAULT_ENV'] !== $Config->App->CheckForConda)
		$this->Quit(8, $_SERVER['CONDA_DEFAULT_ENV']);

		return;
	}

}

