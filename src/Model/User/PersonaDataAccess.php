<?php

// require_once("../DataAccess.php");


	/*
	passwordDoesNotMeetRequiredLength
	passwordTooLong
	passwordIsMissingUppercaseCharacter
	passwordIsMissingDigits
	*/
function validatePasswordIsSecure($password, $delegate = null)
{
	$debug = false;
	if ($debug)
	{
		if ($delegate)
		{
			error_log("Running - `validatePasswordIsSecure` with delegate: ".get_class($delegate));
		}
		else
		{
			error_log("`validatePasswordIsSecure` - No delegate.");
		}
	}
	global $_GLOBALS;
	if (!isset($_GLOBALS["APP_PASSWORD_REQUIREMENTS"]))
	{
		die('Need to set: `$_GLOBALS["APP_PASSWORD_REQUIREMENTS"])`');
	}
	$settings = $_GLOBALS["APP_PASSWORD_REQUIREMENTS"];

	// Validate password length
	if (strlen($password) < $settings['min_length'])
	{
		if ($debug)
		{
			error_log("`passwordDoesNotMeetRequiredLength`");
		}
		$delegate->passwordDoesNotMeetRequiredLength($settings['min_length']);
		return false;
	}
	if (strlen($password) > $settings['max_length'])
	{
		if ($debug)
		{
			error_log("`passwordTooLong`");
		}
		$delegate->passwordTooLong($settings['max_length']);
		return false;
	}

	// Validate character requirements
	if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) 
	{
		
		/*
		if ($delegate && method_exists($delegate, "passwordIsMissingUppercaseCharacter"))
		{
			return $delegate->passwordIsMissingUppercaseCharacter();
		}
		*/
		if ($debug)
		{
			error_log("`passwordIsMissingUppercaseCharacter`");
		}
		$delegate->passwordIsMissingUppercaseCharacter();
		return false;
	}
	if ($settings['require_lowercase'] && !preg_match('/[a-z]/', $password)) 
	{
		if ($debug)
		{
			error_log(`passwordIsMissingLowercaseCharacter`);
		}
		$delegate->passwordIsMissingLowercaseCharacter();
		return false;
	}
	if ($settings['require_digits'] && !preg_match('/\d/', $password)) 
	{
		if ($debug)
		{
			error_log("`passwordIsMissingDigits`");
		}
		$delegate->passwordIsMissingDigits();
		return false;
	}
	if ($settings['require_special_chars'] && !preg_match($settings['special_chars'], $password))
	{
		if ($debug)
		{
			error_log("`passwordIsMissingSpecialCharacters`");
		}
		$delegate->passwordIsMissingSpecialCharacters($settings['special_chars']);
		return false;
	}

	return true;
}
	


class PersonaDataAccess extends DataAccess 
{
	
	public function rowStyleForItem($item, $index)
	{
		$style = "";

		$estado = $item["estado"];

		switch ($estado)
		{
			case 'activo':	
				$style .= "background-color:green;";
				break;
			case 'inactivo':
				$style .= "background-color:gray";
				break;
		}

		return $style;
	}
	public function injectVariablesForUserOnItem(&$user, &$item)
	{
		parent::injectVariablesForUserOnItem($user, $item);
		$item["created_by_user_id"] = $user["id"];
	}

	public function addWhereClauseForUser($user, &$query)
    {
		$debug = false;
		
        $flatRoleDataAccess = DataAccessManager::get("role_person_relationships");

		$isDev              = $flatRoleDataAccess->isUserInRoleNamed("DEV",             $user);
        $isAdminAdmin       = $flatRoleDataAccess->isUserInRoleNamed("SOFTWARE_ADMIN",  $user);
        $isAdminUser        = $flatRoleDataAccess->isUserInRoleNamed("ADMIN_USER",      $user);
        $isSupervisor       = $flatRoleDataAccess->isUserInRoleNamed("SUPERVISOR",      $user);

		if ($isAdminAdmin || $isAdminUser || $isDev || $isSupervisor)
		{
			if ($debug)
			{
				// error_log("`addWhereClauseForUser` - isDev ($isDev) - isAdmimAdmin ($isAdminAdmin) - isAdminUser ($isAdminUser)");
				error_log("`addWhereClauseForUser` - isDev - isAdmimAdmin - isAdminUser");

			}
			return; // Allow All
		}

		$roles = $flatRoleDataAccess->rolesUserCanAddTo($user);

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("PersonaDataAccess/addWhereClauseForUser - `rolesUserCanAddTo`".print_r($roles, true));
		// }

		$hasRoles = $roles && (count($roles) > 0);

		if (!$hasRoles)
		{
			throw new Exception("Not allowed - user has no roles they can add to.");
		}

		$roleRelations = $flatRoleDataAccess->roleRelationsModifiableByUser($user);

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("`PersonaDataAccess/addWhereClauseForUser - Role Relations Modifiable By User: ".print_r($roleRelations, true));
		// }

		$userIDS = [];

		foreach ($roleRelations as $roleRelation)
		{
			$userIDS = array_merge($userIDS, $flatRoleDataAccess->userIDSForRoleRelationsModifiableByRoleRelation($roleRelation));
		}

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("Got User IDS: ".print_r($userIDS, true));
		// }

		$whereGroup = new WhereGroup("OR");

		$whereGroup->addWhereClause(new WhereClause(
			"id", "IN", $userIDS
		));
		$whereGroup->addWhereClause(new WhereClause(
			"created_by_user_id", "=", $this->valueForKey("id", $user)
		));

		$query->addWhereClause($whereGroup);
	}

	

	public function getUserFromToken($token)
	{
		$debug = false;

		$id = htmlspecialchars_decode(explode('_', $token)[0]);
		
		if ($debug) 
		{ 
			error_log("Searching for current user with Cedula: ".$id); 
		}
		
		$current_user = DataAccessManager::get('persona')->getByIdentifier($id);

		if (DataAccessManager::get("persona")->isActive($current_user))
		{
			return null;
		}

		return $current_user;
	}

	public static function hydrateUserWithRoles(&$user)
	{
		$roles = DataAccessManager::get('role_person_relationships')->rolesForUser($user);
		$user["roles"] = $roles;
	}

	public static function deleteCurrentSessionCookie()
	{
		unset($_COOKIE['AuthCookie']);
		setcookie('AuthCookie', '', -1, '/'); 
	}

	public static function logout($returnToPath = null)
	{
		die(Glang::get("session_expired_message"));
	}

	public function getFullName($item)
	{
		return $this->valueForKey("nombres", $item)." ".$this->valueForKey("apellidos", $item);
	}
	
	public function register()
	{	
		$debug = 0;
	
		if ($debug)
		{
			error_log('User Data Access Register (getDB):');
		}
		
		$db = $this->getDB();

		$passwordVirtualColumn = new GTKColumnVirtual($this, "password", [
            'formLabel'        => Glang::get("password"),
			'type'             => 'password', 
			'assignTo'		   => 'password_hash', 
			"process" 		   => function($value) { return password_hash($value, PASSWORD_DEFAULT); }, 
			"allowedFormTypes" => ["new"],
			'removeOnForms'	   => true,
			'hideOnShow'	   => true,
			'hideOnForms'	   => true, 
			'hideOnLists' 	   => true,
			'isInvalid' 		   => function($value) { 
				$value = trim($value);

				if (strlen($value) < 12)
				{
					return new FailureResult(0, Glang::get("password_too_short"));
				}

				    // Check for symbol and number
				if (!preg_match('/[!@#$%^&*(),.?":{}|<>0-9]/', $value)) {
					return new FailureResult(0, Glang::get("password_needs_character_and_symbols"));
				}
			},
		]);

		
		$checkCedulaForProblems = function($value) { 
			if (!verifyCedula($value)) 
			{ 
				return new FailureResult(0, Glang::get("invalid_goverment_id")); 
			}
		};
	
		$columnMappings = [
			new GTKColumnMapping($this, "id", [
				"isPrimaryKey"    => true,
				"isAutoIncrement" => true,
			]),
			new GTKColumnMapping($this, "created_by_user_id", [
				"hideOnForms" => true,
				"valueWhenNewForUser" => function ($user, $item){
					return $user["id"];
				},
			]),
			new GTKColumnMapping($this, "cedula", [
				"isUnique"     => true, 
				'isInvalid'    => $checkCedulaForProblems, 
				"processOnAll"  => function ($rawEmail) { return strtolower($rawEmail); },
			]),
			new GTKColumnMapping($this, "nombres"),
			new GTKColumnMapping($this, "apellidos"),
			new GTKColumnMapping($this, "email", [
				"isRequired"    => true,
                "isUnique"      => true,
				"processOnAll"  => function ($rawEmail) { return strtolower($rawEmail); },
			]),
			$passwordVirtualColumn,
			new GTKColumnMapping($this, "password_hash", [
				'hideOnShow'  => true,
                'hideOnForms' => true, 
                'hideOnLists' => true
            ]),
			new GTKColumnMapping($this, "fecha_creado"),
			new GTKColumnMapping($this, "fecha_modificado"),
			new GTKColumnMapping($this, "estado", [
				'formInputType' => 'select', 
				'possibleValues' => [ 
					'activo' 	 => ['label'=>'Activo'], 
					'inactivo' 	 => ['label'=>'Inactivo'],
				],
			]),
		];

		$this->dataMapping		        = new GTKDataSetMapping($this, $columnMappings);
		$this->defaultOrderByColumnKey  = "fecha_creado";
		$this->defaultOrderByOrder      = "DESC";	
		$this->defaultSearchByColumnKey = "email";
		
		///
		///
		///

		$createAndDisplayNewPassword = new GTKAction($this, "persona.createAndDisplayNewPassword", "actionCreateAndDisplayNewPassword", [ 
            "label"         => "Crearle nueva contraseña",
            "canEchoResult" => true,
        ]);

		$this->addAction($createAndDisplayNewPassword);

		$createManualPassword = new GTKAction($this, "persona.createManualPassword", "actionCreateManualPassword", [ 
            "label"         => "Crear contraseña manual",
            "canEchoResult" => true,
        ]);

		$this->addAction($createManualPassword);


		
		$resetPasswordAction = new DataAccessAction($this, "resetPassword", "Send Reset Password Link");
		$resetPasswordAction->allowedFor = [
			"SOFTWARE_ADMIN",
			"DEVS",
		];
		$resetPasswordAction->doObjectForUserItemDelegateOptions   = DataAccessManager::get("RequestPasswordResetController");
		$resetPasswordAction->doFunctionForUserItemDelegateOptions = "sendResetPasswordLinkFromAdminFromUserToUserDelegateOptions";

		$this->addAction($resetPasswordAction);

		///
		///
		///

		$activateDeactivateUser = new DataAccessAction($this, "activateDeactivateUser", "Send Reset Password Link");
		$activateDeactivateUser->label = function ($user, $item) {
			if (DataAccessManager::get("persona")->isActive($item))
			{
				return "Desactivar";
			}
			else
			{
				return "Activar";
			}
		};
		$activateDeactivateUser->isInvalidForUserItemDelegateOptionsFunction = function ($user, $item, $delegate, $options) {

			/*
			
			"qualifier_model"        => "agencia", 
			"qualifier_model_column" => "id",
			"qualifier_item_column"  => "agencia_id",
			"qualifier_value"        => $agenciaID, 

			function hasPersmission($permission, $user, $item)
			{
				$userRoles = $roleDataAccess->rolesForUser($user);

				foreach ($userRoles as $roles)
				{
					$permission = $rolePermissionRelationships->hasPermission($permission, $role);

					if (!$permission)
					{
						continue;
					}

					$qualifier = $rolePermissionRelationships->getQualifier($permission);

					if (!$qualifier)
					{
						return true;
					}

					$qualifier

					$itemMeetsQualifier...

					$item = DataAccessManager::get(")
				}

				return false;
			}

			*/
			// return DataAccessManager::get("permission")->hasPermission("toggle_user_active", $user, $userToBeActivated);

			$debug = false;

			if (DataAccessManager::get("persona")->isInGroups($user, [
			   "SOFTWARE_ADMIN",
			   "DEV",
			])){
				return false;
			}

			if ($debug)
			{
				error_log("Not an ADMIM or a dev");
			}
			
			$isUserInAgency     = DataAccessManager::get("role_person_relationships")->isUserInRoleNamed("AGENCY", $user);
			$isUserAdminForRole = DataAccessManager::get("role_person_relationships")->valueForKey("is_admin_for_role", $isUserInAgency);

			if ($isUserInAgency && $isUserAdminForRole)
			{
				$isItemInAgency = DataAccessManager::get("role_person_relationships")->isUserInRoleNamed("AGENCY", $item);
				
				if ($isItemInAgency)
				{
					$userQualifier = DataAccessManager::get("role_person_relationships")->valueForKey("qualifier", $isUserInAgency);
					$itemQualifier = DataAccessManager::get("role_person_relationships")->valueForKey("qualifier", $isItemInAgency);

					if ($userQualifier == $itemQualifier)
					{
						return false;
					}
				}
			}


			return true;
		};
		$activateDeactivateUser->doObjectForUserItemDelegateOptions   = $this;
		$activateDeactivateUser->doFunctionForUserItemDelegateOptions = "toggleUserActiveByUserAndUserItemDelegateOptions";

		$this->addAction($activateDeactivateUser);
	}

	function generateReadableRandomString($length = 10) {
		$vowels = 'aeiou';
		$consonants = 'bcdfghjklmnpqrstvwxyz';
		$symbols = '!@#$%^&*()';
		$allChars = $vowels . $consonants . $symbols . strtoupper($vowels) . strtoupper($consonants);
		$charactersLength = strlen($allChars);
		$randomString = '';
		$isVowel = rand(0, 1); // Randomly start with a vowel or a consonant
	
		for ($i = 0; $i < $length; $i++) {
			if ($i % 2 == $isVowel) {
				// Choose a vowel
				$randomString .= $vowels[rand(0, strlen($vowels) - 1)];
			} else {
				// Choose a consonant or symbol
				if (rand(0, 10) > 8) { // 20% chance to pick a symbol
					$randomString .= $symbols[rand(0, strlen($symbols) - 1)];
				} else { // 80% chance to pick a consonant
					$randomString .= $consonants[rand(0, strlen($consonants) - 1)];
				}
			}
		}
		return $randomString;
	}
	

	public function actionCreateAndDisplayNewPassword($user, $userToUpdate)
	{
		$debug = false;

		// Generar 4 letras aleatorias mayusculas
		$letras = [];
		$abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		for ($i = 0; $i < 4; $i++) {
			$letras[] = $abc[random_int(0, strlen($abc) - 1)];
		}

		// Generar 4 números aleatorios
		$numeros = [];
		for ($i = 0; $i < 4; $i++) {
			$numeros[] = random_int(0, 9);
		}

		// Unir primero letras y luego números
		$newPassword = implode('', $letras) . implode('', $numeros);

		if ($debug)
		{
			error_log("New password: ".$newPassword);
		}

		$this->updatePasswordHashForPersona($userToUpdate, $newPassword);

		$toPublish = "Nueva contraseña: ".$newPassword;
		$toPublish .= "<br/>";
		$toPublish .= "Apuntar! Esto solo se va a mostrar una vez."."<br/>";

		$toPublish .=  AllLinkTo("persona", ["label" => "Volver a lista", ]);
		$toPublish .= "<br/>";
		$toPublish .= '<a href="/">Ir a inicio</a>';

		die($toPublish);
	}

	public function actionCreateManualPassword($user, $userToUpdate)
	{
		$debug = false;

		// Verificar si se envió el formulario
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
			$newPassword = $_POST['new_password'];
			$confirmPassword = $_POST['confirm_password'] ?? '';

			// Validar que las contraseñas coincidan
			if ($newPassword !== $confirmPassword) {
				$error = "Las contraseñas no coinciden.";
			}
			// Validar longitud mínima
			elseif (strlen($newPassword) < 8) {
				$error = "La contraseña debe tener al menos 8 caracteres.";
			}
			// Validar que no esté vacía
			elseif (empty(trim($newPassword))) {
				$error = "La contraseña no puede estar vacía.";
			}
			else {
				// Actualizar la contraseña
				$this->updatePasswordHashForPersona($userToUpdate, $newPassword);

				$toPublish = "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
				$toPublish .= "<h3>✅ Contraseña actualizada exitosamente</h3>";
				$toPublish .= "<p><strong>Usuario:</strong> " . htmlspecialchars($this->getFullName($userToUpdate)) . "</p>";
				$toPublish .= "<p><strong>Email:</strong> " . htmlspecialchars($userToUpdate['email']) . "</p>";
				$toPublish .= "<p><strong>Nueva contraseña:</strong> " . htmlspecialchars($newPassword) . "</p>";
				$toPublish .= "<p style='color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 3px;'>";
				$toPublish .= "⚠️ <strong>Importante:</strong> Esta contraseña solo se muestra una vez. Guárdala en un lugar seguro.";
				$toPublish .= "</p>";
				$toPublish .= "</div>";

				$toPublish .= "<div style='margin: 20px 0;'>";
				$toPublish .= AllLinkTo("persona", ["label" => "Volver a lista", ]);
				$toPublish .= " | ";
				$toPublish .= '<a href="/">Ir a inicio</a>';
				$toPublish .= "</div>";

				die($toPublish);
			}
		}

		// Mostrar formulario para crear contraseña manual
		$userName = $this->getFullName($userToUpdate);
		$userEmail = $userToUpdate['email'];

		$toPublish = "<div style='max-width: 600px; margin: 0 auto; padding: 20px;'>";
		$toPublish .= "<h2>🔐 Crear Contraseña Manual</h2>";
		$toPublish .= "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
		$toPublish .= "<p><strong>Usuario:</strong> " . htmlspecialchars($userName) . "</p>";
		$toPublish .= "<p><strong>Email:</strong> " . htmlspecialchars($userEmail) . "</p>";
		$toPublish .= "</div>";

		if (isset($error)) {
			$toPublish .= "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
			$toPublish .= "❌ " . htmlspecialchars($error);
			$toPublish .= "</div>";
		}

		$toPublish .= "<form method='POST' style='background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
		$toPublish .= "<div style='margin-bottom: 15px;'>";
		$toPublish .= "<label for='new_password' style='display: block; margin-bottom: 5px; font-weight: bold;'>Nueva Contraseña:</label>";
		$toPublish .= "<input type='password' id='new_password' name='new_password' required ";
		$toPublish .= "style='width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 4px; font-size: 16px;' ";
		$toPublish .= "placeholder='Ingrese la nueva contraseña' minlength='8'>";
		$toPublish .= "<small style='color: #666;'>Mínimo 8 caracteres</small>";
		$toPublish .= "</div>";

		$toPublish .= "<div style='margin-bottom: 20px;'>";
		$toPublish .= "<label for='confirm_password' style='display: block; margin-bottom: 5px; font-weight: bold;'>Confirmar Contraseña:</label>";
		$toPublish .= "<input type='password' id='confirm_password' name='confirm_password' required ";
		$toPublish .= "style='width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 4px; font-size: 16px;' ";
		$toPublish .= "placeholder='Confirme la nueva contraseña' minlength='8'>";
		$toPublish .= "</div>";

		$toPublish .= "<div style='display: flex; gap: 10px;'>";
		$toPublish .= "<button type='submit' style='background-color: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;'>";
		$toPublish .= "✅ Actualizar Contraseña";
		$toPublish .= "</button>";
		$toPublish .= "<a href='" . AllLinkTo("persona", ["label" => "Cancelar", "returnUrl" => true]) . "' ";
		$toPublish .= "style='background-color: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block;'>";
		$toPublish .= "❌ Cancelar";
		$toPublish .= "</a>";
		$toPublish .= "</div>";
		$toPublish .= "</form>";

		$toPublish .= "<div style='margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-radius: 5px;'>";
		$toPublish .= "<h4>💡 Recomendaciones de seguridad:</h4>";
		$toPublish .= "<ul style='margin: 0; padding-left: 20px;'>";
		$toPublish .= "<li>Usa al menos 8 caracteres</li>";
		$toPublish .= "<li>Combina letras mayúsculas y minúsculas</li>";
		$toPublish .= "<li>Incluye números y símbolos</li>";
		$toPublish .= "<li>Evita información personal fácil de adivinar</li>";
		$toPublish .= "</ul>";
		$toPublish .= "</div>";

		$toPublish .= "</div>";

		die($toPublish);
	}

	public function toggleUserActiveByUserAndUserItemDelegateOptions($user, $item, $delegate, $options)
	{
		$toPublish = "";

		try
		{
			$userWasActive = $this->isActive($item);

			$this->toggleUserActiveByUserAndUserItem($user, $item);

			$toPublish = "";

			if ($userWasActive)
			{
				$toPublish .= "Usuario fue desactivado existosamente.";
			}
			else
			{
				$toPublish .= "Usuario fue desactivado existosamente.";
			}
		}
		catch (Exception $e)
		{
			$toPublish .= "Hubo un problema ejecutando esta acción. Intente nuevamente o reporte al administrador.";
		}

		$toPublish .= "<br/>";
		$toPublish .=  AllLinkTo("persona", ["label" => "Volver a lista", ]);
		$toPublish .= "<br/>";
		$toPublish .= '<a href="/">Ir a inicio</a>';

		die($toPublish);
	}

	public function toggleUserActiveByUserAndUserItem($user, $item)
	{
		if ($this->isActive($item))
		{
			$item["estado"] = "inactivo";
		}
		else
		{
			$item["estado"] = "activo";
		}

		$this->update($item);
	}

	public function isActive($user)
	{
		$debug = false;
		
		if (!$user)
		{
			if ($debug)
			{
				gtk_log("`isActive` - No user was provided");
			}
			return false;
		}

		$estado = $this->valueForKey("estado", $user);

		if ($debug)
		{
			gtk_log("`isActive` - estadoDeUsuario`: ".$estado);
		}

		switch ($estado)
		{
			case 'activo':
			case 'ACTIVO':
			case 1:
			case true:
				return true;
			case 'inactivo':
			default:
				if($debug)
				{
					gtk_log("No value was active");
				}
				return false;
		}
	}

	public function updatePasswordHashForPersona($persona, $newPassword)
	{
		$debug = false;

		$toUpdate = [];

		if ($debug)
		{
			error_log("Will update password for user: ".print_r($persona, true));
		}

		$toUpdate["id"] = $this->valueForIdentifier($persona);
		$toUpdate["password_hash"] = password_hash($newPassword, PASSWORD_DEFAULT);

		if ($debug)
		{
            error_log("Will update password hash. Using object: ".print_r($toUpdate, true));
		}

		$this->update($toUpdate);
	}

	
	public function findUserByCedula($cedula) 
	{
		$debug = 0;

		$query = "SELECT * FROM {$this->tableName()} WHERE cedula = :cedula";
		
		$db = $this->getDB();
		
		$statement = $db->prepare($query);

		$statement->bindValue(':cedula', sanitizeCedula($cedula));
				
		// Execute the query
		$statement->execute();
			
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		
		if ($debug)
		{
			error_log("`findUserByCedula` result: ".serialize($row));
		}
		
		return $row;
	}	

	// public function createUserWithNoPassword($)

	public function createUserIfNotExists($user)
{
    $existingUser = $this->where("email", $user['email']);
    if (!$existingUser)
    {
        if ($user["password"])
        {
            $password_hash = password_hash($user['password'], PASSWORD_DEFAULT);
            $user['password_hash'] = $password_hash;
        }

        $this->createUser($user);
        return $this->getDB()->lastInsertId(); // Devolver el ID del usuario recién creado
    }

}
	
	public function createUser($user)
	{
		$query = "INSERT INTO {$this->tableName()} 
			(cedula,  nombres,  apellidos,  email,  password_hash, fecha_creado, estado)
			VALUES
			(:cedula, :nombres, :apellidos, :email, :password_hash, :fecha_creado, 'activo')";
			
		$statement = $this->getDB()->prepare($query);

		$cedula = null;

		if (isset($user["cedula"]))
		{
			$cedula = sanitizeCedula($user["cedula"]);
		}

		$statement->bindValue(':cedula',    	$cedula);
		$statement->bindValue(':nombres',   	$user["nombres"]);
		$statement->bindValue(':apellidos', 	$user["apellidos"]);
		$statement->bindValue(':email', 		$user["email"]);
		$statement->bindValue(':password_hash', $user["password_hash"]);
		$statement->bindValue(':fecha_creado',  date(DATE_ATOM) );
		
		// Execute the INSERT statement
		$result = $statement->execute();
		
		if ($result) 
		{
			return $this->getDB()->lastInsertId();
		} 
		else 
		{
			error_log('INSERT FAILED');
			return 0;
		}
	}


	public function isInGroup(&$user, $group)
	{
		$debug = false;

		if (!$user)
		{
			if ($debug)
			{
				error_log("User is null");
			}
			return false;
		}

		if (is_array($group))
		{
			return $this->isInGroups($user, $group);
		}
		else if (is_string($group))
		{
			return $this->isInGroups($user, [ $group ]);
		}
		else
		{
			throw new Exception("Invalid group type");
		}
		
	}

	public function isInGroups(&$user, $groups)
	{
		$debug = false;

		// die("isInGroups: ".print_r($user, true));

		if (!$user)
		{
			return false;
		}

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("`userIsInGroup` - ".print_r($user, true));
		// }

		// Usar el sistema de caché unificado
		if (!isset($user["gtk_cache"]["role_names"]))
		{
			// Obtener roles usando el sistema de caché unificado
			$userRoles = DataAccessManager::get("role_person_relationships")->rolesForUser($user);
			
			$userRoleNames = [];
			foreach ($userRoles as $role)
			{
				$userRoleNames[] = $role["name"];
			}
			
			if (!isset($user["gtk_cache"]))
			{
				$user["gtk_cache"] = [];
			}
			$user["gtk_cache"]["role_names"] = $userRoleNames;
		}

		$userRoleNames = $user["gtk_cache"]["role_names"];

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("Got user role names: ".print_r($userRoleNames, true));
		// }

		$intersection = array_intersect($groups, $userRoleNames);

		if ($debug)
		{
			error_log("Intersection (".count($intersection).") - ".print_r($intersection, true));
		}

		return (count($intersection) > 0);
	}

	public function isDeveloper($user = null) 
	{
		return $this->isInGroup($user, "DEV");
	}
	public function isAdmin($user = null) 
	{	
		return $this->isInGroup($user, "SOFTWARE_ADMIN");
	}

	public function assignRoleToUser($role, $user)
	{
		$this->assignRoleToUserByUserDetails($role, $user, null, null);
	}

	public function assignRoleToUserByUserDetails($maybeRole, $user, $grantingUser = null, $grantingDetails = null)
	{	
		$debug = false;

		$role = null;

		if (is_array($maybeRole))
		{
			$role = $maybeRole;
		}
		else
		{
			$role = DataAccessManager::get("roles")->getOne("name", $maybeRole);
		}

		if (!$role)
		{
			throw new Exception("Role not found `{$maybeRole}`");
		}

		if ($debug)
		{
			error_log("Role: ".print_r($role, true));
		}

		$roleID = DataAccessManager::get("roles")->valueForKey("id", $role);
		$userID = $this->valueForKey("id", $user);

		// $rolePersonRelationships = DataAccessManager::get("role_person_relationships");
		// $rolePersonRelationships = DataAccessManager::get("role_person_relationships");

		if (DataAccessManager::get("roles")->isQualifiedRole($role))
		{
			if ($debug)
			{
				error_log("Qualified role");
			}

			if (!$grantingUser)
			{
				throw new Exception("A qualified role must be granted by a specific user");
			}

			$grantingUserID = $this->valueForKey("id", $grantingUser);
		}

		if ($debug)
		{
			error_log("Not a qualified role");
		}

		if (!$userID)
		{
			throw new Exception("User ID not found in".print_r($user, true));
		}
    
		$query = new SelectQuery(DataAccessManager::get("role_person_relationships"));


		$toInsert = [
			"role_id" => $roleID,
			"user_id" => $userID,
		];
		

		DataAccessManager::get("role_person_relationships")->insert($toInsert);

		// DataAccessManager::get("role_person_relationships")->insert($toInsert);

	}

	public function permissionsForUser(&$user)
{
    $debug = false;
    
    if (!$user)
	{
        return ["public",];
    }

    // Usar caché unificado para permisos
    if (isset($user["gtk_cache"]["permissions"]))
    {
        // Log removido para evitar llenar logs innecesariamente
        // if ($debug)
        // {
        //     gtk_log("Returning cached permissions for user");
        // }
        return $user["gtk_cache"]["permissions"];
    }

    $roles = DataAccessManager::get("role_person_relationships")->rolesForUser($user);

    $permissions = [];

    foreach ($roles as $role)
    {
        $rolePermissions = DataAccessManager::get("role_permission_relationships")->permissionsForRole($role);

        // Log removido para evitar llenar logs innecesariamente
        // if ($debug)
        // {
        //     gtk_log("`permissionsForUser`: - Role (".$role["name"].") - has permissions: ".print_r($rolePermissions, true));
        // }

        $permissions = array_merge($permissions, $rolePermissions);
    }

    $permissions = array_unique($permissions);
    sort($permissions);

    // Guardar en caché unificado
    if (!isset($user["gtk_cache"]))
    {
        $user["gtk_cache"] = [];
    }
    $user["gtk_cache"]["permissions"] = $permissions;

    return $permissions;
}

	public function hasOneOfPermissions($permissions, &$user, $closure = null)
	{
		$permissions = $this->permissionsForUser($user);

		foreach ($permissions as $permission)
		{
			if (in_array($permission, $permissions))
			{
				if ($closure && is_callable($closure))
				{
					return $closure($permission);
				}
				else
				{
					return true;
				}
			}
		}

		return false;
	}

	/*
	public function hasPermissionOnItem()
	{
		$debug = false;

		$permissions = $this->permissionsForUser($user);

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	gtk_log("User has permissions: ".print_r($permissions, true));
		// }

		$hasPermission = in_array($permission, $permissions);

		if ($hasPermission)
		{
			return true;
		}

		$exploded = explode(".", $permission);

		if (count($exploded) == 2)
		{
			$dataSourceName = $exploded[0];
			$permissionName = $exploded[1];

			switch ($permissionName)
			{
				case "new":
					$permissionName = "create";
					break;
				case "show":
					$permissionName = "read";
					break;
				case "edit":
					$permissionName = "update";
					break;
				case "destroy":
					$permissionName = "delete";
					break;
				//------------------------------
				case "list":
					$permissionName = "all";
					break;
			}

			$permission = $dataSourceName.".".$permissionName;

			if ($debug)
			{
				gtk_log("Checking for modified permission: ".$permission);
			}

			$hasPermission = in_array($permission, $permissions);
		}

		return $hasPermission;
	}
	*/

	
	public function hasPermission($permission, &$user, $closure = null)
	{
		$debug = false;

		$permissions = $this->permissionsForUser($user);

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	gtk_log("User has permissions: ".print_r($permissions, true));
		// }

		$hasPermission = in_array($permission, $permissions);

		if ($hasPermission)
		{
			return true;
		}

		$exploded = explode(".", $permission);

		if (count($exploded) == 2)
		{
			$dataSourceName = $exploded[0];
			$permissionName = $exploded[1];

			switch ($permissionName)
			{
				case "new":
					$permissionName = "create";
					break;
				case "show":
					$permissionName = "read";
					break;
				case "edit":
					$permissionName = "update";
					break;
				case "destroy":
					$permissionName = "delete";
					break;
				//------------------------------
				case "list":
					$permissionName = "all";
					break;
			}

			$permission = $dataSourceName.".".$permissionName;

			if ($debug)
			{
				gtk_log("Checking for modified permission: ".$permission);
			}

			$hasPermission = in_array($permission, $permissions);
		}

		if ($hasPermission)
		{
			if ($closure && is_callable($closure))
			{
				return $closure($permission);
			}
			else
			{
				return true;
			}
		}
		else
		{
			return false;
		}
	}
	
	public function createOrManageUser($user)
	{
		try
		{
			$this->rawCreateOrManageUser($user);
		}
		catch (Exception $e) 
		{
			echo 'Excecption Creating User: '.$e->getMessage()."\n";
			echo "User: ".print_r($user, true)."\n";
			die();
		}
		echo "----------------------------------------------------------------------------------------\n";
		echo "----------------------------------------------------------------------------------------\n";
		echo "----------------------------------------------------------------------------------------\n";
	}

	public function findUserByIdentifiable($cedulaOrUsername)
	{
		$debug = false;

		$user = null;

		if (verifyCedula($cedulaOrUsername))
		{
			$user = $this->findUserByCedula($cedulaOrUsername);
		}
		else
		{
			// First try to find by primary email in the persona table
			$user = $this->getOne("email", $cedulaOrUsername);
			
			// If not found, check for email aliases
			if (!$user && DAM::get("person_email_aliases")) 
			{
				$personId = DAM::get("person_email_aliases")->findPersonByEmail($cedulaOrUsername);
				
				if ($personId) 
				{
					$user = $this->getOne("id", $personId);
				}
			}
		}

		if (!$user)
		{
			$user = $this->getOne("id", $cedulaOrUsername);
		}

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("Found user by identifiable: ".print_r($user, true));
		// }

		return $user;
	}

	public function updatePasswordOnUser($maybeUser, $password, &$outError = null)
	{
		$user = $maybeUser;

		if (is_string($maybeUser) || is_numeric($maybeUser))
		{
			$user = $this->findUserByIdentifiable($maybeUser);
		}

		if (!$user)
		{
			$outError = "User not found";
			return false;
		}
		

		$password_hash = password_hash($password, PASSWORD_DEFAULT);
		
		$toUpdate = [
			"id" 			=> $this->valueForKey("id", $user),
			"password_hash" => $password_hash,
		];

		return $this->update($toUpdate);
		
	}

	public function createOrManage($user, $searchColumnKey = null)
	{
		return $this->rawCreateOrManageUser($user, $searchColumnKey);
	}

	public function rawCreateOrManageUser($user)
	{
		$cedula       = $user['cedula'] ?? null;
		$email        = $user['email']  ?? null;
		$toIdentifyBy = $email ?? $cedula;
		$emailAliases = $user['email_aliases'] ?? [];

		echo "Looking for user with names: ".$user['nombres']." ".$user['apellidos']." - ID: ".$toIdentifyBy."\n";
		
		$userFromDB = $this->findUserByIdentifiable($toIdentifyBy);

		if (!$userFromDB)
		{
			echo "Creating user: ".$user['nombres']." ".$user['apellidos']." - Cedula: ".($cedula ?? "NA")."\n";                    
		
			$password          = null;
			$generatePassword  = true;
			$generatedPassword = null;
			
			if (isset($user['password']))
			{
				$password = $user['password'];
			}
			else if ($generatePassword)
			{
				$generatedPassword = uniqid();
				$password = $generatedPassword;
        		// $password = DataAccessManager::get("SetPasswordTokenDataAccess")->createPasswordPhrase();
        		echo "Generated password for ".$user["nombres"]." ".$user["apellidos"]." --- Password: ".$password."\n";
       		}

            if ($password)
            {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user['password_hash'] = $password_hash;
            }
        
	    	DataAccessManager::get("persona")->createUser($user);	

            $userFromDB = $this->findUserByIdentifiable($toIdentifyBy);

            if ($email && $generatedPassword)
            {

				if ($email && $generatedPassword)
				{
					if (method_exists($this, "sendWelcomeEmail"))
					{
						$this->sendWelcomeEmail($user, $generatedPassword);
					}
				}
            }

			// Add email aliases if provided
			if ($userFromDB && !empty($emailAliases) && DAM::get("person_email_aliases")) {
				foreach ($emailAliases as $alias) {
					DAM::get("person_email_aliases")->addEmailForPerson($userFromDB['id'], $alias, false);
				}
			}
			
			// Add the primary email as an alias too
			if ($userFromDB && $email && DAM::get("person_email_aliases")) {
				DAM::get("person_email_aliases")->addEmailForPerson($userFromDB['id'], $email, true);
			}

	    }
        else
        {
            echo "User exists!\n";

			// User already exists, add any new email aliases
			if (!empty($emailAliases) && DAM::get("person_email_aliases")) {
				foreach ($emailAliases as $alias) {
					DAM::get("person_email_aliases")->addEmailForPerson($userFromDB['id'], $alias, false);
				}
			}
			
			// Ensure the primary email is in the aliases table
			if ($email && DAM::get("person_email_aliases")) {
				$existingAlias = DAM::get("person_email_aliases")->getOne("email", $email);
				if (!$existingAlias) {
					DAM::get("person_email_aliases")->addEmailForPerson($userFromDB['id'], $email, true);
				}
			}
        }
        
        $roles = $user['roles'] ?? [];
        
        echo "Will assign # of roles (".count($roles).") to user: ".($email ?? $cedula)."...\n";

        foreach ($roles as $role)
        {
            DataAccessManager::get("persona")->assignRoleToUser($role, $userFromDB);
            echo "Assigned role: ".$role." to user: ".($email ?? $cedula)."\n";
        }

		return $userFromDB;
	}


	public function createUserWithRoles($userData, $roleIds)
    {
        // Crear el usuario si no existe
        $userId = $this->createUserIfNotExists($userData);

        if (!$userId) {
            return ['success' => false, 'message' => 'Correo Invalido O Duplicado.'];
        }

        // Obtener información del usuario creado
        $user = $this->getOne("id", $userId);
        $email = $user['email'] ?? 'Sin correo';
        
        // Si no hay roles para asignar, solo crear el usuario
        if (empty($roleIds) || (is_array($roleIds) && count($roleIds) === 0)) {
            return [
                'success' => true, 
                'message' => "Usuario creado exitosamente: {$email} (sin roles asignados)"
            ];
        }

        // Asignar roles al usuario
        $flatRoleDataAccess = DataAccessManager::get('role_person_relationships');
        $roleResult = $flatRoleDataAccess->assignRolesToUser($userId, $roleIds);

        if ($roleResult) {
            // Obtener nombres de los roles asignados
            $rolesDataAccess = DataAccessManager::get('roles');
            $assignedRoles = [];
            
            foreach ($roleIds as $roleId) {
                $role = $rolesDataAccess->getOne("id", $roleId);
                if ($role) {
                    $assignedRoles[] = $role['name'];
                }
            }
            
            $rolesText = implode(', ', $assignedRoles);
            
            return [
                'success' => true, 
                'message' => "Usuario creado exitosamente: {$email} - Roles asignados: {$rolesText}"
            ];
        } else {
            return [
                'success' => false, 
                'message' => "Usuario creado: {$email}, pero error al asignar los roles."
            ];
        }
    }

	public function getAllEmailsForUser($user)
	{
		$userId = is_array($user) ? $user['id'] : $user;
		$emails = [];
		
		// Get the primary email from the persona table
		$userRecord = $this->getOne("id", $userId);
		if ($userRecord && !empty($userRecord['email'])) {
			$emails[] = $userRecord['email'];
		}
		
		// Get all email aliases
		if (DAM::get("person_email_aliases")) {
			$aliases = DAM::get("person_email_aliases")->getEmailsForPerson($userId);
			foreach ($aliases as $alias) {
				if (!in_array($alias['email'], $emails)) {
					$emails[] = $alias['email'];
				}
			}
		}
		
		return $emails;
	}

	/**
	 * Add an email alias to a user
	 * 
	 * @param array|int $user The user array or user ID
	 * @param string $email The email to add
	 * @param bool $isPrimary Whether this should be the primary email
	 * @return bool Success status
	 */
	public function addEmailToUser($user, $email, $isPrimary = false)
	{
		$userId = is_array($user) ? $user['id'] : $user;
		
		if (!DAM::get("person_email_aliases")) {
			return false;
		}
		
		// Check if email already exists for another user
		$existingPersonId = DAM::get("person_email_aliases")->findPersonByEmail($email);
		if ($existingPersonId && $existingPersonId != $userId) {
			return false;
		}
		
		// If setting as primary, update the user record
		if ($isPrimary) {
			$userRecord = $this->getOne("id", $userId);
			if ($userRecord) {
				$userRecord['email'] = $email;
				$this->update($userRecord);
			}
		}
		
		// Add to aliases
		DAM::get("person_email_aliases")->addEmailForPerson($userId, $email, $isPrimary);
		
		return true;
	}

	public function displayNameAndEmailUsuarioForId($userId)
    {
        if (empty($userId) || $userId === 'N/A') {
            return 'N/A';
        }
        
        try {
            $personaData = DataAccessManager::get('persona');
            $persona = $personaData->getOne('id', $userId);
            
            if ($persona) {
                $nombreCompleto = trim($persona['nombres'] . ' ' . $persona['apellidos']);
                $email = !empty($persona['email']) ? $persona['email'] : 'Sin email';
                return htmlspecialchars($userId . ' - ' . $nombreCompleto . ' (' . $email . ')');
            } else {
                return htmlspecialchars($userId . ' - Usuario no encontrado');
            }
        } catch (Exception $e) {
            error_log("Error al obtener información de usuario ID {$userId}: " . $e->getMessage());
            return htmlspecialchars($userId . ' - Error al obtener datos');
        }
    }
}



/*

	public static function getCurrentSessionAndAllowRedirectBack($redirectBack = false)
	{
		static $didLookForSession = false;
		static $isAuthenticated   = null;
		static $currentSession    = null;

		if (!$didLookForSession)
		{
			$debug = false;

			$authToken = $_COOKIE['AuthCookie'];
	
			if ($debug) 
			{ 
				error_log("Searching for current user with `authToken`: ".$authToken); 
			}
		
			$currentSession = DataAccessManager::get("session")->getSessionById($authToken);

			if ($currentSession)
			{
				$isAuthenticated = DataAccessManager::get("session")->verifySession($currentSession);

				if (!$isAuthenticated)
				{
					self::logout();
				}
			}

			$didLookForSession = true;
		}

		return $currentSession;
	}


	public static function isAuthenticatedSession()
	{
		$authToken = $_COOKIE['AuthCookie'];

		return self::isAuthenticatedSession($authToken);
	}

	public function isAuthenticatedToken($authToken)
	{
		$debug = false;
	
		if ($debug) 
		{ 
			error_log("Searching for current user with `authToken`: ".$authToken); 
		}
	
		$currentSession = DataAccessManager::get("session")->getSessionById($authToken);

		if ($currentSession)
		{
			$isAuthenticated = DataAccessManager::get("session")->verifySession($currentSession);

			if (!$isAuthenticated)
			{
				self::logout();
			}
		}
	}

*/
