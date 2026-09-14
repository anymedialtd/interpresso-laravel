<?php

namespace AnyMedia\Interpresso\Services;

use Illuminate\Database\Eloquent\Model;

/** A source descriptor, never the file contents or an entire table in a queue payload. */
class ImportSource
{
    public ?string $fingerprint = null;

    public function __construct(
        public int $languageId,
        public string $languageCode,
        public string $type,
        public string $path,
        public string $namespace = '',
        public string $group = '',
        public bool $isVendor = false,
    ) {
    }

    public function model(): Model
    {
        $model = app($this->path);
        if (!$model instanceof Model) {
            throw new \TypeError('Translatable models must be Eloquent models.');
        }
        return $model;
    }

    public function estimatedJobs(int $chunkSize): int
    {
        if ($this->type === 'model') {
            $model = $this->model();
            $rows = $model->getConnection()->table($model->getTable())->count();
        } else {
            // A byte-based estimate avoids parsing every source during HTTP dispatch.
            // File entries vary in size; batch progress is intentionally approximate.
            $bytes = filesize($this->path);
            $rows = (int) ceil(($bytes === false ? 0 : $bytes) / 64);
        }
        return intdiv($rows, $chunkSize) + 1;
    }
}
