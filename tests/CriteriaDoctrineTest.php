<?php
/**
 * Created by IntelliJ IDEA.
 * User: ivanpianetti
 * Date: 19/01/2019
 * Time: 22:54
 */

namespace bipiane\test;

use bipiane\CriteriaDoctrine;
use bipiane\CriteriaException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class CriteriaDoctrineTest
 * @package bipiane\test
 */
class CriteriaDoctrineTest extends KernelTestCase
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

    public function testCriteria()
    {
        // GET .../api/localidades?
        //      id[ge]=11&
        //      activo=true&
        //      descripcion[like]=%Colon%&
        //      provincia->descripcion=null&
        //      provincia->abrev[ne]=B&
        //      provincia->pais[le]=5&
        //      provincia->pais->descripcion[ne]='null'&
        //      provincia->pais->abrev[ne]=null&
        //      provincia->pais->activo=true
        $queryHTTP = [
            'id' => [
                'ge' => 11
            ],
            'activo' => true,
            'descripcion' => [
                'like' => '%Colon%'
            ],
            'provincia->descripcion' => null,
            'provincia->abrev' => [
                'ne' => 'B'
            ],
            'provincia->pais' => [
                'le' => 5
            ],
            'provincia->pais->descripcion' => [
                'ne' => 'null'
            ],
            'provincia->pais->abrev' => [
                'ne' => null
            ],
            'provincia->pais->activo' => true
        ];

        $criterias = CriteriaDoctrine::obtenerCriterias($queryHTTP, $this->criteriasHabilitadas);

        $this->assertEquals(true, is_array($criterias));
        $this->assertJsonStringEqualsJsonFile('tests/Responses/testCriteriaResponse.json', json_encode($criterias));
    }

    public function testCriteriaFormatErrorNivelCero()
    {
        $criteriasHabilitadas = [
            'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
            'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
            'provincia' => [
                'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                'pais' => [
                    'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                    'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
                    'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                    'activo' => [CriteriaDoctrine::CRITERIA_EQ],
                ],
                'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
                'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                'activo' => [CriteriaDoctrine::CRITERIA_EQ],
            ],
            'activo' => ['EQUAL'],
        ];
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Criterio 'EQUAL' mal definido. Solo se pueden usar 'eq,ne,ge,gt,le,lt,like'");

        CriteriaDoctrine::obtenerCriterias([], $criteriasHabilitadas);
    }

    public function testCriteriaFormatErrorRelacion()
    {
        $criteriasHabilitadas = [
            'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
            'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
            'provincia' => [
                'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                'pais' => [
                    'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                    'descripcion' => [CriteriaDoctrine::CRITERIA_EQ, CriteriaDoctrine::CRITERIA_NE, 'CONTAINS'],
                    'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                    'activo' => [CriteriaDoctrine::CRITERIA_EQ],
                ],
                'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
                'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                'activo' => [CriteriaDoctrine::CRITERIA_EQ],
            ],
            'activo' => [CriteriaDoctrine::CRITERIA_EQ],
        ];
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Criterio 'CONTAINS' mal definido. Solo se pueden usar 'eq,ne,ge,gt,le,lt,like'");

        CriteriaDoctrine::obtenerCriterias([], $criteriasHabilitadas);
    }
}
