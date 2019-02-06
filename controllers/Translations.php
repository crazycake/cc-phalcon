<?php
/**
 * Translations, contains default translations.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.tech>
 */

namespace CrazyCake\Controllers;

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
	 * Default Controllers Translations
	 * @param String $controller - The Controller name
	 */
	public static function defaultCoreTranslations($controller = "")
	{
		$data = [
			"ACCOUNT" => [
				"AUTH_FAILED"        => "El correo ó contraseña no son válidos.",
				"AUTH_BLOCKED"       => "Por seguridad, esta cuenta se encuentra temporalmente bloqueada.",
				"STATE_PENDING"      => "Te hemos enviado un correo de activación a <b>{email}</b>.",
				"STATE_DISABLED"     => "Esta cuenta se encuentra desactivada, por favor comunícate con nuestro equipo.",
				"NOT_FOUND"          => "Esta cuenta no se encuentra registrada o no ha sido activada.",
				"EMAIL_EXISTS"       => "El correo <b>{email}</b> ya se encuentra registrado.",
				"ACTIVATION_SUCCESS" => "¡Tu cuenta ha sido activada!",
				"ACTIVATION_PENDING" => "Te hemos enviado un correo a <b>{email}</b> para que actives tu cuenta.",
				"NOT_HUMAN"          => "🤖 ¿Eres un robot? Por favor <a href=\"javascript:location.reload();\">refresca la aplicación</a> ó inténtalo más tarde.",
				"INVALID_NAME"       => "Tu nombre y apellido deben ser válidos.",
				"INVALID_EMAIL"      => "Tu correo electrónico no es válido.",
				"PASS_TOO_SHORT"     => "Debes ingresar una contraseña de al menos 8 caracteres.",
				"CREATE_PASS"        => "Crea tu nueva contraseña",
				"CURRENT_PASS_EMPTY" => "Para modificar tu contraseña debes ingresar tu contraseña actual.",
				"PASS_DONT_MATCH"    => "Tu contraseña actual no es correcta.",
				"NEW_PASS_EQUALS"    => "Tu nueva contraseña debe ser diferente a la actual.",
				"NEW_PASS_SAVED"     => "Tu contraseña ha sido guardada.",
				"PASS_MAIL_SENT"     => "Te hemos enviado un correo a <b>{email}</b> para recuperar tu contraseña."
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
				"IMG_RATIO"      => "La imagen {file} debe tener un ratio {r}."
			]
		];

		// call handler
		$data = array_replace_recursive($data, self::coreTranslations());

		// return key translations
		return $data[strtoupper($controller)] ?? [];
	}

	/**
	 * Default Javascript Translations (Sent to view)
	 */
	public static function defaultJsTranslations()
	{
		$data = [
			"ALERTS" => [
				"SERVER_ERROR"     => "Ha ocurrido algo inesperado, por favor inténtalo más tarde.",
				"SERVER_TIMEOUT"   => "Ha ocurrido un problema de conexión, por favor inténtalo nuevamente.",
				"NOT_FOUND"        => "Esta acción está deshabilitada, por favor inténtalo más tarde.",
				"ACCESS_FORBIDDEN" => "Tu sesión ha caducado, debes iniciar sesión nuevamente.",
				"CSRF"             => "La aplicación ha estado inactiva por mucho tiempo, refréscala haciendo ".
										"<a href=\"javascript:location.reload();\">click aquí</a>.",
				"LOADING"          => "cargando ...",
				"REDIRECTING"      => "redireccionado ..."
			]
		];

		// call handler
		return array_replace_recursive($data, self::jsTranslations());
	}
}
