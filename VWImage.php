<?php
/**
 * Vertiacal Writing Image
 *
 * @author massat
 * @copyright Copyright(c) 2009 Murashiki co.,ltd.
 * @license http://seeds.ville.jp/vwimage/LICENSE
 */
require_once dirname(__FILE__) . '/VWInvalidParameterException.php';

/**
 * Vertiacal Writing Image
 *
 */
class VWImage
{
    // 画像フォーマット
    const PNG_FORMAT    = 'png';
    const JPG_FORMAT    = 'jpg';
    const GIF_FORMAT    = 'gif';

    // 水平位置
    const ALIGN_RIGHT   = 'right';
    const ALIGN_CENTER  = 'center';
    const ALIGN_LEFT    = 'left';

    // 垂直位置
    const VALIGN_TOP    = 'top';
    const VALIGN_MIDDLE = 'middle';
    const VALIGN_BOTTOM = 'bottom';

    private static $encoding    = 'utf-8';
    private static $canvas_size = 800;

    private $width;     // 画像幅
    private $height;    // 画像高
    private $align;     // 水平位置
    private $valign;    // 垂直位置
    private $font;      // フォント
    private $font_size; // 文字サイズ(px)
    private $color;     // 文字色
    private $bg_color;  // 背景色
    private $format;    // 画像形式

    private $lines;
    private $image;

    /**
     * constructor
     *
     * @param string $string 描画する文字列
     * @param array $params  描画パラメーター
     * @throws VWInvalidParameterException
     *
     * * params (default)
     *  - font-size : 文字サイズ       (40)
     *  - color     : 文字色           (000000)
     *  - bg-color  : 背景色           (FFFFFF)
     *  - format    : 画像フォーマット (png)
     *  - width     : 画像幅
     *  - height    : 画像高
     *  - align     : 水平位置         (right)
     *  - valign    : 垂直位置         (top)
     *  - font      : フォント         (ipam)
     */
    public function __construct($string, array $params = array())
    {
        $string = mb_convert_kana($string, 'AK', self::$encoding); // 半角 -> 全角
        $params = $this->validate_params($params);

        $default_styles = self::get_default_styles();
        $this->lines    = self::explode_string($string);

        $this->font_size = isset($params['font-size']) ? $params['font-size'] : $default_styles['font-size'];
        $this->color     = isset($params['color'])     ? $params['color']     : $default_styles['color'];
        $this->bg_color  = isset($params['bg-color'])  ? $params['bg-color']  : $default_styles['bg-color'];
        $this->format    = isset($params['format'])    ? $params['format']    : $default_styles['format'];
        $this->width     = isset($params['width'])     ? $params['width']     : $this->calc_image_width();
        $this->height    = isset($params['height'])    ? $params['height']    : $this->calc_image_height();
        $this->align     = isset($params['align'])     ? $params['align']     : $default_styles['align'];
        $this->valign    = isset($params['valign'])    ? $params['valign']    : $default_styles['valign'];
        $this->font      = isset($params['font'])      ? $params['font']      : $default_styles['font'];

        $this->create_image();
    }

    /**
     * destructor
     *
     */
    function __destruct()
    {
        if(is_resource($this->image)) imagedestroy($this->image);
    }

    /**
     * 画像データを返すメソッド
     * コンストラクタで指定した画像形式のバイナリデータを返す
     *
     * @return string
     */
    public function image_data()
    {
        $image_data = null;
        switch($this->format) {
            case self::PNG_FORMAT:
                $image_data = $this->to_png();
                break;
            case self::GIF_FORMAT:
                $image_data = $this->to_gif();
                break;
            case self::JPG_FORMAT:
                $image_data = $this->to_jpg();
                break;
        }
        return $image_data;
    }

    /**
     * 画像データをPNG形式で取得するメソッド
     *
     * @return string
     */
    public function to_png()
    {
        ob_start();
        imagepng($this->image, null, 1);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }

    /**
     * 画像データをGIF形式で取得するメソッド
     *
     * @return string
     */
    public function to_gif()
    {
        ob_start();
        imagegif($this->image);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }

    /**
     * 画像データをJPG形式で取得するメソッド
     *
     * @return string
     */
    public function to_jpg()
    {
        ob_start();
        imagejpeg($this->image);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }

    /**
     * コンストラクタで指定した画像形式を取得するメソッド
     *
     * @return string png|gif|jpg
     */
    public function get_format() {
        return $this->format;
    }

    //////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    //////////////////////////////////////////////////////

    // 画像生成 /////////////////

    private function create_image()
    {
        $this->create_image_base();
        $this->paste_strings();
    }

    private function create_image_base()
    {
        $image           = imagecreatetruecolor($this->width, $this->height);
        list($r, $g, $b) = self::hex_to_rgb($this->bg_color);

        $background = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $this->width, $this->height, $background);

        $this->image = $image;
    }

    private function paste_strings()
    {
        $offset_x = $this->calc_offset_x();
        $offset_y = $this->calc_offset_y();
        $this->lines = array_reverse($this->lines);
        foreach($this->lines as $i => $string) {
            $offset_x += $i * $this->font_size;
            $this->paste_string($string, $offset_x, $offset_y);
        }
    }
    
    
    private function paste_string($string, $offset_x, $offset_y)
    {
        $canvas_size        = self::$canvas_size;
        $canvas             = imagecreatetruecolor($canvas_size, $canvas_size);

        $bg_color           = self::hex_to_rgb($this->bg_color);
        $color              = self::hex_to_rgb($this->color);
        $bg_color           = imagecolorallocate($canvas, $bg_color[0], $bg_color[1], $bg_color[2]);
        $color              = imagecolorallocate($canvas, $color[0], $color[1], $color[2]);

        $length             = mb_strlen($string, self::$encoding);

        for($i=0; $i<$length; $i++) {
            $char  = mb_substr($string, $i, 1, self::$encoding);
            $y     = $offset_y + $i * $this->font_size;

            imageFilledRectangle($canvas, 0, 0, $canvas_size, $canvas_size, $bg_color);  // 背景色で塗りつぶして使いまわす
            imageTTFText($canvas, $canvas_size * 72 / 96, 0, 0, $canvas_size * 0.9, $color, $this->font, $char);
            
            // 一部の文字は回転
            if(($angle = self::get_rotate_angle($char)) !== 0) {
                $canvas = imagerotate($canvas, $angle, $bg_color, true);
            }
            imageCopyResampled($this->image, $canvas, $offset_x, $y, 0, 0, $this->font_size, $this->font_size, $canvas_size, $canvas_size);
        }
        imagedestroy($canvas);
    }


    // ヘルパー /////////////////

    private static function get_default_styles()
    {
        return array('width'     => null,
                     'height'    => null,
                     'align'     => self::ALIGN_RIGHT,
                     'valign'    => self::VALIGN_TOP,
                     'font'      => 'ipam',
                     'font-size' => 40,
                     'color'     => '000000',
                     'bg-color'  => 'FFFFFF',
                     'format'    => self::PNG_FORMAT,
                     'scale'     => 2);
    }

    private static function validate_params(array $params)
    {
        $clean = array();

        if(isset($params['font-size'])) {
            $font_size = $params['font-size'];
            if(!ctype_digit($font_size) || !$font_size) {
                throw new VHInvalidParameterException("invalid font-size: {$font_size}");
            }
            $clean['font-size'] = $font_size;
        }

        if(isset($params['color'])) {
            $color  = $params['color'];
            $length = strlen($color);
            if(!preg_match('/^[0-9a-f]+$/i', $color) || $length != 6) {
                throw new VHInvalidParameterException("invalid color: {$color}");
            }
            $clean['color'] = $color;
        }

        if(isset($params['bg-color'])) {
            $bg_color  = $params['bg-color'];
            $length = strlen($bg_color);
            if(!preg_match('/^[0-9a-f]+$/i', $bg_color) || $length != 6) {
                throw new VHInvalidParameterException("invalid bg-color: {$bg_color}");
            }
            $clean['bg-color'] = $bg_color;
        }

        if(isset($params['width'])) {
            $width = $params['width'];
            if(!ctype_digit($width) || !$width) {
                throw new VHInvalidParameterException("invalid width: {$width}");
            }
            $clean['width'] = $width;
        }

        if(isset($params['height'])) {
            $height = $params['height'];
            if(!ctype_digit($height) || !$height) {
                throw new VHInvalidParameterException("invalid height: {$height}");
            }
            $clean['height'] = $height;
        }

        if(isset($params['align'])) {
            $align = $params['align'];
            if(!in_array($align, array(self::ALIGN_RIGHT, self::ALIGN_CENTER, self::ALIGN_LEFT))) {
                throw new VHInvalidParameterException("invalid align: {$align}");
            }
            $clean['align'] = $align;
        }

        if(isset($params['valign'])) {
            $valign = $params['valign'];
            if(!in_array($valign, array(self::VALIGN_TOP, self::VALIGN_MIDDLE, self::VALIGN_BOTTOM))) {
                throw new VHInvalidParameterException("invalid valign: {$valign}");
            }
            $clean['valign'] = $valign;
        }

        if(isset($params['font'])) {
            $font       = $params['font'];
            $font_paths = explode(PATH_SEPARATOR, getenv('GDFONTPATH'));
            $found      = false;
            foreach($font_paths as $font_path) {
                if(is_file(realpath($font_path) . "/{$font}.ttf")) {
                    $found = true;
                    break;
                }
            }
            if(!$found) {
                throw new VHInvalidParameterException("invalid font: {$font}");
            }
            $clean['font'] = $font;
        }

        if(isset($params['format'])) {
            $format = $params['format'];
            if(!in_array($format, array(self::PNG_FORMAT, self::GIF_FORMAT, self::JPG_FORMAT))) {
                throw new VHInvalidParameterException("invalid format: {$format}");
            }
            $clean['format'] = $format;;
        }
        return $clean;
    }

    private function calc_image_width()
    {
        return count($this->lines) * $this->font_size;
    }

    private function calc_image_height()
    {
        $max_length = 0;
        foreach($this->lines as $line) {
            $max_length = max($max_length, mb_strlen($line, self::$encoding) * $this->font_size);
        }
        return $max_length;
    }

    private function calc_offset_x()
    {
        $offset = 0;
        switch($this->align) {
            case self::ALIGN_RIGHT:
                $offset = $this->width - $this->calc_image_width();
                break;
            case self::ALIGN_CENTER:
                $offset = ($this->width - $this->calc_image_width()) / 2;
                break;
            case self::ALIGN_LEFT:
                $offset = 0;
        }
        return $offset;
    }

    private function calc_offset_y()
    {
        $offset = 0;
        switch($this->valign) {
            case self::VALIGN_TOP:
                $offset = 0;
                break;
            case self::VALIGN_MIDDLE:
                $offset = ($this->height - $this->calc_image_height()) / 2;
                break;
            case self::VALIGN_BOTTOM:
                $offset = $this->height - $this->calc_image_height();
                break;
        }
        return $offset;
    }

    private static function get_rotate_angle($char)
    {
        $angle = 0;
        if(strpos('－ー–−—「」[]［］【】()（）=＝', $char) !== false) $angle = -90;
        if(strpos('、。', $char) !== false) $angle = 180;

        return $angle;
    }

    private static function explode_string($string)
    {
        $string = str_replace(array("\r\n", "\r"), "\n", $string);
        return explode("\n", $string);
    }

    private static function hex_to_rgb($hex)
    {
        $r = substr($hex, 0, 2);
        $g = substr($hex, 2, 2);
        $b = substr($hex, 4, 2);
        return array(hexdec($r), hexdec($g), hexdec($b));
    }
}

/**
 * GDがフォントを検索するパスを指定
 *
 */
function fix_font_path()
{
    $font_dir = dirname(__FILE__) . '/font/';
    putenv("GDFONTPATH={$font_dir}" . PATH_SEPARATOR . getenv('GDFONTPATH'));
    $entries  = scandir($font_dir);
    foreach($entries as $entry) {
        $path = $font_dir . $entry;
        if(is_dir($path) && !preg_match('/^\.+$/', $entry)) putenv("GDFONTPATH={$path}" . PATH_SEPARATOR . getenv('GDFONTPATH'));
    }
}
fix_font_path();