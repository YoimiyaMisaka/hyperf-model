<?php

namespace Timebug\Model\Traits;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Timebug\Model\Config\ModelConfig;
use Timebug\Model\Exception\ClassInvalidException;
use Timebug\Model\Exception\ConfigUndefinedException;
use Timebug\Model\Exception\DatabaseInvalidException;
use Timebug\Model\Exception\DirectoryInvalidException;
use Timebug\Model\Exception\TableInvalidException;
use Timebug\Model\Exception\TableNotRequiredException;

trait ConfigurationTrait
{
    /**
     * @var ?InputInterface
     */
    protected ?InputInterface $input;


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
        $db = $this->input->hasOption('database')
            ? $this->input->getOption('database')
            : $config->getDatabase();

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