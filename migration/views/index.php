<?php
$class = get_class($model);
Yii::app()->clientScript->registerScript('gii.migration', "
$('#{$class}_connectionId').change(function(){
	var tableName=$('#{$class}_tableName');
	tableName.autocomplete('option', 'source', []);
	$.ajax({
		url: '" . Yii::app()->getUrlManager()->createUrl('gii/migration/getTableNames') . "',
		data: {db: this.value},
		dataType: 'json'
	}).done(function(data){
		tableName.autocomplete('option', 'source', data);
	});
});
$('#{$class}_migrationClass').change(function(){
	$(this).data('changed',$(this).val()!='');
});
$('#{$class}_tableName').bind('keyup change', function(){
	var migration=$('#{$class}_migrationClass');
	var tableName=$(this).val();
	if(tableName.substring(tableName.length-1)!='*') {
		$('.form .row.migration-class').show();
	}
	else {
		$('#{$class}_migrationClass').val('');
		$('.form .row.migration-class').hide();
	}
	if(!migration.data('changed')) {
		var i=tableName.lastIndexOf('.');
		if(i>=0)
			tableName=tableName.substring(i+1);
		var tablePrefix=$('#{$class}_tablePrefix').val();
		if(tablePrefix!='' && tableName.indexOf(tablePrefix)==0)
			tableName=tableName.substring(tablePrefix.length);
		var migrationClass='';
		$.each(tableName.split('_'), function() {
			if(this.length>0)
				migrationClass+=this.substring(0,1).toLowerCase()+this.substring(1);
		});
		migration.val(migrationClass);
	}
});
$('.form .row.migration-class').toggle($('#{$class}_tableName').val().substring($('#{$class}_tableName').val().length-1)!='*');
");
?>
<h1>Model Generator</h1>

<p>This generator generates a model class for the specified database table.</p>

<?php $form = $this->beginWidget('CCodeForm', array('model' => $model)); ?>

<div class="row sticky">
    <?php echo $form->labelEx($model, 'connectionId') ?>
    <?php echo $form->textField($model, 'connectionId', array('size' => 65)) ?>
    <div class="tooltip">
        The database component that should be used.
    </div>
    <?php echo $form->error($model, 'connectionId'); ?>
</div>
<div class="row sticky">
    <?php echo $form->labelEx($model, 'tablePrefix'); ?>
    <?php echo $form->textField($model, 'tablePrefix', array('size' => 65)); ?>
    <div class="tooltip">
        This refers to the prefix name that is shared by all database tables.
        Setting this property mainly affects how migration classes are named based on
        the table names. For example, a table prefix <code>tbl_</code> with a table name <code>tbl_post</code>
        will generate a migration class named <code>create_table_ost</code>.
        <br/>
        Leave this field empty if your database tables do not use common prefix.
    </div>
    <?php echo $form->error($model, 'tablePrefix'); ?>
</div>
<div class="row">
    <?php echo $form->labelEx($model, 'tableName'); ?>
    <?php $this->widget('zii.widgets.jui.CJuiAutoComplete', array(
        'model' => $model,
        'attribute' => 'tableName',
        'name' => 'tableName',
        'source' => Yii::app()->hasComponent($model->connectionId) ? array_keys(Yii::app()->{$model->connectionId}->schema->getTables()) : array(),
        'options' => array(
            'minLength' => '0',
            'focus' => new CJavaScriptExpression('function(event,ui) {
					$("#' . CHtml::activeId($model, 'tableName') . '").val(ui.item.label).change();
					return false;
				}')
        ),
        'htmlOptions' => array(
            'id' => CHtml::activeId($model, 'tableName'),
            'size' => '65',
            'data-tooltip' => '#tableName-tooltip'
        ),
    )); ?>
    <div class="tooltip" id="tableName-tooltip">
        This refers to the table name that a new migration class should be generated for
        (e.g. <code>tbl_user</code>). It can contain schema name, if needed (e.g. <code>public.tbl_post</code>).
        You may also enter <code>*</code> (or <code>schemaName.*</code> for a particular DB schema)
        to generate a migration class for EVERY table.
    </div>
    <?php echo $form->error($model, 'tableName'); ?>
</div>
<div class="row model-class">
    <?php echo $form->label($model, 'migrationClass', array('required' => true)); ?>
    <?php echo $form->textField($model, 'migrationClass', array('size' => 65)); ?>
    <div class="tooltip">
        This is the name of the model class to be generated (e.g. <code>Post</code>, <code>Comment</code>).
        It is case-sensitive.
    </div>
    <?php echo $form->error($model, 'migrationClass'); ?>
</div>
<div class="row sticky">
    <?php echo $form->labelEx($model, 'baseClass'); ?>
    <?php echo $form->textField($model, 'baseClass', array('size' => 65)); ?>
    <div class="tooltip">
        This is the class that the new migration class will extend from.
        Please make sure the class exists and can be autoloaded.
    </div>
    <?php echo $form->error($model, 'baseClass'); ?>
</div>
<div class="row sticky">
    <?php echo $form->labelEx($model, 'migrationPath'); ?>
    <?php echo $form->textField($model, 'migrationPath', array('size' => 65)); ?>
    <div class="tooltip">
        This refers to the directory that the new migration class file should be generated under.
        It should be specified in the form of a path alias, for example, <code>application.migrations</code>.
    </div>
    <?php echo $form->error($model, 'migrationPath'); ?>
</div>

<?php $this->endWidget(); ?>
