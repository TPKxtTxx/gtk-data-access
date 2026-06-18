<?php

global $_GLOBALS;

if (!$_GLOBALS)
{
    $_GLOBALS = [];
}

if (!isset($_GLOBALS["languages"]))
{
    $_GLOBALS["languages"] = [];
}

$_GLOBALS["default_language"] = "es";


$_GLOBALS["languages"]["es"] = [

    "LoggedInAs" => 'Usuario Activo: ',
    "NotAuthorizedToDoThis" => "No tiene los permisos para esta operación.",

    "DataAccessManager/RequiresRedirect" => 
        "Requiere autenticación... redireccionando. <br/><br/> <a href='/auth/login.php'>Ir a login.</a>", 

    
    "EditDataSourceRenderer/SubmitButtonValue/IsNew" => "Crear",
    "EditDataSourceRenderer/SubmitButtonValue" => "Editar",

    "EmailQueueManager/single"                      => "Correo",
    "EmailQueueManager/plural"                      => "Cola de Correos",

    "FlatRoleDataAccess/single"                                => "Relación Usuario / Rol",
    "FlatRoleDataAccess/plural"                                => "Relaciones Usuario / Rol",
    "FlatRoleDataAccess/user_id"                               => "Usuario",
    "FlatRoleDataAccess/qualifier"                             => "Calificador",
    "FlatRoleDataAccess/is_active"                             => "Activo",
    "FlatRoleDataAccess/qualifier_type"                        => "Tipo Calificador",
    "FlatRoleDataAccess/name"                                  => "Nombre",
    "FlatRoleDataAccess/role_id"                               => "Role ID",


    "LoginPage/user_id_label"                 => "Cédula o Email",
    "LoginPage/password_label"                => "Clave",
    "LoginPage/request_new_user_button"       => "Solicitar Usuario",
    "LoginPage/request_new_password_button"   => "Reconfigurar Password",    

    "RequestUserEmail/subject" => "Solicitud de Usuario para el Sistema - Reserva Chasis",
    "RequestUserEmail/body"    => "Le solicito un usuario para acceder al sistema. Mi nombre, [empresa o transporte] son...",

    "ResetPassword/Form/InvalidRequest" => "No nos ha suministrado un token para poder ayudarle. Favor verificar el link. Redirigiendo a la pagina de contraseña faltante.",
    "ResetPassword/Form/InvalidToken" => "Token inválido o caducado. Redirigiendo a la página de solicitud de restablecimiento de contraseña.",
    "ResetPassword/Form/Success" => "Su clave ha sio cambiada. Puede intentar nuevamente.",
    "ResetPassword/Form/Error" => "Hubo un problema re-estableciendo su clave. A probar nuevamente.",


    "RoleDataAccess/single"                                    => "Rol",
    "RoleDataAccess/plural"                                    => "Roles",
    
    "RolePermissionRelationshipsDataAccess/single"             => "Relación Rol con Permiso",
    "RolePermissionRelationshipsDataAccess/plural"             => "Relaciones Rol con Permiso",
    "PermissionDataAccess/single"                              => "Permiso",
    "PermissionDataAccess/plural"                              => "Permisos",

    "PermissionPersonRelationshipDataAccess/single"            => "Relación Permiso con Persona",
    "PermissionPersonRelationshipDataAccess/plural"            => "Relaciones Permiso con Persona",
    
    "PersonaDataAccess/single"                                 => "Persona",
    "PersonaDataAccess/plural"                                 => "Personas",
    "PersonaDataAccess/cedula"                                 => "Cédula",
    
    "SessionDataAccess/single"                                 => "Sesión",
    "SessionDataAccess/plural"                                 => "Sesiones",
    "SessionDataAccess/client_ip"                              => "IP",
    "SessionDataAccess/device"                                 => "Dispositivo",
    "SessionDataAccess/browser"                                => "Navegador",

    "SetPasswordTokenDataAccess/single"                        => "Token de Establecimiento de Contraseña",
    "SetPasswordTokenDataAccess/plural"                        => "Tokens de Establecimiento de Contraseña",
    "SetPasswordTokenDataAccess/id"                            => "ID",
    "SetPasswordTokenDataAccess/user_id"                       => "ID de Usuario",
    "SetPasswordTokenDataAccess/origin"                        => "Origen",
    "SetPasswordTokenDataAccess/token"                         => "Token",
    "SetPasswordTokenDataAccess/fecha_creado"                  => "Fecha de Creación",
    "SetPasswordTokenDataAccess/lifetime"                      => "Duración",
    "SetPasswordTokenDataAccess/invalidated_at"                => "Fecha de Invalidación",

    "UserAccountRequest/Action/Approved/Email/Subject" => "Su cuenta ha sido aprobada!",
    "UserAccountRequest/Action/Approved/Email/Body"    => function ($options) {
        $userName = $options["userName"] ?? "";

        $toReturn  = "";
        $toReturn .= "Hola,";
        $toReturn .= "Su cuenta ha sido aprobada.";
        $toReturn .= "<br/>";
        
        $link = "https://".$_SERVER["HTTP_HOST"]."/auth/login.php";

        $toReturn .= 'Puede entrar a su cuenta <a href="'.$link.'>en este link</a>';
        $toReturn .= "<br/>";
        $toReturn .= "O pegar lo siguiente en su navegardor: ".$link;
        $toReturn .= "<br/>";
        
        return $toReturn;
    },

    "session_expired_message" =>
        "Su sesión ha expirado—por favor inicie sesión nuevamente. <a href='/auth/login.php'>Iniciar Sesión</a>",

    "request_user_email/subject" =>
        "Solicitud de Usuario para el Sistema - Reserva Chasis",
    "request_user_email/body" =>
        "Le solicito un usuario para acceder al sistema. Mi nombre, [empresa o transporte] son...",

    "login_form/no_email_for_user" =>
        "No hay correo electrónico para el usuario",
    "login_form/id" =>
        "ID # o Correo Electrónico:",
    "login_form/password" =>
        "Clave:",
    "login_form/incomplete_data" =>
        "Favor introducir todos sus datos en el formulario.",
    "login_form/user_not_found" =>
        "Su usuario no fue encontrado en el sistema.",
    "login_form/user_not_active" =>
        "Su usuario no esta activo.",
    "login_form/please_create_password_email/message_sent_page" =>
        "Favor chequear su correo. Dentro de los próximos 5 minutos recibirá instrucciones sobre cómo establecer su clave.",
    "login_form/user_password_does_not_match" => 
        "La clave y el usuario no concuerdan.",
    "login_form/please_create_password_email/subject" =>  
        "Sistema Reserva Chassis: Favor crear su clave",
    "login_form/please_create_password_email/body" => function ($options) {
        global $_GLOBALS;

        $EOL = $options["EOL"] ?? "\n";
        $passwordSetLink = "[Insertar enlace para establecer clave aquí]"; // Reemplazar con el enlace real
        $text = "Buenas,".$EOL.$EOL;
        $text .= "Para poder ingresar al sistema de reserva de chasis, favor crear una clave aquí haciendo click aquí: ".$passwordSetLink.$EOL.$EOL;
        $text .= "Cualquier duda, favor escribir a ".$_GLOBALS["CUSTOMER_SUPPORT_EMAIL"].".".$EOL.$EOL;
        $text .= "Gracias!".$EOL;
        $text .= "Equipo Reserva Chassis";

        return $text;
    },

    "password"                                         => "Contraseña",
    "name"                                             => "Español",
    "none"                                             => "Ninguno",
    "email"                                            => "Correo Electrónico:",
    "email_request"                                    => "Ingrese su correo electrónico",
    "yes"                                              => "Sí",
    "no"                                               => "No",
    "about_us_description"                             => 
        "Fabricante, reparador e alquilador de chasis para camiones. Caucedo, República Dominicana",

    "RequestPasswordReset/Form/user_not_found"               =>
        "No se encontró usuario con esa cédula o email",
    "RequestPasswordReset/Form/too_soon_for_new_password_token" =>  
        "Favor verificar su correo. Recientemente solicitó un link para su contraseña. De no ser así, esperar unos minutos más para poder proceder.",
    "RequestPasswordReset/Form/error_sending_reset_password_token" =>
        "Error al enviar link de cambio de clave.",
    "RequestPasswordReset/Form/no_email_for_user"            =>
        "Si un correo fue encontrado, se le envió un email para resetear la clave",
    "RequestPasswordReset/Form/submitted"                    =>
        "Un link para resetear su clave fue enviado a su correo. Favor revisar su correo."
        ."<br/>"
        ."<br/>"
        ."<a href='/'>Ir a inicio</a>",

    "password_needs_character_and_symbols"             =>
        "Debe incluir caracteres simbólicos, mayúsculas y minúsculas.",
    "password_too_short"                               => 
        "La contraseña debe tener al menos un símbolo y un número",
    "invalid_goverment_id"                             => 
        "Cédula inválida",  

    "check_your_email_for_password_reset"              =>
        "Link enviado a su correo. Favor revisar su correo.",

    "reset_password_email/subject" => "Re-establece tu clave",
    "reset_password_email/body" => function ($options) {
        $linkToToken = $options["linkToToken"];

        $text  = "";
        $text .= "Hola,";
        $text .= "Para re-establecer tu clave, ";
        $text .= "haz click en el siguiente link: ";
        $text .= "<a href='".$linkToToken."' target='_blank'>link</a>";
        $text .= " o haga copy/paste de aquí: ";
        $text .= $linkToToken;

        return $text;
    },


    /*-------------------------------------------------------------------
    **
    **
    **-------------------------------------------------------------------
    */    
    "passwordDoesNotMeetRequiredLength" => function ($passwordRequirements) {
        return "La contraseña no cumple con la longitud mínima requerida de " . $passwordRequirements['min_length'] . " caracteres.";
    },
    "passwordTooLong" => function ($passwordRequirements) {
        return "La contraseña excede la longitud máxima permitida de " . $passwordRequirements['max_length'] . " caracteres.";
    },
    "passwordIsMissingUppercaseCharacter" => function ($passwordRequirements) {
        return "La contraseña debe contener al menos un carácter en mayúscula.";
    },
    "passwordIsMissingLowercaseCharacter" => function ($passwordRequirements) {
        return "La contraseña debe contener al menos un carácter en minúscula.";
    },
    "passwordIsMissingDigits" => function ($passwordRequirements) {
        return "La contraseña debe contener al menos un dígito.";
    },
    "passwordIsMissingSpecialCharacters" => function ($passwordRequirements) {
            return "La contraseña debe contener al menos un carácter especial: " . $passwordRequirements['special_chars'];
    },


];
