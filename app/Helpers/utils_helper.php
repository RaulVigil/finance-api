<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

if (!function_exists('token_generator')) {
    function token_generator($length = 16)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}

if (!function_exists('base_url_api')) {
    function base_url_api($ruta = '')
    {
        return base_url('api/' . $ruta);
    }
}








