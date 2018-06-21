<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/6/18
 * Time: 18:58
 */

namespace QueryBuilder;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class QueryBuilder
 * @package QueryBuilder
 * @author  Ivan Pianetti <ivanpianetti23@gmail.com>
 * @link    https://github.com/bipiane/criteria-builder
 */
class QueryBuilder
{
    /**
     * Transform parameters to a list of criteria required for Doctrine QueryBuilder.
     * @param Request $request
     * @return array
     */
    public static function getCriterias(Request $request)
    {
        $criterias = [];
        foreach ($request->query->all () as $param => $value){
            if (is_array ($value)){
                foreach ($value as $criteria => $valor){
                    array_push ($criterias, [$param, QueryBuilder::formatCriteria ($criteria), QueryBuilder::formatValue ($value)]);
                }
            }else{
                array_push ($criterias, [$param, QueryBuilder::formatCriteria ('eq'), QueryBuilder::formatValue ($value)]);
            }
        }
        return $criterias;
    }

    /**
     * Format REST criterias in Doctrine criteria.
     * @param $criteria
     * @return string
     */
    private static function formatCriteria($criteria)
    {
        $resp = '=';
        switch(strtolower ($criteria)){
            case 'ge':
                $resp = '>=';
                break;
            case 'gt':
                $resp = '>';
                break;
            case 'le':
                $resp = '<=';
                break;
            case 'lt':
                $resp = '<';
                break;
            case 'ne':
                $resp = '!=';
                break;
            case 'like':
            case 'ilike':
                $resp = 'LIKE';
                break;
            default;
                break;
        }

        return $resp;
    }

    /**
     * Format special values
     * @param $value
     * @return string
     */
    private static function formatValue($value)
    {
        if ($value === true || strtolower ($value) === 'true'){
            $value = '1';
        }elseif ($value === false || strtolower ($value) === 'false'){
            $value = '0';
        }
        return $value;
    }
}
