<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 20/01/2019
 * Time: 14:45
 */

namespace bipiane\test;

use bipiane\CriteriaDoctrine;
use bipiane\CriteriaException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OrdenacionTest
 * @package bipiane\test
 */
class OrdenacionTest extends TestCase
{
    private $criteriasHabilitadas = [
        'id' => ['eq', 'ne', 'ge', 'gt', 'le', 'lt'],
        'descripcion' => ['eq', 'ne', 'like'],
        'provincia' => [
            'id' => ['eq', 'ne', 'ge', 'gt', 'le', 'lt',],
            'pais' => [
                'id' => ['eq', 'ne', 'ge', 'gt', 'le', 'lt'],
                'descripcion' => ['eq', 'ne', 'like'],
                'abrev' => ['eq', 'ne', 'like'],
                'activo' => ['eq']
            ],
            'descripcion' => ['eq', 'ne', 'like'],
            'abrev' => ['eq', 'ne', 'like'],
            'activo' => ['eq']
        ],
        'activo' => ['eq']
    ];

    public function testOrder()
    {
        // ASC
        $request = new Request();
        $request->query->set('order', 'ASC');

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('order', $criteria->param);
        $this->assertEquals('ASC', $criteria->valor);

        // DESC
        $request = new Request();
        $request->query->set('order', 'DESC');

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('order', $criteria->param);
        $this->assertEquals('DESC', $criteria->valor);

        // Error
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Solo se permite ordenar por 'ASC' o 'DESC'. Detalle:'order = 'qwerty''");

        $request = new Request();
        $request->query->set('order', 'qwerty');

        CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
    }

    public function testSortNivelCero()
    {
        // OK: Sort a nivel cero
        $request = new Request();
        $request->query->set('sort', 'id');

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), $this->criteriasHabilitadas);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('sort', $criteria->param);
        $this->assertEquals('id', $criteria->valor);

        // Error: Sort a nivel cero
        $request = new Request();
        $request->query->set('sort', 'x_atributo');

        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("No se permite ordenar por 'x_atributo'. Solo se permite por 'id,descripcion,provincia,activo'");

        CriteriaDoctrine::obtenerCriterias($request->query->all(), $this->criteriasHabilitadas);
    }

    public function testSortRelacion()
    {
        // OK
        $request = new Request();
        $request->query->set('sort', 'provincia.pais.descripcion');

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), $this->criteriasHabilitadas);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('sort', $criteria->param);
        $this->assertEquals('provincia.pais.descripcion', $criteria->valor);

        // Error
        $request = new Request();
        $request->query->set('sort', 'provincia.x_descripcion');

        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("No se permite ordenar por 'x_descripcion'. Solo se permite por 'id,pais,descripcion,abrev,activo'");

        CriteriaDoctrine::obtenerCriterias($request->query->all(), $this->criteriasHabilitadas);
    }

    public function testSortRelacionObjetoError()
    {
        $request = new Request();
        $request->query->set('sort', 'provincia');

        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Es necesario definir atributo para ordenar 'provincia'. Pueden ser 'id,pais,descripcion,abrev,activo'");

        CriteriaDoctrine::obtenerCriterias($request->query->all(), $this->criteriasHabilitadas);
    }
}
