<?php
/**
 * Localize
 * Contains default translations.
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
		//get services
		$di       = \Phalcon\DI::getDefault();
		$app_name = $di->getShared("config")->name;

		$data = [
			"ACCOUNT" => [
				"CURRENT_PASS_EMPTY" => "Para modificar tu contraseña debes ingresar tu contraseña actual.",
				"PASS_TOO_SHORT"     => "Debes ingresar una contraseña de al menos 8 caracteres.",
				"PASS_DONT_MATCH"    => "Tu contraseña actual no es correcta.",
				"NEW_PASS_EQUALS"    => "Tu nueva contraseña debe ser diferente a la actual.",
				"INVALID_NAME"       => "Tu nombre no parece ser válido.",
				"PROFILE_SAVED"      => "Los cambios han sido guardados."
			],
			"AUTH" => [
				"AUTH_FAILED"        => "El correo ó contraseña no son válidos.",
				"ACCOUNT_PENDING"    => "Te hemos enviado un correo de activación. Haz click <a href=\"#0\">aquí</a> si no has recibido este correo.",
				"ACCOUNT_DISABLED"   => "Esta cuenta se encuentra desactivada, por favor comunícate con nuestro equipo.",
				"ACCOUNT_NOT_FOUND"  => "Esta cuenta no se encuentra registrada.",
				"ACTIVATION_SUCCESS" => "¡Tu cuenta ha sido activada!",
				"ACTIVATION_PENDING" => "Te hemos enviado un correo a {email} para que actives tu cuenta.",
				"INVALID_NAME"       => "Tu nombre no parece ser válido.",
				"RECAPTCHA_FAILED"   => "No hemos logrado verficar el reCaptcha, por favor inténtalo de nuevo."
			],
			"MAILER" => [
				"SUBJECT_ACTIVATION" => "Confirma tu cuenta",
				"SUBJECT_PASSWORD"   => "Recupera tu contraseña"
			],
			"PASSWORD" => [
				"ACCOUNT_NOT_FOUND" => "Esta cuenta no se encuentra registrada o no ha sido activada.",
				"PASS_MAIL_SENT"    => "Te hemos enviado un correo a {email} para recuperar tu contraseña.",
				"NEW_PASS_SAVED"    => "Tu contraseña ha sido guardada.",
				"PASS_TOO_SHORT"    => "Debes ingresar una contraseña de al menos 8 caracteres.",
				"CREATE_PASS"       => "Crea tu nueva contraseñas",
				"RECAPTCHA_FAILED"  => "No hemos logrado verficar el reCaptcha, por favor inténtalo de nuevo."
			],
			"UPLOADER" => [
				"MAX_SIZE"       => "El archivo {file} excede el máximo tamaño permitido de {size}.",
				"FILE_TYPE"      => "El archivo {file} no es soportado.",
				"IMG_WIDTH"      => "La imagen {file} tiene un ancho distinto de {w}px.",
				"IMG_HEIGHT"     => "La imagen {file} tiene un alto distinto de {h}px.",
				"IMG_MIN_WIDTH"  => "La imagen {file} debe tener un ancho de al menos {w}px.",
				"IMG_MIN_HEIGHT" => "La imagen {file} debe tener una altura de al menos {h}px.",
				"IMG_RATIO"      => "La imagen {file} debe tener un ratio de {r}.",
			]
		];

		//facebook
		if(class_exists("\FacebookController")) {

			$fb_email_settings_url = "https://www.facebook.com/settings?tab=account&section=email&view";

			$data["FACEBOOK"] = [
				"SESSION_ERROR"    => "Ocurrió un problema con tu sesión de Facebook, por favor inténtalo nuevamente. ".
									  "Si aún se presenta este problema, prueba iniciando una nueva sesión en Facebook.",
				"OAUTH_REDIRECTED" => "Ocurrió un problema con tu sesión de Facebook, por favor inténtalo nuevamente.",
				"OAUTH_PERMS"      => "Debes aceptar los permisos de la aplicación en tu cuenta de Facebook.",
				"SESSION_SWITCHED" => "Es posible que tengas abierta otra sesión de Facebook, intenta cerrando tu sesión actual de Facebook.",
				"ACCOUNT_SWITCHED" => "Esta sesión de Facebook está vinculada a otra cuenta ".$app_name.", intenta con otra cuenta en Facebook.",
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
	 * Javascript Translations
	 * @static
	 */
	public static function getJsTranslations()
	{
		//get services
		$di       = \Phalcon\DI::getDefault();
		$app_name = $di->getShared("config")->name;

		$data = [
			"ALERTS" => [
				"SERVER_ERROR"     => "Ha ocurrido algo inesperado, por favor inténtalo más tarde.",
				"SERVER_TIMEOUT"   => "Sin conexión. Revisa tu conexión a Internet e inténtalo de nuevo.",
				"NOT_FOUND"        => "Este enlace está roto, por favor inténtalo más tarde.",
				"ACCESS_FORBIDDEN" => "Tu sesión ha caducado, debes iniciar sesión nuevamente.",
				"CSRF"             => "Esta página ha estado inactiva por mucho tiempo, refréscala haciendo ".
										"<a href=\"javascript:location.reload();\">click aquí</a>.",
				"LOADING"          => "cargando ...",
				"REDIRECTING"      => "redireccionado ...",
			],
			"ACTIONS" => [
				"OK"       => "Ok",
				"ACCEPT"   => "Aceptar",
				"CANCEL"   => "Cancelar",
				"NOT_NOW"  => "Ahora No",
				"SEND"     => "Enviar",
				"GOT_IT"   => "Entendido",
				"DELETE"   => "Eliminar",
				"CONTINUE" => "Continuar",
				"ACTIVATE" => "Activar"
			],
			"MAILER" => [
				"SENT" => "¡Hemos recibido tu mensaje! Te responderemos a la brevedad."
			],
			"CRUD" => [
				"SAVED"          => "Datos guardados.",
				"UPDATED"        => "Datos actualizados.",
				"EMPTY_SEARCH"   => "Sin resultados.",
				"INFO_RESULTS"   => "Del {from} al {to} de {total} registros.",
				"DELETE_CONFIRM" => "¿Estás seguro que quieres eliminar este registro?"
			]
		];

		//call handler
		$data = array_merge($data, self::jsTranslations());

		return $data;
	}
}
