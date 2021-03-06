<?php

namespace LearnositySdk\Utils;

class Json
{
    /**
     * Returns the last error, if relevant, from calls to json_encode()
     *
     * @return string Message detailing the last json_encode error
     */
    public static function checkError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $msg = null;
                break;
            case JSON_ERROR_DEPTH:
                $msg = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $msg = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $msg = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $msg = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $msg = 'Unknown error';
                break;
        }
        return $msg;
    }

    /**
     * Encodes a PHP array into a JSON string. Has settings
     * to unescape both slashes and unicode characters.
     *
     * @param  array  $array Value to convert to JSON
     *
     * @return string JSON encoded string
     */
    public static function encode($array, $options = null)
    {
        $result = false;
        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $jsonOptions = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
            if (!empty($options)) {
                foreach ($options as $o) {
                    $jsonOptions += $o;
                }
            }
            $result = json_encode($array, (int)$jsonOptions);
        } else {
            $result = json_encode($array);

            // Unicode fix: http://stackoverflow.com/a/2934602
            $result = preg_replace_callback(
                '/\\\\u([0-9a-f]{4})/i',
                function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                },
                $result
            );
            // Escaped slashes fix
            $result = str_replace('\/', '/', $result);
        }

        return $result;
    }

    /**
     * Trying to open a file and read json from there
     * @param  string  $path   path to the resource
     * @param  boolean $decode whether to decode
     * @param  boolean $assoc  whether to decode to associative array
     * @return mixed           json string, json object, json array or false on failure
     */
    public static function getFromFile($path, $decode = false, $assoc = true)
    {
        if (is_readable($path)) {
            $json = utf8_encode(file_get_contents($path));
            if ($decode) {
                $json = json_decode($json, $assoc);
            }
            return $json;
        }
        return false;
    }

    /**
     * Returns whether the string value is valid JSON
     *
     * @param string $val A string value to test
     *
     * @return boolean
     */
    public static function isJson($val)
    {
        return !is_null(json_decode($val, true));
    }
}
