<?php

declare(strict_types=1);

namespace Illuminate\Database\Migrations {
    class Migration
    {
    }
}

namespace Illuminate\Database\Schema {
    class ColumnDefinition
    {
        public string $name;
        public string $type;
        public array $options = [];
        public bool $nullable = false;
        public mixed $default = null;
        public bool $hasDefault = false;
        public ?string $comment = null;
        public bool $primary = false;
        public bool $unique = false;
        public bool $indexed = false;
        private Blueprint $blueprint;
        private ?array $fkRef = null;

        public function __construct(Blueprint $blueprint, string $name, string $type, array $options = [])
        {
            $this->blueprint = $blueprint;
            $this->name = $name;
            $this->type = $type;
            $this->options = $options;
        }

        public function nullable(bool $value = true): self
        {
            $this->nullable = $value;
            return $this;
        }

        public function default(mixed $value): self
        {
            $this->default = $value;
            $this->hasDefault = true;
            return $this;
        }

        public function comment(string $value): self
        {
            $this->comment = $value;
            return $this;
        }

        public function primary(): self
        {
            $this->primary = true;
            \Illuminate\Support\Facades\Schema::addPrimary($this->blueprint->table, [$this->name]);
            return $this;
        }

        public function unique(string|array|null $name = null): self
        {
            $this->unique = true;
            \Illuminate\Support\Facades\Schema::addIndex($this->blueprint->table, [$this->name], is_string($name) ? $name : null, 'unique');
            return $this;
        }

        public function index(string|array|null $name = null): self
        {
            $this->indexed = true;
            \Illuminate\Support\Facades\Schema::addIndex($this->blueprint->table, [$this->name], is_string($name) ? $name : null, 'index');
            return $this;
        }

        public function after(string $column): self
        {
            \Illuminate\Support\Facades\Schema::moveColumnAfter($this->blueprint->table, $this->name, $column);
            return $this;
        }

        public function useCurrent(): self
        {
            $this->default = 'CURRENT_TIMESTAMP';
            $this->hasDefault = true;
            return $this;
        }

        public function constrained(?string $table = null, string $column = 'id'): self
        {
            $refTable = $table ?: guessTableFromColumn($this->name);
            $this->fkRef = \Illuminate\Support\Facades\Schema::addForeign(
                $this->blueprint->table,
                [$this->name],
                $refTable,
                [$column],
                null
            );
            return $this;
        }

        public function onDelete(string $action): self
        {
            if ($this->fkRef !== null) {
                \Illuminate\Support\Facades\Schema::setForeignOnDelete($this->blueprint->table, $this->fkRef['id'], $action);
            }
            return $this;
        }

        public function cascadeOnDelete(): self
        {
            return $this->onDelete('cascade');
        }

        public function nullOnDelete(): self
        {
            return $this->onDelete('set null');
        }

        public function restrictOnDelete(): self
        {
            return $this->onDelete('restrict');
        }

        public function change(): self
        {
            return $this;
        }

        public function virtualAs(string $expression): self
        {
            $this->options['virtual_as'] = $expression;
            return $this;
        }

        public function unsigned(): self
        {
            $this->options['unsigned'] = true;
            return $this;
        }
    }

    class ForeignDefinition
    {
        private string $table;
        private int $id;

        public function __construct(string $table, int $id)
        {
            $this->table = $table;
            $this->id = $id;
        }

        public function references(string|array $columns): self
        {
            \Illuminate\Support\Facades\Schema::setForeignReferences($this->table, $this->id, (array) $columns);
            return $this;
        }

        public function on(string $table): self
        {
            \Illuminate\Support\Facades\Schema::setForeignTable($this->table, $this->id, $table);
            return $this;
        }

        public function onDelete(string $action): self
        {
            \Illuminate\Support\Facades\Schema::setForeignOnDelete($this->table, $this->id, $action);
            return $this;
        }

        public function cascadeOnDelete(): self
        {
            return $this->onDelete('cascade');
        }

        public function nullOnDelete(): self
        {
            return $this->onDelete('set null');
        }

        public function restrictOnDelete(): self
        {
            return $this->onDelete('restrict');
        }
    }

    class Blueprint
    {
        public string $table;

        public function __construct(string $table)
        {
            $this->table = $table;
        }

        public function id(string $name = 'id'): ColumnDefinition
        {
            return $this->addColumn('unsignedBigInteger', $name, ['auto_increment' => true])->primary();
        }

        public function char(string $name, int $length = 255): ColumnDefinition
        {
            return $this->addColumn('char', $name, ['length' => $length]);
        }

        public function uuid(string $name): ColumnDefinition
        {
            return $this->addColumn('char', $name, ['length' => 36]);
        }

        public function string(string $name, int $length = 255): ColumnDefinition
        {
            return $this->addColumn('string', $name, ['length' => $length]);
        }

        public function text(string $name): ColumnDefinition
        {
            return $this->addColumn('text', $name);
        }

        public function mediumText(string $name): ColumnDefinition
        {
            return $this->addColumn('mediumText', $name);
        }

        public function longText(string $name): ColumnDefinition
        {
            return $this->addColumn('longText', $name);
        }

        public function integer(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('integer', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function unsignedInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('unsignedInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function smallInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('smallInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function unsignedSmallInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('unsignedSmallInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function unsignedTinyInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('unsignedTinyInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function tinyInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('tinyInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function bigInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('bigInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function unsignedBigInteger(string $name, bool $autoIncrement = false): ColumnDefinition
        {
            $column = $this->addColumn('unsignedBigInteger', $name, ['auto_increment' => $autoIncrement]);
            return $autoIncrement ? $column->primary() : $column;
        }

        public function boolean(string $name): ColumnDefinition
        {
            return $this->addColumn('boolean', $name);
        }

        public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
        {
            return $this->addColumn('decimal', $name, ['precision' => $precision, 'scale' => $scale]);
        }

        public function double(string $name): ColumnDefinition
        {
            return $this->addColumn('double', $name);
        }

        public function json(string $name): ColumnDefinition
        {
            return $this->addColumn('json', $name);
        }

        public function date(string $name): ColumnDefinition
        {
            return $this->addColumn('date', $name);
        }

        public function time(string $name): ColumnDefinition
        {
            return $this->addColumn('time', $name);
        }

        public function dateTime(string $name): ColumnDefinition
        {
            return $this->addColumn('dateTime', $name);
        }

        public function timestamp(string $name): ColumnDefinition
        {
            return $this->addColumn('timestamp', $name);
        }

        public function enum(string $name, array $values): ColumnDefinition
        {
            return $this->addColumn('enum', $name, ['values' => array_values($values)]);
        }

        public function foreignUuid(string $name): ColumnDefinition
        {
            return $this->addColumn('char', $name, ['length' => 36]);
        }

        public function foreignId(string $name): ColumnDefinition
        {
            return $this->addColumn('unsignedBigInteger', $name);
        }

        public function morphs(string $name): void
        {
            $this->string($name . '_type');
            $this->unsignedBigInteger($name . '_id');
            $this->index([$name . '_type', $name . '_id'], $name . '_morphs_index');
        }

        public function nullableMorphs(string $name): void
        {
            $this->string($name . '_type')->nullable();
            $this->unsignedBigInteger($name . '_id')->nullable();
            $this->index([$name . '_type', $name . '_id'], $name . '_morphs_index');
        }

        public function timestamps(): void
        {
            $this->timestamp('created_at')->nullable();
            $this->timestamp('updated_at')->nullable();
        }

        public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
        {
            return $this->timestamp($column)->nullable();
        }

        public function primary(string|array $columns, ?string $name = null): void
        {
            \Illuminate\Support\Facades\Schema::addPrimary($this->table, (array) $columns, $name);
        }

        public function unique(string|array $columns, ?string $name = null): void
        {
            \Illuminate\Support\Facades\Schema::addIndex($this->table, (array) $columns, $name, 'unique');
        }

        public function index(string|array $columns, ?string $name = null): void
        {
            \Illuminate\Support\Facades\Schema::addIndex($this->table, (array) $columns, $name, 'index');
        }

        public function foreign(string|array $columns, ?string $name = null): ForeignDefinition
        {
            $fk = \Illuminate\Support\Facades\Schema::addForeign($this->table, (array) $columns, null, ['id'], $name);
            return new ForeignDefinition($this->table, $fk['id']);
        }

        public function dropColumn(string|array $columns): void
        {
            foreach ((array) $columns as $column) {
                \Illuminate\Support\Facades\Schema::dropColumn($this->table, $column);
            }
        }

        public function renameColumn(string $from, string $to): void
        {
            \Illuminate\Support\Facades\Schema::renameColumn($this->table, $from, $to);
        }

        public function dropForeign(string|array $columns): void
        {
            \Illuminate\Support\Facades\Schema::dropForeign($this->table, $columns);
        }

        public function dropIndex(string|array $index): void
        {
            \Illuminate\Support\Facades\Schema::dropIndex($this->table, $index);
        }

        public function dropUnique(string|array $index): void
        {
            \Illuminate\Support\Facades\Schema::dropIndex($this->table, $index);
        }

        private function addColumn(string $type, string $name, array $options = []): ColumnDefinition
        {
            return \Illuminate\Support\Facades\Schema::upsertColumn($this->table, $name, $type, $options);
        }
    }

    function guessTableFromColumn(string $column): string
    {
        $base = preg_replace('/_id$/', '', $column) ?: $column;
        $special = [
            'user' => 'users',
            'owner' => 'users',
            'actor' => 'users',
            'created_by' => 'users',
            'updated_by' => 'users',
            'approved_by' => 'users',
            'reviewed_by' => 'users',
            'requested_by' => 'users',
            'generated_by' => 'users',
            'changed_by' => 'users',
            'customer' => 'users',
            'court_type' => 'court_types',
            'venue_cluster' => 'venue_clusters',
            'venue_court' => 'venue_courts',
        ];

        return $special[$base] ?? ($base . 's');
    }
}

namespace Illuminate\Support\Facades {
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Database\Schema\ColumnDefinition;

    class Schema
    {
        private static array $tables = [];

        public static function create(string $table, callable $callback): void
        {
            self::$tables[$table] = [
                'columns' => [],
                'primary' => [],
                'foreigns' => [],
                'indexes' => [],
            ];

            $callback(new Blueprint($table));
        }

        public static function table(string $table, callable $callback): void
        {
            self::ensureTable($table);
            $callback(new Blueprint($table));
        }

        public static function hasTable(string $table): bool
        {
            return isset(self::$tables[$table]);
        }

        public static function hasColumn(string $table, string $column): bool
        {
            return isset(self::$tables[$table]['columns'][$column]);
        }

        public static function hasIndex(string $table, string $index): bool
        {
            if (! isset(self::$tables[$table])) {
                return false;
            }

            foreach (self::$tables[$table]['indexes'] as $item) {
                if (($item['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        public static function dropIfExists(string $table): void
        {
            unset(self::$tables[$table]);
        }

        public static function disableForeignKeyConstraints(): bool
        {
            return true;
        }

        public static function enableForeignKeyConstraints(): bool
        {
            return true;
        }

        public static function withoutForeignKeyConstraints(callable $callback): mixed
        {
            return $callback();
        }

        public static function upsertColumn(string $table, string $name, string $type, array $options = []): ColumnDefinition
        {
            self::ensureTable($table);

            if (isset(self::$tables[$table]['columns'][$name])) {
                $column = self::$tables[$table]['columns'][$name];
                $column->type = $type;
                $column->options = array_merge($column->options, $options);
                return $column;
            }

            $column = new ColumnDefinition(new Blueprint($table), $name, $type, $options);
            self::$tables[$table]['columns'][$name] = $column;
            return $column;
        }

        public static function dropColumn(string $table, string $column): void
        {
            unset(self::$tables[$table]['columns'][$column]);
            self::$tables[$table]['primary'] = array_values(array_filter(self::$tables[$table]['primary'] ?? [], fn ($item) => $item !== $column));
            self::$tables[$table]['foreigns'] = array_values(array_filter(
                self::$tables[$table]['foreigns'] ?? [],
                fn ($fk) => ! in_array($column, $fk['columns'], true)
            ));
        }

        public static function renameColumn(string $table, string $from, string $to): void
        {
            if (! isset(self::$tables[$table]['columns'][$from])) {
                return;
            }

            $new = [];
            foreach (self::$tables[$table]['columns'] as $name => $column) {
                if ($name === $from) {
                    $column->name = $to;
                    $new[$to] = $column;
                } else {
                    $new[$name] = $column;
                }
            }
            self::$tables[$table]['columns'] = $new;

            foreach (self::$tables[$table]['primary'] as &$column) {
                if ($column === $from) {
                    $column = $to;
                }
            }
            foreach (self::$tables[$table]['foreigns'] as &$fk) {
                foreach ($fk['columns'] as &$column) {
                    if ($column === $from) {
                        $column = $to;
                    }
                }
            }
        }

        public static function moveColumnAfter(string $table, string $column, string $after): void
        {
            if (! isset(self::$tables[$table]['columns'][$column])) {
                return;
            }

            $moving = self::$tables[$table]['columns'][$column];
            unset(self::$tables[$table]['columns'][$column]);
            $new = [];
            $inserted = false;
            foreach (self::$tables[$table]['columns'] as $name => $value) {
                $new[$name] = $value;
                if ($name === $after) {
                    $new[$column] = $moving;
                    $inserted = true;
                }
            }
            if (! $inserted) {
                $new[$column] = $moving;
            }
            self::$tables[$table]['columns'] = $new;
        }

        public static function addPrimary(string $table, array $columns, ?string $name = null): void
        {
            self::ensureTable($table);
            self::$tables[$table]['primary'] = array_values($columns);
            foreach ($columns as $column) {
                if (isset(self::$tables[$table]['columns'][$column])) {
                    self::$tables[$table]['columns'][$column]->primary = true;
                }
            }
        }

        public static function addIndex(string $table, array $columns, ?string $name, string $type): void
        {
            self::ensureTable($table);
            self::$tables[$table]['indexes'][] = [
                'name' => $name ?: self::defaultIndexName($table, $columns, $type),
                'columns' => array_values($columns),
                'type' => $type,
            ];

            if (count($columns) === 1 && isset(self::$tables[$table]['columns'][$columns[0]])) {
                if ($type === 'unique') {
                    self::$tables[$table]['columns'][$columns[0]]->unique = true;
                } else {
                    self::$tables[$table]['columns'][$columns[0]]->indexed = true;
                }
            }
        }

        public static function dropIndex(string $table, string|array $index): void
        {
            if (! isset(self::$tables[$table])) {
                return;
            }

            $names = (array) $index;
            self::$tables[$table]['indexes'] = array_values(array_filter(
                self::$tables[$table]['indexes'],
                fn ($item) => ! in_array($item['name'], $names, true)
            ));
        }

        public static function addForeign(string $table, array $columns, ?string $refTable, array $refColumns, ?string $name): array
        {
            self::ensureTable($table);
            $id = count(self::$tables[$table]['foreigns']);
            self::$tables[$table]['foreigns'][] = [
                'id' => $id,
                'name' => $name,
                'columns' => array_values($columns),
                'references' => array_values($refColumns),
                'on' => $refTable,
                'on_delete' => null,
            ];

            return ['id' => $id];
        }

        public static function setForeignReferences(string $table, int $id, array $references): void
        {
            self::$tables[$table]['foreigns'][$id]['references'] = array_values($references);
        }

        public static function setForeignTable(string $table, int $id, string $refTable): void
        {
            self::$tables[$table]['foreigns'][$id]['on'] = $refTable;
        }

        public static function setForeignOnDelete(string $table, int $id, string $action): void
        {
            self::$tables[$table]['foreigns'][$id]['on_delete'] = $action;
        }

        public static function dropForeign(string $table, string|array $columns): void
        {
            if (! isset(self::$tables[$table])) {
                return;
            }

            if (is_array($columns)) {
                self::$tables[$table]['foreigns'] = array_values(array_filter(
                    self::$tables[$table]['foreigns'],
                    fn ($fk) => $fk['columns'] !== array_values($columns)
                ));
            }
        }

        public static function modifyColumnFromSql(string $sql): void
        {
            if (! preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+MODIFY\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', trim($sql), $match)) {
                return;
            }

            $table = $match[1];
            $column = $match[2];
            $definition = $match[3];
            if (! isset(self::$tables[$table]['columns'][$column])) {
                return;
            }

            $type = self::$tables[$table]['columns'][$column]->type;
            $options = self::$tables[$table]['columns'][$column]->options;
            if (preg_match('/^ENUM\s*\((.*?)\)/is', $definition, $enumMatch)) {
                $type = 'enum';
                preg_match_all("/'([^']*)'/", $enumMatch[1], $values);
                $options['values'] = $values[1];
            } elseif (preg_match('/^VARCHAR\s*\((\d+)\)/i', $definition, $stringMatch)) {
                $type = 'string';
                $options['length'] = (int) $stringMatch[1];
            } elseif (preg_match('/^CHAR\s*\((\d+)\)/i', $definition, $charMatch)) {
                $type = 'char';
                $options['length'] = (int) $charMatch[1];
            } elseif (preg_match('/^DECIMAL\s*\((\d+),\s*(\d+)\)/i', $definition, $decimalMatch)) {
                $type = 'decimal';
                $options['precision'] = (int) $decimalMatch[1];
                $options['scale'] = (int) $decimalMatch[2];
            }

            $col = self::upsertColumn($table, $column, $type, $options);
            if (stripos($definition, 'NOT NULL') !== false) {
                $col->nullable(false);
            } elseif (preg_match('/\sNULL\b/i', $definition)) {
                $col->nullable(true);
            }
            if (preg_match("/DEFAULT\s+'([^']*)'/i", $definition, $defaultMatch)) {
                $col->default($defaultMatch[1]);
            }
            if (preg_match("/COMMENT\s+'([^']*)'/i", $definition, $commentMatch)) {
                $col->comment($commentMatch[1]);
            }
        }

        public static function all(): array
        {
            return self::$tables;
        }

        private static function ensureTable(string $table): void
        {
            if (! isset(self::$tables[$table])) {
                self::$tables[$table] = [
                    'columns' => [],
                    'primary' => [],
                    'foreigns' => [],
                    'indexes' => [],
                ];
            }
        }

        private static function defaultIndexName(string $table, array $columns, string $type): string
        {
            return $table . '_' . implode('_', $columns) . '_' . $type;
        }
    }

    class DB
    {
        public static function getDriverName(): string
        {
            return 'mysql';
        }

        public static function statement(string $sql): void
        {
            Schema::modifyColumnFromSql($sql);
        }

        public static function unprepared(string $sql): void
        {
        }

        public static function connection(?string $name = null): self
        {
            return new self();
        }

        public static function raw(string $expression): string
        {
            return $expression;
        }

        public static function table(string $table): QueryBuilderStub
        {
            return new QueryBuilderStub();
        }
    }

    class QueryBuilderStub
    {
        public function __call(string $name, array $arguments): self
        {
            if ($name === 'chunkById' && isset($arguments[1]) && is_callable($arguments[1])) {
                $arguments[1]([]);
            }
            return $this;
        }

        public function update(array $values): int
        {
            return 0;
        }
    }
}

namespace {
    use Illuminate\Support\Facades\Schema;

    date_default_timezone_set('Asia/Ho_Chi_Minh');

    if (! function_exists('now')) {
        function now(): \DateTimeImmutable
        {
            return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        }
    }

    $root = dirname(__DIR__);
    $docPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'DB_Design_Report_From_Migrations.md';
    $migrationDir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';

    $existing = parseExistingDoc($docPath);
    $migrationFiles = glob($migrationDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
    sort($migrationFiles, SORT_STRING);

    $errors = [];
    foreach ($migrationFiles as $file) {
        try {
            $migration = require $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
            }
        } catch (\Throwable $exception) {
            $errors[] = basename($file) . ': ' . $exception->getMessage();
        }
    }

    $tables = Schema::all();
    $markdown = buildMarkdown($tables, $existing, $migrationFiles, $errors);
    file_put_contents($docPath, $markdown);

    echo 'Updated ' . $docPath . PHP_EOL;
    echo 'Tables: ' . count($tables) . PHP_EOL;
    if ($errors !== []) {
        echo 'Migration warnings:' . PHP_EOL;
        foreach ($errors as $error) {
            echo '- ' . $error . PHP_EOL;
        }
    }

    function parseExistingDoc(string $path): array
    {
        $result = [
            'summary' => [],
            'purpose' => [],
        ];
        if (! is_file($path)) {
            return $result;
        }

        $lines = preg_split('/\R/u', file_get_contents($path)) ?: [];
        $currentTable = null;
        $capturePurpose = false;
        $inSummary = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, '## PHẦN 1.')) {
                $inSummary = true;
            } elseif (str_starts_with($line, '## PHẦN 2.')) {
                $inSummary = false;
            }

            if ($inSummary && preg_match('/^\|\s*\d+\s*\|\s*([a-zA-Z0-9_]+)\s*\|\s*([^|]+)\|\s*([^|]+)\|\s*([^|]+)\|/u', $line, $match)) {
                $result['summary'][$match[1]] = [
                    'module' => cleanText($match[2]),
                    'title' => cleanText($match[3]),
                    'description' => cleanText($match[4]),
                ];
            }
            if (preg_match('/^## Tên bảng:\s*([a-zA-Z0-9_]+)/u', $line, $match)) {
                $currentTable = $match[1];
                $capturePurpose = false;
                continue;
            }
            if ($currentTable !== null && str_starts_with($line, '### 1.')) {
                $capturePurpose = true;
                continue;
            }
            if ($capturePurpose && trim($line) !== '') {
                $text = cleanText($line);
                if (! isPlaceholder($text)) {
                    $result['purpose'][$currentTable] = $text;
                }
                $capturePurpose = false;
            }
        }

        return $result;
    }

    function buildMarkdown(array $tables, array $existing, array $migrationFiles, array $errors): string
    {
        $tableNames = array_keys($tables);
        $summaryRows = [];
        foreach ($tableNames as $table) {
            $info = tableInfo($table, $existing);
            $summaryRows[] = [
                'table' => $table,
                'module' => $info['module'],
                'title' => $info['title'],
                'description' => $info['description'],
                'links' => foreignSummary($tables[$table]['foreigns']),
            ];
        }

        $out = [];
        $out[] = '# Báo Cáo Thiết Kế Database Dự Án SportGo';
        $out[] = '';
        $out[] = 'Tài liệu này được cập nhật tự động từ các file migration trong `database/migrations`, kết hợp mô tả cột từ `comment(...)` và mô tả nghiệp vụ suy ra từ tên bảng/cột trong code.';
        $out[] = '';
        $out[] = '- Ngày cập nhật: ' . date('Y-m-d H:i:s') . ' (Asia/Saigon)';
        $out[] = '- Số file migration đã đọc: ' . count($migrationFiles);
        $out[] = '- Số bảng trong thiết kế hiện tại: ' . count($tables);
        if ($errors !== []) {
            $out[] = '- Cảnh báo khi đọc migration: ' . count($errors);
        }
        $out[] = '';
        $out[] = '==================================================';
        $out[] = '## PHẦN 1. TỔNG HỢP CÁC BẢNG';
        $out[] = '==================================================';
        $out[] = '';
        $out[] = '| STT | Tên bảng | Module | Tác dụng chính | Mô tả | Các liên kết chính |';
        $out[] = '|---|---|---|---|---|---|';
        foreach ($summaryRows as $index => $row) {
            $out[] = '| ' . ($index + 1) . ' | ' . $row['table'] . ' | ' . $row['module'] . ' | ' . $row['title'] . ' | ' . $row['description'] . ' | ' . $row['links'] . ' |';
        }

        $out[] = '';
        $out[] = '==================================================';
        $out[] = '## PHẦN 2. CHI TIẾT CÁC BẢNG';
        $out[] = '==================================================';

        $currentModule = null;
        foreach ($summaryRows as $row) {
            $table = $row['table'];
            $schema = $tables[$table];
            if ($row['module'] !== $currentModule) {
                $currentModule = $row['module'];
                $out[] = '';
                $out[] = '### MODULE: ' . strtoupper($currentModule);
            }

            $out[] = '';
            $out[] = '## Tên bảng: ' . $table;
            $out[] = '';
            $out[] = '### 1. Mục đích bảng';
            $out[] = $existing['purpose'][$table] ?? $row['description'];
            $out[] = '';
            $out[] = '### 2. Danh sách trường';
            $out[] = '';
            $out[] = '| STT | Tên trường | Kiểu dữ liệu | Null | Default | Khóa/Ràng buộc | Mô tả tác dụng trường | Ví dụ |';
            $out[] = '|---|---|---|---|---|---|---|---|';

            $i = 1;
            foreach ($schema['columns'] as $column) {
                $out[] = '| ' . $i++ . ' | ' . $column->name . ' | ' . typeLabel($column) . ' | ' . ($column->nullable ? 'Có' : 'Không') . ' | ' . defaultLabel($column) . ' | ' . keyLabel($table, $column, $schema) . ' | ' . describeColumn($table, $column, $schema) . ' | ' . exampleValue($table, $column) . ' |';
            }

            $out[] = '';
            $out[] = '### 3. Khóa chính, khóa ngoại, index';
            foreach (constraintLines($schema) as $line) {
                $out[] = $line;
            }
            $out[] = '';
            $out[] = '### 4. Quan hệ với bảng khác';
            foreach (relationLines($table, $schema) as $line) {
                $out[] = $line;
            }
            $out[] = '';
            $out[] = '### 5. Ví dụ bản ghi';
            $out[] = '```json';
            $out[] = json_encode(exampleRecord($table, $schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $out[] = '```';
            $out[] = '';
            $out[] = '---';
        }

        if ($errors !== []) {
            $out[] = '';
            $out[] = '## PHẦN 3. CẢNH BÁO KHI ĐỌC MIGRATION';
            $out[] = '';
            foreach ($errors as $error) {
                $out[] = '- ' . $error;
            }
        }

        return implode(PHP_EOL, $out) . PHP_EOL;
    }

    function tableInfo(string $table, array $existing): array
    {
        $summary = $existing['summary'][$table] ?? [];
        $module = $summary['module'] ?? inferModule($table);
        $title = $summary['title'] ?? inferTableTitle($table);
        $description = $summary['description'] ?? inferTableDescription($table);

        if (isPlaceholder($module)) {
            $module = inferModule($table);
        }
        if (looksLikeColumnType($module) || in_array($title, ['Có', 'Không'], true) || $description === '-' || str_starts_with($description, 'Lưu dữ liệu nghiệp vụ cho bảng `')) {
            $module = inferModule($table);
            $title = inferTableTitle($table);
            $description = inferTableDescription($table);
        }
        if (isPlaceholder($title)) {
            $title = inferTableTitle($table);
        }
        if (isPlaceholder($description)) {
            $description = inferTableDescription($table);
        }

        return compact('module', 'title', 'description');
    }

    function inferModule(string $table): string
    {
        return match (true) {
            in_array($table, ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'user_permission_revokes', 'personal_access_tokens', 'password_reset_tokens', 'sessions', 'verification_codes', 'user_lock_logs'], true) => 'Auth/RBAC',
            in_array($table, ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true) => 'Laravel',
            str_starts_with($table, 'venue_') || in_array($table, ['court_types', 'booking_configs', 'favorite_venues', 'amenities'], true) => 'Venue',
            str_starts_with($table, 'booking') || in_array($table, ['price_slots', 'holiday_prices', 'slot_locks'], true) => 'Booking',
            str_contains($table, 'payment') || str_contains($table, 'wallet') || str_contains($table, 'withdrawal') || str_contains($table, 'refund') || in_array($table, ['system_bank_accounts', 'internal_receipts', 'platform_fee_tiers'], true) => 'Payment',
            str_starts_with($table, 'policy_') || str_contains($table, 'policy') || $table === 'system_policies' => 'Policy',
            str_starts_with($table, 'partner_application') || in_array($table, ['partner_documents', 'partner_histories'], true) => 'Partner',
            str_contains($table, 'contract') || str_contains($table, 'signature') => 'Contract',
            str_contains($table, 'termination') || str_contains($table, 'liquidation') => 'Termination',
            str_contains($table, 'settlement') => 'Settlement',
            str_contains($table, 'document') => 'Document',
            str_contains($table, 'moderation') || str_contains($table, 'violation') || str_contains($table, 'severity') || str_contains($table, 'penalty') || in_array($table, ['reports', 'complaints', 'reviews'], true) => 'Moderation',
            str_starts_with($table, 'ai_') => 'AI',
            str_starts_with($table, 'voucher') => 'Voucher',
            str_contains($table, 'post') || str_contains($table, 'hashtag') || str_contains($table, 'conversation') || str_contains($table, 'message') || str_starts_with($table, 'player_') => 'Community',
            in_array($table, ['audit_logs', 'backup_jobs', 'notifications', 'media', 'banners'], true) => 'System',
            default => 'System',
        };
    }

    function inferTableTitle(string $table): string
    {
        $map = [
            'amenities' => 'Danh mục tiện ích sân',
            'venue_cluster_amenities' => 'Gắn tiện ích cho cụm sân',
            'contract_templates' => 'Mẫu hợp đồng',
            'partner_documents' => 'Tài liệu đối tác',
            'contract_signatures' => 'Chữ ký hợp đồng',
            'partner_liquidations' => 'Thanh lý hợp đồng đối tác',
            'partner_histories' => 'Lịch sử hồ sơ đối tác',
            'venue_location_change_requests' => 'Yêu cầu đổi địa chỉ sân',
            'violation_types' => 'Danh mục loại vi phạm',
            'severity_levels' => 'Danh mục mức độ vi phạm',
            'moderation_thresholds' => 'Ngưỡng xử lý kiểm duyệt',
            'violation_records' => 'Lịch sử vi phạm',
            'user_lock_logs' => 'Lịch sử khóa tài khoản',
            'failed_jobs' => 'Job thất bại',
        ];

        return $map[$table] ?? ('Quản lý ' . str_replace('_', ' ', $table));
    }

    function inferTableDescription(string $table): string
    {
        $map = [
            'amenities' => 'Lưu danh mục tiện ích như bãi xe, wifi, phòng tắm để gắn với cụm sân.',
            'venue_cluster_amenities' => 'Bảng trung gian liên kết cụm sân với tiện ích đang hiển thị cho khách.',
            'contract_templates' => 'Lưu file template hợp đồng đối tác, trạng thái hoạt động và mô tả dùng khi sinh hợp đồng.',
            'partner_documents' => 'Lưu tài liệu/file đối tác tải lên trong quá trình đăng ký và quản lý hồ sơ.',
            'contract_signatures' => 'Ghi nhận người ký, vai trò ký và thông tin phiên ký của hợp đồng đối tác.',
            'partner_liquidations' => 'Lưu hồ sơ thanh lý hợp đồng khi chấm dứt hợp tác với đối tác.',
            'partner_histories' => 'Ghi lại lịch sử thay đổi hồ sơ đối tác để phục vụ audit và theo dõi workflow.',
            'venue_location_change_requests' => 'Lưu yêu cầu chủ sân gửi để thay đổi địa chỉ, khu vực và map URL của cụm sân.',
            'violation_types' => 'Danh mục nhóm vi phạm nội dung/hành vi và điểm mặc định dùng trong kiểm duyệt.',
            'severity_levels' => 'Danh mục cấp độ nghiêm trọng và khoảng điểm dùng để phân loại vi phạm.',
            'moderation_thresholds' => 'Cấu hình ngưỡng cảnh báo và hành động tự động theo chính sách kiểm duyệt.',
            'violation_records' => 'Lưu từng lần ghi nhận vi phạm của đối tượng bị báo cáo hoặc bị hệ thống xử lý.',
            'user_lock_logs' => 'Ghi lại lịch sử khóa/mở khóa tài khoản, người thực hiện và lý do.',
            'failed_jobs' => 'Lưu các queue job chạy lỗi để có thể điều tra và retry khi cần.',
        ];

        return $map[$table] ?? ('Lưu dữ liệu nghiệp vụ cho bảng `' . $table . '` theo schema migration hiện tại.');
    }

    function foreignSummary(array $foreigns): string
    {
        $items = [];
        foreach ($foreigns as $fk) {
            if (! empty($fk['on'])) {
                $items[] = $fk['on'] . ' (' . implode(', ', $fk['columns']) . ')';
            }
        }

        return $items === [] ? 'Không FK' : implode(', ', array_unique($items));
    }

    function typeLabel(object $column): string
    {
        $type = $column->type;
        if (isset($column->options['length'])) {
            $type .= '(' . $column->options['length'] . ')';
        } elseif ($type === 'decimal') {
            $type .= '(' . ($column->options['precision'] ?? 8) . ',' . ($column->options['scale'] ?? 2) . ')';
        } elseif ($type === 'enum' && isset($column->options['values'])) {
            $type .= '(' . implode(', ', $column->options['values']) . ')';
        }

        return $type;
    }

    function defaultLabel(object $column): string
    {
        if (! $column->hasDefault) {
            return '-';
        }
        if (is_bool($column->default)) {
            return $column->default ? 'true' : 'false';
        }
        if ($column->default === null) {
            return 'null';
        }

        return (string) $column->default;
    }

    function keyLabel(string $table, object $column, array $schema): string
    {
        $labels = [];
        if ($column->primary) {
            $labels[] = 'PK';
        }
        foreach ($schema['foreigns'] as $fk) {
            if (in_array($column->name, $fk['columns'], true)) {
                $labels[] = 'FK';
            }
        }
        if ($column->unique) {
            $labels[] = 'UNIQUE';
        } elseif ($column->indexed) {
            $labels[] = 'INDEX';
        }

        return $labels === [] ? '-' : implode(', ', array_unique($labels));
    }

    function describeColumn(string $table, object $column, array $schema): string
    {
        if ($column->comment !== null && trim($column->comment) !== '') {
            return cleanText($column->comment);
        }

        $name = $column->name;
        $specific = specificColumnDescriptions($table);
        if (isset($specific[$name])) {
            return $specific[$name];
        }

        $fk = foreignForColumn($name, $schema);
        if ($fk !== null) {
            return 'Khóa ngoại tham chiếu bảng `' . $fk['on'] . '`, dùng để liên kết bản ghi với dữ liệu liên quan.';
        }

        if ($name === 'id') {
            return 'Khóa chính định danh duy nhất của bản ghi.';
        }
        if ($name === 'created_at') {
            return 'Thời điểm tạo bản ghi.';
        }
        if ($name === 'updated_at') {
            return 'Thời điểm cập nhật bản ghi gần nhất.';
        }
        if ($name === 'deleted_at') {
            return 'Thời điểm xóa mềm; null nghĩa là bản ghi còn hiệu lực.';
        }
        if (str_ends_with($name, '_id')) {
            return 'ID tham chiếu đối tượng liên quan trong nghiệp vụ.';
        }
        if (str_ends_with($name, '_by')) {
            return 'Người dùng/tác nhân thực hiện thao tác tương ứng.';
        }
        if (str_ends_with($name, '_at')) {
            return 'Thời điểm xảy ra sự kiện `' . $name . '`.';
        }
        if (str_contains($name, 'reason')) {
            return 'Lý do/ghi chú giải thích cho trạng thái hoặc quyết định xử lý.';
        }
        if (str_contains($name, 'status')) {
            return 'Trạng thái xử lý hoặc vòng đời của bản ghi.';
        }
        if (str_contains($name, 'amount') || str_contains($name, 'price') || str_contains($name, 'balance') || str_contains($name, 'fee')) {
            return 'Giá trị tiền tệ dùng trong tính toán và đối soát.';
        }
        if (str_contains($name, 'count') || str_contains($name, 'total')) {
            return 'Số lượng/tổng số dùng cho thống kê hoặc giới hạn nghiệp vụ.';
        }
        if (str_contains($name, 'file') || str_contains($name, 'path') || str_contains($name, 'url')) {
            return 'Đường dẫn hoặc URL tới tài nguyên liên quan.';
        }
        if (str_contains($name, 'json') || in_array($column->type, ['json', 'mediumText', 'longText'], true)) {
            return 'Dữ liệu có cấu trúc hoặc nội dung dài phục vụ xử lý nghiệp vụ.';
        }
        if (str_contains($name, 'name') || str_contains($name, 'title')) {
            return 'Tên/tiêu đề hiển thị hoặc dùng trong quản trị.';
        }
        if (str_contains($name, 'description') || str_contains($name, 'note')) {
            return 'Mô tả hoặc ghi chú bổ sung cho bản ghi.';
        }
        if (str_contains($name, 'type') || str_contains($name, 'category')) {
            return 'Loại hoặc nhóm phân loại của bản ghi.';
        }
        if (str_contains($name, 'is_') || $column->type === 'boolean') {
            return 'Cờ bật/tắt cho hành vi hoặc trạng thái tương ứng.';
        }

        return 'Trường dữ liệu phục vụ nghiệp vụ của bảng `' . $table . '`.';
    }

    function specificColumnDescriptions(string $table): array
    {
        $common = [
            'email' => 'Địa chỉ email dùng để đăng nhập, liên hệ hoặc nhận thông báo.',
            'phone' => 'Số điện thoại liên hệ hoặc xác thực người dùng.',
            'ip_address' => 'Địa chỉ IP tại thời điểm thực hiện thao tác.',
            'user_agent' => 'Thông tin trình duyệt/thiết bị tại thời điểm thao tác.',
            'metadata' => 'JSON metadata bổ sung cho nghiệp vụ.',
            'data' => 'JSON dữ liệu bổ sung gửi kèm bản ghi.',
            'payload' => 'Nội dung payload thô dùng cho queue/session/cache.',
            'token' => 'Token đã hash hoặc chuỗi xác thực dùng một lần.',
            'expires_at' => 'Thời điểm hết hạn hiệu lực.',
            'sort_order' => 'Thứ tự sắp xếp khi hiển thị.',
            'is_active' => 'Cờ xác định bản ghi đang hoạt động hay bị tắt.',
            'is_default' => 'Cờ đánh dấu bản ghi mặc định trong nhóm dữ liệu.',
            'slug' => 'Chuỗi định danh thân thiện URL và SEO.',
            'code' => 'Mã định danh nghiệp vụ dễ đọc.',
            'content' => 'Nội dung chính của bản ghi.',
        ];

        $tableMap = [
            'user_lock_logs' => [
                'user_id' => 'Tài khoản bị khóa hoặc mở khóa.',
                'locked_by' => 'Admin/người vận hành thực hiện thao tác khóa.',
                'lock_type' => 'Kiểu khóa: tạm thời, vĩnh viễn hoặc tự động.',
                'reason' => 'Lý do khóa/mở khóa tài khoản.',
                'locked_until' => 'Thời điểm hết hạn khóa tạm thời.',
                'unlocked_at' => 'Thời điểm tài khoản được mở khóa.',
            ],
            'moderation_thresholds' => [
                'system_policy_id' => 'Chính sách hệ thống áp dụng bộ ngưỡng kiểm duyệt.',
                'target_type' => 'Loại đối tượng áp dụng ngưỡng như user, post, comment.',
                'warning_threshold' => 'Ngưỡng điểm/số lần để phát cảnh báo.',
                'action_threshold' => 'Ngưỡng điểm/số lần để tự động thực hiện hành động.',
                'unique_reporters_threshold' => 'Số người báo cáo khác nhau cần đạt trước khi xử lý.',
                'timeframe_days' => 'Khoảng ngày dùng để cộng dồn và đánh giá vi phạm.',
            ],
            'violation_types' => [
                'code' => 'Mã loại vi phạm dùng trong báo cáo và xử lý tự động.',
                'name' => 'Tên loại vi phạm hiển thị cho admin/user.',
                'description' => 'Mô tả hành vi thuộc loại vi phạm.',
                'default_score' => 'Điểm vi phạm mặc định khi chọn loại này.',
            ],
            'severity_levels' => [
                'code' => 'Mã cấp độ nghiêm trọng.',
                'name' => 'Tên cấp độ hiển thị trong kiểm duyệt.',
                'min_score' => 'Điểm tối thiểu để thuộc cấp độ này.',
                'max_score' => 'Điểm tối đa để thuộc cấp độ này.',
            ],
            'violation_records' => [
                'target_type' => 'Loại đối tượng bị ghi nhận vi phạm.',
                'target_id' => 'ID đối tượng bị ghi nhận vi phạm.',
                'violation_type_id' => 'Loại vi phạm được áp dụng.',
                'severity_level' => 'Mức độ nghiêm trọng tại thời điểm ghi nhận.',
                'score' => 'Điểm vi phạm cộng vào hồ sơ đối tượng.',
                'source_type' => 'Nguồn tạo record như report, auto_scan hoặc admin.',
                'source_id' => 'ID nguồn tạo record.',
            ],
        ];

        return array_merge($common, $tableMap[$table] ?? []);
    }

    function foreignForColumn(string $column, array $schema): ?array
    {
        foreach ($schema['foreigns'] as $fk) {
            if (in_array($column, $fk['columns'], true)) {
                return $fk;
            }
        }

        return null;
    }

    function constraintLines(array $schema): array
    {
        $lines = [];
        if (($schema['primary'] ?? []) !== []) {
            $lines[] = '- PK: ' . implode(', ', $schema['primary']);
        } else {
            $lines[] = '- PK: Không khai báo rõ trong migration.';
        }

        foreach ($schema['foreigns'] as $fk) {
            if (empty($fk['on'])) {
                continue;
            }
            $line = '- FK: ' . implode(', ', $fk['columns']) . ' -> ' . $fk['on'] . '.' . implode(', ', $fk['references']);
            if (! empty($fk['on_delete'])) {
                $line .= ' (on delete: ' . $fk['on_delete'] . ')';
            }
            $lines[] = $line;
        }

        foreach ($schema['indexes'] as $index) {
            $prefix = $index['type'] === 'unique' ? '- UNIQUE' : '- INDEX';
            $lines[] = $prefix . ': ' . $index['name'] . ' (' . implode(', ', $index['columns']) . ')';
        }

        return array_values(array_unique($lines));
    }

    function relationLines(string $table, array $schema): array
    {
        $lines = [];
        foreach ($schema['foreigns'] as $fk) {
            if (! empty($fk['on'])) {
                $lines[] = '- ' . $table . ' n-1 ' . $fk['on'] . ' qua ' . implode(', ', $fk['columns']) . '.';
            }
        }

        return $lines === [] ? ['- Không có khóa ngoại trực tiếp trong migration.'] : $lines;
    }

    function exampleRecord(string $table, array $schema): array
    {
        $record = [];
        $count = 0;
        foreach ($schema['columns'] as $column) {
            if ($count >= 8 && ! $column->primary) {
                continue;
            }
            $record[$column->name] = rawExampleValue($table, $column);
            $count++;
        }

        return $record;
    }

    function exampleValue(string $table, object $column): string
    {
        $value = rawExampleValue($table, $column);
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($value === null) {
            return 'null';
        }

        return cleanText((string) $value);
    }

    function rawExampleValue(string $table, object $column): mixed
    {
        $name = $column->name;
        if ($column->primary || str_ends_with($name, '_id') || in_array($column->type, ['char'], true)) {
            return $column->type === 'char' && (($column->options['length'] ?? 36) === 36) ? '10000000-0000-0000-0000-000000000001' : 1;
        }
        if ($column->type === 'date') {
            return '2026-06-22';
        }
        if ($column->type === 'time') {
            return '08:00:00';
        }
        if (in_array($column->type, ['timestamp', 'dateTime'], true) || str_ends_with($name, '_at')) {
            return '2026-06-22 09:00:00';
        }
        if (str_contains($name, 'status')) {
            return ($column->options['values'][0] ?? 'active');
        }
        if (str_contains($name, 'reason')) {
            return 'Nội dung mẫu';
        }
        if (str_contains($name, 'email')) {
            return 'user@sportgo.vn';
        }
        if (str_contains($name, 'phone')) {
            return '0901234567';
        }
        if (str_contains($name, 'type') || str_contains($name, 'category')) {
            return ($column->options['values'][0] ?? 'default');
        }
        if (str_contains($name, 'amount') || str_contains($name, 'price') || str_contains($name, 'balance') || str_contains($name, 'fee')) {
            return 100000.00;
        }
        if (str_contains($name, 'count') || str_contains($name, 'total') || in_array($column->type, ['integer', 'unsignedInteger', 'smallInteger', 'unsignedSmallInteger', 'unsignedTinyInteger', 'tinyInteger', 'bigInteger', 'unsignedBigInteger'], true)) {
            return 1;
        }
        if ($column->type === 'boolean') {
            return true;
        }
        if (in_array($column->type, ['decimal', 'double'], true)) {
            return 1.0;
        }
        if ($column->type === 'json') {
            return ['key' => 'value'];
        }
        if (str_contains($name, 'url')) {
            return 'https://sportgo.vn/example';
        }
        if (str_contains($name, 'path')) {
            return '/storage/example.pdf';
        }
        if (str_contains($name, 'name') || str_contains($name, 'title')) {
            return 'Ví dụ SportGo';
        }
        if (str_contains($name, 'code')) {
            return 'CODE-001';
        }
        if (str_contains($name, 'content') || str_contains($name, 'description') || str_contains($name, 'note') || str_contains($name, 'reason')) {
            return 'Nội dung mẫu';
        }

        return 'example';
    }

    function cleanText(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", '|'], [' ', ' ', '/'], $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return $value;
    }

    function isPlaceholder(?string $value): bool
    {
        if ($value === null) {
            return true;
        }
        $trim = trim($value);
        return $trim === '' || $trim === '-' || $trim === '...' || str_contains($trim, 'Chưa phân loại') || str_contains($trim, 'Cần cập nhật') || str_contains($trim, 'Chưa có mô tả');
    }

    function looksLikeColumnType(string $value): bool
    {
        return in_array($value, [
            'bigInteger',
            'boolean',
            'char',
            'date',
            'dateTime',
            'decimal',
            'double',
            'enum',
            'foreignUuid',
            'integer',
            'json',
            'longText',
            'mediumText',
            'smallInteger',
            'string',
            'text',
            'time',
            'timestamp',
            'unsignedBigInteger',
            'unsignedInteger',
            'unsignedSmallInteger',
            'unsignedTinyInteger',
        ], true);
    }
}
