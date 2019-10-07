<?php

namespace AVKluchko\ApiPlatformExtensionBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

final class OrSearchFilter extends AbstractContextAwareFilter
{
    private const FILTER_KEY = 'filter';

    /**
     * Passes a property through the filter.
     *
     * @param string $property
     * @param $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ): void
    {
        if (null === $value || false === strpos($property, self::FILTER_KEY)) {
            return;
        }
        $parameterName = $queryNameGenerator->generateParameterName($property);
        $search = [];
        $mappedJoins = [];
        foreach ($this->properties as $groupName => $fields) {
            foreach ($fields as $field) {
                $joins = explode('.', $field);
                for ($lastAlias = 'o', $i = 0, $num = \count($joins); $i < $num; $i++) {
                    $currentAlias = $joins[$i];
                    if ($i === $num - 1) {
                        $search[] = "LOWER({$lastAlias}.{$currentAlias}) LIKE LOWER(:{$parameterName})";
                    } else {
                        $join = "{$lastAlias}.{$currentAlias}";
                        if (!\in_array($join, $mappedJoins, true)) {
                            $queryBuilder->leftJoin($join, $currentAlias);
                            $mappedJoins[] = $join;
                        }
                    }
                    $lastAlias = $currentAlias;
                }
            }
        }
        $queryBuilder->andWhere(implode(' OR ', $search));
        $queryBuilder->setParameter($parameterName, '%' . $value . '%');
    }

    /**
     * Gets the description of this filter for the given resource.
     *
     * Returns an array with the filter parameter names as keys and array with the following data as values:
     *   - property: the property where the filter is applied
     *   - type: the type of the filter
     *   - required: if this filter is required
     *   - strategy: the used strategy
     *   - swagger (optional): additional parameters for the path operation,
     *     e.g. 'swagger' => [
     *       'description' => 'My Description',
     *       'name' => 'My Name',
     *       'type' => 'integer',
     *     ]
     * The description can contain additional data specific to a filter.
     *
     * @param string $resourceClass
     *
     * @return array
     * @see \ApiPlatform\Core\Swagger\Serializer\DocumentationNormalizer::getFiltersParameters
     *
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $groupName => $fields) {
            $description[self::FILTER_KEY . '_' . $groupName] = [
                'property' => self::FILTER_KEY,
                'type' => 'string',
                'required' => false,
                'swagger' => ['description' => 'Filter on ' . implode(', ', $fields)],
            ];
        }

        return $description;
    }
}
