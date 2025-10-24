<?php


class RolePersonRelationshipsDataAccess extends DataAccess
{
    private $_cache;
    
    public function register()
    {
        $columnMappings = [
			new GTKColumnMapping($this, "id",	[
                "isPrimaryKey"    => true, 
                "isAutoIncrement" => true, 
                "hideOnForms"     => true, 
            ]), 
            new GTKColumnMapping($this, "user_id", [
                "transformValueOnLists" => function ($item, $value) {
                    $persona = DataAccessManager::get("persona")->getByIdentifier($value);
                    return $value." - ".DataAccessManager::get("persona")->getFullName($persona);
                },
            ]),
			new GTKColumnMapping($this, "role_id", [
                "customInputFunctionClass"  => null,
                "customInputFunctionScope"  => "object", //--- instance?? ---
                "customInputFunctionObject" => DataAccessManager::get('roles'),
                "customInputFunction"       => "generateSelectForUserColumnValueName",
                /*
                'formInputType' => 'select', 
				'possibleValues' => [ 
					'importado'  => ['label'=>'Importado'],
					'solicitado' => ['label'=>'Solicitado'], 
					'activo' 	 => ['label'=>'Activo'], 
					'inactivo' 	 => ['label'=>'Inactivo'],
					'cancelado'  => ['label'=>'Cancelado']
				],
                */
            ]),
            // new GTKColumnMapping($this, "qualifier_type"),
            new GTKColumnMapping($this, "qualifier", [
                "customInputFunction" => function($columnMapping, $user, $item, $value, $options) {
                    $debug = false;

                    $value = $item["role_id"];

                    if ($debug)
                    {
                        error_log("Qualifier closure: Looking for $columnMapping->phpKey - Value: $value");
                    }

                    $role = DataAccessManager::get("roles")->getOne("id", $value);

                    if ($debug)
                    {
                        error_log("Got role: ".print_r($role, true));
                    }

                    $qualifierDataSourceName  = DataAccessManager::get("roles")->valueForKey("qualifier_data_source", $role);
                    $qualifierDataColumn      = DataAccessManager::get("roles")->valueForKey("qualifier_data_source_column", $role);
                    $qualifierLabelColumn     = DataAccessManager::get("roles")->valueForKey("qualifier_data_label_column", $role);

                    if (!$qualifierDataSourceName)
                    {
                        error_log("No - qualifierDataSourceName");
                        error_log(print_r($item, true));
                        error_log(print_r($role, true));
                        error_log("DYING");
                        die();
                        return;
                    }

                    $assignableRoles = [];

                    if (DataAccessManager::get("persona")->isInGroup($user, [
                        "DEV",
                        "SOFTWARE_ADMIN",
                        "SOFTWARE_OWNER",
                    ]))
                    {
                        if ($debug)
                        {
                            error_log("Will get roles for super-users");
                        }
                        $query = new SelectQuery(DataAccessManager::get($qualifierDataSourceName));
                        $query->setColumns([
                            $qualifierDataColumn,
                            $qualifierLabelColumn, 
                        ]);
                        $query->orderBy = [
                            $qualifierLabelColumn,
                        ];
                        $assignableRoles = $query->executeAndReturnAll();
                    }
                    else
                    {
                        $assignableRoles = $this->rolesUserCanAddTo($user);
                    }

                    if ($debug)
                    {
                        error_log("Got roles: ".print_r($assignableRoles, true));
                    }

                    $select = '<select name="'.$columnMapping->phpKey.'">';

                    $select .= generateSelectOptionsDataLabelColumn($assignableRoles, $value, $qualifierDataSourceName, $qualifierDataColumn, $qualifierLabelColumn);
                    
                    $select .= "</select>";

                    return $select;
                },
                /*
                "linkTo" => function ($item, $value) {
                    $name = $item["name"];
                    switch ($name)
                    {
                        case "AGENCY":
                            return "agencia_naviera";
                        default:
                            return null;
                    }
                },
                */
                /*
                "customInputFunction" => function($columnMapping, $user, $item, $value, $options) {
                    $debug = false;

                    if ($debug)
                    {
                        error_log("Arg: columnMapping : ".get_class($columnMapping));
                        // error_log("Arg: user : ".get_class($user));
                        // `error_log("Arg: item : ".get_class($item));
                        // error_log("Arg: value : ".get_class($value));
                        // error_log("Arg: options : ".get_class($options));
                    }

                    $roles = DataAccessManager::get("role_person_relationships")->rolesUserCanAddTo($user);

                    $toReturn = "";

                    $language = isset($options['language']) ? $options['language'] : 'spanish';

                    $select = '<select name="'.$columnMapping->phpKey.'">';
            
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

                    usort($roles, function ($a, $b){
                        return strcasecmp($a["name"], $b["name"]);
                    });

                    foreach ($roles as $role)
                    {
                        $optionValue = DataAccessManager::get("roles")->valueForIdentifier($role);
                        $optionLabel = DataAccessManager::get("roles")->valueForKey("name", $role);
                        $select .= '<option';
                        $select .= ' value="'.$optionValue.'" ';
                        if ($optionValue === $value)
                        {
                            $select .= "selected";
                        }
                        $select .= '>';
                        $select .= $optionLabel;
                        $select .= '</option>';
                    }

                    return $select;

                },
                */
                "transformValueOnLists" => function ($item, $value) {
                    if (!$value)
                    {
                        return null;
                    }

                    if (!isTruthy($value))
                    {
                        return null;
                    }

                    $roleID = $item["role_id"];
                    
                    $role = DataAccessManager::get("roles")->getOne("id", $roleID);

                    $qualifierDataSourceName  = DataAccessManager::get("roles")->valueForKey("qualifier_data_source", $role);

                    if ($qualifierDataSourceName)
                    {
                        // $qualifierDataColumn      = DataAccessManager::get("roles")->valueForKey("qualifier_data_source_column", $role);
                        $qualifierLabelColumn     = DataAccessManager::get("roles")->valueForKey("qualifier_data_label_column", $role);
                        
                        
                        $listItem = DataAccessManager::get($qualifierDataSourceName)->getByIdentifier($value);
                        return DataAccessManager::get($qualifierDataSourceName)->valueForKey($qualifierLabelColumn, $listItem);
                    }

                    /*
                    switch ($name)
                    {
                        case "AGENCY":
                            $agencia = DataAccessManager::get("agencia_naviera")->getByIdentifier($value);
                            return DataAccessManager::get("agencia_naviera")->valueForKey("name", $agencia);        
                        default:
                            return null;
                    }
                    */
                },
            ]),
            new GTKColumnMapping($this, "is_admin_for_role", [
                'formInputType' => 'select', 
				'possibleValues' => [ 
					true  => ['label'=>'TRUE'],
					false => ['label'=>'FALSE'],
				],
            ]),
			new GTKColumnMapping($this, "purpose"),
			new GTKColumnMapping($this, "is_active", [
				'formInputType' => 'select', 
				'possibleValues' => [ 
					true  => ['label'=>'TRUE'],
					false => ['label'=>'FALSE'],
				],
            ]),
            new GTKColumnMapping($this, "permissionsArray", [
                "hideOnForms" => true, 
                "hideOnLists" => true,
            ]),
            new GTKColumnMapping($this, "can_grant_role"),
            new GTKColumnMapping($this, "owns_role"),
			new GTKColumnMapping($this, "date_created"),
			new GTKColumnMapping($this, "date_modified"),
		];

		$this->dataMapping 			   = new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey = "id";
		$this->defaultOrderByOrder     = "DESC";
    }
    
    public function isActive($item)
    {
        return isTruthy($this->valueForKey("is_active", $item));
    }

    public function userIDSForRoleRelationsModifiableByRoleRelation($roleRelation)
    {
        $debug = false;
        
        $userIDS = [];

        $roleRelations = $this->roleRelationsModifiableByRoleRelation($roleRelation);

        if ($debug)
        {
            error_log("userIDSForRoleRelationsModifiableByRoleRelation - Role Relations: ".print_r($roleRelation, true));
        }

        foreach ($roleRelations as $roleRelation)
        {
            $userIDS[] = $roleRelation["user_id"];
        }

        return $userIDS;
    }

    public function roleRelationsModifiableByUser($user)
    {
        $debug = false;

        
		if (DataAccessManager::get("persona")->isInGroup($user, [
			"DEV", 
            "SOFTWARE_ADMIN", 
            "SOFTWARE_OWNER",
        ]))
        {
            return DataAccessManager::get("roles")->selectAll();
        }

        $query = new SelectQuery($this);

        $query->addWhereClause(new WhereClause(
            "user_id", "=", DataAccessManager::get("persona")->valueForIdentifier($user)
        ));

        $query->addWhereClause(new WhereClause(
            "can_grant_role", "=", DataAccessManager::get("persona")->valueForIdentifier($user)
        ));

        $whereGroup = new WhereGroup("OR");
        $whereGroup->addWhereClause(new WhereClause(
            "is_admin_for_role", "=", 1
        ));
        $whereGroup->addWhereClause(new WhereClause(
            "is_admin_for_role", "=", "TRUE"
        ));
        $query->addClause($whereGroup);

        $results = $query->executeAndReturnAll();

        return $results;
    }

    ////////////////////////////////////////////// añadir roles para usuarios
    public function rolesUserCanAddTo($user)
    {
        $debug = false;

        $roleRelations = $this->roleRelationsModifiableByUser($user);
        
        $roleIDS = [];

        foreach ($roleRelations as $roleRelation)
        {
            $roleIDS[] = $roleRelation["role_id"];
        }

        $query = new SelectQuery(DataAccessManager::get("roles"));

        $query->addClause(new WhereClause(
            "id", "IN", $roleIDS
        ));

        $toReturn = $query->executeAndReturnAll();

        if ($debug)
        {
            error_log("Returning `rolesUserCanAddTo`: ".print_r($toReturn, true));
        }

        return $toReturn;

    }


    public function roleRelationsModifiableByRoleRelation($roleRelation)
    {
        $debug = false;

        if ($debug)
        {
            error_log("`roleRelationsModifiableByRoleRelation`: --- Argument ".print_r($roleRelation, true));
        }

        $isRoleAdmin = $this->valueForKey("is_admin_for_role", $roleRelation);

        if (!$isRoleAdmin)
        {
            if ($debug)
            {
                error_log("Not Role Admin. Got: ".print_r($isRoleAdmin, true));
            }
            return [];
        }

        $query = new SelectQuery($this);

        $query->addWhereClause(new WhereClause(
            "role_id", "=", $this->valueForKey("role_id", $roleRelation)
        ));

        $qualifier = $this->valueForKey("qualifier", $roleRelation);

        if (isTruthy($qualifier))
        {
            $query->addWhereClause(new WhereClause(
                "qualifier", "=", $qualifier
            ));
        }

        $results = $query->executeAndReturnAll();

        if ($debug)
        {
            error_log("`rolesModifiableByRole`: --- Results: - ".print_r($results, true));
        }

        return $results;
    }


    public function isInvalidInsertionForUser($formItem, $user)
    {
        $flatRoleDataAccess = DataAccessManager::get("role_person_relationships");

        $isDev            = $flatRoleDataAccess->isUserInRoleNamed("DEV",            $user);
        $isHITAdmin       = $flatRoleDataAccess->isUserInRoleNamed("SOFTWARE_ADMIN",          $user);
        $isHITUser        = $flatRoleDataAccess->isUserInRoleNamed("ADMIN_USER",     $user);
        $isAgencyUser     = $flatRoleDataAccess->isUserInRoleNamed("AGENCY",         $user);
        $agencyUserAdmin  = $this->valueForKey("is_admin_for_role", $isAgencyUser);
        $agencyUserActive = $this->isActive($isAgencyUser);

        if ($isHITUser || $isHITAdmin || $isDev)
        {
            /* No filter needed */
        }
        else if ($isAgencyUser && isTruthy($agencyUserAdmin) && $agencyUserActive)
        {
            $agenciaID        = $isAgencyUser["qualifier"];
            $newItemAgencyID  = $formItem["qualifier"];

            $agencyIDSAreSame = ($agenciaID === $newItemAgencyID);
            
            if (!$agencyIDSAreSame)
            {
                throw new Exception("No permitido");
            }

        }
        else
        {
            throw new Exception("No permitido.");
        }
    }

    public function isIlegalUpdateForUserOnItem($user, $currentItem, $maybeItem)
    {
        $toReturn = [];

        $roleDataAccess = DataAccessManager::get("role_person_relationships");

        $isDev            = $roleDataAccess->isUserInRoleNamed("DEV",            $user);
        $isHITAdmin       = $roleDataAccess->isUserInRoleNamed("SOFTWARE_ADMIN",       $user);
        $isHITUser        = $roleDataAccess->isUserInRoleNamed("ADMIN_USER",        $user);

        $isAgencyUser     = $roleDataAccess->isUserInRoleNamed("AGENCY",          $user);
        $agencyUserAdmin  = $this->valueForKey("is_admin_for_role", $isAgencyUser);
        $agencyUserActive = $this->isActive($isAgencyUser);

        if ($isHITUser || $isHITAdmin || $isDev)
        {
            /* No filter needed */
            return null;
        }
        else if ($isAgencyUser && isTruthy($agencyUserAdmin) && $agencyUserActive)
        {
            $currentItemRoleID = $currentItem["role_id"];
            $maybeItemRoleID   = $maybeItem["role_id"];
            $agencyRoleID      = $isAgencyUser["role_id"];

            $currentItemRoleIsAgency = ($currentItemRoleID == $agencyRoleID);
            $maybeItemRoleIsAgency   = ($maybeItemRoleID    == $agencyRoleID);

            if (!$currentItemRoleIsAgency || $maybeItemRoleIsAgency)
            {
                $toReturn[] = "No permitido cambiar el tipo de rol.";
            }

            $userAgenciaID       = $isAgencyUser["qualifier"];
            $currentItemAgencyID = $currentItem["qualifier"];
            $maybeItemAgencyID   = $maybeItem["qualifier"];


            $currentItemAgencyIDIsSame = ($userAgenciaID === $currentItemAgencyID);
            $maybeItemAgencyIDIsSame   = ($userAgenciaID === $maybeItemAgencyID);
            
            if (!$currentItemAgencyIDIsSame || !$maybeItemAgencyIDIsSame)
            {
                $toReturn[] = "No permitido cambiar el grupo de la agencia";
            }

        }
        else
        {
            $toReturn[] = "No permitido hacer un cambio sin ser un admin para este rol.";
        }

        if (count($toReturn) > 0)
        {
            return $toReturn;
        }
        else
        {
            return null;
        }
    }

    public function roleRelationsWhereUserIsAdmin($user)
    {
        $debug = false;
        
        if (!$user)
        {
            return [];
        }

        $query = new SelectQuery($this);

        $userID = DataAccessManager::get("persona")->valueForKey("id", $user);

        $query->addClause(new WhereClause("user_id", "=", $userID));
        

        $whereGroup = new WhereGroup("OR");
        $whereGroup->addClause(new WhereClause(
            "is_admin_for_role", "=", "1"
        ));
        $whereGroup->addClause(new WhereClause(
            "is_admin_for_role", "=", "TRUE"
        ));

        $query->addClause($whereGroup);

        $results = $query->executeAndReturnAll();

        if ($debug)
        {
            error_log("Results: `roleRelationsWhereUserIsAdmin` - ".print_r($results, true));
        }

        return $results;
    }



    public function addWhereClauseForUser($user, &$query)
    {
        $debug = false;


        $roleDataAccess = DataAccessManager::get("role_person_relationships");

        $isDev            = $roleDataAccess->isUserInRoleNamed("DEV",            $user);
        $isHITAdmin       = $roleDataAccess->isUserInRoleNamed("SOFTWARE_ADMIN",       $user);
        $isHITUser        = $roleDataAccess->isUserInRoleNamed("ADMIN_USER",        $user);

        if ($isHITUser || $isHITAdmin || $isDev)
        {
            if ($debug)
            {
                error_log("`PersonaDataAccess/addWhereClauseForUser` --- User is Dev || Admin || Owner. Allowing all");
            }
            return;
        }

        $roleRelations = $this->roleRelationsWhereUserIsAdmin($user);

        if (count($roleRelations))
        {
            $allRolesGroup = new WhereGroup("OR");

            foreach ($roleRelations as $roleRelation)
            {

                $oneRoleGroup = new WhereGroup("AND");

                $roleID = $this->valueForKey("role_id", $roleRelation);

                $oneRoleGroup->addWhereClause(new WhereClause(
                    "role_id", "=", $roleID
                ));
        
                $qualifier = $this->valueForKey("qualifier", $roleRelation);
        
                if (isTruthy($qualifier))
                {
                    $oneRoleGroup->addWhereClause(new WhereClause(
                        "qualifier", "=", $qualifier
                    ));
                }

                if ($debug)
                {
                    error_log("`addWhereClauseForUser` - For role relation: ".print_r($roleRelation, true));
                }

                $allRolesGroup->addClause($oneRoleGroup);
            }

            $query->addClause($allRolesGroup);
        }
        else
        {
            throw new Exception("No permitido.");
        }
    }
  ////////////////////////////////////////////// roles para usuarios 
  public function rolesForUser(&$user)
  {
      $debug = false;
  
      if ($user === null) {
          return [];
      }
  
      // Usar caché unificado
      if (isset($user["gtk_cache"]["roles"]))
      {
          if ($debug)
          {
              error_log("Returning cached roles for user");
          }
          return $user["gtk_cache"]["roles"];
      }
  
      $roleRelations = $this->roleRelationsForUser($user);
  
      $roleIDS = [];
  
      foreach ($roleRelations as $roleRelation)
      {
          $roleID = $roleRelation["role_id"] ?? null;
  
          if (!$roleID)
          {
              die("No role ID for role relation: ".print_r($roleRelation, true));
              continue;
          }
          else
          {
              $roleIDS[] = $roleID;
          }
      }
  
      $query = new SelectQuery(DataAccessManager::get("roles"));
  
      $query->addClause(new WhereClause(
          "id", "IN", $roleIDS
      ));
  
      $toReturn = $query->executeAndReturnAll();
  
      // Guardar en caché unificado
      if (!isset($user["gtk_cache"]))
      {
          $user["gtk_cache"] = [];
      }
      $user["gtk_cache"]["roles"] = $toReturn;
  
      if ($debug)
      {
          error_log("Returning `rolesForUser`: ".print_r($toReturn, true));
      }
  
      return $toReturn;
  }

    public function isInRole($role, $user)
    {
        return $this->isUserInRoleNamed($role, $user);
    }

    public function isUserInRoleNamed($roleName, $user)
    {
        if (!$user)
        {
            return false;
        }

        $role = DataAccessManager::get("roles")->getOne("name", $roleName);

        if ($role)
        {
            $existingRole = $this->whereDict(1, [
                "role_id" => $role["id"],
                "user_id" => DataAccessManager::get('persona')->valueForKey("id", $user),
            ]);
    
            return $existingRole;
        }
        else
        {
            return null;
        }
    }


    public function isUserInAnyOfTheseRoles($toAllowFrom, &$user)
    {
        $debug = false;

        if (!$user)
        {
            return false;
        }


        $roleRelations = $this->roleRelationsForUser($user);


        $allowedRolesIDS = [];

        foreach ($toAllowFrom as $maybeRoleStringNumber)
        {
            if (is_numeric($maybeRoleStringNumber))
            {
                $allowedRolesIDS[] = $maybeRoleStringNumber;
            }
            else if (is_string($maybeRoleStringNumber))
            {
                $role = DataAccessManager::get("roles")->getOne("name", $maybeRoleStringNumber);

                if ($role)
                {
                    $allowedRolesIDS[] = $role["id"];
                }
            }
            else if (is_array($maybeRoleStringNumber))
            {
                $allowedRolesIDS[] = $maybeRoleStringNumber["id"];
            }
        }

        if ($debug)
        {
            error_log("Roles relations for user: ".print_r($allowedRolesIDS, true));
        }

        $roleIDS = [];
        
        foreach ($roleRelations as $roleRelation)
        {
            $roleIDS[] = $roleRelation["role_id"];
        }

        if ($debug)
        {
            error_log("Role IDs Name: ".print_r($roleIDS, true));
        }

        $intersection = array_intersect($roleIDS, $allowedRolesIDS);

        if ($debug)
        {
            error_log("Allowed Roles: ".print_r($allowedRolesIDS, true));
            error_log("Intersection: ".print_r($intersection, true));
        }

        return count($intersection) > 0;


    }

    public function roleRelationsForUser(&$user = null)
    {

        $debug = false;

        if ($debug)
        {
            error_log("Role Relations for user: ".print_r($user, true));
        }

        if (!$user)
        {
            return [];
        }

        // Usar el sistema de caché unificado en $user["gtk_cache"]
        if (is_array($user) && isset($user["gtk_cache"]["role_relations"]))
        {
            if ($debug)
            {
                error_log("Returning cached role relations");
            }
            return $user["gtk_cache"]["role_relations"];
        }

        $userID = null;

        if (is_array($user))
        {
            $userID = DataAccessManager::get("persona")->valueForKey("id", $user);
        }
        else if (is_numeric($user))
        {
            $userID = $user;
        }
        else
        {
            throw new Exception("Invalid user type: ".gettype($user));
        }

        $userID = (int) $userID;
        $query = new SelectQuery($this, null, [
            new WhereClause("user_id", "=",  $userID),
        ]);

        $toReturn = $query->executeAndReturnAll();
    
        // Guardar en caché unificado si $user es array
        if (is_array($user))
        {
            if (!isset($user["gtk_cache"]))
            {
                $user["gtk_cache"] = [];
            }
            $user["gtk_cache"]["role_relations"] = $toReturn;
        }

        if ($debug)
        {
            error_log("Role Relations for user: ".print_r($toReturn, true));
        }

        return $toReturn;
    }
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// -- OTTO
public function assignRolesToUser($userID, $roles)
{
    // Obtener los roles actuales del usuario
    $currentRoles = $this->roleRelationsForUser($userID);

    // Filtrar los nuevos roles que no están en los roles actuales
    $newRoles = array_filter($roles, function($role) use ($currentRoles) {
        return !in_array($role, array_column($currentRoles, 'role_id'));
    });

    // Insertar los nuevos roles
    foreach ($newRoles as $role) {
        $relationship = [
            'user_id' => $userID,
            'role_id' => $role
        ];
        $this->insert($relationship);
    }

    // Invalidar caché del usuario después de modificar roles
    DataAccessManager::get("session")->invalidateUserCache($userID);

    return true;
}

/**
 * Override del método insert para invalidar caché automáticamente
 */
public function insert(&$input, &$outError = null)
{
    $result = parent::insert($input, $outError);
    
    if ($result && isset($input['user_id']))
    {
        // Invalidar caché del usuario modificado
        DataAccessManager::get("session")->invalidateUserCache($input['user_id']);
    }
    
    return $result;
}

/**
 * Override del método update para invalidar caché automáticamente
 */
public function update(&$item, $options = null)
{
    $result = parent::update($item, $options);
    
    if ($result && isset($item['user_id']))
    {
        // Invalidar caché del usuario modificado
        DataAccessManager::get("session")->invalidateUserCache($item['user_id']);
    }
    
    return $result;
}

/**
 * Override del método delete para invalidar caché automáticamente
 */
public function delete($item)
{
    $user_id = null;
    if (is_array($item) && isset($item['user_id']))
    {
        $user_id = $item['user_id'];
    }
    
    $result = parent::delete($item);
    
    if ($result && $user_id)
    {
        // Invalidar caché del usuario modificado
        DataAccessManager::get("session")->invalidateUserCache($user_id);
    }
    
    return $result;
}



    
}
