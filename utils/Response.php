<?php

class Response {
    public static function send($code, $data) {
        http_response_code($code);
        echo json_encode($data);
        exit();
    }
}
