<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 13:09
 */

namespace bipiane;

use Doctrine\ORM\QueryBuilder;

/**
 * Class CriteriaDoctrine
 * @package bipiane
 * @author  Ivan Pianetti <ivanpianetti23@gmail.com>
 * @link    https://github.com/bipiane/criteria-builder
 */
class CriteriaDoctrine
{
    /**
     * Equal
     */
    const CRITERIA_EQ = 'eq';
    /**
     * Not Equal
     */
    const CRITERIA_NE = 'ne';
    /**
     * Greater and Equal
     */
    const CRITERIA_GE = 'ge';
    /**
     * Greater Than
     */
    const CRITERIA_GT = 'gt';
    /**
     * Less and Equal
     */
    const CRITERIA_LE = 'le';
    /*
     * Less Than
     */
    const CRITERIA_LT = 'lt';
    /**
     * Like/ilike
     */
    const CRITERIA_LIKE = 'like';

    /**
     * Criterias for numbers
     */
    const CRITERIAS_NUMBER = [
        CriteriaDoctrine::CRITERIA_EQ,
        CriteriaDoctrine::CRITERIA_NE,
        CriteriaDoctrine::CRITERIA_GE,
        CriteriaDoctrine::CRITERIA_GT,
        CriteriaDoctrine::CRITERIA_LE,
        CriteriaDoctrine::CRITERIA_LT,
    ];

    /**
     * Criterias for strings
     */
    const CRITERIAS_STRING = [
        CriteriaDoctrine::CRITERIA_EQ,
        CriteriaDoctrine::CRITERIA_NE,
        CriteriaDoctrine::CRITERIA_LIKE,
    ];

    const ALL_CRITERIAS = [
        CriteriaDoctrine::CRITERIA_EQ,
        CriteriaDoctrine::CRITERIA_NE,
        CriteriaDoctrine::CRITERIA_GE,
        CriteriaDoctrine::CRITERIA_GT,
        CriteriaDoctrine::CRITERIA_LE,
        CriteriaDoctrine::CRITERIA_LT,
        CriteriaDoctrine::CRITERIA_LIKE,
    ];

    /**
     * Atributo de db
     * @var string
     */
    public $param;

    /**
     * Criteria Doctrine: =,<=,>=
     * @var string
     */
    public $criteria;

    /**
     * Criteria original de la consulta REST: eq,le,lt
     * @var string
     */
    public $criteriaOriginal;

    /**
     * @var string
     */
    public $valor;

    /**
     * Determina si la query tiene sub query o sub criterias
     * @var boolean
     */
    public $isObjeto;

    /**
     * @var CriteriaDoctrine
     */
    public $subCriteria;

    /**
     * CriteriaDoctrine constructor.
     * @param $param
     * @param $criteria
     * @param $valor
     */
    public function __construct($param, $criteria, $valor)
    {
        $this->param = $param;
        $this->criteria = $this->mapCriteria($criteria);
        $this->criteriaOriginal = $criteria? strtolower($criteria):CriteriaDoctrine::CRITERIA_EQ;
        $this->valor = $this->castValue($valor);
        $this->isObjeto = false;
    }

    function __toString()
    {
        if($this->isObjeto){
            return "{$this->param}{{$this->subCriteria}}";
        }else{
            return "{$this->param} {$this->criteria} '{$this->valor}'";
        }
    }

    /**
     * Construye la expresión 'where' para un objeto.
     * @param $objName
     * @param $parameterName
     * @return string
     */
    function getWhere($objName, $parameterName)
    {
        return "{$objName}.{$this->param} {$this->criteria} :{$parameterName}";
    }

    /**
     * Determina si la criteria es de ordenación o paginación
     * @return bool
     */
    function isPaginacion()
    {
        return in_array($this->param, ['offset', 'limit', 'sort', 'order']);
    }

    /**
     * Transforma los parámetros REST en una lista de criterias necesarias para QueryBuilder.
     * @TODO: No es posible obtener parámetros duplicados. Ej: codigo[ne]=ASP&codigo[ne]=IBU123
     * @param array $query
     * @param $criteriasHabilitadas
     * @throws CriteriaException
     * @return array
     */
    public static function obtenerCriterias(array $query, $criteriasHabilitadas)
    {
        $criterias = [];

        // Verificamos el formato de las criterias habilitadas
        if(CriteriaDoctrine::validarFormatoCriteria($criteriasHabilitadas)){
            foreach($query as $param => $value){
                $criteriaObj = CriteriaDoctrine::getCriteria($param, $value);

                // Validamos la criteria para determinar si es valida y en caso de que no, lanzamos excepción
                if(CriteriaDoctrine::validarCriteria($criteriaObj, $criteriasHabilitadas)){
                    array_push($criterias, $criteriaObj);
                }
            }
        }

        return $criterias;
    }

    /**
     * Cargamos todas las CriteriaDoctrine en la QueryBuilder de Doctrine
     * @param QueryBuilder $qb
     * @param $objAliasName
     * @param CriteriaDoctrine $criteriaBuilder
     * @param $paramName
     * @return QueryBuilder
     */
    public static function crearQuery(QueryBuilder $qb, $objAliasName, CriteriaDoctrine $criteriaBuilder, $paramName)
    {
        if($criteriaBuilder->isObjeto){
            $paramName = "param_{$criteriaBuilder->param}_{$criteriaBuilder->subCriteria->param}_".time();
            $subObjAliasName = $criteriaBuilder->param;

            return CriteriaDoctrine::crearQuery($qb, $subObjAliasName, $criteriaBuilder->subCriteria, $paramName);
        }else{
            $param = $criteriaBuilder->param;
            $valor = $criteriaBuilder->valor;
            $valores = explode(',', $valor);
            if(sizeof($valores) > 1){
                $orCriteria = $qb->expr()->orX();
                foreach($valores as $v){
                    $orCriteria->add($qb->expr()->eq("${objAliasName}.${param}", "'".$v."'"));
                }
                $qb->andWhere($orCriteria);
            }else{
                $where = $criteriaBuilder->getWhere($objAliasName, $paramName);
                $qb->andWhere($where)
                    ->setParameter("${paramName}", $valor);
            }

            return $qb;
        }
    }

    /**
     * Obtiene la lista de JoinCriteria necesarias para que las criterias de objetos funcionen
     * @param $joins
     * @param $objAliasName
     * @param CriteriaDoctrine $criteriaBuilder
     * @return mixed
     */
    public static function obtenerLeftJoins($joins, $objAliasName, CriteriaDoctrine $criteriaBuilder)
    {
        // Si la criteria es sobre un objeto, creamos el join necesario para la consulta
        if($criteriaBuilder->isObjeto){
            $subObjAliasName = $criteriaBuilder->param;
            $join = $objAliasName.'.'.$criteriaBuilder->param;

            $joinCriteria = new JoinCriteria($join, $subObjAliasName);

            // Verificamos que el join no exista en la lista antes de agregarlo
            if(!Utilidades::existInList($joinCriteria->join, $joins, 'join')){
                array_push($joins, $joinCriteria);
            }

            return CriteriaDoctrine::obtenerLeftJoins($joins, $subObjAliasName, $criteriaBuilder->subCriteria);
        }else{
            return $joins;
        }
    }

    /**
     * Obtiene la lista de JoinCriteria necesarias para que el ordenamiento de objetos funcione
     * @param $joins
     * @param $objAliasName
     * @param $sortParams
     * @return mixed
     */
    public static function obtenerLeftJoinsOrder($joins, $objAliasName, $sortParams)
    {
        // Quitamos el primer nivel de ordenamiento. Ej: 'parada.localidad.provincia.id' => 'localidad.provincia.id'
        $sortParams = implode('.', array_slice(explode('.', $sortParams), 1));
        // Verificamos si el sortParams hace referencia a un objeto. Si contiene '.'
        if(strpos($sortParams, '.') !== false){
            $param = explode('.', $sortParams);

            $subObjAliasName = $param[0];
            $joinCriteria = new JoinCriteria($objAliasName.'.'.$subObjAliasName, $subObjAliasName);

            // Verificamos que el join no exista en la lista antes de agregarlo
            if(!Utilidades::existInList($joinCriteria->join, $joins, 'join')){
                array_push($joins, $joinCriteria);
            }

            return CriteriaDoctrine::obtenerLeftJoinsOrder($joins, $subObjAliasName, $sortParams);
        }else{
            return $joins;
        }
    }

    /**
     * Genera una CriteriaDoctrine a partir de los parámetos REST.
     * En caso de ser una consulta anidada, crea una criteria con criterias anidadas.
     * @param $param
     * @param $value
     * @return CriteriaDoctrine|null
     */
    private static function getCriteria($param, $value)
    {
        // Verificamos si es un object criteria: Si el parámetro contiene el string '->'
        if(strpos($param, '->') !== false){
            list($paramObj, $subParamObj) = explode('->', $param, 2);

            $objectCriteria = new CriteriaDoctrine($paramObj, null, null);
            $objectCriteria->isObjeto = true;
            $objectCriteria->subCriteria = CriteriaDoctrine::getCriteria($subParamObj, $value);

            return $objectCriteria;
        }else{
            // Determinamos si el valor del parámetro contiene una criteria para mapearla correctamente
            // Sino asumimos que la criteria por defecto es 'eq'
            if(is_array($value)){
                $criteriaObj = null;
                foreach($value as $criteria => $valor){
                    $criteriaObj = new CriteriaDoctrine($param, $criteria, $valor);
                }

                return $criteriaObj;
            }else{
                return new CriteriaDoctrine($param, CriteriaDoctrine::CRITERIA_EQ, $value);
            }
        }
    }

    /**
     * Validamos que la lista de criterias habilitadas tengan el formato adecuado
     * @param $criteriasHabilitadas
     * @return bool
     * @throws CriteriaException
     */
    private static function validarFormatoCriteria($criteriasHabilitadas)
    {
        foreach($criteriasHabilitadas as $c => $v){
            if(Utilidades::containsArray($v)){
                CriteriaDoctrine::validarFormatoCriteria($v);
            }else{
                foreach($v as $criteriaDoctrine){
                    if(!in_array($criteriaDoctrine, CriteriaDoctrine::ALL_CRITERIAS)){
                        $msj = implode(',', CriteriaDoctrine::ALL_CRITERIAS);
                        throw new CriteriaException(
                            "Criterio '$criteriaDoctrine' mal definido. Solo se pueden usar '$msj'"
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * Valida recursivamente la criteria para determinar si está habilitada para filtrar.
     * En caso de que no sea valida lanzamos excepción
     * @param CriteriaDoctrine $criteriaObj
     * @param $criteriasHabilitadas
     * @throws CriteriaException
     * @return bool
     */
    private static function validarCriteria(CriteriaDoctrine $criteriaObj, $criteriasHabilitadas)
    {
        // Validamos que la criteria sea permitida para filtrar
        if(isset($criteriasHabilitadas[$criteriaObj->param])){
            $criteriasHabilitadasByParam = $criteriasHabilitadas[$criteriaObj->param];

            // Si la criteria es sobre un objeto, validamos recursivamente la sub criteria
            if($criteriaObj->isObjeto){
                return CriteriaDoctrine::validarCriteria($criteriaObj->subCriteria, $criteriasHabilitadasByParam);
            }else{
                // Si el parámetro no es un objeto, pero tiene una lista de criterias habilitadas, es por lo tanto un objeto doctrine y asumimos que se está buscando por PK.
                // Ej: provincia = 12. Donde provincia es un objeto doctrine, 12 es primary key y además tiene una lista de criterias habilitadas.
                // Entonces 'provincia = 12' es equivalente a 'provincia.id = 12'
                $isFindById = Utilidades::containsArray($criteriasHabilitadasByParam);

                // Las búsquedas por pk de objeto deben ser solo numéricas. Ej: no deben ser 'provincia[like] = 12'
                if($isFindById){
                    $criteriasHabilitadasByParam = CriteriaDoctrine::CRITERIAS_NUMBER;
                }
                // Verificamos la criteria está dentro de las habilitadas.
                if(!in_array($criteriaObj->criteriaOriginal, $criteriasHabilitadasByParam)){
                    $msj = implode(',', $criteriasHabilitadasByParam);
                    throw new CriteriaException(
                        "No se permite consultar '{$criteriaObj->param}' por '{$criteriaObj->criteriaOriginal}' y solo se permite por '{$msj}'."
                    );
                }
            }
        }else{
            // Excluímos las criterias de paginación y ordenamiento,
            // ya que esos atributos no están definidos en las criterias habilitadas para el objeto
            if(!$criteriaObj->isPaginacion()){
                throw new CriteriaException(
                    "No se permite consultar por '{$criteriaObj->param}'. Detalle:'{$criteriaObj}'."
                );
            }else{
                // Validamos criterias de paginación y ordenación
                if($criteriaObj->param == 'limit' && !is_numeric($criteriaObj->valor)){
                    throw new CriteriaException(
                        "El atributo 'limit' debe ser numérico. Detalle:'{$criteriaObj}'."
                    );
                }
                if($criteriaObj->param == 'offset' && !is_numeric($criteriaObj->valor)){
                    throw new CriteriaException(
                        "El atributo 'offset' debe ser numérico. Detalle:'{$criteriaObj}'."
                    );
                }
                if($criteriaObj->param == 'order' && !in_array(strtoupper($criteriaObj->valor), ['ASC', 'DESC'])){
                    throw new CriteriaException(
                        "Solo se permite ordenar por 'ASC' o 'DESC'. Detalle:'{$criteriaObj}'."
                    );
                }
                if($criteriaObj->param == 'sort'){
                    CriteriaDoctrine::validarSort($criteriaObj->valor, $criteriasHabilitadas);
                }
            }
        }

        return true;
    }

    /**
     * Verifica que la condición de ordenamiento corresponda con las criterias habilitadas
     * @param $sortParams
     * @param $criteriasHabilitadas
     * @return bool
     * @throws CriteriaException
     */
    private static function validarSort($sortParams, $criteriasHabilitadas)
    {
        // Verificamos que el parámetro de ordenación no comience ni termine con punto
        if(substr($sortParams, 0, 1) === '.' || substr($sortParams, strlen($sortParams) - 1, strlen($sortParams)) === '.'){
            throw new CriteriaException(
                "El parámetro de ordenación '$sortParams' no debe comenzar ni terminar con '.'"
            );
        }

        // Obtenemos y verificamos el primer nivel de ordenamiento. Ej: 'parada.localidad.provincia.id' => 'parada'
        $firstSort = explode('.', $sortParams)[0];
        if(!isset($criteriasHabilitadas[$firstSort])){
            $msj = implode(',', array_keys($criteriasHabilitadas));
            throw new CriteriaException(
                "No se permite ordenar por '$firstSort'. Solo se permite por '$msj'"
            );
        }
        $subCriteriaHabilitada = $criteriasHabilitadas[$firstSort];

        // Verificamos si el sortParams hace referencia a un objeto para validarlo. Si contiene '.'
        if(strpos($sortParams, '.') !== false){
            // Quitamos el primer nivel de ordenamiento. Ej: 'parada.localidad.provincia.id' => 'localidad.provincia.id'
            $sortParams = implode('.', array_slice(explode('.', $sortParams), 1));

            return CriteriaDoctrine::validarSort($sortParams, $subCriteriaHabilitada);
        }else{
            // Verificamos que existan criterias habilitadas para el atributo. Ej: que contenga una lista eq,ge,gt...
            if(sizeof($subCriteriaHabilitada) === 0){
                throw new CriteriaException(
                    "No se permite ordenar por '$firstSort'"
                );
            }
            // Verificamos que el atributo no sea del tipo objeto, es decir que no tenga un sub array de criterias.
            if(Utilidades::containsArray($subCriteriaHabilitada)){
                $msj = implode(',', array_keys($subCriteriaHabilitada));
                throw new CriteriaException(
                    "Es necesario definir atributo para ordenar '$firstSort'. Pueden ser '$msj'"
                );
            }
        }

        return true;
    }

    /**
     * Mapea las criterias al formato que Doctrine necesita
     * @param $criteria
     * @return string
     */
    private function mapCriteria($criteria)
    {
        $resp = '=';
        switch(strtolower($criteria)){
            case CriteriaDoctrine::CRITERIA_GE:
                $resp = '>=';
                break;
            case CriteriaDoctrine::CRITERIA_GT:
                $resp = '>';
                break;
            case CriteriaDoctrine::CRITERIA_LE:
                $resp = '<=';
                break;
            case CriteriaDoctrine::CRITERIA_LT:
                $resp = '<';
                break;
            case CriteriaDoctrine::CRITERIA_NE:
                $resp = '!=';
                break;
            case CriteriaDoctrine::CRITERIA_LIKE:
            case 'ilike':
                $resp = 'LIKE';
                break;
            default;
                break;
        }

        return $resp;
    }

    /**
     * Castea los valores que Doctrine entiende.
     * Los valores booleanos se transforman a 1 y 0.
     * @param $value
     * @return string
     */
    private function castValue($value)
    {
        if($value === true || strtolower($value) === 'true'){
            $value = '1';
        }elseif($value === false || strtolower($value) === 'false'){
            $value = '0';
        }

        return $value;
    }
}
