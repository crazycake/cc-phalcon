<?php

namespace CrazyCake\Qr;

/**
 * QrTagShape Abstract Class
 * @abstract
 */
abstract class QrTagShape {

    /**
     * @var int
     */
    public $size = 0;

    /**
     * @var int
     */
    public $markerSize = 0;

    /**
     * @var string hex color
     */
    public $color = '000000'; //black

    /**
     * @var string hex color
     */
    public $bgColor = 'ffffff'; //white
    public $bgColorRGB = array(255, 255, 255);

    /**
     *
     * @param int $size
     */
    public function __construct() {

        //constructor
    }

    /**
     * @return resource $image
     */
    public abstract function generate();

    /**
     * @param string $color
     * @return array
     */
    public static function hex2dec($color) {
        return array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2)));
    }

    /**
     *
     * @param string $text
     * @param string $fontFile
     * @param int $fontSize
     * @param int $fontAngle
     * @return array
     */
    function calculateTextBox($text, $fontFile, $fontSize, $fontAngle) {
        /*         * **********
          simple function that calculates the *exact* bounding box (single pixel precision).
          The function returns an associative array with these keys:
          left, top:  coordinates you will pass to imagettftext
          width, height: dimension of the image you have to create
         * *********** */
        $rect = imagettfbbox($fontSize, $fontAngle, $fontFile, $text);
        $minX = min(array($rect[0], $rect[2], $rect[4], $rect[6]));
        $maxX = max(array($rect[0], $rect[2], $rect[4], $rect[6]));
        $minY = min(array($rect[1], $rect[3], $rect[5], $rect[7]));
        $maxY = max(array($rect[1], $rect[3], $rect[5], $rect[7]));

        return array(
            "left" => abs($minX) - 1,
            "top" => abs($minY) - 1,
            "width" => $maxX - $minX,
            "height" => $maxY - $minY,
            "box" => $rect
        );
    }

    public function generateMarkerFrame($shape, $fillHoles = true) {
        $markerFrame = imagecreatetruecolor($this->size * 7, $this->size * 7);
        imagefilledrectangle($markerFrame, 0, 0, $this->size * 7, $this->size * 7, imagecolorallocate($markerFrame, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


        if ($fillHoles) {
            $sx = imagesx($shape);
            $sy = imagesy($shape);
            $color = $this->hex2dec($this->color);

            $im2 = imagecreate($sx, $sy);
            imagefilledrectangle($im2, 0, 0, $sx, $sy, imagecolorallocatealpha($im2, $color[0], $color[1], $color[2], 40));
        }

        for ($x = 0; $x < 7; $x++) {
            for ($y = 0; $y < 7; $y++) {
                if ($x == 0 || $x == 6 || $y == 0 || $y == 6) {
                    if ($fillHoles) {
                        self::copy($markerFrame, $im2, $y * $this->size, $x * $this->size, $this->size, $this->size);
                    }
                    self::copy($markerFrame, $shape, $y * $this->size, $x * $this->size, $this->size, $this->size);
                }
            }
        }

        return $markerFrame;
    }

    public function attachMarkerDot(&$frame, $dot) {
        $fw = imagesx($frame);
        $fh = imagesy($frame);
        $dw = imagesx($dot);
        $dh = imagesy($dot);

        self::copy($frame, $dot, $fh / 2 - $dh / 2, $fw / 2 - $dw / 2, $dw, $dh);
    }

    public static function copy(&$dst, $src, $dy, $dx, $w, $h) {
        $tmpImg = imagecreatetruecolor($w, $h);
        imagecopy($tmpImg, $dst, 0, 0, $dy, $dx, $w, $h);
        imagecopy($tmpImg, $src, 0, 0, 0, 0, $w, $h);
        imagecopymerge($dst, $tmpImg, $dy, $dx, 0, 0, $w, $h, 100);
        imagedestroy($tmpImg);
    }

}

/**
 * QrTagEffect Abstract Class
 * @abstract
 */
abstract class QrTagEffect extends QrTagShape {

    // _|_
    public $imUp;
    // _|
    //  |
    public $imLeft;
    // |_
    // |
    public $imRight;
    // ___
    //  |
    public $imDown;
    // _
    //  |
    public $imTopRight;
    //   _
    //  |
    public $imTopLeft;
    //   _|
    //
    public $imBottomRight;
    //   |_
    //
    public $imBottomLeft;
    //   o
    //
    public $imAlone;
    public $imSquare;

}

/**
 * QrTag Class
 */
class QrTag {

    public $error_level = QR_ECLEVEL_M;
    public $text = "";
    public $data = array();
    public $rows = 0;
    public $cols = 0;
    public $width = 0;
    public $height = 0;
    public $image = null;
    public $file = '';
    public $bgColor = 'ffffff';
    public $bgColorRGB = array(255, 255, 255);

    /**
     *
     * @var QrTagShape
     */
    public $dot = null;
    public $dotImg = null;

    /**
     *
     * @var QrTagShape
     */
    public $frameDot = null;
    public $frameDotImg = null;

    /**
     *
     * @var QrTagShape
     */
    public $frame = null;
    public $frameImg = null;

    public function __construct() {
    }

    protected function isPixelMarker($x, $y) {
        if (
                (($x >= 0 && $x <= 6) && ($y >= 0 && $y <= 6)) ||
                (($x >= $this->cols - 7 && $x <= $this->cols - 1) && ($y >= 0 && $y <= 6)) ||
                (($x >= 0 && $x <= 6) && ($y >= $this->cols - 7 && $y <= $this->cols - 1))
        ) {
            return true;
        }
    }

    public function setDot(QrTagShape $dot) {
        $this->dot = $dot;
    }

    private function populateData() {
        $this->bgColorRGB = QrTagShape::hex2dec($this->bgColor);
        $data = QRCode::text($this->text, false, $this->error_level);
        $data = array_map('str_split', $data);
        array_walk_recursive($data, 'intval');
        $this->data = $data;


        if($this->dot instanceof QrTagShape && !($this->dot instanceof QrTagEffect)) {
            $this->dot->bgColorRGB = $this->bgColorRGB;
            $this->dotImg = $this->dot->generate();

            if (!is_resource($this->dotImg)) {
                throw new Exception('Dot must generate a valid image resource.');
            }
        }
        else if($this->dot instanceof QrTagEffect) {
            $this->dot->bgColorRGB = $this->bgColorRGB;
            $this->dot->generate();
        }

        if (!($this->frameDot instanceof QrTagShape)) {
            $this->frameDot->bgColorRGB = $this->bgColorRGB;
            throw new Exception('Frame Dot must be instance of QrTagShape class.');
        }

        $this->frameDot->size = $this->dot->markerSize ? $this->dot->markerSize : $this->dot->size;
        $this->frameDotImg = $this->frameDot->generate();

        if (!($this->frame instanceof QrTagShape)) {
            throw new Exception('Frame must be instance of QrTagShape class.');
        }
        $this->frame->bgColorRGB = $this->bgColorRGB;

        $this->frame->size = $this->dot->size;
        $this->frameImg = $this->frame->generate();


        $this->cols = count($this->data[0]);
        $this->rows = count($this->data);
        $this->width = $this->cols * $this->dot->size;
        $this->height = $this->rows * $this->dot->size;
        $this->image = imagecreatetruecolor($this->width, $this->height);

        // transparent
        imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, imagecolorallocate($this->image, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
    }

    public function generate() {
        $this->populateData();

        for ($x = 0; $x < $this->rows; $x++) {
            for ($y = 0; $y < $this->cols; $y++) {
                if ($this->data[$x][$y] == 1 && !$this->isPixelMarker($x, $y)) {

                    if ($this->dot instanceof QrTagEffect) {
                        // _|_
                        if (empty($this->data[$x][$y - 1]) && empty($this->data[$x][$y + 1]) && empty($this->data[$x - 1][$y]) && !empty($this->data[$x + 1][$y])) {
                            QrTagShape::copy($this->image, $this->dot->imUp, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        // _|
                        //  |
                        else if (empty($this->data[$x][$y - 1]) && !empty($this->data[$x][$y + 1]) && empty($this->data[$x - 1][$y]) && empty($this->data[$x + 1][$y])) {
                            QrTagShape::copy($this->image, $this->dot->imLeft, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        // |_
                        // |
                        else if (!empty($this->data[$x][$y - 1]) && empty($this->data[$x][$y + 1]) && empty($this->data[$x - 1][$y]) && empty($this->data[$x + 1][$y])) {
                            QrTagShape::copy($this->image, $this->dot->imRight, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        // ___
                        //  |
                        else if (empty($this->data[$x + 1][$y]) && empty($this->data[$x][$y + 1]) && empty($this->data[$x][$y - 1]) && !empty($this->data[$x - 1][$y])) {
                            QrTagShape::copy($this->image, $this->dot->imDown, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        // _
                        //  |
                        else if (!empty($this->data[$x][$y - 1]) && !empty($this->data[$x + 1][$y]) && empty($this->data[$x - 1][$y]) && empty($this->data[$x][$y + 1])) {
                            QrTagShape::copy($this->image, $this->dot->imTopRight, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        //   _
                        //  |
                        else if (!empty($this->data[$x + 1][$y]) && !empty($this->data[$x][$y + 1]) && empty($this->data[$x - 1][$y]) && empty($this->data[$x][$y - 1])) {
                            QrTagShape::copy($this->image, $this->dot->imTopLeft, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        //   _|
                        //
                        else if (!empty($this->data[$x - 1][$y]) && !empty($this->data[$x][$y - 1]) && empty($this->data[$x + 1][$y]) && empty($this->data[$x][$y + 1])) {
                            QrTagShape::copy($this->image, $this->dot->imBottomRight, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        //   |_
                        //
                        else if (!empty($this->data[$x - 1][$y]) && !empty($this->data[$x][$y + 1]) && empty($this->data[$x + 1][$y]) && empty($this->data[$x][$y - 1])) {
                            QrTagShape::copy($this->image, $this->dot->imBottomLeft, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                        //   o
                        //
                        else if (empty($this->data[$x - 1][$y]) && empty($this->data[$x + 1][$y]) && empty($this->data[$x][$y - 1]) && empty($this->data[$x][$y + 1])) {
                            QrTagShape::copy($this->image, $this->dot->imAlone, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        } else {
                            QrTagShape::copy($this->image, $this->dot->imSquare, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                        }
                    }
                    else {
                        QrTagShape::copy($this->image, $this->dotImg, $this->dot->size * $y, $this->dot->size * $x, $this->dot->size, $this->dot->size);
                    }
                }
            }
        }

        $this->frame->attachMarkerDot($this->frameImg, $this->frameDotImg);

        $qw = $this->width;
        $qh = $this->height;
        $fw = imagesx($this->frameImg);
        $fh = imagesy($this->frameImg);

        QrTagShape::copy($this->image, $this->frameImg, 0, 0, $fw, $fh);
        QrTagShape::copy($this->image, $this->frameImg, $qw - $fw, 0, $fw, $fh);
        QrTagShape::copy($this->image, $this->frameImg, 0, $qh - $fh, $fw, $fh);

        $w = $this->width + $this->width * 0.05;
        $h = $this->height + $this->height * 0.05;
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
        imagecopy($im, $this->image, $w/2 - $this->width/2, $h/2 - $this->height/2, 0, 0, $this->width, $this->height);

        imagepng($im, $this->file);
    }

    public static function installedShapes() {
        $path = QR_ASSETS_PATH;
        $out = array();

        // get all dots
        $i = 0;
        $shapes = glob($path . 'QrTagDot*.png');
        foreach ($shapes as $shape) {
            $filename = pathinfo($shape, PATHINFO_FILENAME);
            $out['dots'][$filename == 'QrTagDotSquare' ? count($shapes) + 1 : $i++] = $filename;
        }

        // get all frames
        $shapes = glob($path . 'QrTagFrame*.png');
        $i = 0;
        $ii = 0;
        foreach ($shapes as $shape) {
            $filename = pathinfo($shape, PATHINFO_FILENAME);
            if (Utility::beginsWith($filename, 'QrTagFrameDot')) {
            //$out['frame_dots'][$i++] = $filename;
                $out['frame_dots'][$filename == 'QrTagFrameDotSquare' ? count($shapes) + 1 : $i++] = $filename;
            } else {
            //$out['frames'][$ii++] = $filename;
                $out['frames'][$filename == 'QrTagFrameSquare' ? count($shapes) + 1 : $ii++] = $filename;
            }
        }

        krsort($out['dots']);
        krsort($out['frame_dots']);
        krsort($out['frames']);

        return $out;
    }
}

/**
 * QrTagFrameTwoSquare Class
 */
if(!class_exists('QrTagFrameTwoSquare', FALSE)){
    class QrTagFrameTwoSquare extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_SQUARETHINGS;
            $letter = 'G';
            $rect = $this->calculateTextBox($letter, $font, $this->size/1.3, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);
            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
            imagettftext($im, $this->size/1.35, 0, -$rect['width']/20, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$this->size = $rect['width'];
            $frame = $this->generateMarkerFrame($im);

            return $frame;
        }

    }
}

/**
 * QrTagFrameSquare Class
 */
if(!class_exists('QrTagFrameSquare', FALSE)){
    class QrTagFrameSquare extends QrTagShape {
        public function generate() {
            $color = $this->hex2dec($this->color);

            $tmp = imagecreatetruecolor($this->size, $this->size);
            imagefilledrectangle($tmp, 0, 0, $this->size, $this->size, imagecolorallocate($tmp, $color[0], $color[1], $color[2]));
            $frame = $this->generateMarkerFrame($tmp);

            return $frame;
        }
    }
}

/**
 * QrTagFrameGrid Class
 */
if(!class_exists('QrTagFrameGrid', FALSE)){
    class QrTagFrameGrid extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_SQUARETHINGS;
            $letter = 'g';
            $rect = $this->calculateTextBox($letter, $font, $this->size/1.3, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);
            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
            imagettftext($im, $this->size/1.35, 0, -$rect['width']/20, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $frame = $this->generateMarkerFrame($im);

            return $frame;
        }
    }
}

/**
 * QrTagFrameDotSquare Class
 */
if(!class_exists('QrTagFrameDotSquare', FALSE)){
    class QrTagFrameDotSquare extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $im = imagecreatetruecolor($this->size*3, $this->size*3);
            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
            imagefilledrectangle($im, 0, 0, $this->size*3, $this->size*3, $color);

            return $im;
        }
    }
}

/**
 * QrTagFrameDot9 Class
 */
if(!class_exists('QrTagFrameDot9', FALSE)){
    class QrTagFrameDot9 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);
            $font = QR_FONT_PATH_DOTS;
            $letter = 'i';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot7 Class
 */
if(!class_exists('QrTagFrameDot7', FALSE)){
    class QrTagFrameDot7 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'g';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot3 Class
 */
if(!class_exists('QrTagFrameDot3', FALSE)){
    class QrTagFrameDot3 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot18 Class
 */
if(!class_exists('QrTagFrameDot18', FALSE)){
    class QrTagFrameDot18 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot16 Class
 */
if(!class_exists('QrTagFrameDot16', FALSE)){
    class QrTagFrameDot16 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'q';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot15 Class
 */
if(!class_exists('QrTagFrameDot15', FALSE)){
    class QrTagFrameDot15 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'o';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot14 Class
 */
if(!class_exists('QrTagFrameDot14', FALSE)){
    class QrTagFrameDot14 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'p';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot12 Class
 */
if(!class_exists('QrTagFrameDot12', FALSE)){
    class QrTagFrameDot12 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'm';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameDot11 Class
 */
if(!class_exists('QrTagFrameDot11', FALSE)){
    class QrTagFrameDot11 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_DOTS;
            $letter = 'l';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
            //$rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

            //$this->attachMarkerDot($frame, $im);
            return $im;
        }
    }
}

/**
 * QrTagFrameAngle Class
 */
if(!class_exists('QrTagFrameAngle', FALSE)){
    class QrTagFrameAngle extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_SQUARETHINGS;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size/1.35, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);
            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
            imagettftext($im, $this->size/1.35, 0, -$rect['width']/20, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $frame = $this->generateMarkerFrame($im);

            return $frame;
        }
    }
}

/**
 * QrTagFrame9 Class
 */
if(!class_exists('QrTagFrame9', FALSE)){
    class QrTagFrame9 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'k';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame7 Class
 */
if(!class_exists('QrTagFrame7', FALSE)){
    class QrTagFrame7 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'i';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame6 Class
 */
if(!class_exists('QrTagFrame6', FALSE)){
    class QrTagFrame6 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'g';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame5 Class
 */
if(!class_exists('QrTagFrame5', FALSE)){
    class QrTagFrame5 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame3 Class
 */
if(!class_exists('QrTagFrame3', FALSE)){
    class QrTagFrame3 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame2 Class
 */
if(!class_exists('QrTagFrame2', FALSE)){
    class QrTagFrame2 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame16 Class
 */
if(!class_exists('QrTagFrame16', FALSE)){
    class QrTagFrame16 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'r';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame13 Class
 */
if(!class_exists('QrTagFrame13', FALSE)){
    class QrTagFrame13 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'o';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagFrame12 Class
 */
if(!class_exists('QrTagFrame12', FALSE)){
    class QrTagFrame12 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_FRAMES;
            $letter = 'n';
            $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            //$frame = $this->generateMarkerFrame($im, false);
            return $im;
            //return $frame;
        }
    }
}

/**
 * QrTagDotSquare Class
 */
if(!class_exists('QrTagDotSquare', FALSE)){
    class QrTagDotSquare extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);
            $im = imagecreatetruecolor($this->size, $this->size);
            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
            imagefilledrectangle($im, 0, 0, $this->size, $this->size, $color);
            $this->image = $im;
            $this->markerSize = $this->size/1.01;
            return $im;
        }
    }
}

/**
 * QrTagDotButterfly Class
 */
if(!class_exists('QrTagDotButterfly', FALSE)){
    class QrTagDotButterfly extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_HAROP;
            $letter = 'B';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $rect['width'] += 1;
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, -$rect['width'] / 20, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot9 Class
 */
if(!class_exists('QrTagDot9', FALSE)){
    class QrTagDot9 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'i';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot8 Class
 */
if(!class_exists('QrTagDot8', FALSE)){
    class QrTagDot8 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'h';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot7 Class
 */
if(!class_exists('QrTagDot7', FALSE)){
    class QrTagDot7 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'g';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}


/**
 * QrTagDot6 Class
 */
if(!class_exists('QrTagDot6', FALSE)){
    class QrTagDot6 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot5 Class
 */
if(!class_exists('QrTagDot5', FALSE)){
    class QrTagDot5 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot4 Class
 */
if(!class_exists('QrTagDot4', FALSE)){
    class QrTagDot4 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot3 Class
 */
if(!class_exists('QrTagDot3', FALSE)){
    class QrTagDot3 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot23 Class
 */
if(!class_exists('QrTagDot23', FALSE)){
    class QrTagDot23 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;

            $circleImg = new QrTagDot11();
            $circleImg->size = $this->size / 1.34;
            $circleImg->color = $this->color;
            $circleImg->generate();



            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'M';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'O';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);


            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'L';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'N';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopLeft, true);
            $trans_colour = imagecolorallocate($this->imTopLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomLeft, true);
            $trans_colour = imagecolorallocate($this->imBottomLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomRight, true);
            $trans_colour = imagecolorallocate($this->imBottomRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopRight, true);
            $trans_colour = imagecolorallocate($this->imTopRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // alone
            $this->imAlone = $circleImg->image;

            $this->markerSize = $rect['width'] / 1.33;
        }
    }
}

/**
 * QrTagDot21 Class
 */
if(!class_exists('QrTagDot21', FALSE)){
    class QrTagDot21 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;


            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'D';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'F';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);


            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'G';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'E';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopLeft, true);
            $trans_colour = imagecolorallocate($this->imTopLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomLeft, true);
            $trans_colour = imagecolorallocate($this->imBottomLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomRight, true);
            $trans_colour = imagecolorallocate($this->imBottomRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopRight, true);
            $trans_colour = imagecolorallocate($this->imTopRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // alone
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imAlone = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imAlone, true);
            $trans_colour = imagecolorallocate($this->imAlone, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imAlone, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imAlone, $color[0], $color[1], $color[2]);
            imagettftext($this->imAlone, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            $this->markerSize = $rect['width'] / 1.33;
        }
    }
}

/**
 * QrTagDot20 Class
 */
if(!class_exists('QrTagDot20', FALSE)){
    class QrTagDot20 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;


            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'z';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'B';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.38), $color, $font, $letter);


            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'C';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.34), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'A';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopLeft, true);
            $trans_colour = imagecolorallocate($this->imTopLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomLeft, true);
            $trans_colour = imagecolorallocate($this->imBottomLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomRight, true);
            $trans_colour = imagecolorallocate($this->imBottomRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopRight, true);
            $trans_colour = imagecolorallocate($this->imTopRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // alone
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imAlone = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imAlone, true);
            $trans_colour = imagecolorallocate($this->imAlone, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imAlone, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imAlone, $color[0], $color[1], $color[2]);
            imagettftext($this->imAlone, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            $this->markerSize = $rect['width'] / 1.33;
        }

    }
}

/**
 * QrTagDot2 Class
 */
if(!class_exists('QrTagDot2', FALSE)){
    class QrTagDot2 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'b';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot17 Class
 */
if(!class_exists('QrTagDot17', FALSE)){
    class QrTagDot17 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;

            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 's';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'p';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopLeft, true);
            $trans_colour = imagecolorallocate($this->imTopLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'q';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 't';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomLeft, true);
            $trans_colour = imagecolorallocate($this->imBottomLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomRight, true);
            $trans_colour = imagecolorallocate($this->imBottomRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopRight, true);
            $trans_colour = imagecolorallocate($this->imTopRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // alone
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imAlone = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imAlone, true);
            $trans_colour = imagecolorallocate($this->imAlone, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imAlone, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imAlone, $color[0], $color[1], $color[2]);
            imagettftext($this->imAlone, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            $this->markerSize = $rect['width'] / 1.33;
        }
    }
}

/**
 * QrTagDot14 Class
 */
if(!class_exists('QrTagDot14', FALSE)){
    class QrTagDot14 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;

            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'g';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'h';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $this->imTopLeft = $squareImg->image;

            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'i';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'j';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $this->imBottomLeft = $squareImg->image;

            // bottom right
            $this->imBottomRight = $squareImg->image;

            // top right
            $this->imTopRight = $squareImg->image;

            // alone
            $this->imAlone = $squareImg->image;

            $this->markerSize = $rect['width'] / 1.33;
        }
    }
}

/**
 * QrTagDot12 Class
 */
if(!class_exists('QrTagDot12', FALSE)){
    class QrTagDot12 extends QrTagEffect {

        public function generate() {

            $squareImg = new QrTagDotSquare();
            $squareImg->size = $this->size;
            $squareImg->color = $this->color;
            $squareImg->generate();

            $this->imSquare = $squareImg->image;

            // right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'r';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imRight, true);
            $trans_colour = imagecolorallocate($this->imRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'l';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imLeft, true);
            $trans_colour = imagecolorallocate($this->imLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'c';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopLeft, true);
            $trans_colour = imagecolorallocate($this->imTopLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // up
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'a';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imUp = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imUp, true);
            $trans_colour = imagecolorallocate($this->imUp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imUp, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imUp, $color[0], $color[1], $color[2]);
            imagettftext($this->imUp, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // down
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'b';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imDown, true);
            $trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imDown, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
            imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom left
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'e';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomLeft = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomLeft, true);
            $trans_colour = imagecolorallocate($this->imBottomLeft, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomLeft, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomLeft, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomLeft, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // bottom right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'd';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imBottomRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imBottomRight, true);
            $trans_colour = imagecolorallocate($this->imBottomRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imBottomRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imBottomRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imBottomRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // top right
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'u';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imTopRight = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imTopRight, true);
            $trans_colour = imagecolorallocate($this->imTopRight, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imTopRight, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imTopRight, $color[0], $color[1], $color[2]);
            imagettftext($this->imTopRight, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            // alone
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_EDGES;
            $letter = 'f';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $this->imAlone = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($this->imAlone, true);
            $trans_colour = imagecolorallocate($this->imAlone, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
            imagefill($this->imAlone, 0, 0, $trans_colour);
            $color = imagecolorallocate($this->imAlone, $color[0], $color[1], $color[2]);
            imagettftext($this->imAlone, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);

            $this->markerSize = $rect['width'] / 1.33;
        }
    }
}

/**
 * QrTagDot11 Class
 */
if(!class_exists('QrTagDot11', FALSE)){
    class QrTagDot11 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'k';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot10 Class
 */
if(!class_exists('QrTagDot10', FALSE)){
    class QrTagDot10 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'j';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}

/**
 * QrTagDot1 Class
 */
if(!class_exists('QrTagDot1', FALSE)){
    class QrTagDot1 extends QrTagShape {

        public function generate() {
            $color = $this->hex2dec($this->color);

            $font = QR_FONT_PATH_BDOTS;
            $letter = 'a';
            $rect = $this->calculateTextBox($letter, $font, $this->size, 0);
            $im = imagecreatetruecolor($rect['width'], $rect['width']);
            imagesavealpha($im, true);
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);

            $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

            imagettftext($im, $this->size, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
            $this->size = $rect['width'];
            $this->image = $im;
            return $im;
        }
    }
}
