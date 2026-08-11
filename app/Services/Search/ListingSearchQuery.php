<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\CustomFieldType;
use App\Exceptions\CustomFieldConflict;
use App\Exceptions\InvalidSearchQuery;
use App\Models\CustomField;
use App\Models\Listing;
use App\Support\CustomFieldValueNormalizer;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Compiles a validated search request into an Eloquent query over Listing,
 * using the declarative custom-field metadata (docs/DECLARATIVE_FIELDS.md §2B,
 * §5.4). Everything is allowlisted from the field definitions; nothing from the
 * request is ever interpolated into SQL (column and direction are derived from
 * the field's type, values are always bound).
 *
 * VISIBILITY IS NOT THIS COMPILER'S JOB: it never adds a status scope. A public
 * controller must chain ->published(); an admin surface may use any status.
 *
 * Request shape:
 *   [
 *     'category_id' => int>0,                        // required when filters/sort present
 *     'filters' => [ ['key'=>.., 'operator'=>.., 'value'=>..|'values'=>[..]], ... ],
 *     'sort'    => ['key'=>.., 'direction'=>'asc'|'desc'] | null,
 *   ]
 */
final class ListingSearchQuery
{
    public const MAX_FILTERS = 8;

    public const MAX_IN_VALUES = 50;

    /** SQL operators keyed by the contract operator name. */
    private const SQL_COMPARATORS = ['lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>='];

    public function __construct(private readonly CustomFieldValueNormalizer $normalizer = new CustomFieldValueNormalizer) {}

    /**
     * @param  array<string, mixed>  $request
     */
    public function build(array $request): Builder
    {
        $sort = $request['sort'] ?? null;
        if ($sort !== null && ! is_array($sort)) {
            throw InvalidSearchQuery::make('sort must be null or an object.');
        }

        $filters = $this->normalizeFilters($request['filters'] ?? []);
        $hasCategory = array_key_exists('category_id', $request) && $request['category_id'] !== null;

        $query = Listing::query();

        // No category, no filters, no sort → the unscoped base query is allowed.
        if ($filters === [] && $sort === null && ! $hasCategory) {
            return $query->orderBy('listings.id');
        }

        // Otherwise a positive category is required, and it scopes the listings.
        $categoryId = $this->requireCategoryId($request['category_id'] ?? null);
        $query->where('listings.category_id', $categoryId);

        /** @var array<string, CustomField> $fields */
        $fields = CustomField::query()
            ->where('category_id', $categoryId)
            ->get()
            ->keyBy('key')
            ->all();

        $this->applyFilters($query, $fields, $filters);
        $this->applySort($query, $fields, $sort);

        return $query;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function normalizeFilters(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw InvalidSearchQuery::make('filters must be a list.');
        }
        if (count($raw) > self::MAX_FILTERS) {
            throw InvalidSearchQuery::make('Too many filters (max '.self::MAX_FILTERS.').');
        }

        return $raw;
    }

    private function requireCategoryId(mixed $categoryId): int
    {
        $valid = (is_int($categoryId) && $categoryId > 0)
            || (is_string($categoryId) && ctype_digit($categoryId) && (int) $categoryId > 0);

        if (! $valid) {
            throw InvalidSearchQuery::make('A positive category_id is required for custom filters or sort.');
        }

        return (int) $categoryId;
    }

    /**
     * @param  array<string, CustomField>  $fields
     * @param  list<array<string,mixed>>  $filters
     */
    private function applyFilters(Builder $query, array $fields, array $filters): void
    {
        $seen = [];
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                throw InvalidSearchQuery::make('Each filter must be an object.');
            }

            $key = is_string($filter['key'] ?? null) ? $filter['key'] : null;
            $operator = is_string($filter['operator'] ?? null) ? $filter['operator'] : null;

            $field = $fields[$key] ?? null;
            if ($key === null || $field === null) {
                throw InvalidSearchQuery::make('Unknown or cross-category filter field.');
            }
            if (! (bool) $field->filterable) {
                throw InvalidSearchQuery::make("Field '{$key}' is not filterable.");
            }
            if (isset($seen[$key])) {
                throw InvalidSearchQuery::make("Duplicate filter for field '{$key}'.");
            }
            $seen[$key] = true;

            /** @var CustomFieldType $type */
            $type = $field->type;
            if ($operator === null || ! in_array($operator, $type->filterOperators(), true)) {
                throw InvalidSearchQuery::make("Operator not allowed for field '{$key}'.");
            }

            $column = $type->valueColumn();
            $fieldId = (int) $field->id;

            $query->whereExists(function (QueryBuilder $sub) use ($fieldId, $column, $type, $operator, $filter): void {
                $sub->selectRaw('1')
                    ->from('custom_field_values')
                    ->whereColumn('custom_field_values.listing_id', 'listings.id')
                    ->where('custom_field_values.custom_field_id', $fieldId);

                $this->applyOperator($sub, $column, $type, $operator, $filter);
            });
        }
    }

    /**
     * @param  array<string,mixed>  $filter
     */
    private function applyOperator(QueryBuilder $sub, string $column, CustomFieldType $type, string $operator, array $filter): void
    {
        $col = 'custom_field_values.'.$column;

        switch ($operator) {
            case 'eq':
                $sub->where($col, '=', $this->cast($type, $this->requireValue($filter)));
                break;

            case 'lt':
            case 'lte':
            case 'gt':
            case 'gte':
                $sub->where($col, self::SQL_COMPARATORS[$operator], $this->cast($type, $this->requireValue($filter)));
                break;

            case 'in':
                $values = $filter['values'] ?? null;
                if (! is_array($values) || ! array_is_list($values) || $values === []) {
                    throw InvalidSearchQuery::make("Operator 'in' requires a non-empty values list.");
                }
                if (count($values) > self::MAX_IN_VALUES) {
                    throw InvalidSearchQuery::make("Operator 'in' allows at most ".self::MAX_IN_VALUES.' values.');
                }
                $sub->whereIn($col, array_map(fn ($v) => $this->cast($type, $v), $values));
                break;

            case 'between':
                $range = $filter['value'] ?? null;
                if (! is_array($range) || ! array_is_list($range) || count($range) !== 2) {
                    throw InvalidSearchQuery::make("Operator 'between' requires a list of exactly [min, max].");
                }
                $sub->whereBetween($col, [$this->cast($type, $range[0]), $this->cast($type, $range[1])]);
                break;

            case 'contains':
                $value = (string) $this->cast($type, $this->requireValue($filter));
                // Escaping and case-folding both live in SearchTerm; $col is
                // built from field metadata above, never from request input.
                $sub->whereRaw(SearchTerm::likeExpression($col), [SearchTerm::containsPattern($value)]);
                break;

            default:
                throw InvalidSearchQuery::make('Unsupported operator.'); // unreachable (allowlisted)
        }
    }

    /** @param array<string,mixed> $filter */
    private function requireValue(array $filter): mixed
    {
        if (! array_key_exists('value', $filter)) {
            throw InvalidSearchQuery::make('This operator requires a value.');
        }

        return $filter['value'];
    }

    /**
     * @param  array<string, CustomField>  $fields
     * @param  array<string,mixed>|null  $sort
     */
    private function applySort(Builder $query, array $fields, ?array $sort): void
    {
        if ($sort === null) {
            $query->orderBy('listings.id');

            return;
        }

        $key = is_string($sort['key'] ?? null) ? $sort['key'] : null;
        $rawDirection = $sort['direction'] ?? 'asc';
        if (! is_string($rawDirection)) {
            throw InvalidSearchQuery::make('sort direction must be a string.');
        }
        $direction = strtolower($rawDirection);

        $field = $fields[$key] ?? null;
        if ($key === null || $field === null) {
            throw InvalidSearchQuery::make('Unknown or cross-category sort field.');
        }
        if (! (bool) $field->sortable) {
            throw InvalidSearchQuery::make("Field '{$key}' is not sortable.");
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw InvalidSearchQuery::make('Invalid sort direction.');
        }

        /** @var CustomFieldType $type */
        $type = $field->type;
        $column = $type->valueColumn();
        $alias = 'cfv_sort';
        $fieldId = (int) $field->id;

        // LEFT JOIN is dup-free because (custom_field_id, listing_id) is unique.
        $query->leftJoin("custom_field_values as {$alias}", function ($join) use ($alias, $fieldId): void {
            $join->on("{$alias}.listing_id", '=', 'listings.id')
                ->where("{$alias}.custom_field_id", '=', $fieldId);
        })
            ->select('listings.*')
            ->orderByRaw("{$alias}.{$column} is null asc") // NULLs last
            ->orderBy("{$alias}.{$column}", $direction)
            ->orderBy('listings.id'); // deterministic tie-breaker
    }

    /** Typed cast for comparison; surfaces an invalid value as a search error. */
    private function cast(CustomFieldType $type, mixed $raw): string|bool
    {
        try {
            return $this->normalizer->castForFilter($type, $raw);
        } catch (CustomFieldConflict $e) {
            throw InvalidSearchQuery::make('Invalid filter value: '.$e->getMessage());
        }
    }
}
