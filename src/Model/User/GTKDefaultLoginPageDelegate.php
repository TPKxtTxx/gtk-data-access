<?php 

class GTKDefaultLoginPageDelegate extends GTKHTMLPage
{
	// public $messages = [];

	/*
	public function __construct(Type $var = null) {
		$this->var = $var;
	} 
	*/ 

	public function __construct($options = [])
	{
		parent::__construct($options);
		
		$this->setAuthenticate(false);
		$this->authenticationRequired = false;
		$this->permissionRequired     = "public";
	}	

	public function processPost()
	{
		$loginUser = new LoginUser();
		$loginUser->setDelegate($this);
		return $loginUser->loginUser($this->post["user_id"], $this->post["password"]);
	}

	public function renderBody()
	{		
		ob_start(); ?>
        <h1 class="ml-12 text-2xl font-bold my-4 center">Login</h1>

        <?php if (count($this->messages)): ?>
            <div class="space-y-2">
                <?php foreach ($this->messages as $message): ?>
                    <p class="text-red-600"><?php echo htmlspecialchars($message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
                
        <form action="/auth/login.php" method="post">
			<table>
            <tr>
            <td><label for="user_id"><?php echo Glang::get("login_form/id"); ?></label>
            <td><input type="text" name="user_id" id="user_id">
			</tr>
                
            <tr>
            <td><label for="password"><?php echo Glang::get("login_form/password"); ?></label>
        	<td><input type="password" name="password" id="password">
			</tr>
			</table>
                
            <input type="submit">
        </form>


		<?php
		global $_GLOBALS;
		if (isset($_GLOBALS["CUSTOMER_SUPPORT_EMAIL"])):


			$mailto = "mailto:" . $_GLOBALS["CUSTOMER_SUPPORT_EMAIL"];
			$subject = Glang::get("request_user_email/subject");
			$body    = Glang::get("request_user_email/body");
			// URL-encode subject and body
			$subject = urlencode($subject);
			$body = urlencode($body);
			
			$full_mailto_link = $mailto . "?subject=" . $subject . "&body=" . $body;
		?>
			
			<div class="px-8 py-8">
			<a href="<?php echo $full_mailto_link; ?>">Request User</a>
			<br/>
			<br/>
		<?php endif; ?>

		<br/>
		<a href="/auth/requestPasswordToken.php">Request or reset Password</a>
		</div>

		<?php 
		return ob_get_clean();
	}

	public function incompleteData($missingFields)
	{
		$this->messages[] = Glang::get("login_form/incomplete_data");
	}
	public function userDoesNotExist()
	{
		$this->messages[] = Glang::get("login_form/user_not_found");
	}
	public function userIsNotActive($user)
	{
		$this->messages[] = Glang::get("login_form/user_not_active");
	}
	public function userDoesNotHavePassword($user)
	{
		$debug = false;;

		if ($debug)
		{
			error_log("Processing user...: ".print_r($user, true));
		}

		$email = DataAccessManager::get("persona")->valueForKey("email", $user);

		if (!$email)
        {
			$message = Glang::get("login_form/no_email_for_user");

            throw new Exception($message);
            return new FailureResult(0, $message);
        }

		$passwordSetLink = DataAccessManager::get("RequestPasswordResetController")->passwordSetLinkForUser($user, "USER_LOGIN");

		if ($debug)
		{
			echo $passwordSetLink;
		}
		
		DataAccessManager::get("EmailQueueManager")->addToQueue(
            $email,
			Glang::get("login_form/please_create_password_email/subject"),
            Glang::get("login_form/please_create_password_email/body"));

	    echo Glang::get("login_form/please_create_password_email/message_sent_page");
		die();
	}
	public function userAndPasswordDoNotMatch()
	{
		$this->messages[] = Glang::get("login_form/user_password_does_not_match");
	}
	public function tooManyLoginAttempts()
	{
		return;
	}
	public function successfulMatchFromUntrustedDevice()
	{
		return;
	}
	public function successfulMatchForUser($user)
	{
		$debug = false;

		if ($debug)
		{
			error_log("Did get successful match for user: ".print_r($user, true));
		}

		$sessionAccessor = DataAccessManager::get('session');

		if (method_exists($sessionAccessor, 'completeLoginForUser'))
		{
			$sessionGuid = $sessionAccessor->completeLoginForUser($user);
		}
		else
		{
			$sessionGuid = $sessionAccessor->newSessionForUser($user);
		}

		if ($debug)
		{
			error_log("Session GUID: ".$sessionGuid);
		}

		if (!$sessionGuid)
		{
			$this->messages[] = "No se pudo iniciar sesión. Intente de nuevo.";
			return;
		}

		redirectToPath('/', "Bienvenidos!");
		die();
	}
}
  
