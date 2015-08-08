<?php
/**
 * PdfHelper: PDF helper to generate PDF files
 * Requires Snappy composer library and wkhtmltopdf library.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Utils;

//imports
use Phalcon\Exception;
use Knp\Snappy\Pdf;

class PdfHelper
{
    /**
     * The wkhtmltopdf binary location
     * @var string
     */
    private $wkhtmltopdf;

    /**
     * The snappy lirary
     * @var string
     */
    private $snappy;

    /**
     * contructor
     */
    function __construct() {

        //OSX or UbuntuServer
        $this->wkhtmltopdf = (php_uname('s') == "Darwin") ? "/usr/local/bin/wkhtmltopdf" : "/usr/local/bin/wkhtmltopdf.sh";
        //instance with binary path
        $this->snappy = new Pdf($this->wkhtmltopdf);
        //set options
        $this->snappy->setOption('lowquality', false);
        $this->snappy->setOption('page-width', 700);
    }

    /**
     * Generates a PDF file from HTML
     * @param string $html The html input
     * @param string $output_path The output file path
     * @param boolean $binary Return output as binary
     * @return mixed
     */
    public function generatePdfFileFromHtml($html, $output_path, $binary = true)
    {
        if(empty($html))
            throw new Exception("PdfHelper::generatePdfFileFromHtml -> The html input string is required.");

        if(empty($output_path))
            throw new Exception("PdfHelper::generatePdfFileFromHtml -> The output_path input string is required.");

        //remove file?
        if(is_file($output_path))
            unlink($output_path);

        //generate the PDF file!
        try {
            $this->snappy->generateFromHtml($html, $output_path);
        }
        catch(\Exception $e) {
            throw new Exception("PdfHelper::generatePdfFileFromHtml -> Snappy library error: ".$e->getMessage());
        }

        //get binary file?
        if($binary)
            return file_get_contents($output_path);
        else
            return $output_path;
    }
}
