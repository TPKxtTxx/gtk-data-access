<?php

/*
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


/*

CREATE TABLE EmailQueue (
    EmailID INT IDENTITY(1,1) PRIMARY KEY,
    SenderEmail NVARCHAR(255) NOT NULL,
    Subject NVARCHAR(255),
    MessageText NVARCHAR(4000), -- Approximate size for 10-12 pages of text
    Status NVARCHAR(50) NOT NULL DEFAULT N'Pending',
    CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
    SentAt DATETIME,
    SendAt DATETIME,
    ErrorDescription NVARCHAR(4000),
    -- Recipient information
    RecipientEmail NVARCHAR(255) NOT NULL,
    CCRecipients NVARCHAR(4000), -- Comma-separated list of CC email addresses
    BCCRecipients NVARCHAR(4000) -- Comma-separated list of BCC email addresses
);

*/

class EmailQueueManager extends DataAccess
{
    private $userName;
    private $password;
    private $useGmail;
    private $smtpPort;
    private $hostAddress;
    public  $sendFrom;
    public  $debugLevel;

    public function __construct($p_db, $options = [])
    {
        parent::__construct($p_db, $options);

        $this->sendFrom = $options['sendFrom'] ?? null;
    }

    public function register()
    {

        global $_GLOBALS;


        $this->userName    = $_GLOBALS["EMAIL_QUEUE_USER"];
        $this->password    = $_GLOBALS["EMAIL_QUEUE_PASSWORD"];
        $this->hostAddress = $_GLOBALS["EMAIL_QUEUE_SMTP_HOST"];
        $this->sendFrom    = $_GLOBALS["EMAIL_QUEUE_SEND_FROM"];
        

        if (!$this->userName)
        {
            die("DIE w/error: Starting email queue without username");
        }

        if (!$this->password)
        {
            die("DIE w/error: Starting email quweue without password");
        }

        if (!$this->hostAddress)
        {
            die("NO host address for Email Queue Manager");
        }

                //Enable SMTP debugging
        //$mail->SMTPDebug = SMTP::DEBUG_OFF;    // = off (for production use)
        //$mail->SMTPDebug = SMTP::DEBUG_CLIENT; // = client messages
        $this->debugLevel = SMTP::DEBUG_SERVER; //  = client and server messages

    

        $columns = [
            new GTKColumnMapping($this, "email_id", [
                "columnType"       => "INTEGER",
                "isPrimaryKey"     => true,
                 "isAutoIncrement" => true,
                "dbKey"            => "EmailID",           
                "formLabel"        => "ID de Correo Electrónico",
            ]),         
            new GTKColumnMapping($this, "sender_email",      [ "dbKey" => "SenderEmail",        "formLabel" => "Correo Electrónico del Remitente"]),
            new GTKColumnMapping($this, "subject",           [ "dbKey" => "Subject",            "formLabel" => "Asunto"]),
            new GTKColumnMapping($this, "message_text",      [ "dbKey" => "MessageText",        "formLabel" => "Texto del Mensaje"]),
            new GTKColumnMapping($this, "status",            [ "dbKey" => "Status",             "formLabel" => "Estado"]),
            new GTKColumnMapping($this, "created_at",        [ "dbKey" => "CreatedAt",          "formLabel" => "Fecha de Creación"]),
            new GTKColumnMapping($this, "sent_at",           [ "dbKey" => "SentAt",             "formLabel" => "Fecha de Envío"]),
            new GTKColumnMapping($this, "send_at",           [ "dbKey" => "SendAt",             "formLabel" => "Fecha de Programación de Envío"]),
            new GTKColumnMapping($this, "error_description", [ "dbKey" => "ErrorDescription",   "formLabel" => "Descripción del Error"]),
            new GTKColumnMapping($this, "recipient_email",   [ "dbKey" => "RecipientEmail",     "formLabel" => "Correo Electrónico del Destinatario"]),
            new GTKColumnMapping($this, "cc_recipients",     [ "dbKey" => "CCRecipients",       "formLabel" => "Destinatarios en Copia"]),
            new GTKColumnMapping($this, "bcc_recipients",    [ "dbKey" => "BCCRecipients",      "formLabel" => "Destinatarios en Copia Oculta"]),
            new GTKColumnMapping($this, "is_html"),
            new GTKColumnMapping($this, "string_to_attach_filename"),
            new GTKColumnMapping($this, "string_to_attach"),
             
        ];

		$this->dataMapping 			= new GTKDataSetMapping($this, $columns);
		$this->defaultOrderByColumnKey = "CreatedAt";
		$this->defaultOrderByOrder  = "DESC";
		$this->singleItemName	    = "Email";
		$this->pluralItemName	    = "Emails";
		$this->_allowsCreation      = false;
        
    }

    function verifyEmailDomain($email)
    {
        // Extract the domain from the email
        $domain = substr(strrchr($email, "@"), 1);

        // Check if the domain has a valid MX record
        if (!checkdnsrr($domain, "MX")) {
            return false;
        }

        return true;
    }

    function verifyEmailString($toCheck) 
    {
    // Separar por coma
        $emails = str_contains($toCheck, ",") 
            ? explode(",", $toCheck) 
            : [$toCheck];

        foreach ($emails as $email) {

            // Limpieza mínima (NO Sanitize)
            $email = trim($email);
            $email = str_replace(["\r", "\n"], "", $email);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }


    public function addAlertToQueue(
        $sendTo, 
        $subject, 
        $messageText, 
        $options = []
    ) {
        

        $this->addAlertToQueue($sendTo, $subject, $messageText, $options);
    }

    public function addDictionaryToQueue($dictionary) 
    {
        $sendTo = $dictionary["to"] ?? $dictionary['sendTo'];
        $subject = $dictionary['subject'];
        $messageText = $dictionary['body'];

        return $this->addToQueue(
            $sendTo, 
            $subject, 
            $messageText, 
            $dictionary);
    }

    public function addToQueue(
        $sendTo, 
        $subject, 
        $messageText, 
        $options = []
    ) {
        $debug = false;

        if (!$sendTo)
        {
            throw new Exception("El campo `sendTo` es obligatorio.");
        }

        if (is_string($sendTo))
        {

            $sendTo = strtolower($sendTo);

            if(strlen($sendTo) < 7)
            {
                return false;
            }
    
            if (!$this->verifyEmailString($sendTo))
            {
                throw new Exception("El campo `sendTo` no es un correo electrónico válido: ".print_r($sendTo, true));
            }
        }
        else if (is_array($sendTo))
        {
            $sendToArray = $sendTo;

            foreach ($sendToArray as $email)
            {
                if(strlen($email) < 7)
                {
                    return false;
                }
                
                if (!$this->verifyEmailString($email))
                {
                    throw new Exception("El campo `sendTo` no es un correo electrónico válido: ".print_r($sendTo, true));
                }
            }

            $sendTo = implode(",", $sendToArray);
        }

        $senderEmail   = $options['senderEmail']   ?? $this->sendFrom; 
        $ccRecipients =  $options['ccRecipients']  ?? null;
        $bccRecipients = $options['bccRecipients'] ?? null;
        $sendAt        = $options['sendAt']        ?? null;
        $status        = "Pending";
        $createdAt     = $options['createdAt']     ?? date('Y-m-d H:i:s');

        $stringToAttach         = $options["stringToAttach"] ?? null;
        $stringToAttachFilename = $options["stringToAttachFilename"] ?? null;


        $toInsert = [
            "sender_email"     => $options['senderEmail']   ?? $this->sendFrom,
            "subject"          => $subject,   
            "message_text"     => $messageText,    
            "status"           => "Pending",
            "created_at"       => date('Y-m-d H:i:s'),
            "sent_at"          => null,       
            "send_at"          => $options['sendAt'] ?? null,        
            "recipient_email"  => strtolower($sendTo),
            "cc_recipients"    => $options['ccRecipients']  ?? null,
            "bcc_recipients"   => $options['bccRecipients'] ?? null,
            "is_html"          => $options["isHTML"] ?? true,
            "string_to_attach"          => $stringToAttach,
            "string_to_attach_filename" => $stringToAttachFilename,
        ];

        $this->insert($toInsert);

        /*
        // Insert the email into the database queue
        $stmt = $this->getDB()->prepare("
            INSERT INTO EmailQueue (
                SenderEmail, 
                RecipientEmail,
                CCRecipients, 
                BCCRecipients, 
                Subject, 
                MessageText, 
                SendAt,
                Status,
                CreatedAt
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $senderEmail, 
            $sendTo, 
            $ccRecipients, 
            $bccRecipients, 
            $subject,
            $messageText, 
            $sendAt,
            $status,
            $createdAt]);
        */
    }

    public function getPendingEmails($debug, $logFunction = null)
    {
        if ($debug && !$logFunction)
        {
            $logFunction = function($arg){
                error_log($arg);
            };
        }

        // Query for pending emails scheduled for sending
        $currentTimestamp = date('Y-m-d H:i:s');

        if ($debug)
        {
            $logFunction("Will prepare query.");
        }

        $statement = $this->getDB()->prepare("
            SELECT *
            FROM EmailQueue
            WHERE (Status = 'Pending' OR Status IS NULL) AND (SendAt IS NULL OR SendAt <= ?)
        ");

        if ($debug)
        {
            $logFunction("Will execute query.");
        }

        $statement->execute([$currentTimestamp]);

        if ($debug)
        {
            $logFunction("Will fetchAll.");
        }

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($debug)
        {
            $logFunction("Result for `getPendingEmails`: ".count($result));
        }

        return $result;
    }

    public function updateEmailWithSuccess(
        $email, 
        $currentTimestamp = null
    ){
        $timestamp = null;

        if ($currentTimestamp)
        {
            $timestamp = $currentTimestamp;
        }
        else
        {
            $timestamp = date('Y-m-d H:i:s');
        }

        // Update the status of the email to 'Sent'
        $updateStmt = $this->getDB()->prepare("
            UPDATE EmailQueue
            SET Status = 'Sent', 
                SentAt = ?
            WHERE EmailID = ?
        ");
        $updateStmt->execute([$timestamp, $email['EmailID']]);
    }

    public function updateEmailWithException($email, $exception)
    {
        // Handle email sending errors and update the status to 'Failed' with error description
        $updateStmt = $this->getDB()->prepare("
            UPDATE EmailQueue
            SET Status = 'Failed', 
                ErrorDescription = ?
            WHERE EmailID = ?
        ");
        $updateStmt->execute([$exception->getMessage(), $email['EmailID']]);
    }

    // https://github.com/PHPMailer/PHPMailer/blob/master/examples/gmail.phps
    public function saveMailInSentFolder($mail)
    {
        //You can change 'Sent Mail' to any other folder or tag
        $path = '{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail';

        //Tell your server to open an IMAP connection using the same username and password as you used for SMTP
        $imapStream = imap_open($path, 
            $mail->Username, 
            $mail->Password);

        $result = imap_append($imapStream, $path, $mail->getSentMIMEMessage());
        imap_close($imapStream);

        return $result;
    }

    public function sendEmail($mailer, $email /* Array */)
    {
        $debug = false;

        $failString = "";

        $successString = "";

        $successString .= "Sent email `";

        $mailer->Subject = $email['Subject'];
        $mailer->Body    = $email['MessageText'];

        $successString .= $mailer->Subject."` to... ";

        $recipientEmails = parseCSVLine($email['RecipientEmail']);
        $ccRecipients    = parseCSVLine($email['CCRecipients']);
        $bccRecipients   = parseCSVLine($email['BCCRecipients']);

        foreach ($recipientEmails as $address) {
            $successString .= $address." ";
            $mailer->addAddress($address);
        }

        foreach ($ccRecipients as $address) {
            $mailer->addCC($address);
        }

        foreach ($bccRecipients as $address) {
            $mailer->addBCC($address);
        }

        if ($email["string_to_attach"])
        {
            $mailer->addStringAttachment(
                $email["string_to_attach"],
                $email["string_to_attach_filename"] ?? "attachment.txt"
            );
        }

        try 
        {
            // Send the email
            $mailer->send();

            $this->updateEmailWithSuccess($email);

            if ($debug)
            {
                error_log($successString);
            }

            return $successString;
        } 
        catch (Exception $e) 
        {
            $this->updateEmailWithException($email, $e);

            $errorString = "FAIL @ Email Send Message: ".$e->getMessage();

            if ($debug)
            {
                error_log($errorString);
            }

            return $errorString;
        }
    }


    public function sendPendingEmails($debug, $logFunction = null, $sendFrom = null) 
    {
        if ($debug && !$logFunction)
        {
            $logFunction = function($arg){
                error_log($arg);
            };
        }

        if ($debug)
        {
            $logFunction("Will query for pending emails");
        }

        $pendingEmails = $this->getPendingEmails($debug, $logFunction);

        if ($debug)
        {
            $logFunction("Got pending emails: ".count($pendingEmails));
        }

        $mailer = $this->getMailer($sendFrom);

        $infoString = "";

        foreach ($pendingEmails as $email)
        {
            if (isTruthy($email["is_email"]))
            {
                $mailer->isHTML(true);
            }


            $toAppend = $this->sendEmail($mailer, $email)."\n\n";

            if ($debug)
            {
                $logFunction($toAppend);
            }

            $infoString .= $toAppend;

            $mailer->isHTML(true);
            $mailer->clearAddresses();
            $mailer->clearCCs();
            $mailer->clearBCCs();
            $mailer->clearAttachments();
        }

        return $infoString;
    }

    private function parseEmailAddresses($emailAddresses) 
    {
        // Check if the input is a comma-separated list
        if (strpos($emailAddresses, ',') !== false) 
        {
            // Split and trim the list into an array
            $addresses = array_map('trim', explode(',', $emailAddresses));
            return $addresses;
        }
        return $emailAddresses;
    }


    public function getMailer($sendFrom = null)
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->SMTPDebug     = $this->debugLevel;
        $networkSupportsIPv6 = true;

        if ($networkSupportsIPv6)
        {
            $mail->Host = $this->hostAddress;
        }
         else
        {
             $mail->Host = gethostbyname($this->hostAddress);
             //if your network does not support SMTP over IPv6,
             //though this may cause issues with TLS
        }

        $useImplicitTLS = true; // RFC8314 SMTPS

        if ($useImplicitTLS)
        {
            $mail->Port       = 465;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        else
        {
            $mail->Port       = 587;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }


        $mail->SMTPAuth = true;

        $mail->Username = $this->userName;
        $mail->Password = $this->password;


        if (!$sendFrom)
        {
            if ($this->sendFrom)
            {
                $sendFrom = $this->sendFrom;
            }
            
            if (!$sendFrom)
            {
                global $_GLOBALS;
                $sendFrom = $_GLOBALS["EMAIL_QUEUE_SEND_FROM"];
            }

            if (!$sendFrom)
            {
                $sendFrom = $this->userName;
            }
        }

        $mail->setFrom($sendFrom);

        return $mail;
    }

    public function reportError($subject, $body)
    {
        global $_GLOBALS;
        $errorEmail = $_GLOBALS["ON_ERROR_EMAIL"];

        if (!$errorEmail)
        {
            $message  = "";
            $message .= "Aviso - Ha occurido un error en el sistema.";
            $message .= "Aun no se ha configurado un correo electrónico para recibir notificaciones de error.";
            $message .= "Favor tomar las acciones de lugar.";

            error_log($message);

            die($message);
        }
        
        DataAccessManager::get("email_queue")->addToQueue(
            $errorEmail,
            $subject,
            $body);

    }
}
