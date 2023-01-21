<?php

namespace Local;

use Nether\Console;

use Phar;
use DateTime;
use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Nether\Object\Datastore;

class TortConsoleApp
extends Console\Client {

	const
	AppName    = 'Tort',
	AppDesc    = 'PHP-CLI Wrapper for TorToiSe TTS',
	AppVersion = '1.0.2-dev';

	const
	DefaultVoice     = 'train_atkins',
	DefaultGenCount  = 3,
	DefaultLengthMin = 300,
	DefaultLengthMax = 400;

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
	public function
	Generate():
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
		$Config->Exec->Voice = $this->GetOption('voice') ?? $Config->Exec->Voice;
		$Config->Exec->GenCount = $this->GetOption('count') ?? $Config->Exec->GenCount;
		$Config->Exec->TextLenMin = $this->GetOption('len-min') ?? $Config->Exec->TextLenMin;
		$Config->Exec->TextLenMax = $this->GetOption('len-max') ?? $Config->Exec->TextLenMax;
		$Config->Exec->KeepCombined = $this->GetOption('keep-combined') ?? $Config->Exec->KeepCombined;
		$Config->Exec->DontRename = $this->GetOption('dont-rename') ?? $Config->Exec->DontRename;
		$Config->Exec->Label = $this->GetOption('label') ?? $Config->Exec->Label;
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
		$Commands = new Datastore;
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

		if($Config->App->CheckForConda) {
			if(!isset($_ENV['CONDA_DEFAULT_ENV']))
			if(!isset($_SERVER['CONDA_DEFAULT_ENV']))
			$this->Quit(4);
		}

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
			$Text = new Datastore([ $File ]);
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

		if($Lines instanceof Datastore)
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

		$Config->Exec->Write(sprintf(
			'%s%sjob.json',
			$OutputDir,
			DIRECTORY_SEPARATOR
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
	VoiceList():
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
	VoiceConf():
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

	#[Console\Meta\Command('phar', TRUE)]
	#[Console\Meta\Info('Compile a tort.phar for easy use/distribution.')]
	public function
	BuildPhar():
	int {

		$BaseDir = dirname(__FILE__, 3);
		$Index = $this->GetPharIndex();
		$OutFile = $this->Repath("{$BaseDir}/build/tort/tort.phar");

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
			$this->Repath("{$BaseDir}/tort.json"),
			$this->Repath("{$BaseDir}/build/tort/tort.json")
		);

		copy(
			$this->Repath("{$BaseDir}/README.md"),
			$this->Repath("{$BaseDir}/build/tort/README.md")
		);

		copy(
			$this->Repath("{$BaseDir}/LICENSE.md"),
			$this->Repath("{$BaseDir}/build/tort/LICENSE.md")
		);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	Repath(string $Input):
	string {

		if(PHP_OS_FAMILY === 'Windows')
		$Input = str_replace('/', DIRECTORY_SEPARATOR, $Input);

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
		return $this->Repath($More);

		if(preg_match('#^[A-Za-z]:\\\\#', $More))
		return $this->Repath($More);

		////////

		$Path = $this->Repath(rtrim("{$Path}/{$More}"));

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
	MergeArgsIntoVoiceConf(TortConfigPackage $Config, Datastore $Voice):
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
			$Text = new Datastore(explode("\n", $Text));

			$Text
			->Remap(fn(string $Line)=> trim($Line));

			if(is_string($Lines))
			$Lines = $this->ProcessLines($Lines);

			return [ $Text, $File, $Lines ];
		}

		return [ NULL, NULL, FALSE ];
	}

	protected function
	ProcessLines(string $Lines):
	Datastore {

		$Output = new Datastore;
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
	Datastore {

		// handle digesting what was asked for.

		$Output = match($Voice) {
			'all'
			=> $this->GetVoices($VoiceDir),

			default
			=> new Datastore(explode(',', $Voice))
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

		$DS = DIRECTORY_SEPARATOR;
		$Found = glob("{$OutputDir}{$DS}*_combined.wav");

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

		$DS = DIRECTORY_SEPARATOR;
		$New = NULL;
		$Dirname = NULL;
		$Basename = NULL;
		$OutputDir = $this->ReplacePathTokens($OutputDir, $Conf);
		$Found = glob("{$OutputDir}{$DS}*.wav");

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
				$this->ReplacePathTokens($Basename, $Conf),
			);

			$this->FormatLn(
				'%s %s => %s',
				$this->FormatPrimary('Renaming:'),
				$Basename,
				$New
			);

			rename($File, "{$Dirname}{$DS}{$New}");
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

		$PBarReg = '#(\d+)+/(\d+) \[([\d\:]+)<([\d\:]+), ([^\]]+)\]#';
		$PBarData = NULL;

		$PrefixData = $this->Formatter->DimCyan($Prefix);
		$PrefixTime = $this->Formatter->BrightCyan($Prefix);

		$PP = popen($Cmd, 'r');

		while($Data = fread($PP, 4096)) {
			$Data = trim($Data);

			// handle if we got something that smells like progress.
			if(preg_match($PBarReg, $Data, $PBarData)) {
				printf(
					"\r%sIter %s of %s @ %s [%s, ETA %s]",
					$PrefixTime,
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

				// ignore that annoying errant progress.
				if(str_contains($Next, '?it/s'))
				continue;

				// treat anything else as printable.
				printf(
					'%s%s%s',
					$PrefixData,
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
	Datastore {

		$List = new Datastore(glob($this->GetLocalPath(
			$VoiceDir, '*'
		)));

		$List
		->Remap(fn(string $Path)=> basename($Path))
		->Sort();

		return $List;
	}

	protected function
	GetPharIndex():
	Datastore {

		$Dirs = [ 'core', 'vendor' ];
		$Files = [ 'tort.php', 'composer.json', 'composer.lock' ];
		$Index = new Datastore;
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
			$LineNum,
		);

		if($Colour)
		$Output = $this->Formatter->{$Colour}($Output);

		if($Info)
		$Output .= $Info;

		return $Output;
	}

}

