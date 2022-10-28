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
class ColumnCreatorCommand extends HyperfCommand
{
    use ConfigurationTrait;

    protected $name = "creator:column";

    public function configure()
    {
        parent::configure();
        $this->addOption('pool', 'P', InputOption::VALUE_OPTIONAL, '连接池', 'default');
        $this->addOption('dir', 'D', InputOption::VALUE_OPTIONAL, '生成目录', 'app/Infrastructure/Database/Constant');
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
        $constants = "";
        foreach ($columns as $column) {
            $constantName = strtoupper($column->COLUMN_NAME);
            $desc = $column->COLUMN_COMMENT == '' ? $column->COLUMN_NAME : $column->COLUMN_COMMENT;
            $constants .= "    /**\n";
            $constants .= "     * {$desc}\n";
            $constants .= "     */\n";
            $constants .= "    const $constantName = \"{$column->COLUMN_NAME}\";\n\n";
        }

        $namespace = ucfirst(str_replace('/', '\\', $path));
        $tableName = $this->getTable();
        $className = $this->getClassName();

        $template = <<<EOF
<?php
declare(strict_types=1);
namespace {$namespace};

class {$className}
{
    /**
     * 表名
     */
    const TABLE_NAME = "{$tableName}";
    
$constants
}

EOF;
        $path = BASE_PATH . '/' . $path;
        is_dir($path) || mkdir($path);
        $filename = $path . $className . '.php';
        file_exists($filename) || file_put_contents($filename, $template);
        $this->info("create $filename successfully.");
        return $template;
    }
}