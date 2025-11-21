<?php

function getFunctionArgumentCount($functionName) {
    $reflectionFunction = new ReflectionFunction($functionName);
    return $reflectionFunction->getNumberOfParameters();
}

class DataAccessInputSelectArgument
{
    public $user               = null;
    public $dataAccessor       = null;
    public $objectID           = null;
    public $foreignColumnName  = null;
    public $foreignColumnValue = null;
    public $idValue             = null;
    public $options            = [];
}

function generateSelectOptionsDataLabelColumn($rows, $currentValue, $dataSourceName, $dataColumn, $labelColumn)
{
    $debug = false;

    if ($debug)
    {
        try
        {
            error_log("`generateSelectOptionsDataLabelColumn` - Current value: ".$currentValue);
        }
        catch (Exception $e)
        {
            //
        }

    }

    $language = "es";

    $addNullCase = true;

    $select = "";

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

    foreach ($rows as $row)
    {
        $optionValue = DataAccessManager::get($dataSourceName)->valueForKey($dataColumn,  $row);
        $optionLabel = DataAccessManager::get($dataSourceName)->valueForKey($labelColumn, $row);
        $select .= '<option';
        $select .= ' value="'.$optionValue.'" '; 
        if ($debug)
        {
            error_log("Generating select value: ".$currentValue." - ".$optionValue);
        }
        if ($optionValue == $currentValue)
        {
            $select .= "selected";
        }
        $select .= '>';
        $select .= $optionLabel;
        $select .= '</option>';
    }

    return $select;
}


enum DataAccessInsertResult: string 
{
    case Success            = "Success";
    case Failure            = "Failure";
    case SyntaxFailure      = "SyntaxFailure";
    case ConnectionFailure  = "ConnectionFailure";
}

interface DataAccessInterface
{
    public function singleItemName();
    public function getPluralItemName();
    public function insert($toInsert);
    public function insertFromForm($toInsert);
    public function update($toUpdate);
    public function updateFromForm($toUpdate);
    public function selectFromOffsetForUser(
        $user,
        $offset,
        $limit,
        $options = []
    );
    
}

class GTKTableCellItemPresenter
{
    public function listDisplayForDataSourceUserItem($dataSource, $user, $item, $primaryKeyValue)
    {

    }
}

/*
class MegaProject
{
    public int      $id;
    public string   $name;
    public string   $description;
    public int      $organization_id;
    public int      $owner_id;
    public DateTime $deadline;
    public int      $budgeted_time;
    public DateTime $created_at;
    public DateTime $updated_at;
    public DateTime $deleted_at;
}
*/


abstract class DataAccess /* implements Serializable */
{
    use DataAccessAuditTrait;

    public $identifierKeys = [
        "id",
        "identifier",
        "identificador",
    ];


    public function getOrderBySpec()
    {   
        /*
        $orderBy = new OrderByClause(
            $this->defaultOrderByColumnKey, 
            $this->defaultOrderByOrder);
        return $orderBy;
        */

        return [
            [
                $this->defaultOrderByColumnKey(),
                $this->defaultOrderByOrder,
            ]
        ];
    }
    public  $debug;
    public  $dataSetViews = [];
    public  $dataAccessorName;
	private $db;
	public  $_tableName;
	public  $dataMapping;
    public  $defaultSearchByColumnKey;
	public  $defaultOrderByColumnKey; 
	public  $defaultOrderByOrder = 'ASC';
    public  $singleItemName      = 'Data Access Item';
    public  $pluralItemName      = 'Data Access Items';
    public  $editURL             = 'edit.php';
    public  $permissions;
    public  $crudBaseRoute;
    public  $_actions;
    public $cache = [];
    public $defaultOrderBy;
    
    public $_allowsCreation;

    public $preSetFilters;

	public $sqlServerTableName;
	public $sqlServerDefaultOrderByColumn;

	public $sqliteTableName;
	public $sqliteDefaultOrderByColumn;

    public function columnIsFalsyClauseRawSQL($columnName)
    {
        $toReturn = " (";
        $toReturn .= $this->dbColumnNameForKey($columnName)." = 0";
        $toReturn .= " OR ";
        $toReturn .= $this->dbColumnNameForKey($columnName)." IS NULL";
        $toReturn .= " OR ";
        $toReturn .= $this->dbColumnNameForKey($columnName)." = ''";
        $toReturn .= ")";

        return $toReturn;
    }

    public function columnIsTruthyClauseRawSQL($columnName)
    {
        $toReturn = " (";
        $toReturn .= $this->dbColumnNameForKey($columnName)." = 1";
        $toReturn .= " OR ";
        $toReturn .= $this->dbColumnNameForKey($columnName)." != 0";
        $toReturn .= " OR ";
        $toReturn .= $this->dbColumnNameForKey($columnName)." != ''";
        $toReturn .= ")";

        return $toReturn;
    }


    public function withTransaction($closure, $maxRetries = 5, $retryDelay = 100000)
    {
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try 
            {
                $this->getPDO()->beginTransaction();

                $result = $closure();

                $this->getPDO()->commit();
                return $result;
            } 
            catch (PDOException $e) 
            {
                if ($e->getCode() === '40001' || $e->getCode() === 'HY000') { // SQLSTATE code for a busy database
                    gtk_log("Database is busy, retrying... Attempt: " . ($attempt + 1));
                    usleep($retryDelay); // wait before retrying
                    $attempt++;
                } else {
                    gtk_log("Error during transaction: " . $e->getMessage());
                    $this->getPDO()->rollBack();
                    throw $e; // Re-throw the exception after rollback
                }
            } catch (Exception $e) {
                gtk_log("Error during transaction: " . $e->getMessage());
                $this->getPDO()->rollBack();
                throw $e; // Re-throw the exception after rollback
            }
        }

        gtk_log("Max retries reached. Database is still busy.");
        return false;
    }

    public function currentUser()
    {
        return DAM::get("session")->getCurrentUser();
    }

    public function currentUserHasRoles($arrayOfRows)
    {
        $currentUser = $this->currentUser();

        if ($currentUser)
        {
            return DataAccessManager::get("persona")->isInGroups(
                $currentUser, 
                $arrayOfRows);
        }

        return false;
    }

    public function getDataSource($name)
    {
        return DataAccessManager::get($name);
    }

    public function dataSource($name)
    {
        return DataAccessManager::get($name);
    }

    public function defaulSearchByColumnMapping()
    {
        if ($this->defaultSearchByColumnKey)
        {
            $columnMapping = $this->columnMappingForKey($this->defaultSearchByColumnKey);

            if (!$columnMapping)
            {
                throw new Exception("Invalid column mapping key to search by column: ".get_class($this).": ".$this->defaultSearchByColumnKey);
            }

            return $columnMapping;
        }
        else
        {
            $primaryKeyMapping = $this->primaryKeyMapping();

            if (!$primaryKeyMapping)
            {
                throw new Exception("No primary key mapping found for: ".$this->tableName());
            }

            return $primaryKeyMapping;
        }
    }

    public static function initFromDataAccessManager(DataAccessManager $dataAccessManager, $configName, $config)
    {
        $debug = false;

        if ($configName == "conduce")
        {
            $debug = false;
        }

        if (isset($config['db']))
        {
            $dbName = $config['db'];
        }
        else
        {
            throw new Exception("Cannot instanitate class of name: with configName: ".$configName);
        }

        if ($debug)
        {
            error_log("Will get DB for DB Name: ".$dbName);
        }

        $db = $dataAccessManager->getDatabaseInstance($dbName);

        if ($debug)
        {
            error_log("Got DB for DB Name: ".$dbName);
        }
        // $instance = new self($db, $config);
        $instance = new static($db, $config);

        if ($debug)
        {
            error_log("Did create instance for: ".$configName);
        }


        return $instance;
    }

    function getUserIPAndAllowLocal($allowLocal = false) {
		$ipKeys = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		];

        if (isset($_SERVER))
        {

	    	foreach ($ipKeys as $key) {
	    		if (array_key_exists($key, $_SERVER) === true) {
	    			foreach (explode(',', $_SERVER[$key]) as $ip) {
	    				$ip = trim($ip); // just to be safe
                    
	    				if ($allowLocal) {
	    					// If allowing local, just check for valid IP
	    					if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
	    						return $ip;
	    					}
	    				} else {
	    					// If not allowing local, exclude private and reserved ranges
	    					if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
	    						return $ip;
	    					}
	    				}
	    			}
	    		}
	    	}
	    	return 'UNKNOWN';

        }
        else
        {
            return "N/A - Not on server";
        
        }
	}

    public function createOrManage($item, $columnMappingKeyToSearchBy = null)
    {
        $debug = false;

        if ($debug)
        {
            error_log("Will create or manage item: ".print_r($item, true));
        }

        if (!is_array($item))
        {
            throw new Exception("Item is not an array: ".print_r($item, true));
        }

        if (!$columnMappingKeyToSearchBy)
        {
            $columnMappingKeyToSearchBy = $this->defaulSearchByColumnMapping();
        }
        else if ($columnMappingKeyToSearchBy instanceof GTKColumnMapping)
        {
            $columnMappingKeyToSearchBy = $columnMappingKeyToSearchBy->phpKey;
        }
        else if (is_string($columnMappingKeyToSearchBy))
        {
            $columnMappingKeyToSearchBy = $this->dataMapping->columnMappingForPHPKey($columnMappingKeyToSearchBy);
        }
        

        $didLookup = false;

        if ($columnMappingKeyToSearchBy)
        {
            $valueToSearchFor = $columnMappingKeyToSearchBy->getValueFromArray($item);
            $dbItem           = $this->getOne($columnMappingKeyToSearchBy, $valueToSearchFor);
            $didLookup        = true;
        }

        if (is_array($columnMappingKeyToSearchBy))
        {
            $dbItem = $this->getOne($columnMappingKeyToSearchBy, $valueToSearchFor);
            $didLookup = true;
            $query = new SelectQuery($this);

            foreach ($columnMappingKeyToSearchBy as $keys => $value)
            {
                $query->where($keys, "=", $value);
            }

            $dbItem = $query->executeAndReturnOne();
        }

        if (!$didLookup)
        {
            throw new Exception("No column mapping key to search by found for: ".get_class($this));
        }

        if ($dbItem)
        {
            if ($debug)
            {
                error_log("Will update item: ".print_r($item, true));
            }

            $changes = $this->calculateChanges($dbItem, $item);

            if (count($changes))
            {
                if (method_exists($this, "updateFromSeed"))
                {
                    $this->updateFromSeed($item, $dbItem);
                }   
                else
                {
                   $this->update($item);
                }
            }

            return $this->valueForIdentifier($dbItem);
        }
        else
        {
            if ($debug)
            {
                error_log("Will insert item: ".print_r($item, true));
            }

            $id = null;
            
            if (method_exists($this, "insertFromSeed"))
            {
                $id = $this->insertFromSeed($item);
            }
            else
            {
                $id = $this->insert($item);
            }

            return $id;
        }
    }

    public function removeIdentifierKeyFromItem(&$item)
    {
        $primaryKeyMapping = $this->primaryKeyMapping();

        $phpKey = $primaryKeyMapping->phpKey;

        if (isset($item[$phpKey]))
        {
            unset($item[$phpKey]);
        }

        $dbKey = $primaryKeyMapping->getSqlColumnName();

        if (isset($item[$dbKey]))
        {
            unset($item[$dbKey]);
        }

        return $item;
    }

    public function createOrAnnounceTable()
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("Will create table for: ".get_class($this));
            gtk_log("Will use `createTable()` for: ".get_class($this));
        }

        $driverName = $this->getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!$this->tableExists())
        {
            switch ($driverName)
            {
                case "mysql":
                case "sqlsrv":
                case "pgsql":
                case "oci":
                case "oci8":
                case "odbc":
                    gtk_log("CANNOT create table for: ".get_class($this));
                    $sql = $this->createTableSQLString();
                    gtk_log("Table is not created. Consider: ".$sql);
                    return;
                case "sqlite":
                default:
                    $this->createTable();
                    return;

                if ($debug)
                {
                    error_log("Did create table");
                }
            }
        }
        else
        {
            switch ($driverName)
            {
                case "mysql":
                case "sqlsrv":
                case "pgsql":
                case "oci":
                case "oci8":
                case "odbc":
                    $columns = $this->dataMapping->ordered;
                    $missingColumns = [];
        
                    foreach ($columns as $columnMapping)
                    {
                        if (!$columnMapping->doesColumnExist($this->getPDO(), $this->tableName()))
                        {
                            $missingColumns[] = $columnMapping;
                            // $columnMapping->addColumnIfNotExists($this->getPDO(), $this->tableName());
                        }
                    }
        
                    if (count($missingColumns))
                    {
                        gtk_log("On...`".get_class($this)."`: ".$this->tableName());
                        gtk_log("Missing columns: ");
        
                        foreach ($missingColumns as $columns)
                        {
                            $columnSQL = $columnMapping->getCreateSQLForPDO($this->getPDO());
                            $message = "For column: ".$columnMapping->phpKey." consider... ".$columnSQL;
                            gtk_log($message);
                        }
        
                        return;
                    }
                    return;
                case "sqlite":
                default:
                    if ($debug)
                    {
                        error_log("Table already exists.");
                        error_log("Creating missing columns...");
                    }
                    $columns = $this->dataMapping->ordered;
                
                    foreach ($columns as $columnMapping)
                    {
                        $columnMapping->addColumnIfNotExists($this->getPDO(), $this->tableName());
                    }
            }
        }
    }

    public function createOrManagePermissionsWithKey($key)
    {
        $permissions = [
            "create",
            "read",
            "update",
            "delete",
            //-------------------------------------------------------------------------
            "all",
        ];

        foreach ($permissions as $permissionKey)
        {
            $permissionName = $key.".".$permissionKey;

            $permission = [
                "name"         => $permissionName,
                "is_active"    => true,
                "date_created" => date("Y-m-d H:i:s"),
            ];

            echo "Creating permission: ".$permissionName."\n";

            DataAccessManager::get("permissions")->insertIfNotExists($permission);
        }

        foreach ($this->dataSetViews as $name => $dataSetView)
        {
            $permissionName = $key.".".$name;

            $permission = [
                "name"         => $permissionName,
                "is_active"    => true,
                "date_created" => date("Y-m-d H:i:s"),
            ];

            echo "Creating permission: ".$permissionName."\n";

            DataAccessManager::get("permissions")->insertIfNotExists($permission);
        }
    }

    function deleteRelation($conditions)
    {
        $sql = "DELETE FROM " . $this->tableName() . " WHERE ";
        $params = [];
        $clauses = [];

        foreach ($conditions as $columnKey => $value) {
            $columnName = $this->dbColumnNameForKey($columnKey);

            if (!$columnName) {
                gtk_log("No column name found for key: $columnKey");
                die("Error de sistema.");
            }

            $clauses[] = $columnName . " = :" . $columnKey;
            $params[":" . $columnKey] = $value;
        }

        $sql .= implode(" AND ", $clauses);

        $statement = $this->getPDO()->prepare($sql);

        foreach ($params as $param => $value) {
            $statement->bindValue($param, $value);
        }

        $statement->execute();
    }
    
	public function __construct(PDO $PDODBObject, $options)
    {
        $debug = false;

        // if the class name is StoneConduce...I want to set debug to true
        if (strpos(get_class($this), "StoneConduce") !== false)
        {
            $debug = false;
        }

		$this->db = $PDODBObject;

        $this->dataAccessorName = $options["dataAccessorName"] ?? get_class($this);
        
        if($debug)
        {
            error_log("esto es dataAccessorName: ".$this->dataAccessorName);
        }

        $this->_actions = [];

        $this->_allowsCreation = true;
		
        
        
        if(isset($options["tableName"]))
        {
            $this->setTableName($options["tableName"]);
        }

        
        $this->defaultOrderByColumnKey  = $options["defaultOrderByColumnKey"] ?? null;
        $this->defaultOrderByOrder      = $options["defaultOrderByOrder"] ?? "ASC";
        $this->defaultSearchByColumnKey = $options["defaultSearchByColumnKey"] ?? null;
        
        if ($debug)
        {
            error_log("Will register for: ".get_class($this));
        }

        $this->register();//no hace nada en DataAccess

        if ($debug)
        {
            error_log("Did register for: ".get_class($this));
        }
        
        if (!$this->dataMapping)
        {
            throw new Exception("DataMapping is not set for: ".get_class($this));
        }

        if (!$this->primaryKeyMapping())
        {
            $columnMapping = $this->dataMapping->columnMappingForKey("id");

            if (!$columnMapping)
            {
                throw new Exception("Column mapping for id is not set for: ".get_class($this));
            }
            
            $columnMapping->setAsPrimaryKey();
            $columnMapping->setAsAutoIncrement();
            $this->dataMapping->primaryKeyMapping = $columnMapping;
        }
        
        $this->dataMapping->setDataAccessor($this);

        if ($this->_tableName)
        {
            $this->dataMapping->tableName = $this->_tableName;
            
            if($debug)
            {
                error_log("esto es dataMapping->tableName: ".$this->dataMapping->tableName);
            }

        }

        global $_GLOBALS;
        
        $runCreateTable = false;

        if ($debug)
        {
            gtk_log("Will use options: ".print_r($options, true));
        }
        
        if (isset($_GLOBALS["RUN_CREATE_TABLE"]))
        {
            if ($debug)
            {
                gtk_log('Will use `$_GLOBALS["RUN_CREATE_TABLE"]` for: '.get_class($this));
            }
            $runCreateTable = $_GLOBALS["RUN_CREATE_TABLE"];
        }
        else if (isset($options["runCreateTable"]) && ($options["runCreateTable"] == true))
        {
            if ($debug)
            {
                gtk_log('Will use `$options[runCreateTable]` for: '.get_class($this));
            }
            $runCreateTable = true;
        }

        if ($runCreateTable)
        {
            $this->createOrAnnounceTable();
        }
        else
        {
            if ($debug)
            {
                gtk_log("Will NOT create table for: ".get_class($this));
            }
        }

        if (isset($config["permissions"]))
		{
			if (method_exists($this, "setPermissions"))
			{
				$this->setPermissions($config["permissions"]);
			}
		}


        

        if (method_exists($this, "migrate"))
        {
            if ($debug)
            {
                gtk_log("Will use `migrate()` for: ".get_class($this));
            }
            $this->migrate();
        }

        ifResponds0($this, "postRegisterCreateTableAndMigrate"); 
	}

    public function getAll()
    {
        $query = new SelectQuery($this);
        return $query->executeAndReturnAll();
    }

    public function getFreshColumnMappings()
    {
        throw new Exception("getFreshColumnMappings is not implemented for: ".get_class($this));
    }

	public function register()
    {
        $columnMappings = $this->getFreshColumnMappings();    
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    public function serialize() 
    {
        return serialize([
          'dataSetViews' => $this->dataSetViews, 
          'dataAccessorName' => $this->dataAccessorName,
          'db' => get_class($this->db) . ' Connection', 
          'tableName' => $this->tableName,
          'dataMapping' => $this->dataMapping,
          'defaultOrderByColumnKey' => $this->defaultOrderByColumnKey,
          'defaultOrderByOrder' => $this->defaultOrderByOrder,
          'singleItemName' => $this->singleItemName,
          'pluralItemName' => $this->pluralItemName,
          'editURL' => $this->editURL,
          'crudBaseRoute' => $this->crudBaseRoute,
          '_actions' => $this->_actions,
          'cache' => $this->cache,
          '_allowsCreation' => $this->_allowsCreation,
          'preSetFilters' => $this->preSetFilters,
          'sqlServerTableName' => $this->sqlServerTableName,
          'sqlServerDefaultOrderByColumn' => $this->sqlServerDefaultOrderByColumn,
          'sqliteTableName' => $this->sqliteTableName,
          'sqliteDefaultOrderByColumn' => $this->sqliteDefaultOrderByColumn,
        ]);
    }

    public function unserialize($data) 
    {
        $data = unserialize($data);
        
        $this->dataSetViews = $data['dataSetViews'];
        $this->dataAccessorName = $data['dataAcc essorName'];
        $this->tableName = $data['tableName'];
        $this->dataMapping = $data['dataMapping'];
        
        // re-initialize non-serializable values
        // $this->db = new PDO(...); 
    }
	
    /////////////////////////////////////////////////////////////////////////
    // -
    // - Naming
    // -
    /////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////

    public function singleItemName()
    {   
        $currentClass = get_class($this);

        while ($currentClass) 
        {
            $translationKey = $currentClass . "/" . "single";
            $translation = Glang::get($translationKey, ["allowReturnOfNull" => true]);
    
            if ($translation) 
            {
                return $translation;
            }
    
            $currentClass = get_parent_class($currentClass);
        }
        
        if (isset($this->singleItemName) && !empty($this->singleItemName)) 
        {
            return $this->singleItemName;
        } 
        
        $className = get_class($this);
        $nameToUse = $className;

        if (substr($className, -strlen("DataAccess")) === "DataAccess") 
        {
            $nameToUse = substr($className, 0, strlen($className) - strlen("DataAccess"));
            error_log("No Glang for plural name of: ".$className);
        }

        return $this->convertCamelCaseToSpace($nameToUse);
    }


    public function getPluralItemName() 
    {   
        $currentClass = get_class($this);

        while ($currentClass) 
        {
            $translationKey = $currentClass . "/" . "plural";
            $translation = Glang::get($translationKey, ["allowReturnOfNull" => true]);
    
            if ($translation) 
            {
                return $translation;
            }
    
            $currentClass = get_parent_class($currentClass);
        }
        
        // Convert class name from CamelCase to spaced format and add 's' for pluralization
        $className = get_class($this);
        $nameToUse = $className;

        if (substr($className, -strlen("DataAccess")) === "DataAccess") 
        {
            $nameToUse = substr($className, 0, strlen($className) - strlen("DataAccess"));
            error_log("No Glang for plural name of: ".$className);
        }

        return $this->convertCamelCaseToSpace($nameToUse);
    }

    private function convertCamelCaseToSpace($str) {
        return preg_replace('/(?<=\\w)(?=[A-Z])/', " $1", $str);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // -
    // - Display
    // -
    ///////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////

    public function rowStyleForItem($item, $index) {}

    public function displayActionsForUserItem($user, $item, $location = "list")
    {
        $debug = false;

        $toReturn = "";

        $didPrint = false;

        if ($this->userHasPermissionTo("edit", $user, $item))
        {
            $toReturn .= $this->linkForKeyItemOptions("edit", $item, [
                "label" => "Editar"
            ]);
            $toReturn .= "<br/>";
            $didPrint = true;
        }

        
        if ($this->userHasPermissionTo("show", $user, $item))
        {
            $toReturn .= $this->linkForKeyItemOptions("show", $item, [
                "label" => "Ver"
            
            ]);
            $toReturn .= "<br/>";
            $didPrint = true;
        }

        if ($didPrint)
        {
            $toReturn .= "<hr/>";
            $toReturn .= "<br/>";
        }

        $actions = $this->actionsForLocationUserItem($location, $user, $item);

        if ($debug)
        {
            error_log("Display actions for items: ".count($actions));
        }

        foreach ($actions as $action)
        {
            $toReturn .= $action->anchorLinkForItem($user, $item);
        }

        if ($debug)
        {
            error_log("Display actions for items: ".$toReturn);
        }

        return $toReturn;
    }

    public function tableRowContentsForUserItemColumns($user, $item, $columnsToDisplay)
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("tableRowContents --- item --- ".print_r($item, true));
        }

        $isFirstColumn = true;

        $toReturn = "";
    
        $toReturn .= "<td>".$this->displayActionsForUserItem($user, $item)."</td>";
    
        $primaryKeyMapping = $this->primaryKeyMapping();
        // $primaryKeyPHPKey  = $primaryKeyMapping->phpKey;
        $primaryKeyValue   = $primaryKeyMapping->valueFromDatabase($item);

        if ($debug)
        {
            error_log("Will display columns: ");
        }


    
        foreach ($columnsToDisplay as $maybeColumnMapping) 
        {
            if (is_string($maybeColumnMapping))
            {
                $columnMapping = $this->columnMappingForKey($maybeColumnMapping);
            }
            else if ($maybeColumnMapping instanceof GTKTableCellItemPresenter)
            {
                $columnMapping = $maybeColumnMapping;
            }
            else
            {
                $columnMapping = $maybeColumnMapping;
            }


            $toReturn .= $columnMapping->listDisplayForDataSourceUserItem($this, $user, $item, $primaryKeyValue);
        }

        if ($debug)
        {
            error_log("tableRowContents --- ".$toReturn);
        }
        
        return $toReturn;
    }

    public function urlForKeyItemOptions($key, $item, $options = null)
    {
        $itemIdentifier = null;
        
        if (is_array($item))
        {
            $primaryKeyMapping = $this->primaryKeyMapping();
            $itemIdentifier    = $primaryKeyMapping->valueFromDatabase($item);
        }
        else
        {
            $itemIdentifier = $item;
        }
        
        return $this->urlForKeyItemIdentifierOptions($key, $itemIdentifier, $options);
    }

    public function urlForKeyItemIdentifierOptions($key, $itemIdentifier, $options = null)
    {
        $queryParameters = $options["queryParameters"] ?? [];

        $queryParameters["id"]          = $itemIdentifier;
        $queryParameters["data_source"] = get_class($this);
        $queryParameters["data_source"] = $this->dataAccessorName;

        $toReturn  = "";
        $toReturn .= $key."?".http_build_query($queryParameters);

        return $toReturn;
    }

    public function linkForKeyItemOptions($key, $item, $options = null)
    {
        $debug = false;


        $label = ucfirst($key);

        $label = $options["label"] ?? "Item";

        $toReturn  = "";
        $toReturn .= '<a ';
        $toReturn .= ' href="'.$this->urlForKeyItemOptions($key, $item, $options).'"';
        $toReturn .= '>';
        $toReturn .= $label;
        $toReturn .= '</a>';

        return $toReturn;
    }

    public function showURLForItem($item, $options = null)
    {
        return $this->urlForKeyItemOptions("show", $item, $options);
    }

    public function editURLForItem($item, $options = null)
    {
        return $this->urlForKeyItemOptions("edit", $item, $options);
    }

    //-----------

    public function mostAdvancedLinkForUserItem($user, $item, $options = null)
    {
        $debug = false;

        $updatePermission = $this->userHasPermissionTo("update", $user, $item);

        $value = $options["label"] ?? $this->identifierForItem($item);

        if ($debug)
        {
            error_log("Has update permission? ".$updatePermission);
        }

        if ($updatePermission)
        {
            return $this->editLinkForItem($item, [
                'label' => $value,
            ]);
        }


        $readPermission = $this->userHasPermissionTo("show", $user, $item);

        if ($readPermission)
        {
            return $this->showLinkForItem($item, [
                'label' => $value,
            ]);
        }

        return null;
    }

    public function showLinkForItem($item, $options = null)
    {
        return $this->linkForKeyItemOptions("show", $item, $options);
    }

    public function editLinkForItem($item, $options = null)
    {
        return $this->linkForKeyItemOptions("edit", $item, $options);
    }

    ////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////
    // -------------------------------------------------------------
    // Columns
    // -------------------------------------------------------------
    ////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////
    
    public function columnMappingForKey($key)
    {
        return $this->dataMapping->columnMappingForKey($key);
    }

    public function columnNameForKey($key)
    {
        $columnMapping = $this->columnMappingForKey($key);

        if ($columnMapping)
        {
            return $columnMapping->getSqlColumnName();
        }
        else
        {
            return null;
        }
    }

    public function getOrderedColumns()
    {
        return $this->dataMapping->ordered;
    }

    public function getOrderedColumnKeys()
    {
        $columnKeys = [];

        foreach($this->dataMapping->ordered as $columnMapping)
        {
            array_push($columnKeys, $columnMapping->phpKey);
        }

        return $columnKeys;
    }

    public function labelForColumnKey($key)
    {
        $columnMapping = $this->columnMappingForKey($key);
        return $columnMapping->getFormLabel($this);
    }

    public function valueForIdentifier($item)
    {
        return $this->dataMapping->valueForIdentifier($item);
    }

    function getByPHPKey($key, $array)
    {
        return $this->valueForKey($key, $array);
    }

    function valueForKey($key, $array, $options = null)
    {
        return $this->dataMapping->valueForKey($key, $array, $options);
    }

    function dbColumnNameForPHPKey($key)
    {
        return $this->dataMapping->dbColumnNameForPHPKey($key);
    }

    public function dbColumnNameFor($key)
    {
        return $this->dataMapping->dbColumnNameForKey($key);
    }

    public function dbColumnNameForKey($key, $includeTableName = false)
    {
        $columnName = $this->dataMapping->dbColumnNameForKey($key);

        if ($includeTableName)
        {
            $tableName = $this->tableName();
            $columnName = $tableName.".".$columnName;
        }

        return $columnName;
    }

    public function joinConditionForKeyToKey($key, $otherTableKey)
    {
        return $this->joinConditionForKeyToTableKey($key, $otherTableKey);
    }

    public function joinConditionForKeyToTableKey($key, $tableNameWithKey, $tableKey = null)
    {
        $dbColumnName = $this->dbColumnNameForKey($key, true);

        if ($tableKey)
        {
            $dbColumnNameToJoinOn = DAM::get($tableNameWithKey)->dbColumnNameForKey($tableKey, true);
        }
        else
        {
            if (strpos($tableNameWithKey, ".") == false)
            {
                throw new Exception("Invalid key name: ".$tableNameWithKey);
            }

            $array = explode(".", $tableNameWithKey);
            $tableName = $array[0];
            $otherTableKey = $array[1];
            $dbColumnNameToJoinOn = DAM::get($tableName)->dbColumnNameForKey($otherTableKey, true);
        }

        return $dbColumnName." = ".$dbColumnNameToJoinOn;
    }

    public function getSearchableColumnsForUser($user, $groupName = null)
    {
        $debug = false;

        $toReturn = [];
        
        $columns = $this->dataMapping->ordered;

        if (!$groupName || ($groupName === "searchable"))
        {
            foreach ($columns as $columnMapping)
            {
                if ($columnMapping->isSearchable())
                {
                    array_push($toReturn, $columnMapping);
                }
            }
        }
        else if ($groupName === "all")
        {
            $toReturn = $columns;
        }
        else if ($groupName)
        {
            foreach ($columns as $columnMapping)
            {
                if ($columnMapping->isPartOfGroupAndUser($groupName, $user))
                {
                    array_push($toReturn, $columnMapping);
                }
            }
        }
        else
        {
            $toReturn = [];

            foreach ($columns as $columnMapping)
            {
                if ($columnMapping->isSearchable())
                {
                    array_push($toReturn, $columnMapping);
                }
            }
        }


        usort($toReturn, function($a, $b) {
            return strcasecmp($a->getFormLabel($this), $b->getFormLabel($this));
        });

        if ($debug)
        {
            error_log("Searchable columns for user: ".count($toReturn));
        }

        return $toReturn;
    }

    /////////////////////////////////////////////////////////////////////////////////
    // -
    // - Data Set Views
    // -
    /////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////

    public function addDataSetView($lookupKey, $DataSetView)
    {
        $this->dataSetViews[$lookupKey] = $DataSetView;
    }

    public function getDataSetView($lookupKey)
    {
        return $this->dataSetViews[$lookupKey];
    }

    // MARK: - Columns

    public function addAction($actionName, $actionArray = null)
    {
        if (!$this->_actions)
        {
            $this->_actions = [];
        }
        if (is_object($actionName))
        {
            $this->_actions[$actionName->key] = $actionName;
        }
        else if (is_array($actionName))
        {
            $keyName = $actionArray["key"];
            $this->_actions[$keyName] = $actionArray;
        }
        else
        {
            $this->_actions[$actionName] = $actionArray;
        }
        
    }


    // MARK: - CACHE


    public function getObjectPreferCache($cacheName, $objectKey, Closure $fetchClosure)
    {
        if (isset($this->cache[$objectKey]))
        {
            return $this->cache[$objectKey];
        }

        if (!$fetchClosure instanceof Closure) {
            throw new Exception("The provided object is not a Closure");
        }

        $object = $fetchClosure($objectKey);

        if ($object)
        {
            $this->cache[$objectKey] = $object;
            return $object;
        }
        else
        {
            $this->cache[$objectKey] = false;
            return false;
        }
    }
    
    public function getObjectForArray(&$solicitud, $objectKey, Closure $dataClosure)
    {
        if (!$solicitud)
        {
            throw new Exception("Searching for array in empty object.");
        }
        
        if (!isset($solicitud["objects"]) || !is_array($solicitud["objects"])) {
            $solicitud["objects"] = [];
        }

        if (isset($solicitud["objects"][$objectKey])) {
            return $solicitud["objects"][$objectKey];
        }

        if (!$dataClosure instanceof Closure) {
            throw new Exception("The provided object is not a Closure");
        }

        $data = $dataClosure($objectKey, $solicitud);
    
        if ($data)
        {
            $solicitud["objects"][$objectKey] = $data;
            return $data;
        }
        else
        {
            // $solicitud["objects"][$objectKey] = null; // Will NOT appear as set
            $solicitud["objects"][$objectKey] = false; // Will appear as set
            return null;
        }
    }

	public function sqlServerTableName(){
		return $this->sqlServerTableName;
	}

	public function defaultOrderByColumnKey(){
		return $this->defaultOrderByColumnKey;
	}

    public function actionsForLocationUserItem($location, $user, $item) 
    { 
        $debug = false;

        $actionsForLocation = [];

        if ($debug)
        {
            error_log("Building actions for location: $location");
        }

        foreach ($this->_actions as $action)
        {
            if ($debug)
            {
                error_log("Checking action with label: ".$action->labelForUserItem($user, $item));
            }

            switch ($location)
            {
                case "all":
                case "list":
                case "lists":
                    if (!$action->hideOnListsForUserItem($user, $item))
                    {
                        if ($debug)
                        {
                            error_log("Adding to list");
                        }
                        $actionsForLocation[] = $action;
                    }
                    break;
                case "show":
                case "edit":
                default:
                    if (!$action->hideOnEditForUserItem($user, $item))
                    {
                        if ($debug)
                        {
                            error_log("Adding to edit list");
                        }
                        $actionsForLocation[] = $action;
                    }
                    break;
            }
        }

        if ($debug)
        {
            error_log("Got actions for location: ".count($actionsForLocation));
        }

        return $actionsForLocation;
    }

    public function getAction($name)
    {
        return $this->_actions[$name];
    }

    public function itemContainsPrimaryMappingKey($item)
    {
        return $this->primaryKeyMapping()->doesItemContainOurKey($item);
    }

    public function primaryKeyMapping()
    {
        return $this->dataMapping->primaryKeyMapping;
    }

    public function isSame($a, $b)
    {
        return ($a === $b);
    }


    public function identifierForItem($item)
    {
        $identifierValue = $this->dataMapping->primaryKeyMapping->valueFromDatabase($item);
        
        if (!$identifierValue) 
        {
            foreach ($this->identifierKeys as $key)
            {
                if (isset($item[$key]))
                {
                    $identifierValue = $item[$key];
                }
            }
        }

        return $identifierValue;
    }

    public function getByIdentifier($identifier)
    {
        if (is_array($identifier))
        {
            $query = new SelectQuery($this);
            $query->where(new WhereClause(
                $this->primaryKeyMapping()->getSqlColumnName(), "IN", $identifier
            ));
            return $query->executeAndReturnAll();
        }
        else
        {
            $columnName = $this->dataMapping->primaryKeyMapping->getSqlColumnName();
            return $this->getOne($columnName, $identifier);
        }
    }

    public function getById($identifier)
    {
        return $this->getByIdentifier($identifier);
    }


    public function areEqual($itemA, $itemB)
    {
        $itemAIdentifier = $this->identifierForItem($itemA);
        $itemBIdentifier = $this->identifierForItem($itemB);

        return ($itemAIdentifier === $itemBIdentifier);
    }

    /////////////////////////////////////////////////////////////////
    // -
    // - Permssissions
    // -
    /////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////
    
    public function itemIsVisibleToUser(&$user, &$item)
    {
        return true;
    }

    public function canViewEditPage(&$user, &$item)
    {
        return false;
    }

    public function isEditableToUser(&$user, &$item)
    {
        return false;
    }

    public function isInvalidForTypeUserOnItem($type, $user, $item)
    {
        $debug = false;
        return null;

        $errorFields = [];

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            if ($columnMapping->isPrimaryKey)
            {
                continue;
            }
            if ($columnMapping->isAutoIncrement)
            {
                continue;
            }

            $value = $this->getByPHPKey($columnMapping->phpKey, $item);

            if ($columnMapping->isRequired())
            {
                if (!$value || strlen($value) == 0)
                {
                    $message = "El campo {$columnMapping->getFormLabel} es requerido.";
                    if ($debug)
                    {
                        gtk_log($message);
                    }
                    $errorFields[$columnMapping->phpKey] = $message;
                    continue;
                }
            }
            if ($columnMapping->validate)
            {
                if ($debug)
                {
                    gtk_log("Validating {$columnMapping->phpKey} with value: {$value}");
                }

                $result = $columnMapping->isInvalidForUserOnItem($user, $value);

                if ($result)
                {
                    $result->mergeWithResult($result);
                    $errorFields[$columnMapping->phpKey] = $result->message();
                }

                if ($debug)
                {
                    gtk_log("Validation result: {$result}");
                }
            }
        }

        if (count($errorFields))
        {
            return $errorFields;
        }
        else
        {
            return null;
        }
    }

    public function editableFieldsForUserItem($user, $item)
    {
        $debug = false;

        $fields = [];

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            if ($columnMapping->isPrimaryKey)
            {
                continue;
            }
            if ($columnMapping->isAutoIncrement)
            {
                continue;
            }

            if ($columnMapping->isEditableForUserOnItem($user, $item))
            {
                array_push($fields, $columnMapping);
            }
        }

        if ($debug)
        {
            error_log("Editable fields for user: ".count($fields));
        }

        return $fields;
    }

    /////////////////////////////////////////////////////////////////////////////////
    // -
    // - CREATION
    // -
    /////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////

    public function columnToCheckIfExists()
	{
        
			throw new Exception("TODO: `columnToCheckIfExists` for ".get_class($this));
	}

	public function createIfNotExists($item)
	{
		if (method_exists($this, "columnToCheckIfExists"))
		{
            $columnKey = $this->columnToCheckIfExists();

			if (!$this->getOne($columnKey, $this->valueForKey($columnKey, $item)))
			{
				$this->insert($item);
			}
		}
		else
		{
			throw new Exception("Column to check if exists does not exist");
		}
	}

    public function setTableName($name)
    {
        $this->_tableName = $name;
    }

	
	public function tableName() 
    {
        $debug = false;


		if ($this->_tableName)
		{
            if ($debug)
            {
                error_log("Table name is set: ".$this->_tableName);
            }
			return $this->_tableName;
		}
		else
		{
            if ($debug)
            {
                error_log("Table name is NOT set.");
            }
            $class = new ReflectionClass($this);
            while ($parent = $class->getParentClass()) {
                if ($parent->getName() === 'DataAccess') {
                    return $class->getName();
                }
                $class = $parent;
            }
            return get_class($this);
		}
	}

    public function getTableName()
    {
        return $this->tableName();
    }
	

    public function getPDO()
    {
        return $this->db;
    }
	
	public function getDB(){ 


        // if (typeof THIS.DB === "PDODATABASE")
        // {
        //
        // }
        // if ([$this->db isKindOfClass[PDODatabase class]])
        // {
        //     \
        // }
        //
        // if ($this->db != PDO)
        // {
        //     die("La aplication esta teniendo problemas");
        // }
        
        return $this->db; 
    }
	

    public function createTableSQLString()
    {
        $debug = false;

        $columns = $this->dataMapping->ordered;

        $sql = "";
        $tableName = $this->tableName();

        $sql .= "CREATE TABLE ".$tableName;
        $sql .= " (";

        $isFirst = true;

        if ($debug)
        {
            error_log("Will make table with ".count($columns)." columns.");
        }

        $additionalIndexQueries = [];

        foreach ($columns as $columnMapping)
        {
            if (!($columnMapping instanceof GTKColumnMapping))
            {
                continue;
            }
            if ($debug)
            {
                error_log("'DataAccess/createTableSQLString()' - working on column: ".$columnMapping->phpKey);
            }
            if ($isFirst)
            {
                $isFirst = false;
            }
            else
            {
                $sql .= ", ";
            }
            
            $sql .= $columnMapping->getCreateSQLForPDO($this->getPDO());      
        }

        $sql .= ");";

        foreach ($additionalIndexQueries as $query)
        {
            $sql .= $query.";";
        }

        if ($debug)
        {
            error_log("Create Table - SQL: ".$sql);
        }

        return $sql;
    }

    public function createUniqueIndexes()
    {
        $debug = false;

        $tableName = $this->tableName();
        $columns   = $this->dataMapping->ordered;

        foreach ($columns as $columnMapping)
        {
            if (!$columnMapping->isPrimaryKey()) 
            {
                if ($columnMapping->isUnique()) 
                {
                    $columnName = $columnMapping->dbColumnName();
                    // Assuming the table name is available in a variable $tableName
                    $uniqueIndexName = "unique_".$tableName."_".$columnName; // Generate a unique index name
                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS $uniqueIndexName ON $tableName ($columnName)"; // Append the SQL for creating a unique index
                    
                    if ($debug)
                    {
                        gtk_log("Creating unique index: ".$uniqueIndexName." - SQL: ".$sql);
                    }
                    
                    $this->getPDO()->exec($sql);

                }
            }
            
        }
    }

    public function createTable()
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("Running `DataAccess/createTable`");
        }

        if (!$this->tableExists())
        {
            
            $sql = $this->createTableSQLString();

            try
            {
                if ($debug)
                {
                    gtk_log("createTable :://:: Will exec query: $sql");
                }

                $this->getPDO()->exec($sql);

                if ($debug)
                {
                    gtk_log("Table created.");
                }
            }
            catch (PDOException $e)
            {
                die("Error creating table for ".get_class($this)." SQL: ".$sql." - ".$e->getMessage());
                
                $isInvalid = '';

                QueryExceptionManager::manageQueryExceptionForDataSource(
                    $this, 
                    $e, 
                    $sql, 
                    null,  // $item,
                    $isInvalid); // $isInvalid
        
                throw $e;
            }

        }
        else
        {
            if ($debug)
            {
                error_log("Table already exists.");
                error_log("Creating missing columns...");
            }
            $columns = $this->dataMapping->ordered;

            foreach ($columns as $columnMapping)
            {
                $columnMapping->addColumnIfNotExists($this->getPDO(), $this->tableName());
            }
        }

        $this->createUniqueIndexes();
    }

    public function allowsCreationForUser($user)
    {
        return false;
    }

    public function allowsCreation()
    {
        return $this->_allowsCreation;
    }

    public function addFilter($filterName, $filter)
    {
        if (!isset($this->preSetFilters) || is_null($this->preSetFilters))
        {
            $this->preSetFilters = [];
        }

        $this->preSetFilters[$filterName] =  $filter;
    }

    public function removeFilterWithName($filterName)
    {
        unset($this->preSetFilters[$filterName]);
    }

    public function getFilter($name)
    {
        return $this->preSetFilters[$name];
    }

    public function filters($user = null)
    {
        if ($this->preSetFilters)
        {
            return $this->preSetFilters;
        }
        else
        {
            return null;
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // -
    // - Duplicates
    // -
    ///////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////

    function duplicatesFromOptions($options)
    {
        $debug = 0;

        $columnName = null;

        if (isset($options["columnName"]))
        {
            $columnName = $options["columnName"];
        }

        return $this->duplicatesOnColumn($columnName);
    }

    function  duplicatesOnColumn($columnName)
    {
        $columnName = $this->columnNameForKey($columnName);

        $sql = <<<EOD
        WITH Duplicates AS (
            SELECT $columnName
            FROM {$this->tableName}
            GROUP BY $columnName
            HAVING COUNT($columnName) > 1
        )
        SELECT t.*
        FROM {$this->tableName} t
        INNER JOIN Duplicates d ON t.$columnName = d.$columnName
        WHERE t.$columnName IS NOT NULL AND t.$columnName != ''
        ORDER BY t.$columnName;
        EOD;

        $stmt = $this->getDB()->prepare($sql);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    
    function transferDuplicatedOnColumn($softColumnName, $newTableName)
    {
        $debug = false;

        $columnMapping = $this->columnMappingForKey($softColumnName);

        if ($debug)
        {
            echo "Got column mapping: ".serialize($columnMapping)."\n";
        }
        

        if (!$columnMapping)
        {
            die("No column mapping found for key: $softColumnName on ".get_class($this));
        }

        $columnName = $columnMapping->getSqlColumnName();

        if ($debug)
        {
            echo "Got column name: ".$columnName."\n";
        }

        $duplicateRows = $this->duplicatesOnColumn($columnName);

        $duplicatesValues =  array_map(function($row) use ($columnName) {
            return $row[$columnName];
        }, $duplicateRows);

        $whereConditions = "$columnName IN (";
        $isFirst         = true;

        $isString = true;

        foreach ($duplicatesValues as $value)
        {
            if (!$isFirst)
            {
                $whereConditions .= ",";
            }

            
            if ($isString)
            {
                $whereConditions .= "'";
            }
            
            $whereConditions .= $value;

            if ($isString)
            {
                $whereConditions .= "'";
            }

            $isFirst = false;
        }

        $whereConditions .= ")";

        $this->transferRecords($newTableName, $whereConditions);
    }

    public function beginDefferedTransaction()
    {
        $this->getDB()->beginTransaction();
    }

    public function beginExclusiveTransaction()
    {
        $this->getDB()->exec("BEGIN EXCLUSIVE TRANSACTION;");
    }

    public function beginImmediateTransaction()
    {
        $this->getDB()->exec("BEGIN IMMEDIATE TRANSACTION;");
    }

    public function beginTransaction($timeout = 5000)
    {
        $dbDriver = $this->getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($dbDriver)
        {
            case 'mysql':
                $this->getDB()->exec("SET SESSION wait_timeout = $timeout");
                break;
            case 'sqlite':
                $this->getDB()->exec("PRAGMA busy_timeout = $timeout");
                break;
            default:
                break;
        }
        
        $this->getDB()->beginTransaction();


        switch ($dbDriver)
        {
            case 'sqlite':
                $this->getDB()->exec("PRAGMA read_uncommitted = true");
                break;
            default:
                break;
        }
    }

    public function commit()
    {
        $this->getDB()->commit();
    }

    public function rollback()
    {
        $this->getDB()->rollBack();
    }

    public function endTransaction()
    {
        $this->getDB()->commit();
    }

    public function endTransactionWithCommit()
    {
        $this->getDB()->commit();
    }

    public function endTransactionAndRollback()
    {
        $this->getDB()->rollBack();
    }

    function transferRecords($newTable, $whereConditions) 
    {
        $debug = false;

        $db = $this->getDB();

        try {
            $db->beginTransaction();
            $originalTable =  $this->tableName();

            $sql = "SELECT * INTO $newTable FROM $originalTable WHERE $whereConditions";

            if ($debug)
            {
                echo $sql;
            }

            $db->exec($sql);
    
            $sql = "DELETE FROM $originalTable WHERE $whereConditions";
            $db->exec($sql);
    
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            throw $e;
        }
    }

    
    // MARK: - WHERE



    // MARK: - Delete


    function delete($item)
    {
        if (is_string($item))
        {
            return $this->deleteID($item);
        }
        else
        {
            return $this->deleteObject($item);
        }
    }

    function deleteObject($object)
    {
        $identifier = $this->identifierForItem($object);

        return $this->deleteID($identifier);
    }

    function deleteWhere($columnKey, $value)
    {

        $columnName = $this->dbColumnNameForKey($columnKey);

        if (!$columnName)
        {
            gtk_log("No column name found for key: $columnKey");
            die("Error de sistema.");
        }

        $sql  = "DELETE FROM ".$this->tableName();
        $sql .= " WHERE ".$columnName." = :toBind";

        $statement = $this->getPDO()->prepare($sql);

        $statement->bindValue(":toBind", $value);

        $statement->execute();
    }

    function deleteID($identifier)
    {
        $sql = "DELETE FROM ".$this->tableName()." WHERE ".$this->dbColumnNameForKey("id")." = :id";

        $statement = $this->getPDO()->prepare($sql);

        $statement->bindValue(":id", $identifier);

        $statement->execute();
    }

    function deleteIDSContaining($identifier)
    {
        $sql = "DELETE FROM ".$this->tableName()." WHERE ".$this->dbColumnNameForKey("id")." LIKE :id";

        gtk_log($sql);

        $statement = $this->getPDO()->prepare($sql);

        $statement->bindValue(":id", '%'.$identifier.'%');

        $statement->execute();
    }

    // MARK: - COUNT


    function count($options = null) {
        $debug = 0;

        $sql = "SELECT count(*) FROM ".$this->tableName();

        $whereOptions = null;

        if ($options && array_key_exists('where', $options))
        {
            if ($debug)
            {
                gtk_log("`count`: Where options found.");
            }
            

            $whereOptions = $options['where'];

            $sql = $this->addWhereClausesToSql($sql, $whereOptions);
        }

        if ($debug)
        {
            gtk_log("`count`: SQL: {$sql}");
        }

        $stmt = $this->getDB()->prepare($sql);

        if ($whereOptions)
        {
            $this->applyWhereClauseToStatement($stmt, $whereOptions);
        }

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_COLUMN);

        return $result;
    }

    public function whereDict($num, $dict)
    {
        $whereClauses = [];

        foreach ($dict as $column => $filter)
        {
            $clause = new WhereClause($column, "=", $filter);

            $whereClauses[] = $clause;
        }

        $query = new SelectQuery($this, null, $whereClauses);

        $count = $query->count();

        if ($count === 0)
        {
            return [];
        }
        else if ($count === 1)
        {
            $result = $query->executeAndReturnAll();
            return $result[0];
            // return $generator->current();
        }
        else
        {
            return $query->executeAndYield();
        }
    }

    public function query()
    {
        return new SelectQuery($this);
    }

    public function whereWhere($num, ...$args)
    {
        $debug = false;

        if ($debug)
        {

        }

        if ((count($args) % 2) > 1)
        {
            throw new Exception("Missing parameter in args");
        }

        $whereClauses = [];

        for ($i = 0; $i < count($args); $i += 2)
        {
            $column = $args[$i];
            $filter  = $args[$i+1];

            $clause = new WhereClause($column, "=", $filter);

            $whereClauses[] = $clause;
        }

        $query = new SelectQuery($this, null, $whereClauses);

        $count = $query->count();
        

        if ($count == 0)
        {
            return null;
        }

        if ($count === 1)
        {
            $result = $query->executeAndReturnAll();
            return $result[0];
        }
        else
        {
            return $query->executeAndYield();
            
        }
    }

    public function where($parameterName, $maybeOperatorOrParameterValue, $maybeParameterValue = null)
    {
        $isOperator = WhereClause::isOperator($maybeOperatorOrParameterValue);

        if ($isOperator)
        {
            $query = new SelectQuery($this);
            $query->where(new WhereClause($parameterName, $maybeOperatorOrParameterValue, $maybeParameterValue));
            return $query->executeAndReturnAll();
        }
        else
        {
            $query = new SelectQuery($this);
            $query->where(new WhereClause($parameterName, "=", $maybeOperatorOrParameterValue));
            return $query->executeAndReturnAll();
        }
    }

    public function whereOne($parameterName, $parameterValue)
    {
        return $this->getOne($parameterName, $parameterValue);
    }

    function getOne($columnNameOrArrayOrColumnMapping, $input = null, $debug = false)
    {
        $debug = false;

        if (is_array($columnNameOrArrayOrColumnMapping))
        {
            $query = new SelectQuery($this);

            foreach ($columnNameOrArrayOrColumnMapping as $column => $value)
            {
                $query->where($column, "=", $value);
            }

            return $query->getOne();
        }
        else
        {
            $result = $this->getMany($columnNameOrArrayOrColumnMapping, $input, $debug);

            $toReturn = null;

            if (count($result))
            {
                $toReturn = $result[0];
            }
        
            if ($debug)
            {
                gtk_log("Returning: ".serialize($toReturn));
            }

            return $toReturn;
        }

    }
 


	public function findByParameter($parameterName, $parameterValue)
	{
        $columnName = $this->dbColumnNameForKey($parameterName);

		$query = "SELECT * FROM {$this->tableName()} WHERE ".$columnName." = :parameterValue";
		
		$db = $this->getDB();
				
		$statement = $db->prepare($query);

		$statement->bindValue(':parameterValue', $parameterValue);
			
		$statement->execute();
		
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $result;
	}






	function getMany($columnName, $input, $debug = false)
    {
        $debug = false;

        if ($columnName instanceof GTKColumnMapping)
        {
            $columnName = $columnName->getSqlColumnName();
        }
        else
        {
            $columnName = $this->dbColumnNameForKey($columnName);
        }
    
		if ($debug)
		{
			gtk_log("Debugging data access: `getMany()`");
			gtk_log("Column name: {$columnName}");
			gtk_log("Input: {$input}<br/>");
		}
		
        
        $sql = "SELECT * FROM ".$this->tableName()." WHERE ".$columnName." = :input";

		if ($debug)
		{
			gtk_log("SQL: {$sql}");
		}

        $stmt = $this->getDB()->prepare($sql);

        if (!($stmt instanceof PDOStatement))
        {
            gtk_log("Error: ".serialize($this->getDB()->errorInfo()));
            gtk_log("Statement: ".serialize($stmt));
            die("Error: ".serialize($this->getDB()->errorInfo()));
        }
        else if ($debug)
        {
            gtk_log("Got PDOStatement :).");
        }
        // Bind the parameter value
        $stmt->bindParam(':input', $input);

        // Execute the query
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ($debug)
		{
			$toPrint = serialize($result);
			gtk_log("Result: {$toPrint}");
		}

		return $result;
	}

    public function executeRawQuery($sql, $params = [])
    {
        $stmt = $this->getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }   


    function addWhereClausesToSql($sql, $whereOptions)
    {
        if (count($whereOptions) > 0)
        {
            $sql .= " WHERE ";
            $isFirst = true;

            foreach ($whereOptions as $clauseOptions)
            {
                $type = null;

                if (!array_key_exists("type", $clauseOptions))
                {
                    gtk_log("No type set in $clauseOptions");
                    die("Error de sistema.");
                }
                else
                {
                    $type = $clauseOptions['type'];
                }

                if ($isFirst)
                {
                    $isFirst = false;
                }
                else
                {
                    if (array_key_exists('condition', $clauseOptions))
                    {
                        switch ($clauseOptions['condition'])
                        {
                            case 'or':
                            case 'OR':
                                $sql .= " OR ";
                                break;
                            case 'not':
                            case 'NOT':
                                $sql .= " NOT ";
                                break;
                            case 'and':
                            case 'AND': 
                            default:
                                $sql .= " AND ";
                                break;
                        }
                    }
                    else
                    {
                        $sql .= " AND ";
                    }
                }

                if ($type === "raw")
                {
                    if (!array_key_exists('sql', $clauseOptions))
                    {
                        gtk_log("No SQL set in $clauseOptions");
                        die("Error de sistema.");
                    }
                    else
                    {
                        $sql .=  $clauseOptions['sql'];

                        if (array_key_exists('otherFilters', $clauseOptions))
                        {
                            foreach ($clauseOptions['otherFilters'] as $otherFilter)
                            {
                                $filter = $this->getFilter($otherFilter);
                                $sql .= " AND (".$filter['sql'].")";
                            }
                        }
                    }
                }
                else if ($type === "column")
                {
                    $pKey = null;

                    if (!array_key_exists('phpKey', $clauseOptions))
                    {
                        gtk_log("No PHP key set in $clauseOptions");
                        die("Error de sistema.");
                    }
                    else
                    {
                        $pKey = $clauseOptions['phpKey'];
                    }

                    $sqlKey = $this->dbColumnNameForPHPKey($pKey);

                    if (!$sqlKey)
                    {
                        gtk_log("No SQL key found for PHP key: {$pKey}");
                        die("Error de sistema.");
                    }

                    if (array_key_exists('operator', $clauseOptions))
                    {
                        switch($clauseOptions['operator'])
                        {
                            case 'like':
                            case 'LIKE':
                                $operator = 'LIKE';
                                break;
                            case 'equals':
                            case '=':
                            default:
                                $operator = '=';
                                break;
                        }
                    }
                    else
                    {
                        $operator = '=';
                    }

                    $sql .= "{$sqlKey} {$operator} :{$sqlKey}";
                    $toBind[$sqlKey] = $clauseOptions['value'];
                }
            }

            
            // return [
            //     'sql' => $sql,
            //     'toBind' => $toBind
            // ];

            return $sql;
        }
        else
        {
            // return [
            //     'sql' => $sql,
            //     'toBind' => []
            // ];
            return $sql;
        }
    }

    function applyWhereClauseToStatement($stmt, $whereOptions)
    {
        if (count($whereOptions) > 0)
        {
            foreach ($whereOptions as $clauseOptions)
            {  
                $type = null;

                if (!array_key_exists("type", $clauseOptions))
                {
                    gtk_log("No type set in $clauseOptions");
                    die("Error de sistema.");
                }
                else
                {
                    $type = $clauseOptions['type'];
                }
                
                if ($type == "column")
                {
                    $column = null;
                    $value  = null;

                    if (!isset($clauseOptions['phpKey']))
                    {
                        gtk_log("No column set in $clauseOptions");
                        die("Error de sistema.");
                    }
                    else
                    {
                        $column = $clauseOptions['phpKey'];
                    }
                    

                    if (!isset($clauseOptions['value']))
                    {
                        gtk_log("No value set for column: {$column}");
                        die("Error de sistema.");
                    }
                    else
                    {
                        $value = $clauseOptions['value'];
                    }

                    $sqlKey = $this->dbColumnNameForKey($column);
                 
                
                    if (array_key_exists('operator', $clauseOptions))
                    {
                        switch($clauseOptions['operator'])
                        {
                            case 'like':
                            case 'LIKE':
                                $stmt->bindValue(":{$sqlKey}", '%'.$value.'%');
                                break;
                            case 'equals':
                            case '=':
                            default:
                                $stmt->bindValue(":{$sqlKey}", $value);
                                break;
                        }
                    }
                    else
                    {
                        $stmt->bindValue(":{$sqlKey}", $value);
                    }
                }
            }
        }
    }


    // MARK: - SELECT ALL



    function selectAll() {
        $sql = "SELECT * FROM ".$this->tableName();

        $stmt = $this->getDB()->prepare($sql);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }


    public function selectAllOrderedBy($columnName, $orderByOrder = 'ASC')
    {
        return $this->selectFromOffset(-1, -1, [
            'orderBy'      => $columnName,
            'orderByOrder' => $orderByOrder,
        ]);
    }

    public function generateClosureForUser($user)
    {
        return null;
    }

    public function getGeneratorForUser($user, $options)
    {
        $offset = 0;
        $limit  = 300;

        if (isset($options["offset"]))
        {
            $offset = $options["offset"];
        }

        if (isset($options["limit"]))
        {
            $limit = $options["limit"];
        }

        $items = $this->selectFromOffsetForUser($user, $offset, $limit);

        $closure = $this->generateClosureForUser($user);

        return new ArrayGeneratorWithClosure($items, $closure);
    }

    // Quiet Baby Boy
    public function addWhereClauseForUser($user, &$selectQuery)
    {
        $debug = false;
        if ($debug)
        {
            error_log("`addWhereClauseForUser` - Got user: ".print_r($user, true));
            error_log("`addWhereClauseForUser` - Got select query: ".print_r($selectQuery, true));
        }

    }

    public function baseQueryForUser($user, $options = [])
    {
        $selectQuery = new SelectQuery($this);

        if (array_key_exists('where', $options))
        {
            $item = $options['where'];

            if (is_array($item))
            {
                foreach ($item as $value)
                {
                    $selectQuery->addWhereClause($value);
                }
            }
            else 
            {
                $selectQuery->addWhereClause($item);
            }
        }

        if (method_exists($this, "addWhereClauseForUser"))
        {
            $this->addWhereClauseForUser($user, $selectQuery);
        }


        return $selectQuery;
    }

    public function countForUser($user, $options = [])
    {
        return $this->baseQueryForUser($user, $options)->getCount();
    }

    public function selectQueryObjectFromOffsetForUser(        
        $user,
        $offset,
        $limit,
        $options = []
    ){
        $debug = false;

        /*
        if (isset($options["offset"]))
        {
            $offset = $options["offset"];
        }

        if (isset($options["limit"]))
        {
            $limit = $options["limit"];
        }
        */

        $selectQuery = $this->baseQueryForUser($user, $options);

        if (array_key_exists('orderBy', $options) && isTruthy($options["orderBy"]))
        {
            if ($debug)
            {
                gtk_log("Will get order by column defined from options.");
            }
            $orderBy = $options['orderBy'];

            if ($debug)
            {
                gtk_log("Got order by: ".$orderBy);
            }

            $orderByColumn = $this->dbColumnNameForKey($orderBy);

            if ($debug)
            {
                gtk_log("Order by column defined from options!");
            }
        }
        else
        {
            $selectQuery->orderBy = $this->getOrderBySpec();
        }

        if ($limit)
        {
            $selectQuery->setLimit($limit);

            if ($offset)
            {
                $selectQuery->setOffset($offset);
            }

            if ($debug)
            {
                gtk_log("`selectQueryObjectFromOffsetForUser` - Limit: ".$limit);
                gtk_log("`selectQueryObjectFromOffsetForUser` - Offset: ".$offset);
            }
        }
        else
        {
            if ($debug)
            {
                gtk_log("`selectQueryObjectFromOffsetForUser` - No limit set.");
            }
        }
    
        return $selectQuery;
    }

    public function selectFromOffsetForUser(
        $user,
        $offset,
        $limit,
        $options = []
    ){
        $selectQuery = $this->selectQueryObjectFromOffsetForUser($user, $offset, $limit, $options);

        return $selectQuery->executeAndYield();
        
    }     

	function selectFromOffset(
        $offset, 
        $limit, 
        $options = []
    ) {
        $debug = false;

        if ($debug)
        {
            gtk_log("Select from offset with args...");
            gtk_log("Offset: (is_numeric:".is_numeric($offset).") - ".$offset);
            gtk_log("Limit: (is_numeric:".is_numeric($limit).") - ".$limit);
            gtk_log("Options (count:".count($options).") - ".serialize($options));
        }

        is_numeric($offset) or die("Offset is not numeric");
        is_numeric($limit) or die("Limit is not numeric");

        $orderByColumn = 0;

        if (array_key_exists('orderBy', $options) && isTruthy($options["orderBy"]))
        {
            if ($debug)
            {
                gtk_log("Will get order by column defined from options.");
            }
            $orderBy       = $options['orderBy'];

            if ($debug)
            {
                gtk_log("Got order by: ".$orderBy);
            }

            $orderByColumn = $this->dbColumnNameForKey($orderBy);
            if ($debug)
            {
                gtk_log("Order by column defined from options!");
            }
        }
        
        if (!$orderByColumn)
        {
            $orderByColumn = $this->defaultOrderByColumnKey();
            if ($debug)
            {
                gtk_log("Order by column defined from default methos!");
            }
        }

        $orderByOrder = array_key_exists('orderByOrder', $options) ? $options['orderByOrder'] : $this->defaultOrderByOrder;

        if ($debug)
        {
            gtk_log("Order By Key    :: ".$orderBy);
            gtk_log("Order By Column :: ".$orderByColumn);
            gtk_log("Order By Order  :: ".$orderByOrder);
        }

        if (!$this->defaultOrderByColumnKey)
        {
            gtk_log("No default order by column set for data access class: " . get_class($this));
            die("Error de sistema.");
        }

        $sql    = "SELECT * FROM {$this->tableName()}";
    
        $whereOptions = null;

        if (array_key_exists('where', $options))
        {
            $whereOptions = $options['where'];

            $sql = $this->addWhereClausesToSql($sql, $whereOptions);
        }

        $sql .= " ORDER BY {$orderByColumn} {$orderByOrder}";


        $driverName = $this->getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driverName)
        {
            case 'mysql':
            case 'sqlite':
                if ($limit > 0)
                {
                    $sql .= " ";
                    $sql .= "LIMIT {$limit} 
                             OFFSET {$offset}";
                }
                break;
            case 'pgsql':
            case 'sqlsrv':
                if ($limit > 0)
                {
                    $sql .= " ";
                    $sql .= "OFFSET {$offset} 
                            ROWS FETCH NEXT {$limit} 
                            ROWS ONLY";
                }
                break;
            case 'oci': // Oracle
                // $sql = "SELECT * FROM (
                //     SELECT *, ROW_NUMBER() OVER (ORDER BY {$orderByColumn} {$orderByOrder}) AS row_num
                //     FROM {$this->tableName()}
                // ) WHERE row_num BETWEEN {$offset} + 1 AND {$offset} + {$limit}";
                // break;
            default:
                gtk_log("Connected to a database with unsupported driver: " . $driverName);
                die();
        }

        if ($debug)
        {
            gtk_log("SQL: {$sql}");
        }

        $stmt = $this->getDB()->prepare($sql);

        if ($whereOptions)
        {
            $this->applyWhereClauseToStatement($stmt, $whereOptions);
        }

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    // MARK: - INSERT

    public function insertSqlWithPHPKeys($item, $debug = false)
    {
        $debug = false;

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            if ($debug)
            {
                $serialized = 'Not serializable';
                
                try
                {
                    $serialized = serialize($columnMapping);
                }
                catch (Exception $e)
                {
                    $serialized = $e->getMessage();
                }
                if ($debug && false)
                {
                    gtk_log("Column Mapping `insertSqlWithPHPKeys:1`: {$columnMapping->phpKey} - {$serialized}");
                }
                
            }
            

            if ($columnMapping->isAutoIncrement())
            {
                unset($item[$columnMapping->phpKey]);
            }
        }

        $sql = "INSERT INTO ".$this->tableName()." (";

        $first = true;

        foreach ($item as $key => $value)
        {
            $columnMapping = $this->dataMapping->columnMappingForKey($key);

            $canInsert = $columnMapping && $columnMapping->isInsertable();

            if (!$canInsert)
            {
                if (!$columnMapping)
                {
                    $className = get_class($this);
                    gtk_log("$className:: `insertSqlWithPHPKeys` No column mapping for key: {$key} - continuing...");
                }
                continue;
            }

            if ($first)
            {
                $first = false;
            }
            else
            {
                $sql.=  ", ";
            }

            $columnName = $columnMapping->getSqlColumnName();
            $sql .= $columnName;
            
            if ($debug)
            {
                error_log("Value for `$key`::- ".$columnMapping->valueFromDatabase($item));
            }
        }

        $sql.= ") VALUES (";

        $first = true;
        foreach ($item as $key => $value)
        {
            $columnMapping = $this->dataMapping->columnMappingForKey($key);

            $canInsert = $columnMapping && $columnMapping->isInsertable();

            if (!$canInsert)
            {
                if (!$columnMapping)
                {
                    $className = get_class($this);
                    gtk_log("$className:: `insertSqlWithPHPKeys` No column mapping for key: {$key} - continuing...");
                }
                continue;
            }
            
            if ($first)
            {
                $first = false;
            }
            else
            {
                $sql .=  ", ";
            }

            $sql .= ":$key";
        }

        $sql.= ")";

        if ($debug)
        {
            gtk_log("SQL: `insertSqlWithPHPKeys`: ".$sql);
        }

        return $sql;
    }

    public function isInvalidInsert($arrayToInsert)
    {
        return false;
    }

    public function insertOrError($input, &$outError = '')
    {
        $debug = false;

        $isInvalidInsert = false;

        if(method_exists($this,'isInvalidInsert'))
        {
            if($this->isInvalidInsert($input))
            {
                $isInvalidInsert = true ;
            }

        }

        // $isDictionary = isDictionary($input);

        $isDictionary = count(array_filter(array_keys($input), function($key) {
            return is_string($key);
        })) > 0;

        if ($isDictionary and !$isInvalidInsert)
        {
            if ($debug)
            {
                gtk_log("`insert` - isDictionary - saving with PHP Keys :) ");
                gtk_log(print_r($input, true));
            }
            $toReturn = $this->insertWithPHPKeys($input, $outError);
        }
        else
        {
            gtk_log("`insert` - isArray - for-looping :) ");

            foreach ($input as $item)
            {
                $value = $this->insertWithPHPKeys($item, $outError, [
                    'debug' => $debug,
                ], $outError);

                if ($outError != '')
                {
                    return;
                }

            }
        }

        return $toReturn;
    }

    public function injectVariablesForUserOnItem(&$user, &$item)
    {
        if (!isset($item["created_at"]) || !isset($item["fecha_creado"]))
        {
            $columnMappingForCreatedAt = $this->columnMappingForKey("created_at");

            if ($columnMappingForCreatedAt)
            {
                $item["created_at"] = date('Y-m-d H:i:s');
            }
            else
            {
                $columnMappingForFechaCreado = $this->columnMappingForKey("fecha_creado");

                if ($columnMappingForFechaCreado)
                {
                    $item["fecha_creado"] = date('Y-m-d H:i:s');
                }
            }
        }

        $columnMappingForCreatedAt = $this->columnMappingForKey("modified_at");
        if ($columnMappingForCreatedAt)
        {
            $item["modified_at"] = date('Y-m-d H:i:s');
        }
        else
        {
            $columnMappingForFechaCreado = $this->columnMappingForKey("fecha_modificado");
            if ($columnMappingForFechaCreado)
            {
                $item["fecha_modificado"] = date('Y-m-d H:i:s');
            }
        }
        
    }

    function doesColumnExist($columnName)
    {
        $db = $this->getDB();
        $tableName = $this->tableName();
    
        // Check if the column exists
        $stmt = $db->query("PRAGMA table_info('$tableName')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $exists = false;
    
        foreach ($columns as $column) {
            if ($column['name'] === $columnName) {
                $exists = true;
                break;
            }
        }

        return $exists;
    }

    function addColumnIfNotExists($columnName, $columnDefinition = '') {
        $db = $this->getDB();
        $tableName = $this->tableName();
    
        $exists = $this->doesColumnExist($columnName);
    
        if (!$exists) 
        {
            $db->exec("ALTER TABLE `$tableName` ADD COLUMN `$columnName` $columnDefinition");
        }
    }
    

    public function insertIfNotExists($input, &$outError = null)
    {
        $debug = false;

        $options = [
            "exceptionsNotToHandle" => [
                "uniqueConstraint",
            ],
        ];

        if (isDictionary($input))
        {
            if ($debug)
            {
                gtk_log("DICTIONARY - `insertIfNotExists` - isDictionary - saving with PHP Keys :) ".print_r($input, true));
            }

            try
            {
                return $this->insertAssociativeArray($input, $outError, $options);
            }
            catch (Exception $e)
            {
                if (QueryExceptionManager::isUniqueConstraintException($e))
                {
                    if ($debug)
                    {
                        gtk_log("DICTIONARY -`insertIfNotExists` - IGNORING VIOLATION: uniqueConstraint for item: ".print_r($input, true));
                    }
                }
                else
                {
                    throw $e;
                }
            }
        }
        else
        {
            gtk_log("`insertIfNotExists` - isArray - for-looping :) ");

            $toReturn = [];

            foreach ($input as $item)
            {
                try
                {
                    $value = $this->insertAssociativeArray($item, $outError, $options);

                    array_push($toReturn, $value);
                }
                catch (Exception $e)
                {
                    if (QueryExceptionManager::isUniqueConstraintException($e))
                    {
                        if ($debug)
                        {
                            gtk_log("ARRAY - `insertIfNotExists` - IGNORING VIOLATION: uniqueConstraint for item: ".print_r($item, true));
                        }
                    }
                    else
                    {
                        throw $e;
                    }
                }
                }

            return $toReturn;
        }
    }

    public function insert(&$input, &$outError = null)
    {
        $debug = false;

        if (isDictionary($input))
        {
            if ($debug)
            {
                gtk_log("`insert` - isDictionary - saving with PHP Keys :) ");
            }
            return $this->insertAssociativeArray($input, $outError);
            
        }
        else
        {
            gtk_log("`insert` - isArray - for-looping :) ");

            $toReturn = [];

            foreach ($input as $item)
            {
                $value = $this->insertAssociativeArray($item, $outError, [
                    'debug' => $debug,
                ]);

                array_push($toReturn, $value);
            }

            return $toReturn;
        }
    }

    public function insertFromFormForUser(&$formItem, &$user, &$isInvalid, $options = null)
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("Got form item: ".serialize($formItem));
        }

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            $processFunction = $columnMapping->formNewProcessFunction;


            if ($debug)
            {
                gtk_log("`insertFromForm` - editProcessFunction?".isTruthy($processFunction)." - for key: ".$columnMapping->phpKey);
            }


            if ($processFunction)
            {
                $phpKey          = $columnMapping->phpKey;
                $containsKey     = isset($formItem[$phpKey]);

                
                if ($debug)
                {
                    gtk_log("`insertFromForm` - containsKey?".$containsKey);
                }

                if ($containsKey)
                {
                    if (is_callable($processFunction))
                    {
                        if ($debug)
                        {
                            $formValue = $columnMapping->getValueFromArray($formItem);
                            gtk_log("Current form value: ".$formValue);
                        }

                        $newValue          = $processFunction($columnMapping, $formItem);
        
                        if ($debug)
                        {
                            gtk_log("`insertFromForm` - newValue".$newValue);
                        }

                        $formItem[$phpKey] = $newValue;
                    }
                    else
                    {
                        throw new Exception("Uncallable `formNewProcessFunction` for columnMapping of key: ".$columnMapping->phpKey);
                    }
                }
            }

        }
        
        $isInvalid = $this->isInvalidForTypeUserOnItem(
            "INSERT",
            $user,
            $formItem);

        if ($debug)
        {
            gtk_log("Pre Insert Check Value: ".serialize($isInvalid));
        }
        
        if ($isInvalid)
        { 
            return $isInvalid;
        }

        $this->injectVariablesForUserOnItem(
            $user,
            $formItem
        );
        
        if ($debug)
        {
            gtk_log("Will insert form item: ".serialize($formItem));
        }

        $toReturn = $this->insertWithPHPKeys(
            $formItem, 
            $isInvalid,
            $options);

        if ($isInvalid)
        {
            return $isInvalid;
        }

        $formItem["id"] = $toReturn;

        if ($debug)
        {
            error_log("Did insert: object ID: ".$toReturn);
        }

        ifResponds2(
            $this, 
            'didInsertItemOnFormWithUser', 
            $formItem, 
            $user);


        return $toReturn;
    }


    public function insertWithPHPKeys(
        &$item, 
        &$isInvalid = '',
        $options = null
    ){
        return $this->insertAssociativeArray(
            $item, 
            $isInvalid,
            $options
        );
    }

    /*
        $user = arrayValueIfExists("user", $options);

        if (!$user)
        {
            $user = DAM::get("session")->getCurrentUser();
        }

                        $processOnInsertForUser = $columnMapping->processOnInsertForUser($user);

                    if ($processOnInsertForUser)
                    {
                        $value = $processOnInsertForUser($value);
                    }

                    $processOnAll = $columnMapping->processOnAllForUser($user);

                    if ($processOnAll)
                    {
                        $value = $processOnAll($value);
                    }


    */
    function handleExceptions($e, $sql, $item, $isInvalid, $debug, $options)
    {
        if ($debug)
        {
            gtk_log("Did execute statement: $sql. Exception: ".$e->getMessage());
        }

        if (isset($options["exceptionsNotToHandle"]))
        {
            if (in_array("uniqueConstraint", $options["exceptionsNotToHandle"]))
            {
                if (QueryExceptionManager::isUniqueConstraintException($e))
                {
                    throw $e;
                }
            }

        }


        $result = QueryExceptionManager::manageQueryExceptionForDataSource(
            $this, 
            $e, 
            $sql, 
            $item, 
            $isInvalid);
        
        if ($isInvalid != '')
        {
            return null;
        }
        throw $e;
    }
    
	public function insertAssociativeArray(
        &$item, 
        &$isInvalid = '',
        $options = null
    ){
        $debug = false;




        $ignoreErrors = null;

        if ($options && isset($option["debug"]))
        {
            $debug = $option["debug"];
        }

        if ($debug)
        {
            gtk_log("Object: ".print_r($item, true));
        }

        $sql = $this->insertSqlWithPHPKeys($item);

        if ($debug)
        {
            gtk_log("SQL `insertAssociativeArray` : {$sql}");
            gtk_log("Object: ".print_r($item, true));
        }

        $stmt = null;

        try
        {
            $stmt = $this->getDB()->prepare($sql);
        }
        catch (Exception $e)
        {
            gtk_log("Error: ".$e->getMessage());
            $this->handleExceptions($e, $sql, $item, $isInvalid, $debug, $options);
        }
        
        

        if ($debug)
        {
            gtk_log("Got statement!");
        }

        $columnMappingErrorsToReport = [];

        foreach ($item as $key => $value)
        {
            if ($debug)
            {
                gtk_log("Looking for column mapping: ".$key);
            }


            $columnMapping = $this->dataMapping->columnMappingForKey($key);

            if (!$columnMapping)
            {
                $className = get_class($this);
                
                if ($debug)
                {
                    gtk_log("$className:: `insertWithPHPKeys` No column mapping for key: {$key} - continuing...");
                }
                continue;
            }
            else
            {

                $canInsert = $columnMapping && $columnMapping->isInsertable();

                if (!$canInsert)
                {
                    if ($columnMapping)
                    {
                        $className = get_class($this);
                        throw new Exception("Trying to insert for un-insertable column: ".$columnMapping->phpKey); 
                    }
                    continue;
                }
                else
                {
                    if ($debug)
                    {
                        gtk_log("Binding value `insertWithPHPKeys`: ".$key);
                    }

                    if (isTruthy($value) || ($value === false))
                    {
                        $stmt->bindValue(":$key", $value);
                    }
                    else
                    {
                        $stmt->bindValue(":$key", null);
                    }

                }
                
            }
        }

        if (count($columnMappingErrorsToReport) > 0)
        {
            $isInvalid = $columnMappingErrorsToReport;
            return null;
        }

        try
        {
            $result = $stmt->execute();
        }
        catch (Exception $e)
        {
            $this->handleExceptions($e, $sql, $item, $isInvalid, $debug, $options);
        }

        if ($debug)
        {
            gtk_log("Did execute statement. Result `insertWithPHPKeys` : {$result}");
        }

        $id = null;

        if (!$this->itemContainsPrimaryMappingKey($item))
        {
            $id = $this->lastInsertId();

            if ($debug)
            {
                gtk_log("`insertWithPHPKeys` —> Last insert ID: {$id}");
            }

            $item[$this->primaryKeyMapping()->phpKey] = $id;
            $item["ROWID"] = $id ?? "SuccessButNoId";
        }

        $this->recordAudit('INSERT', $id, $item);

        return $id;
        // return $result;
    }

    public function lastInsertId()
    {
        return $this->getDB()->lastInsertId();
    }

    public function containsAnyKeyInFamily($key, $item)
    {
        // MARK: TODO
    }

    // MARK: - UPDATE

    
    // Mark: - Update Where Key

    public function updateWhereKey($item, $whereKey)
    {
        $debug = true;

        $columnNames = array_keys($item);

        unset($columnNames[$whereKey]);

        $setClause = implode(' = ?, ', $columnNames) . ' = ?';
    
        $sql = "UPDATE ".$this->tableName()." SET " .$setClause . " WHERE ".$whereKey." = ?";
    
        if ($debug) { gtk_log("Update SQL: {$sql}"); }

        $stmt = $this->getDB()->prepare($sql);

        $whereValue = $item[$whereKey];
        $paramValues = array_values($item);
        array_push($paramValues, $whereValue);
        
        foreach ($paramValues as $index => $value) {
            if ($debug) { 
                gtk_log("Binding value: {$value} to index: {$index}. Should be: ".$columnNames[$index]); 
            }
            $stmt->bindValue($index + 1, $value);
        }

        $linesAffected = $stmt->execute();
    
        // Execute the query
        if ($linesAffected > 0)
        {
            if ($debug) { gtk_log("Update successful. Affected: ".$linesAffected); }
            return true;
        }
        else
        {
            return false;
        }
    }


    public function updateFromFormForUser(&$formItem, &$user, &$isInvalid, $options = null)
    {
        $mainDebug = false;

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            $debug = $mainDebug ?? $columnMapping->debug;

            $processFunction = $columnMapping->formEditProcessFunction;

            if ($debug)
            {
                gtk_log("`updateFromForm` - editProcessFunction?".isTruthy($processFunction)." - for key: ".$columnMapping->phpKey);
            }

            // Note - decididing to process on `containsKey` and not on `value` because yes
            if ($processFunction)
            {
                $phpKey      = $columnMapping->phpKey;

                $containsKey = isTruthy($formItem[$phpKey]);

                if ($debug)
                {
                    gtk_log("`updateFromForm` - containsKey?".$containsKey);
                }

                if ($containsKey)
                {
                    if (is_callable($processFunction))
                    {
                        if ($debug)
                        {
                            $formValue = $columnMapping->getValueFromArray($formItem);
                            gtk_log("Current form value: ".$formValue);
                        }

                        $newValue          = $processFunction($columnMapping, $formItem);

                        if ($debug)
                        {
                            gtk_log("`updateFromForm` - newValue".$newValue);
                        }

                        $formItem[$phpKey] = $newValue;
                    }
                    else
                    {
                        $isInvalid = [];
                        throw new Exception("Uncallable `formEditProcessFunction` for columnMapping of key: ".$columnMapping->phpKey);
                    }
                }
            }

        }

        $identifier = $this->valueForIdentifier($formItem);

        $currentItem = $this->getByIdentifier($identifier);

        $isInvalid = ifResponds3(
            $this, 
            'isIlegalUpdateForUserOnItem', 
            $user,
            $currentItem,
            $formItem);
        
        if ($isInvalid)
        {
            if ($isInvalid)
            {
                error_log("Got isInvalid: ".print_r($isInvalid, true));
            }
            return $isInvalid;
        }

        ifResponds3(
            $this,
            "willUpdateItemByUser",
            $user,
            $currentItem,
            $formItem);

        $toReturn = $this->updateWithPHPKeys($formItem, $options);

        ifResponds3(
            $this,
            "didUpdateItemByUser",
            $user,
            $currentItem,
            $formItem);           
        

        return $toReturn;
    }



    public function updateColumnTextForColumnMapping($columnMapping, $isFirst)
    {
        if (!$columnMapping)
        {
            return;
        }

        $sql = "";
                
        if ($columnMapping->isAutoIncrement())
        {
            return false;
        }

        if ($columnMapping->isPrimaryKey())
        {
            return false;
        }

        if (!$isFirst)
        {
            $sql.=  ", ";
        }


        $sql.= $columnMapping->getSqlColumnName()." = :".$columnMapping->phpKey;

        return $sql;
    }

    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    // ----------------------------------------------------------------
    // Update
    // ----------------------------------------------------------------
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////

    public function update(&$item, $options = null)
    {
        return $this->updateWithPHPKeys($item, $options);
    }

    public function updateOrError($item, &$outError = null)
    {
        return $this->updateWithPHPKeys($item, null, $outError);
    }

    public function updateWithOptionsOrError($item, $options, &$outError = null)
    {
        return $this->updateWithPHPKeys($item, $options, $outError);
    }
    
    public function updateWithPHPKeys(&$item, $options = null, &$outError = null)
    {
        $debug = false;

        $primaryKeyMapping = $this->primaryKeyMapping();
        $identifierValue   = $this->identifierForItem($item);

        if ($debug)
        {
            gtk_log("`updateWithPHPKeys`: Primary key mapping. : ".$primaryKeyMapping->phpKey);
            gtk_log("`updateWithPHPKeys`: Identifer value.     : ".$identifierValue);
        } 

        $sql = $this->updateQueryStringForPHPKeys($item, $options);

        if ($debug)
        {
            gtk_log("SQL String: ".$sql);
        }

        try
        {
            $stmt = $this->getDB()->prepare($sql);

            if (isset($options["updateAllKeys"]) && $options["updateAllKeys"])
            {
                foreach ($this->dataMapping->ordered as $columnMapping)
                {
                    $debugLoop = $debug ?? $columnMapping->debug;

                    if ($debugLoop)
                    {
                        gtk_log("`updateWithPHPKeys`: Will bind value for column mapping: ".$columnMapping->phpKey);
                    }

                    $columnMapping->bindValueToStatementForItem($stmt, $item);
                }
            }
            else
            {
                foreach ($item as $key => $value)
                {
                    if (in_array($key, $this->identifierKeys))
                    {
                        continue;
                    }

                    $columnMapping = $this->dataMapping->columnMappingForPHPKey($key);

                    if ($columnMapping == $primaryKeyMapping)
                    {
                        continue;
                    }

                    if (!$columnMapping)
                    {
                        gtk_log("`updateWithPHPKeys`: No column mapping for key: ".$key);
                        continue;
                    }

                    if ($columnMapping->doesItemContainOurKey($item))
                    {
                        if ($debug)
                        {
                            gtk_log("`updateWithPHPKeys`: Will bind value for column mapping: ".$columnMapping->phpKey);
                        }

                        $columnMapping->bindValueToStatementForItem($stmt, $item);
                    }
                    else
                    {
                        if ($debug)
                        {
                            gtk_log("`updateWithPHPKeys`: No column mapping for key: ".$key);
                        }
                    }
                }
            }

            if ($debug)
            {
                gtk_log("`updateWithPHPKeys`: Binding primary key value ($primaryKeyMapping->phpKey): ".$identifierValue);
            }

            $stmt->bindValue(":".$primaryKeyMapping->phpKey, $identifierValue);

            $oldItem =  $this->getByIdentifier($identifierValue);
            
            $changes = $this->calculateChanges($oldItem, $item);

            $result = $stmt->execute();

            $this->recordAudit('UPDATE', $identifierValue, $changes);

            
        }
        catch (Exception $e)
        {
            return QueryExceptionManager::manageQueryExceptionForDataSource($this, $e, $sql, $item, $outError);
        }

        if ($debug)
        {
            gtk_log("`updateWithPHPKeys` - Update result: ".$result);
        }

        return $result;
    }


    public function updateQueryStringForPHPKeys($item, $options = null)
    {
        $debug = false;


        $sql = "UPDATE ".$this->tableName()." SET ";

        $isFirst = true;

        $columnMappingErrorsToReport = [];

        if (isset($options["updateAllKeys"]) && $options["updateAllKeys"])
        {
            if ($debug)
            {
                gtk_log("Will update all keys in item: ".print_r($item, true));
            }

            foreach ($this->dataMapping->ordered as $columnMapping)
            {
                $text = $this->updateColumnTextForColumnMapping($columnMapping, $isFirst);

                if ($text)
                {
                    if ($text)
                    {
                        $sql .= $text;
                    }
    
                    if ($isFirst)
                    {
                        $isFirst = false;
                    }
                }
            }
        }
        else
        {
            if ($debug)
            {
                gtk_log("Will update only keys in item: ".print_r($item, true));
            }

            foreach ($item as $key => $value)
            {
                if (in_array($key, $this->identifierKeys))
                {
                    continue;
                }

                if ($debug)
                {
                    gtk_log("Looking for column mapping: ".$key." - value: ".$value);
                }


                try
                {
                    $columnMapping = $this->dataMapping->columnMappingForPHPKey($key);
                }
                catch (ColumnMappingException $e) 
                {
                    $columnMappingErrorsToReport[] = $e;
                    if ($debug) {
                        gtk_log($e->getMessage());
                    }
                    continue;
                }

                if ($columnMapping)
                {
                    $text = $this->updateColumnTextForColumnMapping($columnMapping, $isFirst);

                    if ($text)
                    {
                        $sql .= $text;
                        
                        if ($isFirst)
                        {
                            $isFirst = false;
                        }
                    }
                }
            }
        }

        if (count($columnMappingErrorsToReport) > 0)
        {
            throw new AccumulatedColumnMappingException($columnMappingErrorsToReport);
        }

        $primaryKeyMapping = $this->dataMapping->primaryKeyMapping;

        $sql.= " WHERE ".$primaryKeyMapping->getSqlColumnName()." = :".$this->dataMapping->primaryKeyMapping->phpKey;

        if ($debug)
        {
            gtk_log("Update SQL: {$sql}");
        }

        return $sql;
    }

    
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    // ----------------------------------------------------------------
    // Differentiantion
    // ----------------------------------------------------------------
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////


    public function getDatabaseName()
    {
        $driverName = $this->getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driverName == 'sqlsrv') 
        {
            $sql    = "SELECT DB_NAME() AS DatabaseName";
            $stmt   = $this->getPDO()->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['DatabaseName'];
            } else {
                throw new Exception("Failed to retrieve the database name.");
            }
        } 
        else 
        {
            throw new Exception("Unsupported driver for fetching database name: $driverName");
        }
    }

    function getDBType() 
    {
        return $this->getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function tableExists()
    {
        $debug = false;

        $driverName = $this->getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        $sql = "";
        $param = [];

        $tableName = $this->tableName();
        
        // Build SQL query based on the DB type
        switch ($driverName) {
            case 'mysql':
                $sql = "SHOW TABLES LIKE ?";
                $param = [$tableName];
                break;
                
            case 'pgsql':
                $sql = "SELECT * FROM information_schema.tables";
                $sql .= " WHERE table_schema = 'public'"; // Assuming your tables are in the default 'public' schema
                $sql .= " AND table_name = ?";
                $param = [$tableName];
                break;
                
    
            case 'sqlite':
                $sql .= "SELECT name";
                $sql .= " FROM sqlite_master";
                $sql .= " WHERE";
                $sql .= " type='table'";
                $sql .= " AND name=?";
                $param = [
                    $tableName
                ];
                break;
    
            case 'sqlsrv':
                $sql .= "SELECT * FROM INFORMATION_SCHEMA.TABLES";
                $sql .= " WHERE TABLE_SCHEMA = ?";
                $sql .= " AND TABLE_NAME = ?";

                $dbName = $this->getDatabaseName();

                $param = [
                    $dbName, 
                    $tableName
                ];
                break;
    
            default:
                throw new Exception("Unsupported database driver: $driverName");
        }
    
        $stmt = $this->getPDO()->prepare($sql);
        $stmt->execute($param);

        if ($driverName == 'sqlite') {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($debug)
            {
                gtk_log("Query: ".$sql);
                gtk_log("Query Result: ".print_r($result, true));
            }
            return $result !== false;
        } 
        else 
        {
            return $stmt->rowCount() > 0;
        }
    }

    public function isTypeOfObject($item)
    {
        $isPHP   = true;
        $isFirst = true;

        foreach ($this->dataMapping->ordered as $columnMapping)
        {
            if ($columnMapping->isVirtual())
            {
                continue;
            }

            if ($isFirst)
            {
                if (array_key_exists($columnMapping->phpKey, $item))
                {
                    $isPHP = true;
                }
                else
                {
                    $isPHP = false;
                }
            }

            if ($isPHP)
            {
                if (!array_key_exists($columnMapping->phpKey, $item))
                {
                    return false;
                }
            }
            else
            {
                if (!array_key_exists($columnMapping->sqlServerKey, $item))
                {
                    return false;
                }
            }
        }

        return true;
    }

    public function saveImage(
        $fileIdentifier, 
        $fileKey, 
        $fileValue, 
        $fileKeyOptions)
    {
        $debug = false;


        


        if ($debug)
        {
            gtk_log("Saving image for file key: ".$fileKey);
            gtk_log("File Identifier: ".$fileIdentifier);
            gtk_log("File Value:" . serialize($fileValue));
            gtk_log("File Key Options:" . serialize($fileKeyOptions));
        }

        $fileName       = $fileValue["name"];
        $fileSize       = $fileValue["size"];
        $fileType       = $fileValue["type"];
        $fileExtension  = pathinfo($fileName, PATHINFO_EXTENSION);

        if (!array_key_exists("maxMegaBytes", $fileKeyOptions))
        {
            gtk_log("No max mega bytes found for file key: ".$fileKey);
            $maxMegaBytes = 8;
        }
        else
        {
            $maxMegaBytes = $fileKeyOptions["maxMegaBytes"];
        }
        


        if (array_key_exists("basePath", $fileKeyOptions))
        {
            $basePath = $fileKeyOptions["basePath"];
        }
        else if (array_key_exists("path", $fileKeyOptions))
        {
            $basePath = $fileKeyOptions["path"];
        }
        else
        {
            gtk_log("No base path found for file key: ".$fileKey);
            die("Error de sistema.");
        }

        if (!array_key_exists("acceptedExtensions", $fileKeyOptions))
        {
            // gtk_log("No accepted extensions found for file key: ".$fileKey);
            // die("Error de sistema.");
            $acceptedExtensions = [
                "jpg", 
                "jpeg", 
                "png", 
                "gif",
                "pdf",
            ];
        }
        else
        {
            $acceptedExtensions = $fileKeyOptions["acceptedExtensions"];
        }
        

        $megaByte       = 1024 * 1024;
        $maxFileSize    = $maxMegaBytes * $megaByte;

        if ($debug)
        {
            gtk_log("File Name: $fileName");
            gtk_log("File Size: $fileSize");
            gtk_log("File Type: $fileType");
            gtk_log("File Extension: $fileExtension");
            gtk_log(serialize($_FILES[$fileKey]));
        }

        if ($fileSize > $maxFileSize)
        {
            if ($debug)
            {
                gtk_log("Error: El archivo es demasiado grande. Maximo: ".$maxMegaBytes." MB.");
            }
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "El archivo es demasiado grande. Maximo: ".$maxMegaBytes." MB.",
                ]);
        }


        if (!in_array($fileExtension, $acceptedExtensions)) 
        {
            if ($debug)
            {
                gtk_log("Error: El archivo no es una imagen valida. Tipos aceptados: ".implode(", ", $acceptedExtensions));
            }
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "El archivo no es una imagen valida. Usted nos envio: $fileType. Tipos aceptados: ".implode(", ", $acceptedExtensions),
                ]);
        }
        

        if (!ini_get('file_uploads')) // Check if file uploads are enabled
        {
            gtk_log('File uploads are not enabled on the server.');
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "File uploads are not enabled on the server.",
                ]);
        }
        
        if ($debug)
        {
            gtk_log('File uploads are enabled on the server.');
        }


        $uploadDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        // Check if the temporary directory for file uploads is writable
        
        if (!is_writable($uploadDir)) 
        {
            gtk_log('The temporary upload directory is not writable: '.$uploadDir);
            gtk_log('INI_GET: '.ini_get('upload_tmp_dir'));
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "The temporary upload directory is not writable: ".$uploadDir,
                ]);
        }
        
        if ($debug)
        {
            gtk_log('The temporary upload directory is writable.');
        }

        if (!file_exists($basePath))
        {
            if ($debug)
            {
                gtk_log("Creating directory: ".$basePath);
            }
            if (!mkdir($basePath, 0777, true))
            {
                gtk_log("No se pudo crear el directorio de carga: ".$basePath);
                return new FailureResult(
                    FormResultOutcomes::Failure,
                    [
                        "message" => "Error grave en el servidor.",
                    ]);
            }
        }

        $basePath = $basePath."\\".$fileKey;

        if (!file_exists($basePath))
        {
            if ($debug)
            {
                gtk_log("Creating directory: ".$basePath);
            }
            if (!mkdir($basePath, 0777, true))
            {
                gtk_log("No se pudo crear el directorio de carga: ".$basePath);
                return new FailureResult(
                    FormResultOutcomes::Failure,
                    [
                        "message" => "Error grave en el servidor.",
                    ]);
            }
        }

        if (!is_writable($basePath)) 
        {
            gtk_log('The target upload directory is not writable.');
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "The target upload directory is not writable: ".$basePath,
                ]);
        }
        
        $saveFileAt = $basePath."\\".$fileIdentifier.".".$fileExtension;

        if ($debug)
        {
            gtk_log("Attempting write to File Path: $saveFileAt");
        }
        if (!move_uploaded_file($fileValue['tmp_name'], $saveFileAt))
        {
            // $error = error_get_last();
            if ($debug)
            {
                gtk_log("Error: No se pudo mover el archivo. ".$fileValue['error']);
                if (!DataAccessManager::get('persona')->isDeveloper())
                {
                    echo '<pre>';
                    print_r($fileValue["error"]);
                    echo '</pre>';
                }
            }
            return new FailureResult(
                FormResultOutcomes::Failure,
                [
                    "message" => "No se pudo mover el archivo al directorio de imagenes. ".$fileValue['error']." Su solicitud fue grabada con $fileIdentifier",
                ]);
        }

        return new SuccessResult(FormResultOutcomes::Successful, []);
        // Perform server-side checks: 
        //     Even after validating the file type during the upload, 
        //     perform additional server-side checks when reading the image file. 
        //     For example, you can use libraries like getimagesize() or 
        //     image manipulation functions (e.g., `imagecreatefromjpeg()) to 
        //     ensure the file is a valid image of the expected format.

    }


    // AlquiladoraDataAccess::--generateSelectForUserColumnValueName($user, $dataAccessor, $objectID, $foreignColumnName, $foreignColumnValue, $options = []) 
    // DataAccess::-------------generateSelectForUserColumnValueName($foreignColumnMapping, $user, $item, $currentValue, $options = [])
    
    /*
    public function generateSelectForUserColumnValueName(
        $foreignColumnMapping,
        $user,
		$item,
		$currentValue,
		$options = []
    ){
    */

    public function generateSelectForUserColumnValueName(
        $user,
		$dataAccessor       = null,
		$objectID           = null,
		$foreignColumnName  = null,
		$foreignColumnValue = null,
		$options = []
	){
        $argument = new DataAccessInputSelectArgument();

        $argument->user                 = $user;               
        $argument->dataAccessor         = $dataAccessor;       
        $argument->objectID             = $objectID;           
        $argument->foreignColumnName    = $foreignColumnName;  
        $argument->foreignColumnValue   = $foreignColumnValue; 
        $argument->options              = $options;            

        return $this->generateSelect($argument);
    }


    public function generateSelect(DataAccessInputSelectArgument $argument)
    {
        $debug = false;

        $user               = $argument->user;                 
        $dataAccessor       = $argument->dataAccessor;         
        $objectID           = $argument->objectID;             
        $foreignColumnName  = $argument->foreignColumnName;    
        $foreignColumnValue = $argument->foreignColumnValue;  
        $idValue            = $argument->idValue; 
        $options            = $argument->options;

        if ($debug)
        {
            gtk_log("Start `DataAccess->generateSelectForUserColumnValueName` - ".get_class($this));
            gtk_log("Data Accessor: ".get_class($dataAccessor));
            gtk_log("Object ID: ".$objectID);
            gtk_log("Foreign column name: ".$foreignColumnName);
            gtk_log("Foreign column value: ".$foreignColumnValue);
            gtk_log("Options: ".print_r($options, true));
        }

        $item         = null;
        $currentValue = null;

        if ($dataAccessor && $objectID)
        {
            $item = $dataAccessor->getByIdentifier($objectID);

            $currentValue = $dataAccessor->valueForKey($foreignColumnName, $item);
        }
        

        $defaultColumnForDataAccessor = [
            "columnValue" => null,
            "columnName"  => null,
        ];

        if (method_exists($this, 'getDefaultOptionsForSelectForUser'))
        {
            if ($debug)
            {
                error_log("Calling - getDefaultOptionsForSelectForUser");
            }

            $defaultColumnForDataAccessor = $this->getDefaultOptionsForSelectForUser($user);
            
            if ($debug)
            {
                error_log("Got - ".print_r($defaultColumnForDataAccessor, true));
            }
        }

        $columnForSelectInputValue  = $options["columnForSelectInputValue"]  ??  $defaultColumnForDataAccessor["columnValue"];
        $columnForSelectInputLabel  = $options["columnForSelectInputLabel"]  ??  $defaultColumnForDataAccessor["columnName"];

        if ($debug)
        {
            error_log("columnForSelectInputLabel: $columnForSelectInputLabel - columnForSelectInputValue - $columnForSelectInputValue");
        }

        if (!$columnForSelectInputLabel)
        {
            throw new Exception("columnForSelectInputLabel is a required option when calling `generateSelectForUserColumnValueName`.");
        }

        if (!$columnForSelectInputValue)
        {
            throw new Exception("columnForSelectInputValue is a required option when calling `generateSelectForUserColumnValueName`.");
        }

		if ($debug)
		{
			gtk_log("Will prepare select...");
		}

    	$language = isset($options['language']) ? $options['language'] : 'spanish';

        if(isset($idValue))
        {
            $select = '<select name="' . $foreignColumnName . '" id="' . $idValue . '" >';
        }
        else
        {
            $select = '<select name="' . $foreignColumnName . '" >';
        }

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

        if ($debug)
        {
            gtk_log("Will get data by: ".$columnForSelectInputLabel);
        }

        $data = $this->selectAllOrderedBy($columnForSelectInputLabel);

        if ($debug)
        {
            gtk_log("Did get data. Count:".count($data));
            gtk_log("Will get label on key: ".$columnForSelectInputLabel);
            gtk_log("Will get value on key: ".$columnForSelectInputValue);
        }

    	foreach ($data as $row)
		{
            if ($debug)
            {
                gtk_log("Working with row (".count($row).") - ".serialize($row));
            }

            $label = $this->valueForKey($columnForSelectInputLabel, $row);
            $value = $this->valueForKey($columnForSelectInputValue, $row);

            if ($debug)
            {
                gtk_log("Got label: ".$label);
                gtk_log("Got value: ".$value);
            }

        	$select .= '<option';
			$select .= ' value="'.$value .'"';
			if ($value == $currentValue)
			{
				$select .= ' selected ';
			}
			$select .= '>';
			$select .= $value.' - '.$label;
			$select .= '</option>';
    	}
 
    	$select .= '</select>';

        if ($debug)
        {
            gtk_log("Made select: ".$select);
        }

    	return $select;
	}

    // MARK: - DB Checks

    public function isSqlite()
    {
        $driverName = $this->getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driverName)
        {
            case 'sqlite':
                return true;
            default:
                return false;
        }
    }

    /*
    {
        "name": "RENTER",
    }

    */

    public function registerPermissions($setupObject, $qualifier = null)
    {
        $useInheritance = true;

        $actionsInOrder = [
            "read",
            "list",
            "create",      
            "update", 
            "delete",
        ];

        $currentIndex = 0;

        foreach ($actionsInOrder as $action)
        {
            $roles = $permissions[$action] ?? [];

            foreach ($roles as $maybeRole)
            {   
                $roleName            = null;
                $canGrantPermission  = false;
                $canRemovePermission = false;

                if (is_string($maybeRole))
                {
                    $roleName     = $maybeRole;
                    $canGrantRole = false;

                }
                else if (is_array($maybeRole))
                {
                    $roleName            = $maybeRole["name"];
                    $canGrantPermission  = $maybeRole["canGrant"] ?? false;
                    $canRemovePermission = $maybeRole["canRemove"] ?? false; 
                    
                }
                else
                {
                    throw new Exception("Invalid object in setup on: ".get_class($this)." - Action: ".$action." - Object Type: ".gettype($maybeRole));
                }

                if (!$roleName)
                {
                    throw new Exception("No role name found for action: ".$action);
                }

                $roleID = DataAccessManager::get("roles")->getByName($roleName);

                $permission["actionName"] = get_class($this).".".$action;
                $permission["roleID"]     = $roleID;
                $permission["canGrant"]   = $canGrantPermission;
                $permission["canRemove"]  = $canRemovePermission;


                // addWhereClauseForUser($user)

                /*
                $isQualifiedBy      = "function|columnValue";
                $qualifierName      = null;
                $qualifierValue     = $qualifier; 

                $isQualifiedBy = $permission["isQualifiedBy"];

                switch ($isQualifiedBy)
                {
                    case "function":
                        $functionName = $permission["qualifierName"];
                        return $functionName($action, $user, $object);
                        break;
                    case "columnValue":
                        $permission["qualifierColumn"] = $qualifier;
                        break;
                    default:
                        // Do Nothing
                }
                function qualiferFunction($actionName, $user, $object)
                {
                    return true;
                }
                */
                
                DataAccessManager::get("RolePermissions")->addPermission($permission);
            
                if ($useInheritance && ($currentIndex > 0))
                {
                    $actionsInOrder = array_slice($actionsInOrder, 0, $currentIndex);

                    foreach ($actionsInOrder as $localAction)
                    {
                        $permission["actionName"] = get_class($this).".".$localAction;
                        DataAccessManager::get("RolePermissions")->addPermission($permission);
                    }
                }
            }
            $currentIndex++;
        }
    }

    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }

    public function allowsCreationOnDefaultFormForUser($user)
    {
        return true;
    }

    /*
    public function validPermissionKeysForOption($maybePermission)
    {

    } 
    */

    public function optionsForEditPage()
    {
        return [];
    }

    public function optionsForShowPage()
    {
        return [];
    } 

    public function optionsForAllPage()
    {
        return [];
    }

    public function userHasPermissionTo($maybePermission, $user, $item = null, $options = null)
    {
        $debug = false;

        $permissionName = $this->dataAccessorName.".".$maybePermission;

        if ($debug)
        {
            error_log("`userHasPermissionTo` on ".get_class($this)." - ".$permissionName);
        }

        return DataAccessManager::get("persona")->hasPermission($permissionName, $user);
        
        /*
        $hasPermission = DataAccessManager::get("persona")->hasPermission($permissionName, $user, $item, $options);
        
        if (!$hasPermission)
        {
            return false;
        }


        $permission = DataAccessManager::get("permission")->getByName($permissionName);

        if (DataAccessManager::get("permission")->isQualifiedPermission($permission))
        {

        }
        else
        {
            return true;
        }
        */
    }

    public function renderObjectForRoute($routeAsString, $user)
    {
		
		switch ($routeAsString)
		{
			case "create":
            case "new":
				return DataAccessManager::get("NewDataSourceRenderer", false)->renderForDataSource($this, $user, $this->optionsForEditPage());
            case "show":
			case "read":
				return DataAccessManager::get("ShowDataSourceRenderer", false)->renderForDataSource($this, $user, $this->optionsForShowPage());
			case "update":
			case "edit":
				return DataAccessManager::get("EditDataSourceRenderer", false)->renderForDataSource($this, $user, $this->optionsForEditPage());
			case "all":
			case "list":
				return DataAccessManager::get("AllDataSourceRenderer", false)->renderForDataSource($this, $user, $this->optionsForAllPage());
			case "delete":
				die("NEED TO CREATE RENDER `Delete`");
		}

        $dataSetView = $this->getDataSetView($routeAsString);

        if ($dataSetView)
        {
            return $dataSetView->renderForUser($user);
        }

        return null;

    }

    public function printRowContents($item, $columnsToDisplay, $options)
    {

    }

    public function generateJSONForUserItems($user, $itemsOrQueryObject, $columnsToDisplay = null, $options = null)
{
    $debug = false;
    $items = null;
    $count = 0;
    $itemType = $this->dataAccessorName ?? 'unknown';

    // Get column mappings
    $columnMappingsToDisplay = null;
    if (!$columnsToDisplay) {
        $columnMappingsToDisplay = $this->dataMapping->ordered;
    } else {
        $columnMappingsToDisplay = [];
        foreach ($columnsToDisplay as $maybeColumnMapping) {
            if (is_string($maybeColumnMapping)) {
                $columnMappingsToDisplay[] = $this->columnMappingForKey($maybeColumnMapping);
            } else if (($maybeColumnMapping instanceof GTKColumnBase) || ($maybeColumnMapping instanceof GTKItemCellContentPresenter)) {
                $columnMappingsToDisplay[] = $maybeColumnMapping;
            }
        }
    }

    // Process input items
    if (is_array($itemsOrQueryObject)) {
        $count = count($itemsOrQueryObject);
        $items = $itemsOrQueryObject;
    } else if (method_exists($itemsOrQueryObject, "count") && method_exists($itemsOrQueryObject, "getIterator")) {
        $count = $itemsOrQueryObject->count();
        $items = $itemsOrQueryObject->getIterator();
    } else {
        $count = $itemsOrQueryObject->count();
        $items = $itemsOrQueryObject->executeAndYield();
    }

    $result = [
        'count' => $count,
        'data'  => []
    ];

    if ($count == 0) {
        return json_encode($result);
    }

    // Process items
    foreach ($items as $index => $currentItem) {
        if ($options["whileIterating"] ?? null) {
            $options["whileIterating"]($currentItem, $index);
        }

        $processedItem = [];
        
        // Process each field using column mappings
        foreach ($columnMappingsToDisplay as $columnMapping) {
            $displayFunction = null; // This would come from your column mapping
            $fieldName = $columnMapping->getColumnName();
            
            if ($displayFunction) {
                $argument = new GTKColumnMappingListDisplayArgument();
                $argument->user = $user;
                $argument->item = $currentItem;
                $argument->options = null;
                
                $processedItem[$fieldName] = $displayFunction($argument);
            } else {
                $processedItem[$fieldName] = $columnMapping->valueFromDatabase($currentItem);
            }
        }

        $itemData = [
            'item_type' => $itemType,
            'item' => $processedItem
        ];

        $result['data'][] = $itemData;
    }

    return json_encode($result, JSON_PRETTY_PRINT);
}

    public function generateTableForUserItems($user, $itemsOrQueryObject, $columnsToDisplay = null, $options = null)
    {
        $debug = false;
		$items = null;
		$count = 0;

        $whileIterating = $options["whileIterating"] ?? null;

        $columnMappingsToDisplay = null;

        if (!$columnsToDisplay)
        {
            $columnMappingsToDisplay = $this->dataMapping->ordered;
        }
        else
        {
            $columnMappingsToDisplay = [];

            foreach ($columnsToDisplay as $maybeColumnMapping)
            {
                if (is_string($maybeColumnMapping))
                {
                    $columnMappingsToDisplay[] = $this->columnMappingForKey($maybeColumnMapping);
                }
                else if (($maybeColumnMapping instanceof GTKColumnBase) || ($maybeColumnMapping instanceof GTKItemCellContentPresenter))
                {
                    $columnMappingsToDisplay[] = $maybeColumnMapping;
                }
            }
        }
	
		if (is_array($itemsOrQueryObject))
		{
			$count = count($itemsOrQueryObject);
			$items = $itemsOrQueryObject;
	
			if ($debug)
			{
				gtk_log("Is Array!");
				gtk_log("Items count: ".$count);
				if ($count < 200)
				{
					gtk_log("Items: ".print_r($items, true));
				}
				
			}
		}
        else if (method_exists($itemsOrQueryObject, "count") && method_exists($itemsOrQueryObject, "getIterator"))
        {
            $count = $itemsOrQueryObject->count();
            $items = $itemsOrQueryObject->getIterator();

            if ($debug)
            {
                gtk_log("Is GTK Countable Generator!");
                gtk_log("Items count: ".$count);
            }
        }
		else
		{
			$count = $itemsOrQueryObject->count();
			$items = $itemsOrQueryObject->executeAndYield();
	
			if ($debug)
			{
				gtk_log("Is Query Object!");
				gtk_log("Items count: ".$count);
			}
		}
	
	
		if (!isset($dataSourceName))
		{
			$dataSourceName = $this->dataAccessorName;
		}
	
		if ($debug)
		{
			error_log("Generating table for user: ".print_r($user, true));
			// error_log("Columns to display: ".print_r($columnsToDisplay, true));
			if (is_array($items))
			{
				error_log("Items: ".print_r($items, true));
			}
			else
			{
				error_log("Items: ".get_class($items));
			}
			error_log("Data source: ".get_class($this));
		}
	
		$index = 0;
	
		ob_start(); // Start output buffering 
		?>
		<table>
			<thead>
				<tr>
					<?php foreach ($columnMappingsToDisplay as $columnMapping): ?>
						<?php
                            echo "<th class='min-w-[75px]'>";
							echo $columnMapping->getFormLabel();
							echo "</th>";
						?>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			
			<?php if ($count == 0): ?>
			<tr>
				<td colspan="<?php echo gtk_count($columnsToDisplay) + 1; ?>">
					No hay elementos que mostrar.
				</td>
			</tr>
			<?php else: ?>
				<?php foreach ($items as $index => $currentItem): ?>
						<?php 
                            $itemIdentifier = $this->dataMapping->valueForIdentifier($currentItem); 
                            if ($whileIterating)
                            {
                                $whileIterating($currentItem, $index);
                            }
                        ?>
						<tr class="border-b border-gray-200"
							style=<?php echo '"'.$this->rowStyleForItem($currentItem, $index).'"'; ?>
							id=<?php echo '"cell-'.$this->dataAccessorName.'-'.$itemIdentifier.'"'; ?>
						>
                        <?php 
                        foreach ($columnMappingsToDisplay as $columnMapping)
                        {
                            $displayFunction = null;

                            $toDisplay = null;

                            if ($displayFunction)
                            {
                                $argument = new GTKColumnMappingListDisplayArgument();

                                $argument->user    = $user;
                                $argument->item    = $currentItem;
                                $argument->options = null;
                                
                                $toDisplay = $displayFunction($argument);
                            }
                            else
                            {
                                $toDisplay = $columnMapping->valueFromDatabase($currentItem);
                            }

                            echo "<td>".$toDisplay."</td>";
                        } 
                        ?>
						</tr>
						<?php $index++; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php return ob_get_clean(); // End output buffering and get the buffered content as a string
	}
}
