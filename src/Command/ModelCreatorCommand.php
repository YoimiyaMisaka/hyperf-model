<?php

namespace Timebug\Model\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Timebug\Model\Config\ModelConfig;
use Timebug\Model\Exception\ClassInvalidException;
use Timebug\Model\Exception\ConfigUndefinedException;
use Timebug\Model\Exception\DatabaseInvalidException;
use Timebug\Model\Exception\DirectoryInvalidException;
use Timebug\Model\Exception\TableInvalidException;
use Timebug\Model\Exception\TableNotRequiredException;

#[Command]
class ModelCreatorCommand extends HyperfCommand
{

    protected ?string $name = "creator:model";

    /**
     * @var ?InputInterface
     */
    protected ?InputInterface $input;

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
    protected ?string \$table = '{$tableName}';
}

EOF;

        $targetPath = rtrim(BASE_PATH . '/' . $path, '/');
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

    /**
     * 获取配置
     *
     * @throws ConfigUndefinedException
     */
    protected function getConfig(): ModelConfig
    {
        try {
            $poolName = $this->input->getOption('pool');
            $container = ApplicationContext::getContainer();
            $config = $container->get(ConfigInterface::class);
            $key = sprintf("model.%s", $poolName);
            if (!$config->has($key)) {
                throw new ConfigUndefinedException("Invalid Configuration.");
            }
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new ConfigUndefinedException("Invalid Configuration: {$e->getMessage()}.");
        }

        return new ModelConfig($config->get($key, []));
    }

    /**
     * 获取目录
     *
     * @param ModelConfig $config 配置
     * @param string $type 目录类型
     * @return string
     * @throws DirectoryInvalidException
     */
    protected function getDir(ModelConfig $config, string $type = ModelConfig::DIR_MODEL): string
    {
        $path = $type == ModelConfig::DIR_MODEL ? $config->getModelPath() : $config->getColumnPath();
        $dir = $this->input->hasOption('dir')
            ? $this->input->getOption('dir')
            : $path;

        if ($dir == '') {
            throw new DirectoryInvalidException("Directory Invalid.");
        }
        return $dir;
    }

    /**
     * 获取数据库
     *
     * @param ModelConfig $config
     * @return string
     * @throws DatabaseInvalidException
     */
    protected function getDatabase(ModelConfig $config): string
    {
        $db = $config->getDatabase();
        if ($this->input->hasOption('database')) {
            $optDb = $this->input->getOption('database');
            $optDb && $db = $optDb;
        }

        if ($db == '') {
            throw new DatabaseInvalidException("Invalid Database.");
        }

        return $db;
    }

    /**
     * 获取表名
     *
     * @return string
     * @throws TableInvalidException|TableNotRequiredException
     */
    protected function getTable(): string
    {
        if (!$this->input->hasOption('table')) {
            throw new TableNotRequiredException("Table is not required.");
        }

        $table = $this->input->getOption('table');
        if ($table == '') {
            throw new TableInvalidException("Invalid Table Name.");
        }

        return $table;
    }

    /**
     * 获取类名
     *
     * @return string
     * @throws TableNotRequiredException|ClassInvalidException|TableInvalidException
     */
    protected function getClassName(): string
    {
        if (!$this->input->hasOption('class')) {
            $table = $this->getTable();
            $tableArr = explode('_', $table);
            return Collection::make($tableArr)->map(fn($item) => ucfirst($item))->reduce(fn($carry, $item) => $carry . $item, '');
        }


        $class = $this->input->getOption('class');
        if ($class == '') {
            throw new ClassInvalidException("className Invalid.");
        }
        return $class;
    }
}