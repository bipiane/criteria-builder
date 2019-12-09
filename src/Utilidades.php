<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 13:12
 */

namespace bipiane;

/**
 * Class Utilidades
 * @package bipiane
 */
class Utilidades
{
    /**
     * Determina si un valor existe dentro de un array de objetos
     * @param $value
     * @param $array
     * @param $attr_by
     * @return bool
     */
    public static function existInList($value, $array, $attr_by)
    {
        foreach ($array as $obj) {
            if ($obj->{'get' . ucfirst($attr_by)}() == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina si un array tiene sub arrays
     * @param $array
     * @return bool
     */
    public static function containsArray($array)
    {
        if (is_array($array)) {
            foreach ($array as $value) {
                if (is_array($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
