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
	 * Overridable CoreTranslations
	 */
	public static function coreTranslations($trans) { return []; }

	/*
	 * Overridable CoreTranslations
	 */
	public static function jsTranslations($trans) { return []; }

	/**
	 * Default Controllers Translations
	 * @param String $controller - The Controller name
	 */
	public static function defaultCoreTranslations($controller = "")
	{
		$dic = [
			"ACCOUNT" => [
				"AUTH_FAILED"        => "El correo ó contraseña no son válidos.",
				"AUTH_BLOCKED"       => "Por seguridad, esta cuenta se encuentra temporalmente bloqueada.",
				"STATE_PENDING"      => "Te hemos enviado un correo de activación a <b>{email}</b>.",
				"STATE_DISABLED"     => "Esta cuenta se encuentra desactivada, por favor comunícate con nuestro equipo.",
				"NOT_FOUND"          => "Esta cuenta no se encuentra registrada o no ha sido activada.",
				"EMAIL_EXISTS"       => "El correo <b>{email}</b> ya se encuentra registrado.",
				"ACTIVATION_SUCCESS" => "¡Tu cuenta ha sido activada!",
				"ACTIVATION_PENDING" => "Te hemos enviado un correo a <b>{email}</b> para que actives tu cuenta.",
				"NOT_HUMAN"          => "🤖 Para asegurarnos que no eres un robot por favor haz <a href=\"#\">click aquí</a> e inténtalo nuevamente.",
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
		self::overwrite($dic, static::coreTranslations((\Phalcon\DI::getDefault())->getShared("trans")));

		// return key translations
		return $dic[strtoupper($controller)] ?? [];
	}

	/**
	 * Default Javascript Translations (Sent to view)
	 */
	public static function defaultJsTranslations()
	{
		$dic = [
			"ALERTS" => [
				"SERVER_ERROR"     => "Ha ocurrido algo inesperado, por favor inténtalo más tarde.",
				"SERVER_TIMEOUT"   => "Ha ocurrido un problema de conexión, por favor inténtalo nuevamente.",
				"NOT_FOUND"        => "Esta acción está deshabilitada, por favor inténtalo más tarde.",
				"ACCESS_FORBIDDEN" => "Tu sesión ha caducado, debes iniciar sesión nuevamente.",
				"CSRF"             => "La aplicación ha estado inactiva por mucho tiempo, refréscala haciendo ".
										"<a href=\"javascript:location.reload();\">click aquí</a>."
			]
		];

		// call handler
		self::overwrite($dic, static::jsTranslations((\Phalcon\DI::getDefault())->getShared("trans")));

		return $dic;
	}

	/**
	 * Array combine with overwrite
	 */
	private static function overwrite(&$original, $overwrite)
	{
		foreach ($overwrite as $key => $value) {

			if (array_key_exists($key, $original) && is_array($value))
				self::overwrite($original[$key], $overwrite[$key]);
			else
				$original[$key] = $value;
		}
	}
}
