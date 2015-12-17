<?php
namespace Primalbase\Migrate;

use Google\Spreadsheet\CellFeed;
use Google\Spreadsheet\WorksheetFeed;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\SpreadsheetService;
use Google_Client;
use Google\Spreadsheet\Worksheet;
use View;
use Config;
use Exception;

class MigrateBuild extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'migrate:build';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Build migration file from The google spreadsheets.';

  /**
   * Create a new command instance.
   *
   */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
    $tables = $this->argument('table');
		$isAll  = $this->option('all');

		try {
			$spreadsheetService = $this->connection();
		} catch (Exception $e) {
			$this->error($e->getMessage());
			$this->error('Con\'t connected to Google Drive API');
			return false;
		}

    $sheetName = Config::get('laravel-migrate-build::build.spread_sheet_name');
		$spreadsheetFeed = $spreadsheetService->getSpreadsheets();
		$spreadsheet     = $spreadsheetFeed->getByTitle($sheetName);
		if (!$spreadsheet) {
			$this->error($sheetName.' is not exists.');
			return false;
		}
		$worksheetFeed   = $spreadsheet->getWorksheets();

		if ($isAll)
		{
			$definitions = $this->getAvailableTableDefinitions($worksheetFeed);
			$this->info(implode(PHP_EOL, array_pluck($definitions, 'tableName')));

			if (!$this->confirm('Built all tables?'))
				return false;
		}
		elseif (!empty($tables))
    {
			$definitions = [];
			foreach ($tables as $table)
			{
				$worksheet  = $worksheetFeed->getByTitle($table);
				if (is_null($worksheet))
				{
					$this->error($table.' table not exists.');
					return false;
				}
				$definition = $this->readTableDefinition($worksheet);
				if (!$definition)
					continue;

				$definitions[] = $definition;
			}
    }
		else
		{
			$this->error($this->getSynopsis());
			echo PHP_EOL;
			$this->comment('Listing tables.');
			$definitions = $this->getAvailableTableDefinitions($worksheetFeed);
			$this->info(implode(PHP_EOL, array_pluck($definitions, 'tableName')));
			$this->error('Select any table.  -- But not implemented yet.');
			return false;
		}

    $makes = 0;
		foreach ($definitions as $definition) {

			$filePath = app_path(sprintf('database/migrations/%s_%s.php', date('Y_m_d_His'), $definition['keyName']));
			$migration = View::make('laravel-migrate-build::migration', [
				'className'   => $definition['className'],
				'tableName'   => $definition['tableName'],
				'engine'      => $definition['engine'],
				'rowFormat'   => $definition['rowFormat'],
				'increments'  => $definition['increments'],
				'timestamps'  => $definition['timestamps'],
				'publishes'   => $definition['publishes'],
				'softDeletes' => $definition['softDeletes'],
				'columns'     => $definition['columns'],
			])->render();
			$this->info($definition['keyName']);
			$this->info($migration);
			$files = glob(app_path('database/migrations/????_??_??_??????_'.$definition['keyName'].'.php'));
			if (count($files) > 0)
			{
				if (!$this->confirm('Already exists. Overwrite?'))
					continue;

				$filePath = $files[0];
			}
      else
      {
        $makes++;
      }
			file_put_contents($filePath, $migration);
		}

		if ($makes > 0)
		{
			$this->call('optimize');
		}

		return true;
  }

	protected function getAvailableTableDefinitions(WorksheetFeed $worksheetFeed)
	{
		$checks = Config::get('laravel-migrate-build::build.available_sheet_check');

		$tables = [];
		foreach ($worksheetFeed as $sheet)
		{
			if ($this->getCellString($sheet->getCellFeed(), $checks['row'], $checks['col']) != $checks['value'])
			{
				continue;
			}
			$tables[] = $this->readTableDefinition($sheet);
		}

		return $tables;
	}


  protected function connection()
  {
		$keyPath = Config::get('laravel-migrate-build::build.client_key_path');

		$client = new Google_Client();
		$client->setApplicationName('MigrateBuild');
		$client->setScopes([
			'https://spreadsheets.google.com/feeds',
			'https://docs.google.com/feeds',
		]);
		putenv('GOOGLE_APPLICATION_CREDENTIALS='.$keyPath);
		$client->useApplicationDefaultCredentials();
		$client->refreshTokenWithAssertion();
		$serviceRequest = new DefaultServiceRequest(array_get($client->getAccessToken(), 'access_token'));
		ServiceRequestFactory::setInstance($serviceRequest);

		return new SpreadsheetService();
	}

	protected function readTableDefinition(Worksheet $worksheet)
	{
		$checks = Config::get('laravel-migrate-build::build.available_sheet_check');

		$cellFeed = $worksheet->getCellFeed();

		if ($this->getCellString($cellFeed, $checks['row'], $checks['col']) != $checks['value'])
			return false;

		$definition = [];
		$definition['tableName'] = $this->getCellString($cellFeed, 2, 15);
		$definition['increments'] = $this->getCellFlag($cellFeed, 4, 5);
		$definition['timestamps'] = $this->getCellFlag($cellFeed, 4, 10);
		$definition['publishes'] = $this->getCellFlag($cellFeed, 4, 15);
		$definition['softDeletes'] = $this->getCellFlag($cellFeed, 4, 20);
		$definition['engine'] = $this->getCellString($cellFeed, 4, 29);
		$definition['rowFormat'] = $this->getCellString($cellFeed, 4, 43);
		$columns = [];
		foreach (range(7, $worksheet->getRowCount()) as $row)
		{
			$no = $this->getCellNumber($cellFeed, $row, 1);
			if ($no == 0) break;
			$columns[] = [
				'label'    => $this->getCellString($cellFeed, $row, 3),
				'name'     => $this->getCellString($cellFeed, $row, 12),
				'type'     => $this->getCellString($cellFeed, $row, 21),
				'size'     => $this->getCellNumber($cellFeed, $row, 26, null),
				'default'  => $this->getCellValue($cellFeed, $row, 28),
				'index'    => $this->getCellFlag($cellFeed, $row, 31),
				'unique'   => $this->getCellFlag($cellFeed, $row, 33),
				'nullable' => $this->getCellFlag($cellFeed, $row, 35),
			];
		}
		$definition['columns'] = $columns;
		$definition['keyName'] = sprintf("create_%s_table", $definition['tableName']);
		$definition['className'] = sprintf("Create%sTable", studly_case($definition['tableName']));

		return $definition;
	}

	protected function getCellFlag(CellFeed $cellFeed, $row, $col, $default = false)
	{
		$cell = $cellFeed->getCell($row, $col);
		if (isset($cell))
		{
			return ($cell->getContent() == '○');
		}
		return $default;
	}

	protected function getCellString(CellFeed $cellFeed, $row, $col, $default = '')
	{
		$cell = $cellFeed->getCell($row, $col);
		if (isset($cell))
		{
			return $cell->getContent();
		}
		return $default;
	}

	protected function getCellNumber(CellFeed $cellFeed, $row, $col, $default = 0)
	{
		$cell = $cellFeed->getCell($row, $col);
		if (isset($cell))
		{
			return floatval($cell->getContent());
		}
		return $default;
	}

	protected function getCellValue(CellFeed $cellFeed, $row, $col, $default = null)
	{
		$cell = $cellFeed->getCell($row, $col);
		if (isset($cell))
		{
			return $cell->getContent();
		}
		return $default;
	}

	protected function makeTableDefinition()
	{

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('table', InputArgument::IS_ARRAY, 'Target table(s).'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('all', null, InputOption::VALUE_NONE, 'Build all tables.', null),
		);
	}

}
