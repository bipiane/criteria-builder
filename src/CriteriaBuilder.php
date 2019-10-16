<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 12:45
 */

namespace bipiane;

use Doctrine\ORM\QueryBuilder;

/**
 * Class CriteriaBuilder
 * @package bipiane
 * @author  Ivan Pianetti <ivanpianetti23@gmail.com>
 * @link    https://github.com/bipiane/criteria-builder
 */
class CriteriaBuilder
{
    /**
     * Agrega a una Doctrine QueryBuilder filtros por parámetos HTTP
     * @param QueryBuilder $qb Repositorio de la clase
     * @param array $queryHTTP
     * @param array $criteriasHabilitadas lista de criterias habilitadas para filtrar de la clase
     * @param int $maxLimit
     * @param string $defaulSort
     * @param string $defaulOrder
     * @return QueryBuilder
     */
    public static function fetchFromQuery(
        QueryBuilder $qb,
        array $queryHTTP,
        array $criteriasHabilitadas,
        $maxLimit = 100,
        $defaulSort = 'id',
        $defaulOrder = 'ASC'
    )
    {
        // Obtenemos las criterias desde la Query REST
        $criterias = CriteriaDoctrine::obtenerCriterias($queryHTTP, $criteriasHabilitadas);

        $objAliasName = $qb->getRootAliases()[0];
        $sortParams = "{$objAliasName}.{$defaulSort}";
        $orderParams = $defaulOrder;
        $offset = null;
        $limit = null;
        $joins = [];

        foreach ($criterias as $item) {
            if ($item instanceof CriteriaDoctrine) {
                // Obtenemos la lista de joins necesarios para QueryBuilder
                $joins = CriteriaDoctrine::obtenerLeftJoins($joins, $objAliasName, $item);

                $param = $item->param;
                $valor = $item->valor;

                // Creamos query Doctrine si la criteria no es de paginación u ordenación
                if (!$item->isPaginacion()) {
                    $valores = explode(',', $valor);
                    if (sizeof($valores) > 1) {
                        $orCriteria = $qb->expr()->orX();
                        foreach ($valores as $v) {
                            $orCriteria->add($qb->expr()->eq("${objAliasName}.${param}", "'" . $v . "'"));
                        }
                        $qb->andWhere($orCriteria);
                    } else {
                        $paramName = "param_${objAliasName}_${param}";
                        $qb = CriteriaDoctrine::crearQuery($qb, $objAliasName, $item, $paramName);
                    }
                } else {
                    if ($param == 'sort') {
                        $sortParams = $objAliasName . '.' . $valor;
                    }
                    if ($param == 'order') {
                        $orderParams = $valor;
                    }
                    if ($param == 'offset') {
                        $offset = $valor;
                    }
                    if ($param == 'limit') {
                        $limit = $valor;
                    }
                }
            }
        }

        // Agregamos los joins necesarios para poder ordenear objetos
        $joins = CriteriaDoctrine::obtenerLeftJoinsOrder($joins, $objAliasName, $sortParams);

        // Cargamos los joins a QueryBuilder
        foreach ($joins as $joinCriteria) {
            if ($joinCriteria instanceof JoinCriteria) {
                $qb->leftJoin($joinCriteria->join, $joinCriteria->alias);
            }
        }

        // Ordenamos por los últimos 2 campos. Ej: 'parada.localidad.provincia.id' => 'provincia.id'
        $sortParams = implode('.', array_slice(explode('.', $sortParams), -2));
        $qb->orderBy($sortParams, $orderParams);
        $qb->setMaxResults(min($limit ? $limit : 10, $maxLimit));
        $qb->setFirstResult($offset ? $offset : 0);

        return $qb;
    }
}
