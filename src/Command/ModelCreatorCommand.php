<?php

namespace Timebug\Model\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputOption;
use Timebug\Model\Exception\ClassInvalidException;
use Timebug\Model\Exception\TableInvalidException;
use Timebug\Model\Exception\TableNotRequiredException;
use Timebug\Model\Traits\ConfigurationTrait;

#[Command]
class ModelCreatorCommand extends HyperfCommand
{
    use ConfigurationTrait;

    protected $name = "creator:model";

    public function configure()
    {
        parent::configure();
        $this->addOption('pool', 'P', InputOption::VALUE_OPTIONAL, '连接池', 'default');
        $this->addOption('dir', 'D', InputOption::VALUE_OPTIONAL, '生成目录', 'app/Infrastructure/Database/Model');
        $this->addOption('database', 'DB', InputOption::VALUE_OPTIONAL, '数据库');
        $this->addOption('table', 'T', InputOption::VALUE_REQUIRED, '数据表');
        $this->addOption('class', 'C', InputOption::VALUE_OPTIONAL, '类名');
    }

    public function handle()
    {
        try {
            $config = $this->getConfig();

            $dir = $this->getDir($config);
            $db  = $this->getDatabase($config);
            $table = $this->getTable();

            $columns = Db::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->get(['COLUMN_NAME','DATA_TYPE', 'COLUMN_COMMENT'])
                ->toArray();

            var_dump($this->make($columns, $dir));
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

    }

    private function typeMap(string $type): string
    {
        return match ($type) {
            'tinyint', 'smallint', 'int', 'bigint' => 'int',
            'decimal', 'float' => 'float',
            default => 'string',
        };
    }

    private function propertyMark(string $column, string $type, string $comment): string
    {
        $typ = $this->typeMap($type);
        return "{$typ} \${$column} {$comment}";
    }

    /**
     * @param array $columns
     * @param string $path
     * @return string
     * @throws ClassInvalidException
     * @throws TableInvalidException
     * @throws TableNotRequiredException
     */
    private function make(array $columns, string $path): string
    {
        $props = "\n/**\n";

        foreach ($columns as $column) {
            $mark = $this->propertyMark($column->COLUMN_NAME, $column->DATA_TYPE, $column->COLUMN_COMMENT);
            $props .= " * @property {$mark}\n";
        }

        $props .= " */";

        $namespace = ucfirst(str_replace('/', '\\', $path));
        $tableName = $this->getTable();



        $className = $this->getClassName();

        $template = <<<EOF
<?php

namespace {$namespace};

{$props}
class {$className} extends BaseModel
{
    protected \$table = '{$tableName}';
}

EOF;

        $targetPath = BASE_PATH . '/' . $path;
        if (!is_dir($targetPath)) {
            mkdir($targetPath);
        }

        $fileName = $targetPath . '/' . $className . '.php';
        if (!file_exists($fileName)) {
            file_put_contents($fileName, $template);
        }
        $this->line("create $fileName successfully");

        return $template;
    }
}