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
        '__clase__' => 'AcmeBundle\Acme',
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

    /**
     * @throws CriteriaException
     */
    public function testCriteria()
    {
        // GET .../api/localidades?
        //      id[ge]=11&
        //      activo=true&
        //      descripcion[like]=%Colon%&
        //      provincia-descripcion=null&
        //      provincia-abrev[ne]=B&
        //      provincia-pais[le]=5&
        //      provincia-pais-descripcion[ne]='null'&
        //      provincia-pais-abrev[ne]=null&
        //      provincia-pais-activo=true
        $queryHTTP = [
            'id' => [
                'ge' => 11
            ],
            'activo' => true,
            'descripcion' => [
                'like' => '%Colon%'
            ],
            'provincia-descripcion' => null,
            'provincia-abrev' => [
                'ne' => 'B'
            ],
            'provincia-pais' => [
                'le' => 5
            ],
            'provincia-pais-descripcion' => [
                'ne' => 'null'
            ],
            'provincia-pais-abrev' => [
                'ne' => null
            ],
            'provincia-pais-activo' => true
        ];

        $criterias = CriteriaDoctrine::obtenerCriterias($queryHTTP, $this->criteriasHabilitadas);

        $this->assertEquals(true, is_array($criterias));
        $this->assertJsonStringEqualsJsonFile('tests/Responses/testCriteriaResponse.json', json_encode($criterias));
    }

    /**
     * @throws CriteriaException
     */
    public function testCriteriaMalDefinida()
    {
        $criteriasHabilitadas = [
            'id' => 'eq',
            'provincia' => [
                'descripcion' => ['like'],
            ],
        ];
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Criterio mal definido. Se debe definir una lista de criterias para el filtro 'id'");
        CriteriaDoctrine::obtenerCriterias([], $criteriasHabilitadas);
    }

    /**
     * @throws CriteriaException
     */
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
                    'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
                ],
                'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
                'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
            ],
            'activo' => ['EQUAL'],
        ];
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Criterio 'EQUAL' mal definido. Solo se pueden usar 'eq,ne,ge,gt,le,lt,like'");

        CriteriaDoctrine::obtenerCriterias([], $criteriasHabilitadas);
    }

    /**
     * @throws CriteriaException
     */
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
                    'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
                ],
                'descripcion' => CriteriaDoctrine::CRITERIAS_STRING,
                'abrev' => CriteriaDoctrine::CRITERIAS_STRING,
                'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
            ],
            'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
        ];
        $this->expectException(CriteriaException::class);
        $this->expectExceptionMessage("Criterio 'CONTAINS' mal definido. Solo se pueden usar 'eq,ne,ge,gt,le,lt,like'");

        CriteriaDoctrine::obtenerCriterias([], $criteriasHabilitadas);
    }

    public function testCriteriasFlatten()
    {
        $criterias = [
            '__clase__' => 'AcmeBundle\Acme',
            'id' => [CriteriaDoctrine::CRITERIA_EQ],
            'provincia' => [
                'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                'pais' => [
                    'id' => [CriteriaDoctrine::CRITERIA_EQ],
                ],
                'descripcion' => [CriteriaDoctrine::CRITERIA_LIKE],
            ],
            'activo' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
        ];

        /** @var string[] $flatten */
        $flatten = CriteriaDoctrine::criteriasFlatten($criterias);

        $result = ['id', 'provincia-id', 'provincia-id[ne]',
            'provincia-id[ge]', 'provincia-id[gt]',
            'provincia-id[le]', 'provincia-id[lt]',
            'provincia-pais-id', 'provincia-descripcion[like]',
            'activo'];

        $this->assertEquals($flatten, $result, 'La lista aplanada de criterias no es correcta');
    }

    /**
     * Consulta con selector de api antiguo
     * @throws CriteriaException
     */
    public function testCriteriaOldSelector()
    {
        $oldSelector = CriteriaDoctrine::API_SELECTOR_OLD;
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
            "provincia${oldSelector}pais${oldSelector}abrev" => [
                'ne' => null
            ],
            'provincia->pais->activo' => true
        ];

        $criterias = CriteriaDoctrine::obtenerCriterias($queryHTTP, $this->criteriasHabilitadas);

        $this->assertEquals(true, is_array($criterias));
        $this->assertJsonStringEqualsJsonFile('tests/Responses/testCriteriaResponse.json', json_encode($criterias));
    }
}
