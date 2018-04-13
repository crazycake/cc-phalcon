<?php
/**
 * Translations, contains default translations.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Controllers;

//imports
use Phalcon\Mvc\Controller;

/**
 * Translations Trait
 */
trait Translations
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
	 * @param String $controller - The Controller name
	 */
	public static function getCoreTranslations($controller = "")
	{
		$data = [
			"ACCOUNT" => [
				"AUTH_FAILED"        => "El correo ó contraseña no son válidos.",
				"STATE_PENDING"      => "Te hemos enviado un correo de activación. Haz click <a href=\"#0\">aquí</a> si no has recibido este correo.",
				"STATE_DISABLED"     => "Esta cuenta se encuentra desactivada, por favor comunícate con nuestro equipo.",
				"NOT_FOUND"          => "Esta cuenta no se encuentra registrada o no ha sido activada.",
				"EMAIL_EXISTS"       => "El correo {email} ya se encuentra registrado, ingresa con tu correo <a href=\"{link}\">aquí</a>.",
				"ACTIVATION_SUCCESS" => "¡Tu cuenta ha sido activada!",
				"ACTIVATION_PENDING" => "Te hemos enviado un correo a {email} para que actives tu cuenta.",
				"RECAPTCHA_FAILED"   => "No hemos logrado verficar el reCaptcha, por favor inténtalo de nuevo.",
				"INVALID_NAME"       => "Tu nombre y apellido deben ser válidos.",
				"INVALID_EMAIL"      => "Tu correo electrónico no es válido.",
				"PASS_TOO_SHORT"     => "Debes ingresar una contraseña de al menos 8 caracteres.",
				"CREATE_PASS"        => "Crea tu nueva contraseña",
				"CURRENT_PASS_EMPTY" => "Para modificar tu contraseña debes ingresar tu contraseña actual.",
				"PASS_DONT_MATCH"    => "Tu contraseña actual no es correcta.",
				"NEW_PASS_EQUALS"    => "Tu nueva contraseña debe ser diferente a la actual.",
				"NEW_PASS_SAVED"     => "Tu contraseña ha sido guardada.",
				"PASS_MAIL_SENT"     => "Te hemos enviado un correo a {email} para recuperar tu contraseña."
			],
			"MAILER" => [
				"SUBJECT_ACTIVATION" => "Confirma tu cuenta",
				"SUBJECT_PASSWORD"   => "Recupera tu contraseña"
			],
			"UPLOADER" => [
				"MAX_SIZE"       => "El archivo {file} excede el máximo tamaño permitido de {size}.",
				"FILE_TYPE"      => "El archivo {file} no es soportado.",
				"IMG_WIDTH"      => "La imagen {file} tiene un ancho distinto de {w}px.",
				"IMG_HEIGHT"     => "La imagen {file} tiene un alto distinto de {h}px.",
				"IMG_MIN_WIDTH"  => "La imagen {file} debe tener un ancho de al menos {w}px.",
				"IMG_MIN_HEIGHT" => "La imagen {file} debe tener una altura de al menos {h}px.",
				"IMG_RATIO"      => "La imagen {file} debe tener un ratio de {r}."
			]
		];

		//facebook
		if(class_exists("\FacebookController")) {

			$data["FACEBOOK"] = [
				"SESSION_ERROR"    => "Ocurrió un problema con tu sesión de Facebook, por favor inténtalo nuevamente. ".
									  "Si aún se presenta este problema, prueba iniciando una nueva sesión en Facebook.",
				"OAUTH_REDIRECTED" => "Ocurrió un problema con tu sesión de Facebook, por favor inténtalo nuevamente.",
				"OAUTH_PERMS"      => "Debes aceptar los permisos de la aplicación en tu cuenta de Facebook.",
				"SESSION_SWITCHED" => "Es posible que tengas abierta otra sesión de Facebook, intenta cerrando tu sesión actual de Facebook.",
				"ACCOUNT_SWITCHED" => "Esta sesión de Facebook está vinculada a otra cuenta, intenta usando otra cuenta de Facebook.",
				"ACCOUNT_DISABLED" => "Esta cuenta se encuentra desactivada, por favor comunícate con nuestro equipo.",
				"INVALID_EMAIL"    => 'No hemos logrado obtener tu correo primario de Facebook, asegúrate de aceptar los permisos y validar
									   tu correo primario en tu cuenta de Facebook.
									   Haz <a href="'.\FacebookController::$FB_EMAIL_SETTINGS_URL.'" target="_blank">click aquí</a>
									   para configurar tu correo primario.'
			];
		}

		//call handler
		$data = array_merge($data, self::coreTranslations());

		//return controller translations
		return $data[strtoupper($controller)] ?? [];
	}

	/**
	 * Javascript Translations (Sent to view)
	 */
	public static function getJsTranslations()
	{
		$data = [
			"ALERTS" => [
				"SERVER_ERROR"     => "Ha ocurrido algo inesperado, por favor inténtalo más tarde.",
				"SERVER_TIMEOUT"   => "Sin conexión. Revisa tu conexión a Internet e inténtalo de nuevo.",
				"NOT_FOUND"        => "Este enlace está roto, por favor inténtalo más tarde.",
				"ACCESS_FORBIDDEN" => "Tu sesión ha caducado, debes iniciar sesión nuevamente.",
				"CSRF"             => "Esta página ha estado inactiva por mucho tiempo, refréscala haciendo ".
										"<a href=\"javascript:location.reload();\">click aquí</a>.",
				"LOADING"          => "cargando ...",
				"REDIRECTING"      => "redireccionado ..."
			],
			"ACTIONS" => [
				"OK"       => "Ok",
				"ACCEPT"   => "Aceptar",
				"CANCEL"   => "Cancelar",
				"DELETE"   => "Eliminar",
				"CONTINUE" => "Continuar",
				"SEND"     => "Enviar"
			],
			"MAILER" => [
				"SENT" => "¡Hemos recibido tu mensaje! Te responderemos a la brevedad."
			]
		];

		//call handler
		$data = array_merge($data, self::jsTranslations());

		return $data;
	}
}