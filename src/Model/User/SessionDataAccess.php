<?php


class SessionDataAccess extends DataAccess 
{
	public $_currentUser		       = null;
	public $didCheckForCurrentUser = false;

	public function currentUserHasPermission($permission)
	{
		$currentUser = $this->getCurrentUser();

		if ($currentUser)
		{
			return DataAccessManager::get("persona")->hasPermission($permission, $currentUser);
		}

		return false;
	}
	public function currentUserHasOneOfPermissions($permissions)
	{
		$currentUser = $this->getCurrentUser();
		
		if ($currentUser)
		{
			return DataAccessManager::get("persona")->hasOneOfPermissions(
				$permissions, 
				$currentUser
			);
		}

		return false;
	}

	public function currentUserHasRoles($roles)
	{
		return $this->currentUserIsInGroups($roles);
	}

	public function currentUserIsInGroups($groups)
	{
		$currentUser = $this->getCurrentUser();

		if ($currentUser)
		{
			return DataAccessManager::get("persona")->isInGroup(
				$currentUser,
				$groups);
		}

		return false;
	}
	public function clearCurrentSession()
	{
		return $this->clearCurrentSessioAndRedirectTo("/auth/login.php");
	}
	public function clearCurrentSessioAndRedirectTo($sendWithRedirect = "/auth/login.php")
	{
		$currentSession = $this->getCurrentApacheSession();

		if ($currentSession)
		{
			$this->cancelSession($currentSession);
		}

		GTKCookie::clearAuthCookie();
		
		header("Cache-Control: no-cache, must-revalidate"); // HTTP 1.1
		header("Pragma: no-cache");                         // HTTP 1.0
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past


		// Notify successful logout and redirect to the home page after 3 seconds
		$message     = 'Has cerrado sesión correctamente. En 3 segundos serás redirigido a la página de inicio.';
		$redirectURL = '/index.php'; // Change this to the URL of your home page
		
		echo "<!DOCTYPE html>
		<html lang='es'>
		<head>
			<meta charset='UTF-8'>
			<meta http-equiv='X-UA-Compatible' content='IE=edge'>
			<meta name='viewport' content='width=device-width, initial-scale=1.0'>
			<title>Cierre de sesión</title>
			<script>
				setTimeout(function() {
					window.location.href = '$redirectURL';
				}, 3000);
			</script>
		</head>
		<body>
			<p>$message</p>
			<p>Si no eres redirigido automáticamente, haz clic <a href='$redirectURL'>aquí</a>.</p>
		</body>
		</html>";
		die(); // Terminate the script execution
	}

	public function isDeveloper()
	{
		if ($this->currentUserHasRoles([
			"DEV",
			"DEVELOPER",
		]))
		{
			return true;
		}

		return false;
	}

	public function getCurrentUser()
	{
		$debug = false;

		static $session     = null;
		static $currentUser = null;

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("`getCurrentUser` - Checking if current user is set: ".print_r($currentUser,true));
		// }

		
		if (!$this->didCheckForCurrentUser)
		{
			$this->didCheckForCurrentUser = true;

			$session = $this->getCurrentApacheSession();

			// Log removido para evitar llenar logs innecesariamente
			// if ($debug)
			// {
			// 	error_log("`getCurrentUser` - Got session: ".print_r($session, true));	
			// }

			if ($session)
			{	
				
				$currentUser = $this->getUserFromSession($session);
			
			// Log removido para evitar llenar logs innecesariamente
			// if ($debug)
			// {
			// 	error_log("`getCurrentUser` - Got current user: ".print_r($currentUser, true));
			// }
			}
			else
			{
				if ($debug)
				{
					error_log("`getCurrentUser` - No session found.");
				}
			}

			
		}

		// Log removido para evitar llenar logs innecesariamente
		// if ($debug)
		// {
		// 	error_log("`getCurrentUser` - Returning current user: ".print_r($currentUser, true));
		// }

		return $currentUser;
	}


	public function getCurrentUserIfAny()
	{
		return $this->getCurrentUser();
	}


	public function getCurrentApacheUserOrSendToLoginWithRedirect($sendWithRedirect = null)
	{
		$debug = false;

		static $didSearchForSession = false;
		static $session 			= null;

		if (!$didSearchForSession)
		{
			$session = $this->getCurrentApacheSession();

			if ($debug)
			{
				error_log("Did search for session - got: ".print_r($session, true));
			}
		}

		if ($session)
		{
			if ($this->verifySession($session))
			{
				$user = $this->getUserFromSession($session);

				if ($debug)
				{
					error_log("`getUserFromSession` - got user: ".print_r($user, true));

				}

				return $user;
			}
		}

		if ($sendWithRedirect)
		{
			redirectToURL("/auth/login.php", null, [
				"redirectTo" => $sendWithRedirect,
			]);
		}
		
		return null;
	}

	public function getUserFromSession($session)
	{
		$debug = false;

		$user_id = $this->valueForKey("user_id", $session);
		$cacheTTL = 300; // 5 minutos de TTL
		
		// ============================================
		// NIVEL 1: APCu Cache (Ultra rápido - 0.01-0.1ms)
		// ============================================
		$apcu = APCuCacheManager::getInstance();
		if ($apcu->isEnabled())
		{
			$user = $apcu->get("user_data_{$user_id}", $success);
			
			if ($success)
			{
				// Log removido para evitar llenar logs innecesariamente
				// if ($debug)
				// {
				// 	error_log("✓ Returning user from APCu cache (ultra fast)");
				// }
				return $user;
			}
		}

		// ============================================
		// NIVEL 2: Session Cache (Rápido - 1-5ms)
		// ============================================
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		$sessionKey = "user_cache_{$user_id}";
		
		if (isset($_SESSION[$sessionKey]) && isset($_SESSION[$sessionKey]['timestamp']))
		{
			$cacheAge = time() - $_SESSION[$sessionKey]['timestamp'];
			
			if ($cacheAge < $cacheTTL)
			{
				$user = $_SESSION[$sessionKey]['user'];
				
				// Subir a APCu para próxima vez
				if ($apcu->isEnabled())
				{
					$apcu->set("user_data_{$user_id}", $user, $cacheTTL - $cacheAge);
				}
				
				// Log removido para evitar llenar logs innecesariamente
				// if ($debug)
				// {
				// 	error_log("✓ Returning user from session cache (age: {$cacheAge}s, promoted to APCu)");
				// }
				
				return $user;
			}
			else
			{
			// Log removido para evitar llenar logs innecesariamente
			// if ($debug)
			// {
			// 	error_log("Session cache expired (age: {$cacheAge}s), refreshing...");
			// }
			}
		}

		// ============================================
		// NIVEL 3: Database (Lento - 500-1000ms)
		// ============================================
		$user = DataAccessManager::get("persona")->getOne("id", $user_id);

		if ($user)
		{
			// Pre-cargar todos los datos necesarios para cachear
			$this->preloadUserData($user);

			// Guardar en ambos cachés
			// 1. APCu (compartido, ultra rápido)
			if ($apcu->isEnabled())
			{
				$apcu->set("user_data_{$user_id}", $user, $cacheTTL);
			}

			// 2. Sesión (fallback)
			$_SESSION[$sessionKey] = [
				'user' => $user,
				'timestamp' => time()
			];

			// Log removido para evitar llenar logs innecesariamente
			// if ($debug)
			// {
			// 	error_log("✓ User loaded from database, cached in APCu + Session");
			// }
		}

		return $user;
	}

	/**
	 * Pre-carga todos los datos del usuario para minimizar consultas posteriores
	 */
	private function preloadUserData(&$user)
	{
		$debug = false;

		if (!$user || !is_array($user))
		{
			return;
		}

		if ($debug)
		{
			error_log("Pre-loading user data for caching...");
		}

		// Pre-cargar role relations
		DataAccessManager::get("role_person_relationships")->roleRelationsForUser($user);
		
		// Pre-cargar roles (esto también usará el caché de role_relations)
		DataAccessManager::get("role_person_relationships")->rolesForUser($user);
		
		// Pre-cargar role names para isInGroups
		DataAccessManager::get("persona")->isInGroups($user, []); // Esto inicializa el caché de nombres
		
		// Pre-cargar permisos
		DataAccessManager::get("persona")->permissionsForUser($user);

		if ($debug)
		{
			error_log("User data pre-loaded successfully");
		}
	}

	/**
	 * Invalida el caché del usuario en TODOS los niveles
	 * Llamar este método cuando se modifiquen roles, permisos o datos del usuario
	 * 
	 * @param int|null $user_id ID del usuario (null = usuario actual)
	 */
	public function invalidateUserCache($user_id = null)
	{
		if ($user_id === null)
		{
			$currentUser = $this->getCurrentUser();
			if ($currentUser)
			{
				$user_id = DataAccessManager::get("persona")->valueForKey("id", $currentUser);
			}
		}

		if ($user_id)
		{
			// Invalidar en APCu
			$apcu = APCuCacheManager::getInstance();
			if ($apcu->isEnabled())
			{
				$apcu->delete("user_data_{$user_id}");
			}

			// Invalidar en sesión
			if (session_status() === PHP_SESSION_NONE) {
				session_start();
			}
			
			$cacheKey = "user_cache_{$user_id}";
			unset($_SESSION[$cacheKey]);
		}
	}

	/**
	 * Limpia todos los cachés de usuarios en TODOS los niveles
	 * Útil para liberar memoria o forzar recarga completa
	 */
	public function clearAllUserCaches()
	{
		// Limpiar APCu
		$apcu = APCuCacheManager::getInstance();
		if ($apcu->isEnabled())
		{
			$apcu->deletePattern('user_data_.*');
		}

		// Limpiar sesión
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		foreach ($_SESSION as $key => $value)
		{
			if (strpos($key, 'user_cache_') === 0)
			{
				unset($_SESSION[$key]);
			}
		}
	}

	/**
	 * Obtiene información del caché actual del usuario en TODOS los niveles
	 * Útil para debugging y monitoreo
	 * 
	 * @return array Array con info del caché de todos los niveles
	 */
	public function getUserCacheInfo($user_id = null)
	{
		if ($user_id === null)
		{
			$currentUser = $this->getCurrentUser();
			if ($currentUser)
			{
				$user_id = DataAccessManager::get("persona")->valueForKey("id", $currentUser);
			}
		}

		$info = [
			'user_id' => $user_id,
			'apcu' => ['exists' => false, 'enabled' => false],
			'session' => ['exists' => false],
			'cache_keys' => []
		];

		if (!$user_id)
		{
			return $info;
		}

		// Info de APCu
		$apcu = APCuCacheManager::getInstance();
		$info['apcu']['enabled'] = $apcu->isEnabled();
		
		if ($apcu->isEnabled())
		{
			$info['apcu']['exists'] = $apcu->exists("user_data_{$user_id}");
			if ($info['apcu']['exists'])
			{
				$user = $apcu->get("user_data_{$user_id}", $success);
				if ($success && isset($user['gtk_cache']))
				{
					$info['cache_keys'] = array_keys($user['gtk_cache']);
				}
			}
		}

		// Info de sesión
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		$cacheKey = "user_cache_{$user_id}";
		if (isset($_SESSION[$cacheKey]))
		{
			$cacheAge = time() - $_SESSION[$cacheKey]['timestamp'];
			$info['session'] = [
				'exists' => true,
				'age_seconds' => $cacheAge,
				'created_at' => date('Y-m-d H:i:s', $_SESSION[$cacheKey]['timestamp']),
				'has_gtk_cache' => isset($_SESSION[$cacheKey]['user']['gtk_cache'])
			];
			
			if (empty($info['cache_keys']) && isset($_SESSION[$cacheKey]['user']['gtk_cache']))
			{
				$info['cache_keys'] = array_keys($_SESSION[$cacheKey]['user']['gtk_cache']);
			}
		}

		// Info general de APCu
		if ($apcu->isEnabled())
		{
			$info['apcu_stats'] = $apcu->getStats();
		}

		return $info;
	}

	/**
	 * Obtiene estadísticas globales del sistema de caché
	 * 
	 * @return array Estadísticas completas
	 */
	public function getCacheStats()
	{
		$apcu = APCuCacheManager::getInstance();
		
		return [
			'apcu_enabled' => $apcu->isEnabled(),
			'apcu_stats' => $apcu->isEnabled() ? $apcu->getStats() : null,
			'session_active' => session_status() === PHP_SESSION_ACTIVE,
			'session_id' => session_id()
		];
	}
 
	public function getUserWithApiKey($apiKey)
	{	
		global $_GLOBALS;

		$email = $_GLOBALS["API_KEY_ARRAY"][$apiKey];

		static $cache = [];
		if (array_key_exists($email, $cache))
		{
			return $cache[$email];
		}

		$user = DataAccessManager::get('persona')->getOne("email", $email);
		
		$cache[$email] = $user;

		return $user;
	}

	public function processAPIKey($apiKey)
	{
		global $_GLOBALS;
		$accessKeyArray = $_GLOBALS["API_KEY_ARRAY"];
	
		if (!$accessKeyArray) {
			error_log("API Key array not found.");
			return null;
		}

		if ($debug)
		{
			error_log("API Key: ".$apiKey);
		}
		
		if (array_key_exists($apiKey,$accessKeyArray))
		{
			$defaultSessionLength = 60 * 60 * 24 * 30;
			$email = $accessKeyArray[$apiKey];
			$user =  DataAccessManager::get('persona')->getOne("email", $email);

			if ($debug)
			{
				error_log("API Key found: ".$apiKey);
				// Log removido para evitar llenar logs innecesariamente
			// error_log("User found: ".print_r($user, true));
			}

			$session = [];
			$session['user']        = $user;
			$session['user_id']     = DAM::get("persona")->identifierForItem($user);
			$session['apikey']      = true;
			$session['valid_until'] = time() + $defaultSessionLength;
			return $session;
		}
		else
		{
			error_log("API Key not found: ".$apiKey);
			return null;
		}
	}

	public function processAuthCookie($authToken, $options)
	{
		$debug = false;

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - Got auth token: ".$authToken);
		}

		$session = DataAccessManager::get("session")->getSessionById($authToken);

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - Got session: ".$session);
		}

		$isValid = $this->verifySession($session);

		if ($debug)
		{
			error_log("Got `isValid`: ".$isValid);
		}

		if (!$isValid)
		{
			if (isTruthy($options["redirectIfInvalid"]))
			{
				redirectToURL("/auth/login.php", null, [
					"redirectTo" => $options["redirectTo"] ?? null,
				]);
				return null;
			}

			if (isTruthy($options["requireValid"]))
			{
				return null;
			}
		}

		if ($session)
		{
			$session["user"] = $this->getUserFromSession($session);
		}
		
		return $session;
	}


	public function getCurrentApacheSession($options = [
		"requireValid"	    => true,
		"redirectIfInvalid" => false,
		"redirectTo"        => null,
	])
	{
		$debug = false;

		global $_SERVER;

		if ($debug)
		{
			$headers = getallheaders();
			error_log("Headers: ".print_r($headers, true));
			// error_log("Server: ".print_r($_SERVER, true));
		}

		$httpTokenKey = "STONEWOOD_AUTH_TOKEN";

		$apiKey = null;

		global $_COOKIE;
		
		if (isset($_SERVER[$httpTokenKey])) 
		{
    		$apiKey = $_SERVER[$httpTokenKey];
		}
		else if (isset($_GET[$httpTokenKey]))
		{
    		$apiKey = $_GET[$httpTokenKey];
		}

		if ($apiKey)
		{
			return $this->processAPIKey($apiKey);
		}
		elseif (isset($_COOKIE['AuthCookie']))
		{
			$authToken = $_COOKIE['AuthCookie'];

			return $this->processAuthCookie($authToken, $options);
		}

		if ($debug)
		{
			error_log("`getCurrentApacheSession` - No auth token or http header for: ".$httpTokenKey);
		}

		return null;

	}


	public function register()
	{	
		$columnMappings = [
			new GTKColumnMapping($this, "id",		 	    [ 
				"formLabel"    => "ID", 
				"isPrimaryKey" =>true, 
				"isUnique"     => true,
				"isAutoIncrement" => true,
				"type"         => "INTEGER",
			]),
			new GTKColumnMapping($this, "session_guid"),
			new GTKColumnMapping($this, "user_id",			[ "formLabel" => "User ID"]),
			new GTKColumnMapping($this, "created_at",	    [ "formLabel" => "Created At"]),
			new GTKColumnMapping($this, "valid_until",   	[ "formLabel" => "Valid Until"]),
			new GTKColumnMapping($this, "canceled",		[ "formLabel" => "Canceled"]),
			new GTKColumnMapping($this, "client_ip",		[
				"formLabel"  => "IP",
				"columnSize" => 45,
			]),
			new GTKColumnMapping($this, "device",			[
				"formLabel"  => "Dispositivo",
				"columnSize" => 64,
			]),
			new GTKColumnMapping($this, "browser",		[
				"formLabel"  => "Navegador",
				"columnSize" => 512,
			]),
		];

		$this->dataMapping = new GTKDataSetMapping($this, $columnMappings);

		/*
		$this->getDB()->query("CREATE TABLE IF NOT EXISTS {$this->tableName()} 
		  (id, 
		   user_id,
		   created_at,
		   valid_until,
		   canceled,
		   UNIQUE (id))");
		*/

		$this->defaultOrderByColumnKey = "created_at";
		$this->defaultOrderByOrder  = "DESC";
	}

	public function resolveClientIp(array $server = null)
	{
		$server = $server ?? $_SERVER;

		if (!empty($server["HTTP_CLIENT_IP"]))
		{
			return trim($server["HTTP_CLIENT_IP"]);
		}

		if (!empty($server["HTTP_X_FORWARDED_FOR"]))
		{
			$ips = explode(",", $server["HTTP_X_FORWARDED_FOR"]);

			return trim($ips[0]);
		}

		return $server["REMOTE_ADDR"] ?? null;
	}

	public function resolveDeviceLabel($userAgent)
	{
		$userAgent = strtolower((string) $userAgent);

		if ($userAgent === "")
		{
			return "Desconocido";
		}

		if (preg_match("/ipad|tablet|kindle|playbook/", $userAgent))
		{
			return "Tablet";
		}

		if (preg_match("/mobile|android|iphone|ipod|phone|blackberry|windows phone/", $userAgent))
		{
			return "Móvil";
		}

		return "Escritorio";
	}

	public function resolveBrowserLabel($userAgent)
	{
		$userAgent = (string) $userAgent;

		if ($userAgent === "")
		{
			return "Desconocido";
		}

		$patterns = [
			"/Edg\/([0-9\.]+)/"        => "Edge",
			"/OPR\/([0-9\.]+)/"        => "Opera",
			"/Chrome\/([0-9\.]+)/"     => "Chrome",
			"/Firefox\/([0-9\.]+)/"   => "Firefox",
			"/Version\/([0-9\.]+).*Safari/" => "Safari",
			"/MSIE ([0-9\.]+)/"        => "Internet Explorer",
			"/Trident\/.*rv:([0-9\.]+)/" => "Internet Explorer",
		];

		foreach ($patterns as $pattern => $browserName)
		{
			if (preg_match($pattern, $userAgent, $matches))
			{
				return $browserName." ".$matches[1];
			}
		}

		return substr($userAgent, 0, 512);
	}

	public function resolveLoginClientInfo(array $server = null)
	{
		$server = $server ?? $_SERVER;
		$userAgent = $server["HTTP_USER_AGENT"] ?? "";

		return [
			"client_ip" => $this->resolveClientIp($server),
			"device"    => $this->resolveDeviceLabel($userAgent),
			"browser"   => $this->resolveBrowserLabel($userAgent),
		];
	}
	
	function guidv4($data = null) {
		// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
		$data = $data ?? random_bytes(16);
		assert(strlen($data) == 16);
	
		// Set version to 0100
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		// Set bits 6-7 to 10
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
	
		// Output the 36 character UUID.
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	
	public function newSessionForUser($user)
	{
		$clientInfo = $this->resolveLoginClientInfo();

		$query = "INSERT INTO {$this->tableName()} 
			(session_guid, user_id, created_at, valid_until, canceled, client_ip, device, browser)
			VALUES
			(:session_guid, :user_id, :created_at, :valid_until, :canceled, :client_ip, :device, :browser)";
			
		$statement = $this->getDB()->prepare($query);
		
		$session_value = uniqid();

		$defaultSessionLength = 60 * 60 * 24 * 30; // 30 days

		$statement->bindValue(':session_guid', $session_value);
		$statement->bindValue(':user_id',     			DataAccessManager::get("persona")->valueForIdentifier($user));
		$statement->bindValue(':created_at', 		 	date(DATE_ATOM));
		$statement->bindValue(':valid_until', 			time() + $defaultSessionLength);
		$statement->bindValue(':canceled', 	  			0);
		$statement->bindValue(':client_ip', $clientInfo["client_ip"]);
		$statement->bindValue(':device',    $clientInfo["device"]);
		$statement->bindValue(':browser',   $clientInfo["browser"]);
		
		// Execute the INSERT statement
		$result = $statement->execute();
		
		if ($result) 
		{
			GTKCookie::setAuthCookie($session_value);

			return $session_value;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			echo 'INSERT FAILED';
			return 0;
		}
	}

	
	
	public function getSessionById($id) {
		$debug = false;
		
		$query = "SELECT * FROM {$this->tableName()} WHERE session_guid = :session_guid";
		
		if ($debug)
		{
			error_log("Running query...: ".$query);
		}
		
		$statement = $this->getDB()->prepare($query);
		
		
		$statement->bindValue(':session_guid', $id);
				
		$statement->execute();
		
        // Fetch the result
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ($debug)
		{
			$toPrint = serialize($result);
			echo "Result: {$toPrint}<br/>";
		}

		if (count($result) > 1)
		{
			error_log("More than one session found for id: {$id}");
			die("Error. Favor avisar administador.");
		}
		else if (count($result) === 0)
		{
			return null;
		}


		$session = $result[0];

		$session["isValid"] = $this->verifySession($session);

		return $session;
	}

	public function isValid($session)
	{
		return $this->verifySession($session);
	}
	
	public function verifySession($session)
	{
		$debug = false;

		if (!$session)
		{
			return false;
		}

		if ($debug) { error_log("Verifying session: ".serialize($session)); }

		$validUntil = $session["valid_until"];

		if (!$validUntil)
		{
			return false;
		}

		if (time() > $validUntil)
		{
			if ($debug) 
			{ 
				error_log("Time: ".time()." > valid_until: ".$session["valid_until"]); 
			}
			return false;
		}
		
		if (isTruthy($session['canceled']))
		{
			return false;
		}

		return true;
	}

	public function cancelSession($session)
	{
		$sql = "UPDATE {$this->tableName()} SET canceled = 1 WHERE session_guid = :session_guid";

		$statement = $this->getDB()->prepare($sql);

		$statement->bindValue(':session_guid', $session["session_guid"]);

		$result = $statement->execute();

		if ($result) 
		{
			return true;
		} 
		else 
		{
			// INSERT failed
			// Handle the error
			// echo 'INSERT FAILED';
			return false;
		}
	}

	public static function routeToPage(
		$requestPath, 
		$get, 
		$post, 
		$server, 
		$cookie, 
		$session, 
		$files, 
		$env
	){
		$user = DataAccessManager::get("session")->getCurrentUser();

		// die(print_r($user, true));

		$isLoginPath = in_array($requestPath, [
			"/auth/login.php", 
			"/auth/login",
			"/login",
			"/login.php",
		]);

		$isLogoutPath = in_array($requestPath, [
			"/auth/logout.php",
			"/auth/logout",
			"/logout",
			"/logout.php",
		]);

		if ($isLoginPath)
		{
			// die("Is Login path");
			if ($user)
			{
				//die("User is logged in");
				header("Location: /");
				exit();
			}
			else
			{
				global $_GTK_SUPER_GLOBALS;
				$loginPage = new GTKDefaultLoginPageDelegate();
				echo $loginPage->render(...$_GTK_SUPER_GLOBALS);
				return;
			}
		}


		if ($isLogoutPath)
		{
			if ($user)
			{
				DataAccessManager::get("session")->clearCurrentSession();
				die("Logged out");
			}
			else
			{
				header("Location: /auth/login.php");
				exit();
			}
		}

		$cleanPath = substr($requestPath, 1);

		$dataAccessManager = DataAccessManager::getSingleton();

		$toRender = $dataAccessManager->toRenderForPath($cleanPath, DataAccessManager::get("session")->getCurrentUser());

		if ($toRender)
		{
		  renderPage($toRender);
		}
		else
		{
		  echo "<h1>404 - Not Found - ".$cleanPath."</h1>";
		}
	}
}
