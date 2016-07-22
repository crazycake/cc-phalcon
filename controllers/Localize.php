<?php
/**
 * Localize
 * Contains default controllers translations.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Mvc\Controller;

/**
 * Localize Trait
 */
trait Localize
{
    /**
     * Core translations handler
     */
    abstract public function coreTranslations();

    /**
     * Javascript translations handler
     */
    abstract public function jsTranslations();

    /**
     * Get Controllers Translations
     * @static
     * @param string $controller - The Controller name
     */
    public static function getCoreTranslations($controller = "")
    {
        //get translate service
        $di = \Phalcon\DI::getDefault();

        $app_name = $di->getShared('config')->app->name;

        $data = [
            "ACCOUNT" => [
                "CURRENT_PASS_EMPTY" => "Para modificar tu contraseña debes ingresar tu contraseña actual.",
                "PASS_TOO_SHORT"     => "Debes ingresar una contraseña de al menos 8 caracteres.",
                "PASS_DONT_MATCH"    => "Tu contraseña actual no es correcta.",
                "NEW_PASS_EQUALS"    => "Tu nueva contraseña debe ser diferente a la actual.",
                "INVALID_NAMES"      => "Tu nombre no parece ser válido.",
                "PROFILE_SAVED"      => "Los cambios han sido guardados."
            ],
            "AUTH" => [
                "AUTH_FAILED"        => "El correo ó contraseña no son válidos.",
                "ACCOUNT_PENDING"    => "Te hemos enviado un correo de activación. Haz click <a href=\"#\">aquí</a> si no has recibido este correo.",
                "ACCOUNT_DISABLED"   => "Esta cuenta se encuentra desactivada por incumplimiento a nuestros términos y condiciones
                                         porfavor <a href=\"javascript:core.redirectTo('contact');\">comunícate aquí</a> con nuestro equipo.",
                "ACCOUNT_NOT_FOUND"  => "Esta cuenta no se encuentra registrada.",
                "INVALID_NAMES"      => "Tu nombre no parece ser válido.",
                "RECAPTCHA_FAILED"   => "No hemos logrado verficar el reCaptcha, porfavor inténtalo de nuevo.",
                "ACTIVATION_SUCCESS" => "¡Tu cuenta ha sido activada!",
                "ACTIVATION_PENDING" => "Te hemos enviado un correo a {email} para que actives tu cuenta.",
                //text titles
                "TITLE_SIGN_IN" => "Ingresa a ".$app_name,
                "TITLE_SIGN_UP" => "Regístrate en ".$app_name
            ],
            "MAILER" => [
                "SUBJECT_ACTIVATION" => "Confirma tu cuenta",
                "SUBJECT_PASSWORD"   => "Recupera tu contraseña"
            ],
            "PASSWORD" => [
                "RECAPTCHA_FAILED"  => "No hemos logrado verficar el reCaptcha, porfavor inténtalo de nuevo.",
                "ACCOUNT_NOT_FOUND" => "Esta cuenta no se encuentra registrada o no ha sido activada.",
                "PASS_MAIL_SENT"    => "Te hemos enviado un correo a {email} para recuperar tu contraseña.",
                "NEW_PASS_SAVED"    => "¡Tu contraseña ha sido modificada!",
                "PASS_TOO_SHORT"    => "Debes ingresar una contraseña de al menos 8 caracteres.",
                //text titles
                "TITLE_RECOVERY"    => "Recupera tu contraseña",
                "TITLE_CREATE_PASS" => "Crea una nueva contraseña"
            ],
            "UPLOADER" => [
                "MAX_SIZE"   => "El archivo {file} excede el máximo tamaño permitido de {size}.",
                "FILE_TYPE"  => "El archivo {file} no es soportado.",
                "IMG_WIDTH"  => "La imagen {file} tiene un ancho distinto de {w}px.",
                "IMG_HEIGHT" => "La imagen {file} tiene un alto distinto de {h}px."
            ]
        ];

        //call handler
        $data = array_merge($data, self::coreTranslations());

        //return controller translations
        return $data[strtoupper($controller)];
    }

    /**
     * Javascript Translations
     * @static
     */
    public static function getJsTranslations()
    {
        //get translate service
        $di = \Phalcon\DI::getDefault();

        $data = [
            "ALERTS" => [
        		"INTERNAL_ERROR"   => "Oops, ha ocurrido un problema, solucionaremos esto a la brevedad.",
        		"SERVER_TIMEOUT"   => "Hemos perdido la comunicación, prueba revisando tu conexión a Internet.",
        		"CSRF" 			   => "Esta página ha estado inactiva por mucho tiempo, haz
                                        <a href=\"javascript:location.reload();\">click aquí</a> para refrescarla.",
        		"NOT_FOUND" 	   => "Oops, el enlace está roto. Porfavor inténtalo más tarde.",
        		"BAD_REQUEST" 	   => "Lo sentimos, no hemos logrado procesar tu petición. Intenta refrescando esta página.",
        		"ACCESS_FORBIDDEN" => "Tu sesión ha caducado, porfavor <a href=\"./signIn\">ingresa nuevamente aquí</a>."
        	],
        	"ACTIONS" => [
        		"OK" 	   => "Ok",
        		"ACCEPT"   => "Aceptar",
        		"CANCEL"   => "Cancelar",
        		"NOT_NOW"  => "Ahora No",
        		"SEND" 	   => "Enviar",
        		"GOT_IT"   => "Entendido",
        		"LOADING"  => "cargando ...",
                "TRANSFER" => "Transferir",
                "NULLIFY"  => "Anular",
                "UNLINK"   => "Desvincular",
                "DELETE"   => "Eliminar"
        	],
        	"MAILER" => [
        		"SENT" => "¡Hemos recibido tu mensaje! Te responderemos a la brevedad."
        	],
            "CRUD" => [
                "SAVED"          => "Datos guardados.",
                "UPDATED"        => "Datos actualizados.",
                "EMPTY_SEARCH"   => "Nada relevante.",
                "INFO_RESULTS"   => "Del {from} al {to} de {total} elementos.",
                "DELETE_CONFIRM" => "¿Estás seguro que quieres eliminar este registro?"
            ]
        ];

        //call handler
        $data = array_merge($data, self::jsTranslations());

        return $data;
    }
}
