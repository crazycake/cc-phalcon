<?php
/**
 * PDF: PDF helper to generate PDF files
 * Requires Snappy composer library and wkhtmltopdf library.
 * @author Nicolas Pulido <nicolas.pulido@crazycake.cl>
 */

namespace CrazyCake\Helpers;

//imports
use Phalcon\Exception;
use Knp\Snappy\Pdf as PDFMaker; //PDF renderizer

/**
 * PDF Helper
 */
class PDF
{
    /**
     * The wkhtmltopdf binary location
     * @var string
     */
    private $wkhtmltopdf;

    /**
     * The snappy library
     * @var string
     */
    private $snappy;

    /**
     * Contructor
     */
    function __construct()
    {
        //OSX or UbuntuServer
        $this->wkhtmltopdf = (php_uname('s') == "Darwin") ? "/usr/local/bin/wkhtmltopdf" : "/usr/local/bin/wkhtmltopdf.sh";
        //instance with binary path
        $this->snappy = new PDFMaker($this->wkhtmltopdf);
        //set options
        $this->snappy->setOption('lowquality', false);
        $this->snappy->setOption('page-width', 700);
    }

    /**
     * Generates a PDF file from HTML
     * @param string $html - The html input
     * @param string $output_path - The output file path
     * @param boolean $binary - Binary output flag
     * @return mixed [string|binary]
     */
    public function generatePdfFileFromHtml($html, $output_path, $binary = true)
    {
        if(empty($html))
            throw new Exception("PDF::generatePdfFileFromHtml -> The html input string is required.");

        if(empty($output_path))
            throw new Exception("PDF::generatePdfFileFromHtml -> The output_path input string is required.");

        //remove file?
        if(is_file($output_path))
            unlink($output_path);

        //generate the PDF file!
        try {
            $this->snappy->generateFromHtml($html, $output_path);
        }
        catch(\Exception $e) {
            throw new Exception("PDF::generatePdfFileFromHtml -> Snappy library error: ".$e->getMessage());
        }

        //get binary file?
        if($binary)
            return file_get_contents($output_path);
        else
            return $output_path;
    }

    /**
     * Merge PDF files
     * @param array $files - The file paths array
     * @param string $output - The file paths array
     * @param string $options - Options: 'file', 'browser', 'download', 'string'
     */
    public function mergePdfFiles($files = array(), $output = "pdf_merged.pdf", $option = "browser")
    {
        if(empty($files))
            throw new Exception("PDF::mergePdfFiles -> Input files is empty");

        //Merge PDFs
        try {
            $pdf = new \Clegginabox\PDFMerger\PDFMerger();
        }
        catch (Exception $e) {
            throw new Exception("PDF::mergePdfFiles -> \Clegginabox\PDFMerger\PDFMerger class is required");
        }

        foreach ($files as $f)
            $pdf->addPDF($f);

        //merge files
        $pdf->merge($option, $output);
    }
}
