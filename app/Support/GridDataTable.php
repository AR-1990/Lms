<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

/**
 * The Yajra engine behind every grid (config datatables.engines.eloquent). On top of Yajra it adds:
 *  - a date range: ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD on the model's $gridDateColumn (default created_at);
 *  - a search box per column: ?col_search[key]=text, where key is a real column of the table (or table.column),
 *    a relation column (relation.column), "xyz_name" for the name of relation xyz, or a column with a
 *    filterColumn() rule. Anything else is ignored, so a computed column never breaks the query.
 * The response tells the grid what it can offer: date_column (null when the table has no date) and
 * column_search (the keys that can be searched).
 */
class GridDataTable extends EloquentDataTable
{
    /**
     * @var array<string, array<int, string>>
     */
    private static array $tableColumns = [];

    /**
     * Callers that are not a DataTables grid (the mobile apps, any plain API client: no "draw") get the same
     * query, filters, computed columns and date range as a normal list: ?page, ?per_page (max 100) and ?search,
     * answered as { items, meta } like every other paginated endpoint. Grids keep the DataTables format.
     */
    public function make(bool $mDataSupport = true): JsonResponse
    {
        $request = $this->request->getBaseRequest();

        if ($request->has('draw')) {
            return parent::make($mDataSupport);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $page = max($request->integer('page', 1), 1);
        $request->merge(['start' => ($page - 1) * $perPage, 'length' => $perPage]);

        $search = $request->input('search');

        if (is_string($search) && trim($search) !== '') {
            $this->plainSearch(trim($search));
        }

        if (! $request->has('order')) {
            $this->query->orderByDesc($this->query->getModel()->getQualifiedKeyName());
        }

        $grid = parent::make($mDataSupport)->getData(true);

        if (! array_key_exists('data', $grid)) {
            return new JsonResponse($grid, 500);
        }

        $total = (int) ($grid['recordsFiltered'] ?? 0);

        return new JsonResponse([
            'items' => $grid['data'],
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    /**
     * ?search for plain API callers: any name, title, code, number, email or phone column of the table.
     */
    private function plainSearch(string $keyword): void
    {
        $table = $this->query->getModel()->getTable();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%';
        $columns = array_values(array_filter($this->columns($table), fn (string $column): bool => (bool) preg_match('/(^|_)(name|title|code|no|number|email|phone|subject)$/', $column)));

        if ($columns !== []) {
            $this->query->where(function (Builder $query) use ($columns, $table, $like): void {
                foreach ($columns as $column) {
                    $query->orWhere($table.'.'.$column, 'like', $like);
                }
            });
        }
    }

    public function columnSearch(): void
    {
        $table = $this->query->getModel()->getTable();
        $dateColumn = $this->dateColumn();
        $this->with('date_column', $dateColumn);

        foreach (['>=' => 'date_from', '<=' => 'date_to'] as $operator => $key) {
            $value = $this->request->input($key);

            if ($dateColumn !== null && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->query->whereDate($table.'.'.$dateColumn, $operator, $value);
            }
        }

        $searchable = [];

        foreach ($this->request->columns() as $index => $column) {
            $key = $this->getColumnName($index);

            if ($key !== null && $this->searchFor($key, '', true)) {
                $searchable[] = $key;
            }
        }

        $this->with('column_search', $searchable);

        foreach ((array) $this->request->input('col_search', []) as $key => $keyword) {
            if (is_string($key) && is_string($keyword) && trim($keyword) !== '' && in_array($key, $searchable, true)) {
                $this->searchFor($key, trim($keyword), false);
            }
        }
    }

    /**
     * Search one column, or (with $onlyCheck) just tell whether it can be searched.
     */
    private function searchFor(string $key, string $keyword, bool $onlyCheck): bool
    {
        $model = $this->query->getModel();
        $table = $model->getTable();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%';

        if ($this->hasFilterColumn($key)) {
            $onlyCheck || $this->applyFilterColumn($this->getBaseQueryBuilder(), $key, $keyword);

            return true;
        }

        $column = str_starts_with($key, $table.'.') ? substr($key, strlen($table) + 1) : $key;

        if (in_array($column, $this->columns($table), true)) {
            if (! $onlyCheck) {
                $flag = preg_match('/^(is|has|can|requires|show|only)_/', $column) ? $this->yesNo($keyword) : null;
                $flag === null
                    ? $this->query->where($table.'.'.$column, 'like', $like)
                    : $this->query->where($table.'.'.$column, $flag);
            }

            return true;
        }

        [$relation, $field] = str_contains($column, '.') ? explode('.', $column, 2) : [Str::camel(Str::beforeLast($column, '_name')), 'name'];

        if (! str_contains($column, '.') && ! str_ends_with($column, '_name')) {
            return false;
        }

        if (! method_exists($model, $relation) || ! ($model->{$relation}() instanceof Relation)) {
            return false;
        }

        $related = $model->{$relation}()->getRelated();

        if (! in_array($field, $this->columns($related->getTable()), true)) {
            return false;
        }

        // A relation to the same table (class → next class) is aliased inside whereHas, so leave the column bare there.
        $target = $related->getTable() === $table ? $field : $related->getTable().'.'.$field;
        $onlyCheck || $this->query->whereHas($relation, fn (Builder $query) => $query->where($target, 'like', $like));

        return true;
    }

    private function yesNo(string $keyword): ?int
    {
        return match (Str::lower($keyword)) {
            'yes', 'y', '1', 'true', 'active', 'on' => 1,
            'no', 'n', '0', 'false', 'inactive', 'off' => 0,
            default => null,
        };
    }

    private function dateColumn(): ?string
    {
        $model = $this->query->getModel();
        $column = property_exists($model, 'gridDateColumn') ? $model->gridDateColumn : 'created_at';

        return in_array($column, $this->columns($model->getTable()), true) ? $column : null;
    }

    /**
     * @return array<int, string>
     */
    private function columns(string $table): array
    {
        return self::$tableColumns[$table] ??= Schema::getColumnListing($table);
    }
}
