<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 20/01/2019
 * Time: 14:44
 */

namespace bipiane\test;

use bipiane\CriteriaDoctrine;
use bipiane\CriteriaException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PaginacionTest
 * @package bipiane\test
 */
class PaginacionTest extends TestCase
{
    public function testOffset()
    {
        // Ok
        $request = new Request();
        $request->query->set('offset', 10);

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('offset', $criteria->param);
        $this->assertEquals(10, $criteria->valor);

        // Error
        $request = new Request();
        $request->query->set('offset', 'XX');

        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("El atributo 'offset' debe ser numérico. Detalle:'offset = 'XX''");

        CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
    }

    public function testLimit()
    {
        // Ok
        $request = new Request();
        $request->query->set('limit', 5);

        $criterias = CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
        $criteria = $criterias[0] instanceof CriteriaDoctrine ? $criterias[0] : null;

        $this->assertEquals('limit', $criteria->param);
        $this->assertEquals(5, $criteria->valor);

        // Error
        $request = new Request();
        $request->query->set('limit', 'XX');

        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("El atributo 'limit' debe ser numérico. Detalle:'limit = 'XX''");

        CriteriaDoctrine::obtenerCriterias($request->query->all(), []);
    }
}
