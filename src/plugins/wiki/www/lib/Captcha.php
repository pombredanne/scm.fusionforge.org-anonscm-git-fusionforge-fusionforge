<?php
/**
 * Session Captcha v1.0
 *   by Gavin M. Roy <gmr@bteg.net>
 * Modified by Benjamin Drieu <bdrieu@april.org> - 2005 for PhpWiki
 * get_captcha_random_word() contributed by Dan Frankowski 2005 for PhpWiki
 * objectified and randomized 2005 by Reini Urban
 *
 * This File is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This File is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with This File; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

class Captcha
{
    public $meta;
    public $width;
    public $height;
    public $length;
    public $failed_msg;
    /**
     * @var WikiRequest $request
     */
    public $request;

    function __construct($meta = array(), $width = 250, $height = 80)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $this->meta =& $meta;
        $this->width = $width;
        $this->height = $height;
        $this->length = 8;
        $this->failed_msg = _("Typed in verification word mismatch ... are you a bot?");
        $this->request =& $request;
    }

    function captchaword()
    {
        if (!$this->request->getSessionVar('captchaword')) {
            $this->request->setSessionVar('captchaword', $this->get_word());
        }
        return $this->request->getSessionVar('captchaword');
    }

    function Failed()
    {
        if ($this->request->getSessionVar('captcha_ok') == true)
            return false;

        if (!array_key_exists('captcha_input', $this->meta)
            or ($this->request->getSessionVar('captchaword')
                and ($this->request->getSessionVar('captchaword') != $this->meta['captcha_input']))
        )
            return true;

        $this->request->setSessionVar('captcha_ok', true);
        return false;
    }

    function getFormElements()
    {
        $el = array();
        if (!$this->request->getSessionVar('captcha_ok')) {
            $el['CAPTCHA_INPUT']
                = HTML::input(array('type' => 'text',
                'class' => 'wikitext',
                'id' => 'edit-captcha_input',
                'name' => 'edit[captcha_input]',
                'size' => $this->length + 2,
                'maxlength' => 256));
            $url = WikiURL("", array("action" => "captcha", "id" => time()), false);
            $el['CAPTCHA_IMAGE'] = HTML::img(array('src' => $url, 'alt' => 'captcha'));
            $el['CAPTCHA_LABEL'] = HTML::label(array('for' => 'edit-captcha_input'),
                _("Type word above:"));
        }
        return $el;
    }

    function get_word()
    {
        if (defined('USE_CAPTCHA_RANDOM_WORD') and USE_CAPTCHA_RANDOM_WORD)
            return $this->get_dictionary_word();
        else
            return rand_ascii_readable($this->length); // lib/stdlib.php
    }

    function get_dictionary_word()
    {
        // Load In the Word List
        $fp = fopen(findFile("lib/captcha/dictionary"), "r");
        $text = array();
        while (!feof($fp))
            $text[] = trim(fgets($fp, 1024));
        fclose($fp);

        // Pick a Word
        $word = "";
        while (strlen(trim($word)) == 0) {
            $x = mt_rand(0, count($text));
            return $text[$x];
        }
        return '';
    }

    // Draw the Spiral
    function spiral(&$im, $origin_x = 100, $origin_y = 100, $r = 0, $g = 0, $b = 0)
    {
        $theta = 1;
        $thetac = 6;
        $radius = 15;
        $circles = 10;
        $points = 35;
        $lcolor = imagecolorallocate($im, $r, $g, $b);
        for ($i = 0; $i < ($circles * $points) - 1; $i++) {
            $theta = $theta + $thetac;
            $rad = $radius * ($i / $points);
            $x = ($rad * cos($theta)) + $origin_x;
            $y = ($rad * sin($theta)) + $origin_y;
            $theta = $theta + $thetac;
            $rad1 = $radius * (($i + 1) / $points);
            $x1 = ($rad1 * cos($theta)) + $origin_x;
            $y1 = ($rad1 * sin($theta)) + $origin_y;
            imageline($im, $x, $y, $x1, $y1, $lcolor);
            $theta = $theta - $thetac;
        }
    }

    function image($word)
    {
        $width =& $this->width;
        $height =& $this->height;

        // Create the Image
        $jpg = imagecreate($width, $height);
        $bg = imagecolorallocate($jpg, 255, 255, 255);
        $tx = imagecolorallocate($jpg, 185, 140, 140);
        imagefilledrectangle($jpg, 0, 0, $width, $height, $bg);

        $x = rand(0, $width);
        $y = rand(0, $height);
        $this->spiral($jpg, $x, $y, $width - 25, 190, 190);

        $x = rand(10, 30);
        $y = rand(50, $height - 20); //50-60

        // randomize the chars
        $angle = 0;
        for ($i = 0; $i < strlen($word); $i++) {
            $angle += rand(-5, 5);
            if ($angle > 25) $angle = 15;
            elseif ($angle < -25) $angle = -15;
            $size = rand(14, 20);
            $y += rand(-10, 10);
            if ($y < 10) $y = 11;
            elseif ($y > $height - 10) $y = $height - 11;
            $x += rand($size, $size * 2);
            imagettftext($jpg, $size, $angle, $x, $y, $tx,
                realpath(findFile("lib/captcha/Vera.ttf")),
                $word[$i]);
        }

        $x = rand(0, $width + 30);
        $y = rand(0, $height + 35); // 115
        $this->spiral($jpg, $x, $y, 255, 190, 190);

        imageline($jpg, 0, 0, $width - 1, 0, $tx);
        imageline($jpg, 0, 0, 0, $height - 1, $tx);
        imageline($jpg, 0, $height - 1, $width - 1, $height - 1, $tx);
        imageline($jpg, $width - 1, 0, $width - 1, $height - 1, $tx);

        if (function_exists("imagejpeg")) {
            header("Content-type: image/jpeg");
            imagejpeg($jpg);
        } elseif (function_exists("imagepng")) {
            header("Content-type: image/png");
            imagepng($jpg);
        } elseif (function_exists("imagegif")) {
            header("Content-type: image/gif");
            imagegif($jpg);
        } else {
            trigger_error("missing GD bitmap support", E_USER_WARNING);
        }
    }

}
