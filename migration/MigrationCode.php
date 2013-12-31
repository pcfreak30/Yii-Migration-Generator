<?php

class MigrationCode extends CCodeModel
{
    public $connectionId = 'db';
    public $tablePrefix;
    public $tableName;
    public $migrationClass;
    public $migrationPath = 'application.migrations';
    public $baseClass = 'CDbMigration';
    public $buildRelations = true;

    /**
     * @var array list of candidate relation code. The array are indexed by AR class names and relation names.
     * Each element represents the code of the one relation in one AR class.
     */
    protected $relations;

    public function rules()
    {
        return array_merge(parent::rules(), array(
            array('tablePrefix, baseClass, tableName, migrationClass, migrationPath, connectionId', 'filter', 'filter' => 'trim'),
            array('connectionId, tableName, migrationPath, baseClass', 'required'),
            array('tablePrefix, tableName, migrationPath', 'match', 'pattern' => '/^(\w+[\w\.]*|\*?|\w+\.\*)$/', 'message' => '{attribute} should only contain word characters, dots, and an optional ending asterisk.'),
            array('connectionId', 'validateConnectionId', 'skipOnError' => true),
            array('tableName', 'validateTableName', 'skipOnError' => true),
            array('tablePrefix, migrationClass', 'match', 'pattern' => '/^[a-zA-Z_]\w*$/', 'message' => '{attribute} should only contain word characters.'),
            array('baseClass', 'match', 'pattern' => '/^[a-zA-Z_][\w\\\\]*$/', 'message' => '{attribute} should only contain word characters and backslashes.'),
            array('migrationPath', 'validateMigrationPath', 'skipOnError' => true),
            array('baseClass, migrationClass', 'validateReservedWord', 'skipOnError' => true),
            array('baseClass', 'validateBaseClass', 'skipOnError' => true),
            array('connectionId, tablePrefix, migrationPath, baseClass, buildRelations', 'sticky'),
        ));
    }

    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), array(
            'tablePrefix' => 'Table Prefix',
            'tableName' => 'Table Name',
            'migrationPath' => 'Migration Path',
            'migrationClass' => 'Migration Class',
            'baseClass' => 'Base Class',
            'buildRelations' => 'Build Relations',
            'connectionId' => 'Database Connection',
        ));
    }

    public function requiredTemplates()
    {
        return array(
            'migration.php',
        );
    }

    public function init()
    {
        if (Yii::app()->{$this->connectionId} === null)
            throw new CHttpException(500, 'A valid database connection is required to run this generator.');
        $this->tablePrefix = Yii::app()->{$this->connectionId}->tablePrefix;
        parent::init();
    }

    public function prepare()
    {
        if (($pos = strrpos($this->tableName, '.')) !== false) {
            $schema = substr($this->tableName, 0, $pos);
            $tableName = substr($this->tableName, $pos + 1);
        } else {
            $schema = '';
            $tableName = $this->tableName;
        }
        if ($tableName[strlen($tableName) - 1] === '*') {
            $tables = Yii::app()->{$this->connectionId}->schema->getTables($schema);
            if ($this->tablePrefix != '') {
                foreach ($tables as $i => $table) {
                    if (strpos($table->name, $this->tablePrefix) !== 0)
                        unset($tables[$i]);
                }
            }
        } else
            $tables = array($this->getTableSchema($this->tableName));

        $this->files = array();
        $templatePath = $this->templatePath;
        if ($this->status == CCodeModel::STATUS_PREVIEW && $this->files != array() && !isset($_POST['generate'], $_POST['answers'])) {
            $files = array();
            foreach ($tables as $table) {
                $files[] = $this->generateClassName($table->name);
            }
            Yii::app()->user->setState('gii_migration_classes', $files);
        } else {
            $files = Yii::app()->user->getState('gii_migration_classes');
        }
        foreach ($tables as $table) {
            $tableName = $this->removePrefix($table->name);
            $className = array_shift($files);
            $params = array(
                'tableName' => $schema === '' ? $tableName : $schema . '.' . $tableName,
                'tablePrefix' => !empty($this->tablePrefix) ? $this->tablePrefix : '',
                'migrationClass' => $className,
                'columns' => $table->columns,
                'connectionId' => $this->connectionId,
            );
            $this->files[] = new CCodeFile(
                Yii::getPathOfAlias($this->migrationPath) . '/' . $className . '.php',
                $this->render($templatePath . '/migration.php', $params)
            );
        }
    }

    public function validateTableName($attribute, $params)
    {
        if ($this->hasErrors())
            return;

        $invalidTables = array();
        $invalidColumns = array();

        if ($this->tableName[strlen($this->tableName) - 1] === '*') {
            if (($pos = strrpos($this->tableName, '.')) !== false)
                $schema = substr($this->tableName, 0, $pos);
            else
                $schema = '';

            $this->migrationClass = '';
            $tables = Yii::app()->{$this->connectionId}->schema->getTables($schema);
            foreach ($tables as $table) {
                if ($this->tablePrefix == '' || strpos($table->name, $this->tablePrefix) === 0) {
                    if (in_array(strtolower($table->name), self::$keywords))
                        $invalidTables[] = $table->name;
                    if (($invalidColumn = $this->checkColumns($table)) !== null)
                        $invalidColumns[] = $invalidColumn;
                }
            }
        } else {
            if (($table = $this->getTableSchema($this->tableName)) === null)
                $this->addError('tableName', "Table '{$this->tableName}' does not exist.");
            if ($this->migrationClass === '')
                $this->addError('migrationClass', 'Migration Class cannot be blank.');

            if (!$this->hasErrors($attribute) && ($invalidColumn = $this->checkColumns($table)) !== null)
                $invalidColumns[] = $invalidColumn;
        }

        if ($invalidTables != array())
            $this->addError('tableName', 'Migration class cannot take a reserved PHP keyword! Table name: ' . implode(', ', $invalidTables) . ".");
        if ($invalidColumns != array())
            $this->addError('tableName', 'Column names that does not follow PHP variable naming convention: ' . implode(', ', $invalidColumns) . ".");
    }

    /*
     * Check that all database field names conform to PHP variable naming rules
     * For example mysql allows field name like "2011aa", but PHP does not allow variable like "$migration->2011aa"
     * @param CDbTableSchema $table the table schema object
     * @return string the invalid table column name. Null if no error.
     */
    public function checkColumns($table)
    {
        foreach ($table->columns as $column) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $column->name))
                return $table->name . '.' . $column->name;
        }
    }

    public function validateMigrationPath($attribute, $params)
    {
        if (Yii::getPathOfAlias($this->migrationPath) === false)
            $this->addError('migrationPath', 'Migration Path must be a valid path alias.');
    }

    public function validateBaseClass($attribute, $params)
    {
        $class = @Yii::import($this->baseClass, true);
        if (!is_string($class) || !$this->classExists($class))
            $this->addError('baseClass', "Class '{$this->baseClass}' does not exist or has syntax error.");
        elseif ($class !== 'CDbMigration' && !is_subclass_of($class, 'CDbMigration'))
            $this->addError('baseClass', "'{$this->migration}' must extend from CDbMigration.");
    }

    public function getTableSchema($tableName)
    {
        $connection = Yii::app()->{$this->connectionId};
        return $connection->getSchema()->getTable($tableName, $connection->schemaCachingDuration !== 0);
    }

    protected function removePrefix($tableName, $addBrackets = true)
    {
        if ($addBrackets && Yii::app()->{$this->connectionId}->tablePrefix == '')
            return $tableName;
        $prefix = $this->tablePrefix != '' ? $this->tablePrefix : Yii::app()->{$this->connectionId}->tablePrefix;
        if ($prefix != '') {
            if ($addBrackets && Yii::app()->{$this->connectionId}->tablePrefix != '') {
                $prefix = Yii::app()->{$this->connectionId}->tablePrefix;
                $lb = '{{';
                $rb = '}}';
            } else
                $lb = $rb = '';
            if (($pos = strrpos($tableName, '.')) !== false) {
                $schema = substr($tableName, 0, $pos);
                $name = substr($tableName, $pos + 1);
                if (strpos($name, $prefix) === 0)
                    return $schema . '.' . $lb . substr($name, strlen($prefix)) . $rb;
            } elseif (strpos($tableName, $prefix) === 0)
                return $lb . substr($tableName, strlen($prefix)) . $rb;
        }
        return $tableName;
    }

    protected function generateClassName($tableName)
    {
        if ($this->tableName === $tableName || ($pos = strrpos($this->tableName, '.')) !== false && substr($this->tableName, $pos + 1) === $tableName)
            return $this->migrationClass;

        $tableName = $this->removePrefix($tableName, false);
        if (($pos = strpos($tableName, '.')) !== false) // remove schema part (e.g. remove 'public2.' from 'public2.post')
            $tableName = substr($tableName, $pos + 1);
        return 'm' . gmdate('ymd_His') . '_create_table_' . strtolower($tableName);

    }

    public function validateConnectionId($attribute, $params)
    {
        if (Yii::app()->hasComponent($this->connectionId) === false || !(Yii::app()->getComponent($this->connectionId) instanceof CDbConnection))
            $this->addError('connectionId', 'A valid database connection is required to run this generator.');
    }
}
