<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseRepository
{
    protected Model $model;

    abstract public function getModel(): Model;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    public function all($columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->model->paginate($perPage, $columns, $pageName, $page);
    }

    public function find($id, $columns = ['*']): ?Model
    {
        return $this->model->find($id, $columns);
    }

    public function findOrFail($id, $columns = ['*']): Model
    {
        return $this->model->findOrFail($id, $columns);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and'): Builder
    {
        return $this->model->where($column, $operator, $value, $boolean);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function forceDelete(Model $model): bool
    {
        return $model->forceDelete();
    }

    public function restore($id): bool
    {
        $model = $this->model->withTrashed()->find($id);
        return $model ? $model->restore() : false;
    }

    public function query(): Builder
    {
        return $this->model->query();
    }

    public function with($relations): Builder
    {
        return $this->model->with($relations);
    }

    public function when($condition, $callback): Builder
    {
        return $this->model->when($condition, $callback);
    }
}
