<?php

namespace Timebug\Model\Config;

class ModelConfig
{

    /**
     * 模型
     */
    const DIR_MODEL = 'model';

    /**
     * 表字典
     */
    const DIR_COLUMN = 'column';

    /**
     * 模型存放路径
     *
     * @var string|mixed
     */
    private string $modelPath;

    /**
     * 表字段存放路径
     *
     * @var string|mixed
     */
    private string $columnPath;

    /**
     * 数据库
     * @var string
     */
    private string $database;

    public function __construct(array $config)
    {
        $this->modelPath = $config['model_path'] ?? 'app/Infrastructure/Database/Model';
        $this->columnPath = $config['column_path'] ?? 'app/Infrastructure/Database/Constant';
        $this->database = $config['database'] ?? '';
    }

    /**
     * @return mixed|string
     */
    public function getModelPath(): mixed
    {
        return $this->modelPath;
    }

    /**
     * @return mixed|string
     */
    public function getColumnPath(): mixed
    {
        return $this->columnPath;
    }

    /**
     * @return string
     */
    public function getDatabase(): mixed
    {
        return $this->database;
    }
}