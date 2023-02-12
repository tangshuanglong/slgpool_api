<?php

namespace Lib\Helper;

/**
 * Json helper
 *
 * @since 2.0
 */
class JsonHelper
{
    /**
     * Wrapper for json_decode that throws when an error occurs.
     *
     * @param string $json    JSON data to parse
     * @param bool   $assoc   When true, returned objects will be converted
     *                        into associative arrays.
     * @param int    $depth   User specified recursion depth.
     * @param int    $options Bitmask of JSON decode options.
     *
     * @return mixed
     * @link http://www.php.net/manual/en/function.json-decode.php
     */
    public static function decode(string $json, bool $assoc = false, int $depth = 512, int $options = 0)
    {
        $data = json_decode($json, $assoc, $depth, $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        return $data;
    }

    /**
     * Wrapper for JSON encoding that throws when an error occurs.
     *
     * @param mixed $value   The value being encoded
     * @param int   $options JSON encode option bitmask
     * @param int   $depth   Set the maximum depth. Must be greater than zero.
     * @return string
     * @link http://www.php.net/manual/en/function.json-encode.php
     */
    public static function encode($value, int $options = 0, int $depth = 512): string
    {
        $json = json_encode($value, $options, $depth);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        return $json;
    }

    /**
     * verify json format
     * @param $json JSON data to parse
     * @return bool
     */
    public static function verify_json($json)
    {
        json_decode($json);
        if (JSON_ERROR_NONE !== json_last_error()){
            return false;
        }
        return true;
    }
}