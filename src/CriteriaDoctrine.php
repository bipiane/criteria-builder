<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 13:09
 */

namespace bipiane;

use ReflectionClass;
use ReflectionException;
use Doctrine\ORM\QueryBuilder;
use Swagger\Annotations\Property;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationException;

/**
 * Class CriteriaDoctrine
 * @package bipiane
 * @author  Ivan Pianetti <ivanpianetti23@gmail.com>
 * @link    https://github.com/bipiane/criteria-builder
 */
class CriteriaDoctrine
{
    /**
     * Api selector
     */
    const API_SELECTOR = '-';

    /**
     * Api selector Old
     */
    const API_SELECTOR_OLD = '->';

    /**
     * Class selector
     */
    const CLASS_SELECTOR = '__clase__';

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
     * Criterias for dates.
     */
    const CRITERIAS_DATE = [
        CriteriaDoctrine::CRITERIA_EQ,
        CriteriaDoctrine::CRITERIA_GE,
        CriteriaDoctrine::CRITERIA_GT,
        CriteriaDoctrine::CRITERIA_LE,
        CriteriaDoctrine::CRITERIA_LT,
    ];

    /**
     * Criterias for strings.
     */
    const CRITERIAS_BOOLEAN = [
        CriteriaDoctrine::CRITERIA_EQ,
    ];

    /**
     * Criterias for strings.
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
        $this->criteria = $this->mapCriteria($criteria, $valor);
        $this->criteriaOriginal = $criteria ? strtolower($criteria) : CriteriaDoctrine::CRITERIA_EQ;
        $this->valor = $this->castValue($valor);
        $this->isObjeto = false;
    }

    function __toString()
    {
        if ($this->isObjeto) {
            return "{$this->param}{{$this->subCriteria}}";
        } else {
            if ($this->isNullFilter()) {
                return "{$this->param} {$this->criteria}";
            }
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
        if ($this->isNullFilter()) {
            return "{$objName}.{$this->param} {$this->criteria}";
        }
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
     * Determina si la consulta es por valor nulo
     * @return bool
     */
    function isNullFilter()
    {
        return ($this->criteria == 'IS NULL' || $this->criteria == 'IS NOT NULL');
    }

    /**
     * Transforma los parámetros REST en una lista de criterias necesarias para QueryBuilder.
     * @TODO: No es posible obtener parámetros duplicados. Ej: codigo[ne]=ASP&codigo[ne]=IBU123
     * @param array $query
     * @param array $criteriasHabilitadas
     * @return array
     * @throws CriteriaException
     */
    public static function obtenerCriterias(array $query, $criteriasHabilitadas)
    {
        $criterias = [];

        // Verificamos el formato de las criterias habilitadas
        if (CriteriaDoctrine::validarFormatoCriteria($criteriasHabilitadas)) {
            foreach ($query as $param => $value) {
                // Reemplazamos selectores viejos por nuevos
                $param = str_replace(CriteriaDoctrine::API_SELECTOR_OLD, CriteriaDoctrine::API_SELECTOR, $param);

                $criteriaObj = CriteriaDoctrine::getCriteria($param, $value);

                // Validamos la criteria para determinar si es valida y en caso de que no, lanzamos excepción
                if (CriteriaDoctrine::validarCriteria($criteriaObj, $criteriasHabilitadas)) {
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
        if ($criteriaBuilder->isObjeto) {
            $paramName = "param_{$criteriaBuilder->param}_{$criteriaBuilder->subCriteria->param}_" . time();
            $subObjAliasName = $criteriaBuilder->param;

            return CriteriaDoctrine::crearQuery($qb, $subObjAliasName, $criteriaBuilder->subCriteria, $paramName);
        } else {
            $param = $criteriaBuilder->param;
            $valor = $criteriaBuilder->valor;
            $valores = explode(',', $valor);
            if (sizeof($valores) > 1) {
                $orCriteria = $qb->expr()->orX();
                foreach ($valores as $v) {
                    $orCriteria->add($qb->expr()->eq("${objAliasName}.${param}", "'" . $v . "'"));
                }
                $qb->andWhere($orCriteria);
            } else {
                $where = $criteriaBuilder->getWhere($objAliasName, $paramName);
                $qb->andWhere($where);

                // Si el where no es por nulo, setteamos el parámetro
                if (!$criteriaBuilder->isNullFilter()) {
                    $qb->setParameter("${paramName}", $valor);
                }
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
        if ($criteriaBuilder->isObjeto) {
            $subObjAliasName = $criteriaBuilder->param;
            $join = $objAliasName . '.' . $criteriaBuilder->param;

            $joinCriteria = new JoinCriteria($join, $subObjAliasName);

            // Verificamos que el join no exista en la lista antes de agregarlo
            if (!Utilidades::existInList($joinCriteria->join, $joins, 'join')) {
                array_push($joins, $joinCriteria);
            }

            return CriteriaDoctrine::obtenerLeftJoins($joins, $subObjAliasName, $criteriaBuilder->subCriteria);
        } else {
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
        if (strpos($sortParams, '.') !== false) {
            $param = explode('.', $sortParams);

            $subObjAliasName = $param[0];
            $joinCriteria = new JoinCriteria($objAliasName . '.' . $subObjAliasName, $subObjAliasName);

            // Verificamos que el join no exista en la lista antes de agregarlo
            if (!Utilidades::existInList($joinCriteria->join, $joins, 'join')) {
                array_push($joins, $joinCriteria);
            }

            return CriteriaDoctrine::obtenerLeftJoinsOrder($joins, $subObjAliasName, $sortParams);
        } else {
            return $joins;
        }
    }

    /**
     * Aplana una array de criterias
     * @param array $criterias
     * @param null $prefijo
     *
     * @return string[]
     */
    public static function criteriasFlatten($criterias, $prefijo = null)
    {
        $filtros = [];
        foreach ($criterias as $attr => $queries) {
            if ($attr !== self::CLASS_SELECTOR) {
                if (is_array($queries)) {
                    foreach ($queries as $key => $q) {
                        $atributo = $prefijo ? $prefijo . self::API_SELECTOR . $attr : $attr;
                        if (is_array($q)) {
                            $filtros = array_merge($filtros, self::criteriasFlatten($q, $atributo . self::API_SELECTOR . $key));
                        } else {
                            $filtroQuery = '[' . $q . ']';
                            if (self::CRITERIA_EQ === $q) {
                                $filtroQuery = '';
                            }
                            array_push($filtros, $atributo . $filtroQuery);
                        }
                    }
                } else {
                    // @TODO Considerar ..&provincia-pais=1 === provincia-pais.id[eq]=1
                    $filtroQuery = '[' . $queries . ']';
                    if (self::CRITERIA_EQ === $queries) {
                        $filtroQuery = '';
                    }
                    array_push($filtros, $prefijo . $filtroQuery);
                }
            }
        }

        return $filtros;
    }

    /**
     * Genera parámetros formato Swagger desde las criterias
     * @param $criterias
     * @param string|null $prefijo
     * @param string|null $clase
     * @return array
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public static function criteriasToSwagger($criterias, $prefijo = null, $clase = null)
    {
        $params = [];

        $clase = self::getCriteriaClase($criterias) ?: $clase;
        $propiedadesSwagger = self::getPropiedadesSwagger($clase);

        foreach ($criterias as $attr => $queries) {
            if (is_array($queries)) {
                foreach ($queries as $key => $q) {
                    $atributo = $prefijo ? $prefijo . self::API_SELECTOR . $attr : $attr;
                    if (is_array($q)) {
                        $subClase = self::getCriteriaClase($queries);
                        $params = array_merge($params, self::criteriasToSwagger($q, $atributo . self::API_SELECTOR . $key, $subClase));
                    } else {
                        $query = self::createQuery($key, $atributo, $q, $propiedadesSwagger);
                        if ($query) {
                            array_push($params, $query);
                        }
                    }
                }
            } else {
                $query = self::createQuery($attr, $prefijo, $queries, $propiedadesSwagger);
                if ($query) {
                    array_push($params, $query);
                }
            }
        }

        return $params;
    }

    /**
     * Crea una query con formato Swagger según CriteriaBuilder
     * @param string $attr
     * @param string $q
     * @param string $prefijo
     * @param Property[] $propiedadesSwagger
     * @return array|null
     */
    private static function createQuery($attr, $prefijo, $q, $propiedadesSwagger)
    {
        $query = null;
        if ($attr !== self::CLASS_SELECTOR) {
            $filtroQuery = '[' . $q . ']';
            if (self::CRITERIA_EQ === $q) {
                $filtroQuery = '';
            }

            $property = $prefijo . $filtroQuery;
            $tipo = self::getTipoSwagger($propiedadesSwagger, $property);
            $query = self::createSwaggerParameter($property, $tipo);
        }
        return $query;
    }

    /**
     * Crea un array con el formato de parámetro Swagger
     * @param string $property
     * @param string $type
     * @return array
     */
    private static function createSwaggerParameter($property, $type)
    {
        return [
            'name' => $property,
            'in' => 'query',
            'type' => $type,
            'required' => false,
        ];
    }

    /**
     * Retorna la clase declarada dentro de las criterias
     * @param array $criterias
     * @return string|null
     */
    private static function getCriteriaClase($criterias)
    {
        $clase = null;

        if (isset($criterias[self::CLASS_SELECTOR])) {
            $clase = $criterias[self::CLASS_SELECTOR];
        }

        return $clase;
    }

    /**
     * Retorna todas las propiedades Swagger de una clase por nombre
     * @param string $classname
     * @return Property[]
     * @throws AnnotationException
     * @throws ReflectionException
     */
    private static function getPropiedadesSwagger($classname)
    {
        /** @var Property[] $lista */
        $lista = [];
        if ($classname) {
            $reader = new AnnotationReader();
            $reflClass = new ReflectionClass($classname);

            $propiedades = $reflClass->getProperties();
            foreach ($propiedades as $reflProp) {
                $annotions = $reader->getPropertyAnnotations($reflProp);
                foreach ($annotions as $annot) {
                    if ($annot instanceof Property) {
                        // Si no tiene propiedad la setteamos con nombre de atributo
                        $annot->property = $annot->property ?: $reflProp->getName();
                        array_push($lista, $annot);
                    }
                }
            }
        }

        return $lista;
    }

    /**
     * Retorna el type Swagger de un atributo de clase
     * @param Property[] $props
     * @param string $atributo
     * @return string|null
     */
    private static function getTipoSwagger($props, $atributo)
    {
        $tipo = null;

        $selectores = explode(self::API_SELECTOR, $atributo);
        $newAttr = $selectores[sizeof($selectores) - 1];
        // Eliminados el texto entre corchetes: id[ge] => id
        $newAttr = preg_replace('/\[[\s\S]+?]/', '', $newAttr);;

        foreach ($props as $prop) {
            if ($prop->property === $newAttr && $prop->type) {
                return $prop->type;
            }
        }

        return $tipo;
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
        // Verificamos si es un object criteria: Si el parámetro contiene el string definidio por API_OPERATOR
        if (false !== strpos($param, self::API_SELECTOR)) {
            list($paramObj, $subParamObj) = explode(self::API_SELECTOR, $param, 2);

            $objectCriteria = new CriteriaDoctrine($paramObj, null, null);
            $objectCriteria->isObjeto = true;
            $objectCriteria->subCriteria = CriteriaDoctrine::getCriteria($subParamObj, $value);

            return $objectCriteria;
        } else {
            // Determinamos si el valor del parámetro contiene una criteria para mapearla correctamente
            // Sino asumimos que la criteria por defecto es 'eq'
            if (is_array($value)) {
                $criteriaObj = null;
                foreach ($value as $criteria => $valor) {
                    $criteriaObj = new CriteriaDoctrine($param, $criteria, $valor);
                }

                return $criteriaObj;
            } else {
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
        foreach ($criteriasHabilitadas as $c => $v) {
            if (Utilidades::containsArray($v)) {
                CriteriaDoctrine::validarFormatoCriteria($v);
            } else {
                if (self::CLASS_SELECTOR !== $c) {
                    if (!is_array($v)) {
                        throw new CriteriaException(
                            "Criterio mal definido. Se debe definir una lista de criterias para el filtro '$c'"
                        );
                    }

                    foreach ($v as $criteriaDoctrine) {
                        if (!in_array($criteriaDoctrine, CriteriaDoctrine::ALL_CRITERIAS)) {
                            $msj = implode(',', CriteriaDoctrine::ALL_CRITERIAS);
                            throw new CriteriaException(
                                "Criterio '$criteriaDoctrine' mal definido. Solo se pueden usar '$msj'"
                            );
                        }
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
     * @param array $criteriasHabilitadas
     * @return bool
     * @throws CriteriaException
     */
    private static function validarCriteria(CriteriaDoctrine $criteriaObj, $criteriasHabilitadas)
    {
        // Validamos que la criteria sea permitida para filtrar
        if (isset($criteriasHabilitadas[$criteriaObj->param])) {
            $criteriasHabilitadasByParam = $criteriasHabilitadas[$criteriaObj->param];

            // Si la criteria es sobre un objeto, validamos recursivamente la sub criteria
            if ($criteriaObj->isObjeto) {
                return CriteriaDoctrine::validarCriteria($criteriaObj->subCriteria, $criteriasHabilitadasByParam);
            } else {
                // Si el parámetro no es un objeto, pero tiene una lista de criterias habilitadas, es por lo tanto un objeto doctrine y asumimos que se está buscando por PK.
                // Ej: provincia = 12. Donde provincia es un objeto doctrine, 12 es primary key y además tiene una lista de criterias habilitadas.
                // Entonces 'provincia = 12' es equivalente a 'provincia.id = 12'
                $isFindById = Utilidades::containsArray($criteriasHabilitadasByParam);

                // Las búsquedas por pk de objeto deben ser solo numéricas. Ej: no deben ser 'provincia[like] = 12'
                if ($isFindById) {
                    $criteriasHabilitadasByParam = CriteriaDoctrine::CRITERIAS_NUMBER;
                }
                // Verificamos la criteria está dentro de las habilitadas.
                if (!in_array($criteriaObj->criteriaOriginal, $criteriasHabilitadasByParam)) {
                    $msj = implode(',', $criteriasHabilitadasByParam);
                    throw new CriteriaException(
                        "No se permite consultar '{$criteriaObj->param}' por '{$criteriaObj->criteriaOriginal}' y solo se permite por '{$msj}'."
                    );
                }
                if ('null' === $criteriaObj->valor) {
                    if (!(self::CRITERIA_EQ === $criteriaObj->criteriaOriginal || self::CRITERIA_NE === $criteriaObj->criteriaOriginal)) {
                        throw new CriteriaException(
                            "Las consultas por valor nulo solo puede ser '" . self::CRITERIA_EQ . ',' . self::CRITERIA_NE . "'."
                        );
                    }
                }
            }
        } else {
            // Excluímos las criterias de paginación y ordenamiento,
            // ya que esos atributos no están definidos en las criterias habilitadas para el objeto
            if (!$criteriaObj->isPaginacion()) {
                throw new CriteriaException(
                    "No se permite consultar por '{$criteriaObj->param}'. Detalle:'{$criteriaObj}'."
                );
            } else {
                // Validamos criterias de paginación y ordenación
                if ($criteriaObj->param == 'limit' && !is_numeric($criteriaObj->valor)) {
                    throw new CriteriaException(
                        "El atributo 'limit' debe ser numérico. Detalle:'{$criteriaObj}'."
                    );
                }
                if ($criteriaObj->param == 'offset' && !is_numeric($criteriaObj->valor)) {
                    throw new CriteriaException(
                        "El atributo 'offset' debe ser numérico. Detalle:'{$criteriaObj}'."
                    );
                }
                if ($criteriaObj->param == 'order' && !in_array(strtoupper($criteriaObj->valor), ['ASC', 'DESC'])) {
                    throw new CriteriaException(
                        "Solo se permite ordenar por 'ASC' o 'DESC'. Detalle:'{$criteriaObj}'."
                    );
                }
                if ($criteriaObj->param == 'sort') {
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
        if (substr($sortParams, 0, 1) === '.' || substr($sortParams, strlen($sortParams) - 1, strlen($sortParams)) === '.') {
            throw new CriteriaException(
                "El parámetro de ordenación '$sortParams' no debe comenzar ni terminar con '.'"
            );
        }

        // Obtenemos y verificamos el primer nivel de ordenamiento. Ej: 'parada.localidad.provincia.id' => 'parada'
        $firstSort = explode('.', $sortParams)[0];
        if (!isset($criteriasHabilitadas[$firstSort])) {
            $msj = implode(',', array_keys($criteriasHabilitadas));
            throw new CriteriaException(
                "No se permite ordenar por '$firstSort'. Solo se permite por '$msj'"
            );
        }
        $subCriteriaHabilitada = $criteriasHabilitadas[$firstSort];

        // Verificamos si el sortParams hace referencia a un objeto para validarlo. Si contiene '.'
        if (strpos($sortParams, '.') !== false) {
            // Quitamos el primer nivel de ordenamiento. Ej: 'parada.localidad.provincia.id' => 'localidad.provincia.id'
            $sortParams = implode('.', array_slice(explode('.', $sortParams), 1));

            return CriteriaDoctrine::validarSort($sortParams, $subCriteriaHabilitada);
        } else {
            // Verificamos que existan criterias habilitadas para el atributo. Ej: que contenga una lista eq,ge,gt...
            if (sizeof($subCriteriaHabilitada) === 0) {
                throw new CriteriaException(
                    "No se permite ordenar por '$firstSort'"
                );
            }
            // Verificamos que el atributo no sea del tipo objeto, es decir que no tenga un sub array de criterias.
            if (Utilidades::containsArray($subCriteriaHabilitada)) {
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
     * @param $valor
     * @return string
     */
    private function mapCriteria($criteria, $valor)
    {
        $resp = '=';
        switch (strtolower($criteria)) {
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

        // Las búsquedas por NULL deben adaptarse
        if (strtolower($valor) === 'null') {
            if ($resp == '=') {
                $resp = 'IS NULL';
            }
            if ($resp == '!=') {
                $resp = 'IS NOT NULL';
            }
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
        if ($value === true || strtolower($value) === 'true') {
            $value = '1';
        } elseif ($value === false || strtolower($value) === 'false') {
            $value = '0';
        }

        return $value;
    }
}
