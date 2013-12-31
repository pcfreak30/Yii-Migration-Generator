<?php
/**
 * This is the template for generating the migration class of a specified table.
 * - $this: the MigrationCode object
 * - $tableName: the table name for this class (prefix is already removed if necessary)
 * - $tablePrefix: the table prefix
 * - $migrationClass: the migration class name
 * - $columns: list of table columns (name=>CDbColumnSchema)
 */
?>
<?php echo "<?php\n"; ?>

/**
 * This is the migration class for table "<?php echo $tableName; ?>".
 *
 */
class <?php echo $migrationClass; ?> extends <?php echo $this->baseClass."\n"; ?>
{
    public function up()
    {
        try {
        $this->createTable('<?php echo $tablePrefix.$tableName; ?>', array(
            <?php foreach($columns as $column): ?>
                '<?php echo $column->name ?>' => '<?php echo $column->dbType ?>',
            ));
            <?php endforeach ?>
            <?php foreach($columns as $column): ?>
            <?php if($column->isPrimaryKey): ?>
            $this->addPrimaryKey('<?php echo $tableName; ?>_pk','<?php echo $tableName; ?>','<?php echo $column->name ?>');
            <?php endif; ?>
            <?php endforeach ?>
            return true;
            } catch (CDbException $e) {
                echo $e->getMessage();
                return false;
            }
    }

    public function down()
    {
        try {
            $this->dropTable('<?php echo $tablePrefix.$tableName; ?>');
            return true;
        } catch (CDbException $e) {
            echo $e->getMessage();
            return false;
        }
    }

}
