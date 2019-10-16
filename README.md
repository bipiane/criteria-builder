[![Latest Stable Version](https://poser.pugx.org/bipiane/criteria-builder/v/stable)](https://packagist.org/packages/bipiane/criteria-builder)
[![Total Downloads](https://poser.pugx.org/bipiane/criteria-builder/downloads)](https://packagist.org/packages/bipiane/criteria-builder)
[![License](https://poser.pugx.org/bipiane/criteria-builder/license)](https://packagist.org/packages/bipiane/criteria-builder)

Doctrine Criteria Builder from HTTP parameters in PHP
------------

Installation
------------

```bash
composer require bipiane/criteria-builder
```

Example
------------

```php
<?php
/**
 * GET ../api/users?
 *          limit=12&
 *          offset=1&
 *          sort=city.name&
 *          order=ASC&
 *          lastname[like]=Pian%&
 *          city.state.code[ne]=null&
 *          city.state.country=1&
 *          enabled=true&
 *          id[ge]=50
 * @param Request $request
 */
public function exampleAction(Request $request)
{
    $criteriaUser = [
        'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
        'name' => CriteriaDoctrine::CRITERIAS_STRING,
        'lastname' => CriteriaDoctrine::CRITERIAS_STRING,
        'city' => [
            'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
            'name' => CriteriaDoctrine::CRITERIAS_STRING,
            'state' => [
                'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                'name' => CriteriaDoctrine::CRITERIAS_STRING,
                'code' => CriteriaDoctrine::CRITERIAS_STRING,
                'country' => [
                    'id' => CriteriaDoctrine::CRITERIAS_NUMBER,
                    'name' => CriteriaDoctrine::CRITERIAS_STRING,
                    'enabled' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
                ],
                'enabled' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
            ],
            'enabled' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
        ],
        'enabled' => CriteriaDoctrine::CRITERIAS_BOOLEAN,
    ];

    try {
        $qb = $this->getDoctrine()->getManager()
            ->getRepository('ModelBundle:User')
            ->createQueryBuilder('usr');

        $qb = CriteriaBuilder::fetchFromQuery(
            $qb,
            $request->query->all(),
            $criteriaUser
        );

        var_dump($qb->getQuery()->getArrayResult());
    } catch (CriteriaException $e) {
    }
    // ...
}
```

Testing
------------

```
./vendor/bin/simple-phpunit
```