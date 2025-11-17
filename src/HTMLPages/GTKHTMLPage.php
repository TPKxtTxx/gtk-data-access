<?php

use function Deployer\desc;

class GTKHTMLFragment
{
	public function render()
	{
		return "";
	}
}


class GTKMenuPrepObject
{
    public $menuString      = "";
    public $hiddenBoxString = "";
	public $user;
}

class GTKMenuItemPair 
{
    public $menuText;
    public $boxId;
    public $boxContent;
    public $accessRequirements;

    function sanitizeForHtmlId($input) 
    {
        // Ensure the ID starts with a letter or underscore
        if (!preg_match('/^[a-zA-Z_]/', $input)) {
            $input = '_' . $input;
        }
        
        // Replace invalid characters with underscores
        $output = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $input);
        
        return $output;
    }
    

    public function __construct($menuText, $boxContent, $accessRequirements = null) {
        $this->menuText           = $menuText;
        $this->boxId              = 'nav_menu_box_'.$this->sanitizeForHtmlId($menuText);
        $this->boxContent         = $boxContent;
        $this->accessRequirements = $accessRequirements;

		// die("GTKMenuItemPair: ".print_r($this, true));
    }

    public function prepareHeaderItemsForUser($user, &$menuString, &$hiddenBoxString) {
        if ($this->hasPermission($user)) 
        {
            $menuString      .= $this->menuString();
            $hiddenBoxString .= $this->hiddenBoxString();
        }
    }

	public function prepareHeaderItemsForGTKMenuPrepObject($menuPrepObject)
	{
		$debug = true;

		if ($debug)
		{
			error_log("Preparing header items for GTKMenuPrepObject: ".print_r($menuPrepObject, true));
		}

		if ($this->hasPermission($menuPrepObject->user))
		{
			if ($debug)
			{
				error_log("Adding menu item: ".$this->menuString());
			}
			$menuPrepObject->menuString .= $this->menuString();
			$menuPrepObject->hiddenBoxString .= $this->hiddenBoxString();
		}
		else
		{
			if ($debug)
			{
				error_log("Not authorized, will not add menu item: ".$this->menuText);
			}
		}
	}
	public function menuString()
	{
		return "<li><a onclick=\"showContent('{$this->boxId}')\">{$this->menuText}</a></li>\n";
	}

	public function hiddenBoxString()
	{
		return "<div id=\"{$this->boxId}\" class=\"box\">\n{$this->boxContent}\n</div>\n";
	}

    public function hasPermission($user) 
    {
		$debug = true;

		if ($debug)
		{
			error_log("Checking access for user: ".print_r($user, true));
			error_log("Access requirements: ".print_r($this->accessRequirements, true));
		}

		$noSpecificPermission = empty($this->accessRequirements['permissions']);
		$noSpecificRole       = empty($this->accessRequirements['roles']);

		if ($noSpecificPermission && $noSpecificRole)
		{
			if ($debug)
			{
				error_log("No specific permission or role, will return true");
			}
			return true;
		}


        $hasRequiredRole = false;
		
		if (!$noSpecificRole)
		{
			if ($debug)
			{
				error_log("Checking access for user: ".print_r($user, true));
			}
			$hasRequiredRole = DAM::get("persona")->isInGroup(
				$user,
				$this->accessRequirements['roles']);

			if ($hasRequiredRole)	
			{
				if ($debug)
				{
					error_log("Has required role: ".print_r($hasRequiredRole, true));
				}
				return true;
			}
		}
        
        $hasRequiredPermission = false;

		if (!$hasRequiredRole)
		{
			if (!$noSpecificPermission)
			{
				foreach ($this->accessRequirements['permissions'] as $permission)
				{
					if (DAM::get("persona")->hasPermission($permission, $user))
					{
						if ($debug)
						{
							error_log("Has permission: ".$permission);
						}
						return true;
					}
				}
			}
		}
        
        return $hasRequiredRole || $hasRequiredPermission;
    }
}

class GTKHTMLPage
{
	public $get; 
	public $post; 
	public $server;
	public $cookie;
	public $phpSession;
	public $files;
	public $env;

	public $allowsCache = false;
	
	public $didSearchForCurrentUser;
	public $didSearchForCurrentSession;

	//----------------------------------------------------------
	public $messages        = [];
	public $user;
	public $session;

	public $header;
	public $footer;

	public $menuString;
	public $hiddenBoxString;

	public bool    $authenticationRequired = false; // ovveride in construct
	public ?string $permissionRequired 	   = null;  // override in construct

	/**
	 * Array to store stylesheets
	 */
	protected $stylesheets = [];
	
	/**
	 * Array to store scripts
	 */
	protected $scripts = [];

	public function __construct($options = [])
	{
		// $pageOptions = OAM::get(get_class($this));
	}

	public function addMenuItem($menuText, $boxId)
	{
		$this->menuString      .= $menuText;
		$this->hiddenBoxString .= $boxId;
	}

	public function setAuthenticate($toSet)
	{
		$this->authenticate = $toSet;
	}

	public function setAuthorize($toSet)
	{
		$this->authorize = $toSet;
	}

	public function currentPath()
	{
	    if (isset($_SERVER["PATH_INFO"])) 
		{
			return $_SERVER["PATH_INFO"];
		} 
		elseif (isset($_SERVER["REQUEST_URI"])) 
		{
			return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
		}
		return null; // Or throw an exception if preferred
	}

	public function currentURL()
	{
		$protocol = $this->server["HTTPS"] ? "https" : "http";
		$host = $this->server["HTTP_HOST"];
		$path = $this->currentPath();
		return $protocol."://".$host.$path;
	}

	public function getClientIP() 
	{
		$ipAddress = null;
		
		if (!empty($this->server['HTTP_CLIENT_IP'])) 
		{
			$ipAddress = $this->server['HTTP_CLIENT_IP'];
		} 
		elseif (!empty($this->server['HTTP_X_FORWARDED_FOR'])) 
		{
			$ipAddress = $this->server['HTTP_X_FORWARDED_FOR'];
		} 
		else 
		{
			$ipAddress = $this->server['REMOTE_ADDR'];
		}
		
		return $ipAddress;
	}

	public function getCurrentUser()
	{
		return $this->currentUser();
	}

	public function getUserID()
	{
		$user = $this->getCurrentUser();

		if (!$user)
		{
			return null;
		}

		return DAM::get("persona")->valueForIdentifier($user);
	}
	
	



	//--- Process GET, POST, PUT, PATCH, renderBODY - Overidable Methods
	//----------------------------------------------------------
	//----------------------------------------------------------

	public function processGet($getObject)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process GET Basic: ".print_r($getObject, true));
		}
		// Does nothing..

		
	}

	public function processPost()
	{
		$debug = false;

		if ($debug)
		{
			error_log("Process POST Basic: ".print_r($this->post, true));
		}
		// Does nothing..
	}


	public function gtk_renderBody()
	{
		$toReturn = $this->renderMessages();

		if (method_exists($this, "renderBody"))
		{
			$toReturn .= $this->renderBody();
		}
		else
		{
			$toReturn .= "<h1 class='font-bold'>";
			$toReturn .= "No `renderBody` method found";
			$toReturn .= "</h1>";
		}
		
		return $toReturn;
	}
	
	public function renderMessages()
	{
		$toReturn = "";

		if (count($this->messages) > 0)
		{
			$toReturn .= "<h1 class='font-bold'>";
			$toReturn .= "Mensajes";
			$toReturn .= "</h1>";
			$toReturn .= "<div>";
			foreach ($this->messages as $message)
			{
				$toReturn .= "<div>";
				if (is_string($message))
				{
					$toReturn .= $message;
				}
				else
				{
					$toReturn .= print_r($message, true);
				}
				$toReturn .= "</div>";
			}
			$toReturn .= "</div>";
		}

		return $toReturn;
	}

	/**
	 * Add a message to be displayed on the page
	 * @param string $type The type of message (error, success, warning, info)
	 * @param string $text The message text
	 */
	public function addMessage($type, $text)
	{
		$this->messages[] = [
			'type' => $type,
			'text' => $text
		];
	}

	public function processPUT(){}
	public function processPatch(){}


	//--- Header, Footer methods
	//---------------------------------------------------------
	//---------------------------------------------------------

	public function includeOrRenderPathOrIncludeDefault($toIncludeOrRender, $theDefaultPath)
	{
		/*
		$toDieWith = "To include or render: ".print_r($toIncludeOrRender, true);
		$toDieWith .= " The default path: ".print_r($theDefaultPath, true);
		die($toDieWith);
		*/
		if ($toIncludeOrRender)
		{
			if (is_callable($toIncludeOrRender))
			{
				return $toIncludeOrRender();
			}

			$classExists = class_exists($toIncludeOrRender);

			if ($classExists && method_exists($toIncludeOrRender, "renderForUser"))
			{
				$instance = new $toIncludeOrRender();
				return $instance->renderForUser($this->currentUser());
			}
			else if ($classExists && method_exists($toIncludeOrRender, "render"))
			{
				$instance = new $toIncludeOrRender();
				return $instance->render();
			}
		}

		if ($theDefaultPath)
		{
			$classExists = class_exists($theDefaultPath);

			if ($classExists && method_exists($theDefaultPath, "renderForUser"))
			{
				$instance = new $theDefaultPath();
				return $instance->renderForUser($this->currentUser());
			}
			else if ($classExists && method_exists($theDefaultPath, "render"))
			{
				$instance = new $theDefaultPath();
				return $instance->render();
			}
		}

		return "";
	}

	public function renderItemAttribute($attribute)
	{
		if (is_string($attribute))
		{
			return $attribute;
		}
		else if (is_callable($attribute))
		{
			return $attribute($this);
		}
	}

	
	public function gtk_renderHeader()
	{
		$contentType = $this->server["CONTENT_TYPE"] ?? "text/html";

		global $_GLOBALS;

		$defaultHeader = null;

		if (isset($_GLOBALS["DEFAULT_HEADER"]))
		{
			$defaultHeader = $_GLOBALS["DEFAULT_HEADER"];
		}
		else
		{
			$defaultHeader = null;
		}

		// die("Header: ".$this->header . " Default: ".$defaultHeader);

		switch($contentType)
		{
			case "application/json":
				return "";
			case "application/xml":
				return "";
			case "application/x-www-form-urlencoded":
			case "multipart/form-data":
			case "text/plain":
			case "text/html":
			default:
				return $this->includeOrRenderPathOrIncludeDefault($this->header, $defaultHeader);
		}
	}

	public function gtk_renderFooter()
	{
		$contentType = $this->server["CONTENT_TYPE"] ?? "text/html";
		global $_GLOBALS;

		$defaultFooter = null;

		if (isset($_GLOBALS["DEFAULT_FOOTER"]))
		{
			$defaultFooter = $_GLOBALS["DEFAULT_FOOTER"];
		}
		else
		{
			$defaultFooter = null;
		}

		switch($contentType)
		{
			case "application/json":
				return "";
			case "application/xml":
				return "";
			case "application/x-www-form-urlencoded":
			case "multipart/form-data":
			case "text/plain":
			case "text/html":
			default:
				return $this->includeOrRenderPathOrIncludeDefault($this->footer, $defaultFooter);
		}
	}

	public function handleNotAuthenticated()
	{
		if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') 
		{
			return json_encode([
				"error" => "Not authenticated",
			]);
		}
		else
		{
			redirectToURL("/auth/login.php", null, []);
		}
	}

	public function handleNotAuthorized()
	{
		die("No autorizado.");
	}

	public function currentSession()
	{
		if (!$this->session)
		{
			if (!$this->didSearchForCurrentSession)
			{
				$this->didSearchForCurrentSession = true;

				$this->session = DataAccessManager::get("session")->getCurrentApacheSession([
					"requireValid" => true,
				]);
			}
		}

		return $this->session;
	}

	public function currentUser()
	{
		if ($this instanceof GTKDefaultLoginPageDelegate)
		{
			// die("GTKDefaultLoginPageDelegate");
			return null;
		}

		if (!$this->user)
		{
			if (!$this->didSearchForCurrentUser)
			{
				$this->didSearchForCurrentUser = true;
			
				$this->user = DataAccessManager::get("session")->getCurrentUser();
				
			}
		}

		return $this->user;
	}

	public function dataSourceFor($name)
	{
		return DataAccessManager::get($name);
	}

	public function getDataSource($name)
	{
		return DataAccessManager::get($name);
	}

	public function isAuthorized()
	{
		$debug = false;

		$isAuthorized = true;

		if ($this->permissionRequired == "public")
		{
			return true;
		}

		if ($this->permissionRequired)
		{
			if (!$this->currentUser())
			{
				if ($debug)
				{
					error_log("No current user, will redirect...");
				}
				return $this->handleNotAuthenticated();
			}

			$userDataAccess = DataAccessManager::get("persona");

			$currentUser = $this->currentUser();

			$isAuthorized = $userDataAccess->hasPermission($this->permissionRequired, $currentUser);
		}

		if ($debug)
		{
			$message = "`render` for ".get_class($this)." : Is authorized: ".print_r($isAuthorized, true)." for permission: ".$this->permissionRequired;

			error_log($message);
		}

		return $isAuthorized;
	}

	public function setCustomLogFile()
	{
		// DO NOTHING
	}

	public function hiddenInput($name, $value)
    {
        return "<input type='hidden' name='".$name."' value='".$value."'>";
    }


	public function render($get, $post, $server, $cookie, $session, $files, $env)
	{
		$debug = false;

		$html = null;
		
		// Debug: verificar template antes de renderizar (activado temporalmente)
		$debugRender = true;
		if ($debugRender) {
			error_log("DEBUG render - template antes de render: " . ($this->template ?? 'NULL'));
			error_log("DEBUG render - class: " . get_class($this));
		}

		$this->setCustomLogFile();

		$this->get 		  = $get;
		$this->post 	  = $post;
		$this->server 	  = $server;
		$this->cookie 	  = $cookie;
		$this->phpSession = $session;
		$this->files      = $files;
		$this->env 		  = $env;

		if (!$this->allowsCache)
		{
			
			session_start();
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
			
		}
		
		if ($debug)
		{
			error_log("`render` : Got current user: ".print_r($this->currentUser(), true));
			
			
			error_log("`render` : Got current session: ".print_r($this->currentSession(), true));
		}
		

		if ($this->authenticationRequired && !$this->currentUser())
		{
			if ($debug)
			{
				error_log("Authentication required, No user, no session, will redirect...");
			}
			return $this->handleNotAuthenticated();
		}

		if (!$this->isAuthorized())
		{
			return $this->handleNotAuthorized();
		}

		// die("Will process get");

		$this->processGet($get);

		switch ($this->server["REQUEST_METHOD"])
		{
			case "POST":
				$toReturn = $this->processPost($post, $files);
				if ($toReturn)
				{
					return $toReturn;
				}
				break;
			case "PATCH":
				$this->processPatch($post);
				break;
			case "PUT":
				$this->processPut($post);
				break;
		}

		if ($this->shouldRespondWithJSON())
		{
			if (method_exists($this, "renderJSON"))
			{
				return $this->renderJSON();
			}
		}

		// Add stylesheets
		$html .= $this->renderStylesheets();
		
		$html .= "<body>\n";
		
		// Add header, body, footer content
		// die("Will render header");
		$html .= $this->gtk_renderHeader();
		// die("Will render body");
		$html .= $this->gtk_render();
		// die("Will render footer");
		$html .= $this->gtk_renderFooter();
		
		// Add scripts at the end of body
		$html .= $this->renderScripts();
		
		$html .= "</body>\n</html>";
		
		return $html;
	}

	public function shouldRespondWithJSON()
	{
		return isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json';
	}

	function htmlEscape($string)
	{
    	if ($string)
    	{
        	return htmlspecialchars($string);
    	}
	}

	/**
	 * Adds a stylesheet to the page
	 * 
	 * @param string $url URL of the stylesheet to add
	 * @return void
	 */
	public function addStylesheet($url)
	{
		$this->stylesheets[] = $url;
	}

	/**
	 * Adds a script to the page
	 * 
	 * @param string $url URL of the script to add
	 * @return void
	 */
	public function addScript($url)
	{
		$this->scripts[] = $url;
	}

	/**
	 * Renders the stylesheets in the head section
	 * 
	 * @return string HTML for the stylesheets
	 */
	protected function renderStylesheets()
	{
		$html = '';
		foreach ($this->stylesheets as $stylesheet) {
			// Agregar cache busting si es un archivo local (no CDN)
			$url = $stylesheet;
			if (strpos($stylesheet, 'http') !== 0 && strpos($stylesheet, '//') !== 0) {
				// Es un archivo local, agregar timestamp para evitar cache
				$separator = strpos($stylesheet, '?') !== false ? '&' : '?';
				$url = $stylesheet . $separator . 'v=' . time();
			}
			$html .= '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . PHP_EOL;
		}
		return $html;
	}

	/**
	 * Renders the scripts at the end of the body
	 * 
	 * @return string HTML for the scripts
	 */
	protected function renderScripts()
	{
		$html = '';
		foreach ($this->scripts as $script) {
			// Agregar cache busting si es un archivo local (no CDN)
			$url = $script;
			if (strpos($script, 'http') !== 0 && strpos($script, '//') !== 0) {
				// Es un archivo local, agregar timestamp para evitar cache
				$separator = strpos($script, '?') !== false ? '&' : '?';
				$url = $script . $separator . 'v=' . time();
			}
			$html .= '<script src="' . htmlspecialchars($url) . '"></script>' . PHP_EOL;
		}
		return $html;
	}
// ==========================================================
// 🌿 Twig Template Engine Support (Opcional y Retrocompatible)
// ==========================================================

protected ?string $template = null;        // Ruta relativa del template Twig
protected array $templateVars = [];        // Variables a pasar al template
protected ?\Twig\Environment $twig = null; // Instancia del motor Twig

public function setTemplate(string $templatePath): void
{
    $this->template = $templatePath;
    // Debug temporal
    error_log("DEBUG setTemplate - Asignado template: " . $templatePath . " en clase: " . get_class($this));
}

public function assign(string $key, mixed $value): void
{
    $this->templateVars[$key] = $value;
}

protected function initTwig(): void
{
    if ($this->twig !== null) {
        return;
    }

    if (!class_exists('\Twig\Environment')) {
        return; // Twig no está instalado
    }

    $templateDirs = [];

    // Obtener la clase que está llamando (no GTKHTMLPage, sino la clase hija como GestionAveriasHTMLPage)
    $reflectionClass = new \ReflectionClass($this);
    $classFile = $reflectionClass->getFileName();
    $classDir = dirname($classFile);

    // Buscar templates en el directorio del módulo donde está la clase
    // Ejemplo: stonewood-lib/src/Taller/Pages/GestionAveriasHTMLPage.php -> stonewood-lib/src/Taller/templates/
    $moduleTemplates = dirname($classDir) . '/templates';
    if (is_dir($moduleTemplates)) {
        $templateDirs[] = realpath($moduleTemplates);
    }

    // Buscar en src/templates del mismo nivel
    $srcTemplates = dirname($classDir, 2) . '/templates';
    if (is_dir($srcTemplates)) {
        $templateDirs[] = realpath($srcTemplates);
    }

    // Global desde bbl-data-access
    if (is_dir(__DIR__ . '/../../templates')) {
        $templateDirs[] = realpath(__DIR__ . '/../../templates');
    }

    // Fallback: directorio actual/templates
    if (empty($templateDirs)) {
        $templateDirs[] = getcwd() . '/templates';
    }

    $loader = new \Twig\Loader\FilesystemLoader($templateDirs);
    $this->twig = new \Twig\Environment($loader, [
        'cache' => false,
        'autoescape' => 'html',
    ]);
}

	protected function gtk_render(): string
	{
		$output = $this->renderMessages();

		// Debug: verificar si template está asignado (activado temporalmente)
		$debug = true;
		if ($debug) {
			error_log("DEBUG gtk_render - template: " . ($this->template ?? 'NULL'));
			error_log("DEBUG gtk_render - class: " . get_class($this));
			error_log("DEBUG gtk_render - templateVars count: " . count($this->templateVars));
		}

    if ($this->template !== null && !empty($this->template)) {
        $this->initTwig();

        if ($this->twig) {
            $this->templateVars['currentUser'] = $this->currentUser();
            $this->templateVars['baseUrl'] = $this->currentURL();
            // Pasar scripts y stylesheets al contexto de Twig
            $this->templateVars['stylesheets'] = $this->stylesheets;
            $this->templateVars['scripts'] = $this->scripts;

            try {
                return $output . $this->twig->render($this->template, $this->templateVars);
            } catch (\Twig\Error\LoaderError |
                     \Twig\Error\RuntimeError |
                     \Twig\Error\SyntaxError $e) {
                return $output . "<div style='color:red;'>
                    Error al renderizar plantilla Twig: {$e->getMessage()}<br>
                    Template buscado: {$this->template}
                </div>";
            }
        } else {
            // Si Twig no se inicializó, mostrar mensaje de error
            return $output . "<div style='color:orange;'>
                Twig no está disponible o no se pudo inicializar. Template: {$this->template}
            </div>";
        }
    }

    // Fallback clásico
    if (method_exists($this, 'renderBody')) {
        $output .= $this->renderBody();
    } else {
        $output .= "<h1>No se encontró método renderBody()</h1>";
        $output .= "<p>Template asignado: " . ($this->template ?? 'NULL') . "</p>";
        $output .= "<p>Clase: " . get_class($this) . "</p>";
    }

    return $output;
}

}

class GTKEditPage extends GTKHTMLPage
{

}



class GTKEntityPage extends GTKHTMLPage
{
	public $entity;
	public $entityDataSourceName;

	public function entityValue($key)
    {
        
        return $this->getDataSource($this->entityDataSourceName)->valueForKey($key, $this->entity);
    }

}
