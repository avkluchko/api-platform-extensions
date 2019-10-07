<?php

namespace AVKluchko\ApiPlatformExtensionBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ORM\QueryBuilder;

class DayFilter extends DateFilter
{
    public const PARAMETER_EQUAL = 'equal';

    public const OPERATOR_VALUE = [
        self::PARAMETER_EQUAL => '=',
        self::PARAMETER_BEFORE => '<=',
        self::PARAMETER_STRICTLY_BEFORE => '<',
        self::PARAMETER_AFTER => '>=',
        self::PARAMETER_STRICTLY_AFTER => '>',
    ];

    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null): void
    {
        // Expect $values to be an array having the period as keys and the date value as values
        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass) ||
            !$this->isDateField($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass);
        }

        $type = (string)$this->getDoctrineFieldType($property, $resourceClass);

        $queryBuilder->andWhere(
            $queryBuilder->expr()->isNotNull(sprintf('%s.%s', $alias, $field))
        );

        if (isset($values[self::PARAMETER_EQUAL])) {
            $this->addSingleWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::OPERATOR_VALUE[self::PARAMETER_EQUAL],
                $values[self::PARAMETER_EQUAL],
                $type
            );
        } else if ($this->isSetInterval($values)) {
            // если задан интервал, то логика через ИЛИ
            $beforeValue = $values[self::PARAMETER_BEFORE] ??
                $values[self::PARAMETER_STRICTLY_BEFORE];
            $beforeOperator = isset($values[self::PARAMETER_BEFORE]) ?
                self::OPERATOR_VALUE[self::PARAMETER_BEFORE] :
                self::OPERATOR_VALUE[self::PARAMETER_STRICTLY_BEFORE];
            $afterValue = $values[self::PARAMETER_AFTER] ??
                $values[self::PARAMETER_STRICTLY_AFTER];
            $afterOperator = isset($values[self::PARAMETER_AFTER]) ?
                self::OPERATOR_VALUE[self::PARAMETER_AFTER] :
                self::OPERATOR_VALUE[self::PARAMETER_STRICTLY_AFTER];

            $this->addIntervalWhere($queryBuilder, $queryNameGenerator, $alias, $field, $beforeOperator, $beforeValue, $afterOperator, $afterValue, $type);

        } else {
            if (isset($values[self::PARAMETER_BEFORE])) {
                $this->addSingleWhere(
                    $queryBuilder,
                    $queryNameGenerator,
                    $alias,
                    $field,
                    self::OPERATOR_VALUE[self::PARAMETER_BEFORE],
                    $values[self::PARAMETER_BEFORE],
                    $type
                );
            } else if (isset($values[self::PARAMETER_STRICTLY_BEFORE])) {
                $this->addSingleWhere(
                    $queryBuilder,
                    $queryNameGenerator,
                    $alias,
                    $field,
                    self::OPERATOR_VALUE[self::PARAMETER_STRICTLY_BEFORE],
                    $values[self::PARAMETER_STRICTLY_BEFORE],
                    $type
                );
            }

            if (isset($values[self::PARAMETER_AFTER])) {
                $this->addSingleWhere(
                    $queryBuilder,
                    $queryNameGenerator,
                    $alias,
                    $field,
                    self::OPERATOR_VALUE[self::PARAMETER_AFTER],
                    $values[self::PARAMETER_AFTER],
                    $type
                );
            } else if (isset($values[self::PARAMETER_STRICTLY_AFTER])) {
                $this->addSingleWhere(
                    $queryBuilder,
                    $queryNameGenerator,
                    $alias,
                    $field,
                    self::OPERATOR_VALUE[self::PARAMETER_STRICTLY_AFTER],
                    $values[self::PARAMETER_STRICTLY_AFTER],
                    $type
                );
            }
        }
    }

    protected function addIntervalWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $alias,
        string $field,
        string $beforeOperator,
        string $beforeValue,
        string $afterOperator,
        string $afterValue,
        $type = null
    ): void
    {
        $type = (string)$type;
        try {
            if (strpos($type, '_immutable') === false) {
                $beforeValue = new \DateTime($beforeValue);
                $afterValue = new \DateTime($afterValue);

            } else {
                $beforeValue = new \DateTimeImmutable($beforeValue);
                $afterValue = new \DateTimeImmutable($afterValue);
            }
        } catch (\Exception $e) {
            // Silently ignore this filter if it can not be transformed to a \DateTime
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('The field "%s" has a wrong date format. Use one accepted by the \DateTime constructor', $field)),
            ]);

            return;
        }

        $fieldName = $alias . '.' . $field;
        $beforeParameter = $queryNameGenerator->generateParameterName($field);
        $afterParameter = $queryNameGenerator->generateParameterName($field);

        $beforeYear = $beforeValue->format('Y');
        $afterYear = $afterValue->format('Y');

        $beforeCondition = $this->createCondition($beforeYear, $fieldName, $beforeOperator, $beforeParameter);
        $afterCondition = $this->createCondition($afterYear, $fieldName, $afterOperator, $afterParameter);

        if ($beforeYear !== $afterYear) {
            $queryBuilder->andWhere($beforeCondition . ' OR ' . $afterCondition);
        } else {
            $queryBuilder->andWhere($beforeCondition);
            $queryBuilder->andWhere($afterCondition);
        }

        $queryBuilder->setParameter($beforeParameter, $beforeValue, $type);
        $queryBuilder->setParameter($afterParameter, $afterValue, $type);
    }

    protected function addSingleWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $alias,
        string $field,
        string $operator,
        string $value,
        $type = null
    ): void
    {
        $type = (string)$type;
        try {
            $value = false === strpos($type, '_immutable') ?
                new \DateTime($value) :
                new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            // Silently ignore this filter if it can not be transformed to a \DateTime
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('The field "%s" has a wrong date format. Use one accepted by the \DateTime constructor', $field)),
            ]);

            return;
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);
        $fieldName = $alias . '.' . $field;

        $queryBuilder->andWhere(
            $this->createCondition($value->format('Y'), $fieldName, $operator, $valueParameter)
        );
        $queryBuilder->setParameter($valueParameter, $value, $type);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();
        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $nullManagement) {
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isDateField($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_EQUAL);
            $description += $this->getFilterDescription($property, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_AFTER);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_AFTER);
        }

        return $description;
    }

    /**
     * @param $values
     * @return bool
     */
    protected function isSetInterval($values): bool
    {
        return (isset($values[self::PARAMETER_AFTER]) || isset($values[self::PARAMETER_STRICTLY_AFTER]))
            && (isset($values[self::PARAMETER_BEFORE]) || isset($values[self::PARAMETER_STRICTLY_BEFORE]));
    }

    protected function createCondition(string $year, string $field, string $operator, string $parameterName): string
    {
        return sprintf(
            'make_date(%s, cast(date_part(\'month\', %s) as integer), cast(date_part(\'day\', %s) as integer)) %s :%s',
            $year,
            $field,
            $field,
            $operator,
            $parameterName
        );
    }
}
