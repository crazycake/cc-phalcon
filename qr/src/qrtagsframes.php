<?php
/**
 * QR Tags Frames
 * This file contains all tags dot classes
 * @contributor Nicolas Pulido <nicolas.pulido@crazycake.cl>
 * @link http://phpqrcode.sourceforge.net/
 */

namespace CrazyCake\Qr;

/**
 * QrTagFrame4 Class
 */
class QrTagFrame4 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'e';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame8 Class
 */
class QrTagFrame8 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'j';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame10 Class
 */
class QrTagFrame10 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'l';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame11 Class
 */
class QrTagFrame11 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'm';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame14 Class
 */
class QrTagFrame14 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'p';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame15 Class
 */
class QrTagFrame15 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'q';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);
        return $im;
    }
}


/**
 * QrTagFrame17 Class
 */
class QrTagFrame17 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 's';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'] - ($rect['width'] / 50), $color, $font, $letter);
        return $im;
    }
}

/**
 * QrTagFrame18 Class
 */
class QrTagFrame18 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 't';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'], $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrame19 Class
 */
class QrTagFrame19 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'u';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'], $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrame20 Class
 */
class QrTagFrame20 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $font = QR_FONT_PATH_FRAMES;
        $letter = 'v';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.3)*7, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagefilledrectangle($im, 0, 0, $rect['width'], $rect['width'], imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));


        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.3)*7, 0, 0, $rect['top'], $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrame2Circle Class
 */
class QrTagFrameCircle extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $color[] = 0;

        $tmp = imagecreatetruecolor($this->size, $this->size);
        imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2], 127));
        imageSmoothArc($tmp, $this->size / 2, $this->size / 2, $this->size * 0.7, $this->size * 0.7, $color, 0, M_PI * 2);
        $frame = $this->generateMarkerFrame($tmp, false);

        return $frame;
    }
}

/**
 * QrTagFrame2Circle2 Class
 */
class QrTagFrameCircle2 extends QrTagShape {
    public function generate() {
        $color = $this->hex2dec($this->color);
        $color[] = 0;
        $bgColorRGB = $this->bgColorRGB;
        $bgColorRGB[] = 0;

        $tmp = imagecreatetruecolor($this->size*7.5, $this->size*7.5);
        imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        imageSmoothArc($tmp, $this->size*7.5/2, $this->size*7.5/2, $this->size*6.8, $this->size*6.8, $color, 0, M_PI * 2);
        imageSmoothArc($tmp, $this->size*7.5/2, $this->size*7.5/2, $this->size*7.5*0.7, $this->size*7.5*0.7, $bgColorRGB, 0, M_PI * 2);

        return $tmp;
    }
}

/**
 * QrTagFrameDot1 Class
 */
class QrTagFrameDot1 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'a';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagesavealpha($im, true);
        $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans_colour);

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrameDot4 Class
 */
class QrTagFrameDot4 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'd';
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

/**
 * QrTagFrameDot5 Class
 */
class QrTagFrameDot5 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'e';
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

/**
 * QrTagFrameDot6 Class
 */
class QrTagFrameDot6 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'f';
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

/**
 * QrTagFrameDot8 Class
 */
class QrTagFrameDot8 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'h';
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

/**
 * QrTagFrameDot17 Class
 */
class QrTagFrameDot17 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 't';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);

        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagesavealpha($im, true);
        $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans_colour);

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrameDot19 Class
 */
class QrTagFrameDot19 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $font = QR_FONT_PATH_DOTS;
        $letter = 'v';
        $rect = $this->calculateTextBox($letter, $font, ($this->size/1.4) * 3, 0);
        $im = imagecreatetruecolor($rect['width'], $rect['width']);
        imagesavealpha($im, true);
        $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $trans_colour);

        $color = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        imagettftext($im, ($this->size/1.4) * 3, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 2), $color, $font, $letter);

        return $im;
    }
}

/**
 * QrTagFrameDotFlower Class
 */
class QrTagFrameDotFlower extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $color[] = 0;

        $im = imagecreatetruecolor($this->size * 3.35, $this->size * 3.35);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
        $dotSize = 1;
        $movePerct = 0.8;
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 - $this->size * $dotSize * $movePerct, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 + $this->size * $dotSize * $movePerct, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2 + $this->size * $dotSize * $movePerct, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2 - $this->size * $dotSize * $movePerct, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);

        return $im;
    }
}

/**
 * QrTagFrameDotFlower2 Class
 */
class QrTagFrameDotFlower2 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $color[] = 0;

        $im = imagecreatetruecolor($this->size * 3.35, $this->size * 3.35);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
        $dotSize = 1;
        $movePerct = 0.8;
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 - $this->size * $dotSize * $movePerct, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 + $this->size * $dotSize * $movePerct, $this->size * 3.35 / 2, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2 + $this->size * $dotSize * $movePerct, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2, $this->size * 3.35 / 2 - $this->size * $dotSize * $movePerct, $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);

        imageSmoothArc($im, $this->size * 3.35 / 2 + $this->size * $dotSize * ($movePerct - 0.2), $this->size * 3.35 / 2 + $this->size * $dotSize * ($movePerct - 0.2), $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 - $this->size * $dotSize * ($movePerct - 0.2), $this->size * 3.35 / 2 - $this->size * $dotSize * ($movePerct - 0.2), $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 - $this->size * $dotSize * ($movePerct - 0.2), $this->size * 3.35 / 2 + $this->size * $dotSize * ($movePerct - 0.2), $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size * 3.35 / 2 + $this->size * $dotSize * ($movePerct - 0.2), $this->size * 3.35 / 2 - $this->size * $dotSize * ($movePerct - 0.2), $this->size * $dotSize, $this->size * $dotSize, $color, 0, M_PI * 2);

        return $im;
    }
}

/**
 * QrTagFrameDotTriCircleClass
 */
class QrTagFrameDotTriCircle extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);
        $color[] = 0;

        $im = imagecreatetruecolor($this->size*3.35, $this->size*3.35);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
        $dotSize = 1.7;
        $movePerct = 0.5;
        imageSmoothArc($im, $this->size*3.35/2, $this->size*3.35/2 - $this->size*$dotSize * ($movePerct - 0.2), $this->size*$dotSize, $this->size*$dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size*3.35/2 + $this->size*$dotSize * ($movePerct - 0.2), $this->size*3.35/2 + $this->size*$dotSize * ($movePerct - 0.2), $this->size*$dotSize, $this->size*$dotSize, $color, 0, M_PI * 2);
        imageSmoothArc($im, $this->size*3.35/2 - $this->size*$dotSize * ($movePerct - 0.2), $this->size*3.35/2 + $this->size*$dotSize * ($movePerct - 0.2), $this->size*$dotSize, $this->size*$dotSize, $color, 0, M_PI * 2);

        return $im;
    }
}

/**
 * QrTagFrameSquare2 Class
 */
class QrTagFrameSquare2 extends QrTagShape {

    public function generate() {
        $color = $this->hex2dec($this->color);

        $tmp = imagecreatetruecolor($this->size, $this->size);
        imagefilledrectangle($tmp, 0, 0, $this->size, $this->size, imagecolorallocate($tmp, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
        imagefilledrectangle($tmp, 0, 0, $this->size - $this->size * 0.1, $this->size - $this->size * 0.1, imagecolorallocate($tmp, $color[0], $color[1], $color[2]));
        $frame = $this->generateMarkerFrame($tmp);

        return $frame;
    }

}
