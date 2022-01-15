<?php


class BITWE_CONTROL
{
	public $bd, $init_config, $log = [];

	function log($log)
	{
		$this->log[] = $log;
	}

	function getVersao($versao)
	{
		return (object)[
			"versao" => $versao,
			"array" => explode(".", $versao)
		];
	}

	function __construct($versao = "", $bd = ["servername" => "", "username" => "", "password" => "", "dbname" => ""])
	{
		if (sizeof(explode(".", $versao)) !== 3)
			throw new Exception("Versões inconsistentes");

		$this->bd = $bd;

		$configfile = fopen(__DIR__ . "/config.json", "a+");
		fclose($configfile);
		$configfile = fopen(__DIR__ . "/config.json", "r+");

		$config = file_get_contents(__DIR__ . "/config.json");
		$config = (object)json_decode($config ?: "");

		$this->init_config = (object)[
			"linha" => 0,
			"versao" => "1.0.0",
		];
		$this->init_config = (object)array_replace_recursive((array)$this->init_config, (array)$config ?: []);
		$this->init_config->versao_check = $versao;

		if ($this->getVersao($this->init_config->versao)->array > $this->getVersao($versao)->array) {
			throw new Exception("Erro control de versões");
		}

		$this->gitIgnore();
		$this->criar_pasta(__DIR__ . "/versions");

		$this->updateSQL();
		$this->init_config->versao = $versao;

		fwrite($configfile, json_encode($this->init_config));
	}

	function updateSQL()
	{

		$versao = $this->init_config->versao;
		$stopcheck = false;


		do {

			if ($versao == $this->init_config->versao_check) {
				$stopcheck = true;
			}
			$checkcreatefile = fopen(__DIR__ . "/versions/" . $versao . ".sql", "a+");
			fclose($checkcreatefile);
			$sqlscript = trim(file_get_contents(__DIR__ . "/versions/" . $versao . ".sql"));
			$sqlscript = explode("\n", $sqlscript);

			$sqlscript_novo = array_slice($sqlscript, $this->init_config->linha);
			$sqlscript_novo = array_filter($sqlscript_novo);
			if ($sqlscript_novo) {
				$bitwe_con = new PDO('mysql:dbname=' . $this->bd["dbname"] . ';host=' . $this->bd["servername"], $this->bd["username"], $this->bd["password"], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
				try {
					$bitwe_con->beginTransaction();
					$stmt = $bitwe_con->prepare($sql_query = implode("\n", $sqlscript_novo));
					$stmt->execute();
					$bitwe_con->commit();
				} catch (Exception $e) {
					$bitwe_con->rollBack();
					echo $e->getMessage();
					die();
				}
				$bitwe_con = null;
			}

			if ($versao == $this->init_config->versao_check && $sqlscript_novo) {
				$this->init_config->linha = sizeof($sqlscript);
			} else {
				$this->init_config->linha = 0;
			}

			for ($new_version = explode(".", $versao), $i = count($new_version) - 1; $i > -1; --$i) {
				if (++$new_version[$i] <= $this->getVersao($this->init_config->versao_check)->array[$i] || !$i) break; // break execution of the whole for-loop if the incremented number is below 10 or !$i (which means $i == 0, which means we are on the number before the first period)
				$new_version[$i] = 0; // otherwise set to 0 and start validation again with next number on the next "for-loop-run"
			}
			$versao = $new_version = implode(".", $new_version);

		} while (!$stopcheck);

	}

	function criar_pasta($path, $check = 1)
	{
		if ($check) {
			if (is_dir($path)) {
				return $path;
			}
		}
		mkdir($path);
		return $path;
	}

	function gitIgnore()
	{
		$gitfile = fopen(__DIR__ . "/.gitignore", "a+");

		$texto = explode("\n", file_get_contents(__DIR__ . "/.gitignore"));

		$ignore_config = array_search("config.json", $texto);

		if ($ignore_config === false) {
			fwrite($gitfile, "\nconfig.json");
		}
	}
}