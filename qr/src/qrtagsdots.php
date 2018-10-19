<?php
/**
 * QR Tags Dot
 * This file contains all tags dot classes
 * @link http://phpqrcode.sourceforge.net/
 */

namespace CrazyCake\Qr;

/**
 * QrTagDotLineH class
 */
class QrTagDotLineH extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreatetruecolor($this->size, $this->size);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledrectangle($im, 0,  $this->size*0.1, $this->size, $this->size - $this->size*0.1, $color);
		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDotCircle class
 */
class QrTagDotCircle extends QrTagShape {
	public function generate() {
		$color = $this->hex2dec($this->color);
		$color[] = 0;

		$tmp = imagecreatetruecolor($this->size, $this->size);
		imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
		imageSmoothArc($tmp, $this->size/2, $this->size/2, $this->size*0.7, $this->size*0.7, $color, 0, M_PI * 2);

		return $tmp;
	}
}

/**
 * QrTagDotLineV class
 */
class QrTagDotLineV extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreatetruecolor($this->size, $this->size);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledrectangle($im, $this->size*0.1,  0, $this->size - $this->size*0.1, $this->size, $color);
		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDotSquare2 class
 */
class QrTagDotSquare2 extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreatetruecolor($this->size, $this->size);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, $color[0], $color[1], $color[2], 127));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledrectangle($im, $this->size*0.1,  $this->size*0.1, $this->size - $this->size*0.1, $this->size - $this->size*0.1, $color);
		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDotSquare3 class
 */
class QrTagDotSquare3 extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreate($this->size, $this->size);
		imagefill($im, 0 ,0, imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledrectangle($im, 1, 1, $this->size/1.6, $this->size/1.6, $color);
		$im = imagerotate($im, 30, 0);

		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDotSquare4 class
 */
class QrTagDotSquare4 extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreate($this->size, $this->size);
		imagefill($im, 0 ,0, imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledrectangle($im, 1, 1, $this->size/1.6, $this->size/1.6, $color);
		$im = imagerotate($im, -30, 0);

		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDotSquare5 class
 */
class QrTagDotSquare5 extends QrTagShape {

	public function generate() {
		$color = $this->hex2dec($this->color);
		$im = imagecreate($this->size, $this->size);
		imagefill($im, 0 ,0, imagecolorallocate($im, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]));
		$color = imagecolorallocate($im, $color[0], $color[1], $color[2]);
		imagefilledpolygon($im, array(
			$this->size/2, 0,
			$this->size, $this->size/2,
			$this->size/2, $this->size,
			0, $this->size/2,
		), 4, $color);

		$this->image = $im;
		$this->markerSize = $this->size/1.01;
		return $im;
	}
}

/**
 * QrTagDot13 class
 */
class QrTagDot13 extends QrTagEffect {

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

/**
 * QrTagDot15 class
 */
class QrTagDot15 extends QrTagEffect {

	public function generate() {

		$squareImg = new QrTagDotSquare();
		$squareImg->size = $this->size;
		$squareImg->color = $this->color;
		$squareImg->generate();

		$this->imSquare = $squareImg->image;

		// right
		$color = $this->hex2dec($this->color);
		$font = QR_FONT_PATH_EDGES;
		$letter = 'm';
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
		$letter = 'o';
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
		$letter = 'n';
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
		$letter = 'k';
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

/**
 * QrTagDot18 class
 */
class QrTagDot18 extends QrTagEffect {

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

		// top left
		$this->imTopLeft = $squareImg->image;
		// bottom left
		$this->imBottomLeft = $squareImg->image;
		// bottom right
		$this->imBottomRight = $squareImg->image;
		// top right
		$this->imTopRight = $squareImg->image;

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

/**
 * QrTagDot19 class
 */
class QrTagDot19 extends QrTagEffect {

	public function generate() {

		$squareImg = new QrTagDotSquare();
		$squareImg->size = $this->size;
		$squareImg->color = $this->color;
		$squareImg->generate();

		$this->imSquare = $squareImg->image;

		// right
		$color = $this->hex2dec($this->color);
		$font = QR_FONT_PATH_EDGES;
		$letter = 'v';
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
		$letter = 'x';
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
		$letter = 'y';
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
		$letter = 'w';
		$rect = $this->calculateTextBox($letter, $font, $this->size, 0);
		$this->imDown = imagecreatetruecolor($rect['width'], $rect['width']);
		imagesavealpha($this->imDown, true);
		$trans_colour = imagecolorallocate($this->imDown, $this->bgColorRGB[0], $this->bgColorRGB[1], $this->bgColorRGB[2]);
		imagefill($this->imDown, 0, 0, $trans_colour);
		$color = imagecolorallocate($this->imDown, $color[0], $color[1], $color[2]);
		imagettftext($this->imDown, $this->size / 1.33, 0, 0, $rect['top'] + ($rect['width'] / 2) - ($rect['width'] / 1.37), $color, $font, $letter);


		// top left
		$this->imTopLeft = $squareImg->image;
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

/**
 * QrTagDot22 class
 */
class QrTagDot22 extends QrTagEffect {

	public function generate() {

		$squareImg = new QrTagDotSquare();
		$squareImg->size = $this->size;
		$squareImg->color = $this->color;
		$squareImg->generate();

		$this->imSquare = $squareImg->image;

		// right
		$color = $this->hex2dec($this->color);
		$font = QR_FONT_PATH_EDGES;
		$letter = 'H';
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
		$letter = 'J';
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
		$letter = 'K';
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
		$letter = 'I';
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
