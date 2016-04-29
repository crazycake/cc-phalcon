<?php
/**
 * Slug Helper for namespaces
 * @author Andres Gutierrez <andres@phalconphp.com>
 * @author Nikolaos Dimopoulos <nikos@niden.net>
 * @contributor Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;

/**
 * Slug Helper
 */
class Slug
{
    /**
     * Creates a slug to be used for pretty URLs
     * @static
     * @link http://cubiq.org/the-perfect-php-clean-url-generator
     * @param string $string - The input string
     * @param array $replace - Placeholders to be replaced
     * @param string $delimiter - A delimiter
     * @return mixed
     */
    public static function generate($string = "", $replace = [], $delimiter = "-")
    {
        if (!extension_loaded("iconv")) {
            throw new Exception("Slug -> iconv php module not loaded");
        }

        // Save the old locale and set the new locale to UTF-8
        $old_locale = setlocale(LC_ALL, "0");
        setlocale(LC_ALL, "en_US.UTF-8");

        $clean = iconv("UTF-8", "ASCII//TRANSLIT", $string);

        if (!empty($replace)) {
            $clean = str_replace((array) $replace, " ", $clean);
        }

        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", "", $clean);
        $clean = strtolower($clean);
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        $clean = trim($clean, $delimiter);
        // Revert back to the old locale
        setlocale(LC_ALL, $old_locale);

        return $clean;
    }
}
