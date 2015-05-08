<?php
/**
 * Slug Helper for namespaces
 * @author Andres Gutierrez <andres@phalconphp.com>
 * @author Nikolaos Dimopoulos <nikos@niden.net>
 * @contributor Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;

class Slug
{
    /**
     * Creates a slug to be used for pretty URLs
     * @static
     * @link http://cubiq.org/the-perfect-php-clean-url-generator
     * @param string $string
     * @param array $replace placeholders to be replaced
     * @param string $delimiter
     * @return mixed
     */
    public static function generate($string, $replace = array(), $delimiter = '-')
    {
        if (!extension_loaded('iconv'))
            throw new Exception('iconv module not loaded');

        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);

        if (!empty($replace))
            $clean = str_replace((array) $replace, ' ', $clean);

        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower($clean);
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        $clean = trim($clean, $delimiter);

        // Revert back to the old locale
        setlocale(LC_ALL, $oldLocale);

        return $clean;
    }
}
