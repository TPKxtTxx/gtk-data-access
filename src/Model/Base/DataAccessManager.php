<?php

if (!function_exists("gtk_log"))
{

	if (strpos($_SERVER['SCRIPT_NAME'], 'phpunit.phar') !== false) {
		// Code is being executed under PHPUnit
		// echo "Running under PHPUnit: ".$_SERVER['SCRIPT_NAME']."\n";
		// echo "\n\n\n";
		function gtk_log($toLog)
		{
			echo $toLog."\n";
		}
	} 
	else 
	{
		// Code is being executed outside of PHPUnit (e.g., by Apache CGI)
		// echo "Not running under PHPUnit: ".$_SERVER['SCRIPT_NAME']."\n";
		// echo "\n\n\n";
		function gtk_log($toLog)
		{
			error_log($toLog);
		}
	}

}

class DataAccessManager 
{
    private $dataAccessors             = [];
    private $dataAccessorConstructions = [];
    private $databaseConfigurations    = [];
    private $databases                 = [];
	private $nonDefaultDataAccessors   = [];




	public function getDefaultOptionsForSelectForUser($user)
    {
        return [
            "columnValue" => "id",
            "columnName"  => "name",
        ];
    }

	public function generateSelectForUserColumnValueName(
        $user,
		$dataAccessor,
		$objectID,
		$foreignColumnName,
		$foreignColumnValue,
		$options = []
	){
		$debug = false;

    	$language = isset($options['language']) ? $options['language'] : 'spanish';

    	$select = '<select name="' . $foreignColumnName . '">';

		$addNullCase = true;

		if ($addNullCase)
		{
        	$select .= '<option';
			$select .= ' value=""';
			$select .= '>';

			switch ($language)
			{
				case "english":
					$select .= "N / A";
					break;
				case "spanish":
				default:
					$select .= "No aplica";
					break;
			}

			$select .= '</option>';
		}

		$constructions = $this->getConstructions();
		$keys = array_keys($constructions);

		usort($keys, function ($a, $b){
			return strcasecmp($a, $b);
		});

    	foreach ($keys as $key)
		{
            if ($debug)
            {
                gtk_log("Working with key ($key) - construction: ".print_r($constructions[$key], true));
            }

            $label = $key;
            $value = $key;

            if ($debug)
            {
                gtk_log("Got label: ".$label);
                gtk_log("Got value: ".$value);
            }

        	$select .= '<option';
			$select .= ' value="'.$value .'"';
			if ($value === $foreignColumnValue)
			{
				$select .= ' selected ';
			}
			$select .= '>';
			$select .= ucwords($label);
			$select .= '</option>';
    	}
 
    	$select .= '</select>';

        if ($debug)
        {
            gtk_log("Made select: ".$select);
        }

    	return $select;


	}

	public static function validateDataSourceName($maybeDataSourceName)
	{
		$debug = false;

		$dataSourceName = null;

		if (is_object($maybeDataSourceName))
		{
			$dataSourceName = get_class($maybeDataSourceName);
		}
		else if (is_string($maybeDataSourceName))
		{
			$dataSourceName = $maybeDataSourceName;
		}
		
		$theValidName = self::getSingleton()->keyForDataAccessorConstructions($dataSourceName);

		if (!$theValidName)
		{
			throw new Exception("Providing invalid key for data source. Invalid key: $dataSourceName");
		}

		return $theValidName;
	}

	public static function editURLTo($maybeDataSourceName, $identifier, $options = null)
	{
		$dataSourceName = self::validateDataSourceName($maybeDataSourceName);
		$queryParameters = [
			"id" => $identifier,
		];
		return "/$dataSourceName/edit?".http_build_query($queryParameters);
	}

	public static function showURLTo($maybeDataSourceName, $identifier, $options = null)
	{
		$dataSourceName = self::validateDataSourceName($maybeDataSourceName);
		$queryParameters = [
			"id" => $identifier,
		];
		return "/$dataSourceName/show?".http_build_query($queryParameters);
	}

	public static function allURLTo($maybeDataSourceName, $options = null)
	{
		$debug = false;

		if ($debug)
		{
			$toPrint = is_string($maybeDataSourceName) ? $maybeDataSourceName : get_class($maybeDataSourceName);
			error_log("`allURLTo`::Got maybe data source name: ".$toPrint);
			error_log("`allURLTo`::Got options: ".print_r($options, true));
		}

		$dataSourceName = self::validateDataSourceName($maybeDataSourceName);

		if ($debug)
		{
			error_log("`allURLTo`::Got data source name: ".$dataSourceName);
		}

		return "/$dataSourceName/all";
	}


	public static function allLinkTo($dataSourceName, $options = null)
	{
		$debug = false;

		if (false && ($dataSourceName == "conduce"))
		{
			$debug = false;
		}

		if ($debug)
		{
			error_log("Got data source name: ".$dataSourceName);
			// die("Will get data source name: ".$dataSourceName);
		}

		$dataSource = self::get($dataSourceName);

		if ($debug)
		{
			error_log("Got data source: ".$dataSourceName);
			// die("`allLinkTo` Got data source: ".$dataSourceName);
		}

		$href       = self::allURLTo($dataSourceName, $options);

		if ($debug)
		{
			error_log("Got HREF: ".$href);
		}

		$id      = $options['id']    ?? '';
		$class   = $options['class'] ?? '';
		$style   = $options['style'] ?? '';
		$label   = $options['label'] ?? $dataSource->getPluralItemName(); // snakeToSpaceCase($dataSourceName);
		

		$toReturn  = "";
		$toReturn .= "<a";
		$toReturn .= ' id="'.$id.'"';
		$toReturn .= ' href="'.$href.'"';
		$toReturn .= ' class="'.$class.'"';
		$toReturn .= ' style="'.$style.'"';
		$toReturn .= '>';
		$toReturn .= $label;
		$toReturn .= "</a>";

		/*
		if (!$class)
		{
			$toReturn .= "<br/>";
		}
		*/
		
		return $toReturn;
	}

	public static function editLinkTo($dataSourceName, $options)
	{
		$href = "/$dataSourceName/edit";

		$id      = $options['class'] ?? '';
		$class   = $options['class'] ?? '';
		$style   = $options['style'] ?? '';
		$label   = $options['label'] ?? snakeToSpaceCase($dataSourceName);

		$toReturn  = "";
		$toReturn .= "<a id='$id' href='$href' class='$class' style='$style'>";
		$toReturn .= $label;
		$toReturn .= "</a>";
		
		return $toReturn;
	}

	public function getConstructions()
	{
		return $this->dataAccessorConstructions;
	}

	public static function maybeGet($name)
	{
		return self::getAccessor($name, false);
	}

	public static function getOnConnection($connectionName, $dataAccessorName)
	{
		$singleton = self::getSingleton();

		return $singleton->internalGetOnConnection($connectionName, $dataAccessorName);	
	}

	public function internalGetOnConnection($connectionName, $dataAccessorName)
	{
		// TODO: Implement internalGetOnConnection() method.
		// This method should lookup the dataAccessor by name,
		// verify which connection it is configured for.
		// If the connection name is the same one as the one we are looking for,
		// return the dataAccessor.
		// If not, then it should create a new dataAccessor on the fly,
		// using the PDO instance associated with the connection name 
		// as the connection and return this new dataAccessor.
		// this dataAccessor should be stored in the $nonDefaultDataAccessors array,
		// so that next time this method is called with the same connection name,
		// it returns the same dataAccessor instance.
		// The key for this dataAccessor in the $nonDefaultDataAccessors array 
		// should be the $this->nonDefaultDataAccessors[$connectionName][$dataAccessorName]
		$debug = false;

		if ($debug) {
			gtk_log("internalGetOnConnection: connectionName: " . $connectionName);
			gtk_log("internalGetOnConnection: dataAccessorName: " . $dataAccessorName);
		}

		// Check if we already have a non-default accessor instance
		if (isset($this->nonDefaultDataAccessors[$connectionName][$dataAccessorName])) {
			return $this->nonDefaultDataAccessors[$connectionName][$dataAccessorName];
		}

		// Get the data accessor configuration
		$key = $this->keyForDataAccessorConstructions($dataAccessorName);
		if (!$key) {
			throw new Exception("Data accessor configuration for '{$dataAccessorName}' not found.");
		}
		$config = $this->dataAccessorConstructions[$key];

		// If the requested connection matches the default connection in config,
		// return the default accessor instance
		if (isset($config['database']) && $config['database'] === $connectionName) {
			return $this->getDataAccessor($dataAccessorName);
		}

		// Get the database instance for the requested connection
		$db = $this->getDatabaseInstance($connectionName);
		if (!$db) {
			throw new Exception("Database connection '{$connectionName}' not found.");
		}

		// Create new data accessor instance with the specified connection
		$className = $config['class'];
		$accessor = new $className($db);

		// Store in nonDefaultDataAccessors array
		if (!isset($this->nonDefaultDataAccessors[$connectionName])) {
			$this->nonDefaultDataAccessors[$connectionName] = [];
		}
		$this->nonDefaultDataAccessors[$connectionName][$dataAccessorName] = $accessor;

		return $accessor;
	}

    public static function get($name)
    {
        return self::getAccessor($name);
    }


	public static function configureSystem()
	{
		$debug = false; 

		static $didConfigure;

		if (!$didConfigure)
		{
			$didConfigure = true;
			
			/*
			error_reporting(E_STRICT);
			
			function terminate_missing_variables($errno, $errstr, $errfile, $errline)
			{                               
			  if (($errno == E_NOTICE) and (strstr($errstr, "Undefined variable")))
			  {
				die ("$errstr in $errfile line $errline");
			  }
			
			
			  return false; // Let the PHP error handler handle all the rest  
			}
			*/
			
			// $old_error_handler = set_error_handler("terminate_missing_variables"); 
			
			if (!function_exists("gtk_log"))
			{
			
				if (strpos($_SERVER['SCRIPT_NAME'], 'phpunit.phar') !== false) {
					// Code is being executed under PHPUnit
					// echo "Running under PHPUnit: ".$_SERVER['SCRIPT_NAME']."\n";
					// echo "\n\n\n";
					function gtk_log($toLog)
					{
						echo $toLog."\n";
					}
				} 
				else 
				{
					// Code is being executed outside of PHPUnit (e.g., by Apache CGI)
					// echo "Not running under PHPUnit: ".$_SERVER['SCRIPT_NAME']."\n";
					// echo "\n\n\n";
					function gtk_log($toLog)
					{
						error_log($toLog);
					}
				}
			
			}
			
			date_default_timezone_set('America/Santo_Domingo');
			
			$envPath = null;
			global $_GLOBALS;
		
			if (isset($_GLOBALS["ENV_FILE_PATH"]))
			{
				$envPath = $_GLOBALS["ENV_FILE_PATH"];
			}
			
			if (!$envPath or $envPath == '')
			{
				die(__CLASS__.': No se a declarado el $_GLOBALS["ENV_FILE_PATH"] como una variable global, o su valor es vacio o null
				. ');
			} 
			
			if (!file_exists($envPath))
			{
				die(__CLASS__.": No se encontró el archivo de configuración de la red. Buscando en: ".$envPath);
			}
			
			if ($debug)
			{
				error_log("Reading `env.php` at: $envPath");
			}
			
			require_once($envPath);	
			
			if (class_exists('PHPUnit\Framework\TestCase')) 
			{
				$testFilePath = dirname($envPath)."test-env.php";
			
				if (file_exists($testFilePath))
				{
					require_once($testFilePath);
				}
			} 

		}

	}

	public static function getSingleton(
		$databaseConfigurations = null, 
		$dataAccessorConstructions = null
	){
	    $debug = false;

	    static $dataAccessManager = null;

	    if (!$dataAccessManager)
	    {
			self::configureSystem();

            global $_GLOBALS;

			// die(print_r($_GLOBALS, true));

			if (!$databaseConfigurations)
			{
				$databaseConfigurations = $_GLOBALS["DataAccessManager_DB_CONFIG"];
			}

			if (!$databaseConfigurations)
			{
				throw new Exception("Database configurations not found");
			}

			// die(print_r($databaseConfigurations, true));

			if (!$dataAccessorConstructions)
			{
				$dataAccessorConstructions = $_GLOBALS["DataAccessManager_dataAccessorConstructions"];
			}

			// die(print_r($dataAccessorConstructions, true));

			if (!$dataAccessorConstructions)
			{
				throw new Exception("Data accessor constructions not found");
			}
        
		    $dataAccessManager = new DataAccessManager(
                $databaseConfigurations,
                $dataAccessorConstructions);
	    }

		return $dataAccessManager;
	}

    public static function getAccessor($name, $throwException = true)
    {
		$singleton = self::getSingleton();

	    if ($name === "DataAccessManager")
	    {
	    	return $singleton;
	    }
        else
        {
			// die("Getting name: ".$name."\n".print_r($singleton, true));
            $dataAccessor = $singleton->getDataAccessor($name, $throwException);
			// die("Data accessor: ".$name."\n".print_r($dataAccessor, true));
			return $dataAccessor;
        }
    }

	public static function getDB($name)
	{
		return self::getSingleton()->getDatabaseInstance($name);
	}


    public function __construct($databaseConfigurations, $dataAccessorConfigurations) 
    {
		if (!$databaseConfigurations)
		{
			throw new Exception("Database configurations not found");
		}

		if (!$dataAccessorConfigurations)
		{
			throw new Exception("Data accessor configurations not found");
		}


        $this->databaseConfigurations    = $databaseConfigurations;
        $this->dataAccessorConstructions = $dataAccessorConfigurations;
    }

	public static function registerAccessor($key, $configuration)
	{
		self::getSingleton()->internalRegisterAccessor($key,$configuration);
	}

    public function internalRegisterAccessor($key, $configuration) 
    {
		$debug = false;

		if ($debug)
		{
			gtk_log("`internalRegisterAccessor`: key: ".$key);
			gtk_log("`internalRegisterAccessor`: configuration: ".print_r($configuration, true));
		}

        if (!isset($configuration['class'])) {
            throw new Exception("Invalid data accessor configuration. Required field: class");
        }

        // Use the class name as the key if no specific key is provided
        // $key = $configuration['key'] ?? $configuration['class'];

        if (isset($this->dataAccessorConstructions[$key])) 
		{
            throw new Exception("Data accessor configuration for '{$key}' already exists.");
        }

		if (!is_array($this->dataAccessorConstructions))
		{
			$this->dataAccessorConstructions = [];
		}

        $this->dataAccessorConstructions[$key] = $configuration;
        
		$this->resetAccessor($key);
    }

    /**
     * Get all registered data accessor keys
     */
    public function getRegisteredKeys() 
    {
        return array_keys($this->dataAccessorConstructions);
    }

    /**
     * Check if a data accessor configuration exists
     */
    public function hasConfiguration($key) 
    {
        return isset($this->dataAccessorConstructions[$key]);
    }

    /**
     * Get configuration for a specific data accessor
     */
    public function getConfiguration($key) 
    {
        if (!isset($this->dataAccessorConstructions[$key])) {
            throw new Exception("Data accessor configuration for '{$key}' not found.");
        }
        return $this->dataAccessorConstructions[$key];
    }

    /**
     * Remove a data accessor configuration
     */
    public function removeConfiguration($key) 
    {
        if (!isset($this->dataAccessorConstructions[$key])) {
            throw new Exception("Data accessor configuration for '{$key}' not found.");
        }
        
        unset($this->dataAccessorConstructions[$key]);
        $this->resetAccessor($key);
    }

// ... existing code ...

	public static function updateConfigurationField($key, $field, $value)
	{
		self::getSingleton()->internalUpdateConfigurationField($key, $field, $value);
	}

    public function internalUpdateConfigurationField($key, $field, $value) 
    {
        if (!isset($this->dataAccessorConstructions[$key])) {
            throw new Exception("Data accessor configuration for '{$key}' not found.");
        }

        // Update the specific field
        $this->dataAccessorConstructions[$key][$field] = $value;
        
        // Reset the accessor instance to ensure it gets recreated with new configuration
        $this->resetAccessor($key);
    }

	public function resetAccessor($key)
	{
		if (isset($this->dataAccessors[$key]))
		{
			unset($this->dataAccessors[$key]);
		}
	}

    public function getDatabaseInstance($dbName) 
    {
		// $debug = false;
		/*
		if (!$dbName)
		{
			return null;
		}
		*/

        if (!isset($this->databases[$dbName])) 
        {
            if (!isset($this->databaseConfigurations[$dbName])) 
            {
                throw new Exception("Database configuration for '{$dbName}' was not found. Please configure env.php and run seed.php.");
            }

            $config = $this->databaseConfigurations[$dbName];

            if (!isset($config["connectionString"]))
            {
                throw new Exception("Database connection string for '{$dbName}' not found.");
            }

            $connection_string = $config["connectionString"];
            $username          = $config["userName"];
            $password          = $config["password"];

            $this->databases[$dbName] = new PDO(
                $connection_string,
                $username, 
                $password
            );  

			if (strpos($connection_string, "sqlite") !== false)
			{
				try
				{

					$this->databases[$dbName]->setAttribute(PDO::ATTR_TIMEOUT, 5000);	
					
					/*
					Journal Mode: ACID (Atomicity, Consistency, Isolation, Durability) properties of database transactions

						DELETE, TRUNCATE, PERSIST, WAL, MEMORY, OFF.
						
					DELETE   - DEFAULT - Creates a rollback journal file and deletes it when the transaction is complete.
					PERSIST  - Creates a rollback journal file and leaves it on disk after the transaction is complete.
					OFF      - No journal file is created. Transactions are atomic but not durable.
					TRUNCATE - Similar to DELETE but truncates the journal file instead of deleting it.
					MEMORY   - Fast. Keeps the journal in memory instead of on disk. Risky if power is lost. 
					WAL      - Does a log, instead of a journal file. Can provide better concurrency and is often faster. Requires more disk space.
					*/
					$this->databases[$dbName]->exec("PRAGMA journal_mode = ".($config["journal_mode"] ?? "WAL"));
					
					
					
					/*
				
					Synchronous: How aggressively SQLite will write data to the disk.

					Options: OFF, NORMAL, FULL (default), EXTRA
					
					NORMAL : SQLite will still sync at critical moments, but less often than FULL.
					FULL   : Safer but slower. NORMAL improves performance while still maintaining good crash resistance.

					*/
					$this->databases[$dbName]->exec('PRAGMA synchronous = '.($config['synchronous'] ?? 'FULL'));
					
					/*
					
					Cache Size: PRAGMA cache_size = -20000


						Meaning: This sets the number of database pages to hold in memory.
						
						Comparison: Larger cache sizes can improve performance by reducing disk I/O.

						Default is 2000 kibibytes (KiB) or about 500 pages.
						Note: The cache size is per database connection.

						1 - Positive vs Negative values:

							Positive value: Sets the cache size in pages
							Negative value: Sets the cache size in kibibytes (KiB)


						2 - Why use a negative value:

							Convenience: It's often easier to think in terms of memory size (KiB) rather than number of pages
							Persistence: A negative value makes the setting persistent across database connections

						Comparison to positive values:

						PRAGMA cache_size = 5000 would set the cache to 5000 pages
						If the page size is 4096 bytes, this would be about 20 MB (similar to -20000)
						However, this positive value setting wouldn't persist across connections

					*/
					$this->databases[$dbName]->exec('PRAGMA cache_size = '.($config["cache_size"] ?? '-20000'));


					// $this->databases[$dbName]->exec('PRAGMA locking_mode = '.($config["locking_mode"] ?? "DEFERRED"));

					/*
					
					Foreign Keys:
					PRAGMA foreign_keys = ON

					Meaning: This enables foreign key constraint enforcement.
					
					Comparison: By default, foreign key constraints are disabled in SQLite. 
					

					Enabling them ensures referential integrity but may slightly impact performance.

					*/
					$this->databases[$dbName]->exec('PRAGMA foreign_keys = '.($config["foreign_keys"] ?? 'OFF'));
								
				}
				catch (Exception $e)
				{
					/*
					So...I needed to give the database and the containing folder write permissions.
					That's why I was getting the error.
					Need to make it 777 or 774 - because form some god-forsaken reason...nothing else worked

					*/
					if (false)
					{
						echo "Connection string: ".$connection_string;
						echo "Error setting journal mode: ".$e->getMessage();
						die();
					}
					else
					{
						throw $e;
					}
				}
			}
        }
        return $this->databases[$dbName];
    }

	public function internalRegisterAlias($key, $alias) 
    {
        if (!isset($this->dataAccessorConstructions[$key])) {
            throw new Exception("Data accessor configuration for '{$key}' not found.");
        }

        // Initialize synonyms array if it doesn't exist
        if (!isset($this->dataAccessorConstructions[$key]['synonyms'])) {
            $this->dataAccessorConstructions[$key]['synonyms'] = [];
        }

        // Check if alias is already used by any configuration
        foreach ($this->dataAccessorConstructions as $existingKey => $config) {
            if ($existingKey === $alias) {
                throw new Exception("Cannot register alias: '{$alias}' is already a primary key.");
            }
            if (isset($config['synonyms']) && in_array($alias, $config['synonyms'])) {
                throw new Exception("Cannot register alias: '{$alias}' is already an alias for '{$existingKey}'.");
            }
        }

        // Add the new alias
        $this->dataAccessorConstructions[$key]['synonyms'][] = $alias;
    }


	public static function registerAlias($key, $alias)
	{
		self::getSingleton()->registerAlias($key, $alias);
	}

    public function getDataAccessor($name, $throwException = true) 
    {
		$debug = false;

		if (false && ($name=="conduce"))
		{
			$debug = false;
		}

		// die(print_r($this->dataAccessors, true));
		
		if (!array_key_exists($name, $this->dataAccessors)) 
        {
			if ($debug)
			{
				error_log("`getDataAccessor`: name: ".$name);
			}

			$key = $this->keyForDataAccessorConstructions($name);
			
			if($debug)
			{
				error_log("`getDataAccessor`: key: ".$key);
			}
            // if (!isset($this->dataAccessorConstructions[$name])) 
			if (!$key) 
            {
				if ($throwException)
				{
					throw new Exception("Data accessor configuration for '{$name}' not found.");
				}
				else
				{
                    return null;
				}  
            }

            $config = $this->dataAccessorConstructions[$key];

			if ($debug)
			{
				error_log("Got config: ".print_r($config, true));
				// die("Got config: ".$key);
			}

			if (!isset($config["class"]))
			{
				throw new Exception("Must set class to start up object");
			}

			$className  = $config['class'];

			$instance = null;

			$initFromDataAccessManager = "initFromDataAccessManager";

			$config["dataAccessorName"] = $name;
			
			if (method_exists($className, $initFromDataAccessManager))
			{
				if ($debug)
				{
					error_log("Calling initFromDataAccessManager: ".$className);
				}

				$instance = $className::$initFromDataAccessManager(
					$this,
					$key,
					$config);

				if ($debug)
				{
					error_log("Instance: ".print_r($instance, true));
				}
			}
			else
			{
				$dbName = null;

				if (isset($config['db']))
				{
					$dbName = $config['db'];
				}
	
				$dbInstance = null;
				if ($dbName)
				{
					$dbInstance = $this->getDatabaseInstance($dbName);
				}

				if ($debug)
				{
					error_log("DB Instance: ".print_r($dbInstance, true));
				}

				$instanceOptions = null;
				if (isset($config['instanceOptions']))
				{
					$instanceOptions = $config['instanceOptions'];
				}
	
				$instance = new $className($dbInstance, $instanceOptions);
	
				if (isset($config["tableName"]))
				{
					$instance->setTableName($config["tableName"]);
				}
	
				if (isset($config["permissions"]))
				{
					if (method_exists($instance, "setPermissions"))
					{
						$instance->setPermissions($config["permissions"]);
					}
				}
			}



            $this->dataAccessors[$name] = $instance;

        }
        return $this->dataAccessors[$name];
    }

    public function keyForDataAccessorConstructions($name) 
    {
        $debug = false;

        if ($debug)
        {
			if (is_string($name))
			{
				error_log("Searching for data accessor key for name: ".$name);	
			}
        }

		// die(print_r($this->databaseConfigurations, true));
		// die(print_r($this->dataAccessorConstructions, true));

        if(!$this->dataAccessorConstructions or gtk_count($this->dataAccessorConstructions) == 0)
		{
			throw new Exception('DataAccessorConstructions is not set');
		}
        if (array_key_exists($name, $this->dataAccessorConstructions)) 
        {
			if ($debug)
			{
				error_log("Found key for data accessor name: ".$name);
			}
            return $name;
        }

        foreach ($this->dataAccessorConstructions as $key => $details) 
        {
            if (isset($details["class"]) && $name === $details["class"])
			{
				if ($debug)
				{
					error_log("Matching on classs returning ".$key." for ".$name);
				}
                return $key;
            }
			if (isset($details["synonyms"]))
			{
				foreach ($details["synonyms"] as $synonym)
				{
					if ($synonym == $name)
					{
						if ($debug)
						{
							error_log("Matching on synonym returning ".$key." for ".$name);
						}
						return $key;
					}					
				}
			}
        }

		if ($debug)
		{
			error_log("No key found for data accessor name: ".$name);
		}

        return null;
    }

    public function notFoundPage($requestPath) {
        return "404 Not Found: " . htmlspecialchars($requestPath);
    }

   
	public function toRenderForPath($requestPath, $user)
	{
		$debug = false;

		$pathParts                = explode('/', trim($requestPath, '/'));
		$potentialDataAccessorKey = $pathParts[0];
		$toTryDataAccessorKey     = null;


		if ($debug)
		{
			gtk_log("Potential data accessor key: ".$potentialDataAccessorKey);
		}

		$dataSource = null;

		if (in_array($potentialDataAccessorKey, [
			"show", "edit", "list"
		]))
		{
			if (!isset($_GET["data_source"]))
			{
				die("Please set `data_source` parameter in URL.");
			}

			$toTryDataAccessorKey = $_GET["data_source"];
			$requestedPermission  = $potentialDataAccessorKey;
		}
		else
		{
			$relevantPathParts 	  = array_slice($pathParts, 1);
			$requestedPermission  = implode('/', $relevantPathParts);
			$toTryDataAccessorKey = $potentialDataAccessorKey;
		}

		if ($debug)
		{
			gtk_log("To try data accessor key: ".$toTryDataAccessorKey);
			gtk_log("Requested permission: ".$requestedPermission);
		}

		$dataSource = $this->getDataAccessor($toTryDataAccessorKey, false);

		if (!$dataSource)
		{
			return null;
		}

		$permissionURL = $toTryDataAccessorKey.".".$requestedPermission;

		if ($debug)
		{
			gtk_log("Permission URL: ".$permissionURL);
		}

		// $this->getDataAccessor("persona")->logAction($user, $permissionURL);
		// if ($dataSource->userHasPermissionTo($routeAsString, $user))

		$hasPermission = $this->getDataAccessor("persona")->hasPermission($permissionURL, $user);

		if ($hasPermission)
		{
			return $dataSource->renderObjectForRoute($requestedPermission, $user);
		}
	
		if (!$user)
		{

			echo Glang::get("DataAccessManager/RequiresRedirect");

			if ($debug)
			{
				error_log("Requires redirect. No user.");
			}

			
			
			if (in_array($requestPath, [
				"auth/login.php", 
				"auth/login",
				"login",
				"login.php",
			]))
			{
				$loginPageClass = class_exists('StonewoodLoginPageDelegate')
					? 'StonewoodLoginPageDelegate'
					: 'GTKDefaultLoginPageDelegate';
				$loginPage = new $loginPageClass();
				global $GTK_SUPER_GLOBALS;
				echo $loginPage->render(...$GTK_SUPER_GLOBALS);
				return;
			}
			else
			{
				header("Refresh:3; url=/auth/login.php");
			}


			exit();
		}
		else
		{
			die(Glang::get("NotAuthorizedToDoThis"));
		}
	}

	public static function createTables()
	{
		self::getSingleton()->internalCreateTables();
	}

	public function internalCreateTables()
	{
		$debug = false;

		foreach ($this->dataAccessorConstructions as $key => $construction)
		{
			$dataAccessor = $this->getDataAccessor($key, false);
    
    		if (method_exists($dataAccessor, "createOrAnnounceTable"))
    		{
        		$dataAccessor->createOrAnnounceTable();
    		}
    		else
    		{
				if ($debug)
				{
					gtk_log("`createTables`: ".$key." - has no `createOrAnnounceTable` \n");
				}
    		}
    
		}
	}

	public static function createPermissions($PERMISSION_ARRAYS_TO_ADD)
	{
		self::getSingleton()->internalCreatePermissions($PERMISSION_ARRAYS_TO_ADD);
	}

	public function internalCreatePermissions($PERMISSION_ARRAYS_TO_ADD)
	{
		foreach ($this->dataAccessorConstructions as $key => $construction)
		{
			$accessor = DAM::get($key);
		
			if (method_exists($accessor, "createOrManagePermissionsWithKey"))
			{
				$accessor->createOrManagePermissionsWithKey($key);
			}
			else
			{
				echo $key." - has no `createOrManagePermissionsWithKey` \n";
			}
		}
		
		//-------------------------------------------------------------------------
		//-------------------------------------------------------------------------
		
		foreach ($PERMISSION_ARRAYS_TO_ADD as $permissions)
		{
			foreach ($permissions as $permission)
			{
				$permission = [
					"name"         => $permission,
					"is_active"    => true,
					"date_created" => date("Y-m-d H:i:s"),
				];
		
				DAM::get("permissions")->insertIfNotExists($permission);
		
				echo "Creating permission: ".$permission["name"]."\n";
			}
		}
	
	}
}
