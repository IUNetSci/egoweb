<?php
/* @var $this ImportExportController */
?>

<div class="panel panel-success">
    <div class="panel-heading">
        Import Study
    </div>

    <div class="panel-body">
        <?php echo CHtml::form('/importExport/importstudy', 'post', array('id'=>'importForm','enctype'=>'multipart/form-data')); ?>
        <div class="form-group">
            <div class="col-lg-3">
                <input id="userfile" name="files[]" class="form-control" type="file" multiple/>
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-3">
                <input type="text" name="newName" class="form-control" placeholder="New Name (optional)">
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-4 ">
                <button class="btn btn-success">Import</button>
            </div>
        </div>
        </form>
    </div>
</div>

<br clear=all>
<br clear=all>

<div class="panel panel-warning">
    <div class="panel-heading">
        Replicate Study
    </div>

    <div class="panel-body">
        <?php
        // replicate study
        $form=$this->beginWidget('CActiveForm', array(
            'id'=>'replicate',
            'enableAjaxValidation'=>false,
            'action'=>'/importExport/replicate'
        ));
        ?>
        <div class="form-group">
            <div class="col-lg-3">
<?php
$criteria=new CDbCriteria;
$criteria->order = 'name';
echo CHtml::dropdownlist(
    'studyId',
    '',
    CHtml::listData(Study::model()->findAll($criteria), 'id', 'name'),
    array("class"=>"form-control")
);
?>
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-3">
                <?php echo CHtml::textField('name', '',array('class'=>"form-control", "placeholder"=>"new name")); ?>
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-4 ">
                <button class="btn btn-warning">Replicate</button>
            </div>
        </div>
        <?php $this->endWidget(); ?>
    </div>
</div>

<br clear=all>
<br clear=all>


<div class="panel panel-info">
    <div class="panel-heading">
        Export Study
    </div>

    <div class="panel-body">
        
<script>
function getInterviews(dropdown){
	$.get('/importExport/ajaxinterviews/' + dropdown.val(), function(data){$('#interviews').html(data);});
}

</script>
<?php
// export study
$form=$this->beginWidget('CActiveForm', array(
    'id'=>'export',
    'enableAjaxValidation'=>false,
    'action'=>'/importExport/exportstudy'
));
$criteria=new CDbCriteria;
$criteria->order = 'name';

echo CHtml::dropdownlist(
	'studyId',
	'',
	CHtml::listData(Study::model()->findAll($criteria),'id', 'name'),
	                array(
                        'empty' => 'Select',
                        'onchange'=>"js:getInterviews(\$(this))",
                        'class'=>'form-control'
                    )

);
echo "<br><br>";
echo " Include Responses<br><br>";
?>
    <div id="interviews"></div>
        <div class="form-group">
            <div class="col-lg-4 ">
                <button class="btn btn-info">Export</button>
            </div>
        </div>
    <?php $this->endWidget(); ?>
    </div>
</div>

<script type="text/javascript">
//On import study form submit
/*
$( "#importForm" ).submit(function( event) {
    var userfile = document.getElementById('userfile').files[0];

    if(userfile && userfile.size < <?php Yii::app()->params['maxUploadFileSize']; ?> ) { //This size is in bytes.

        var res_field = document.getElementById('userfile').value;
        var extension = res_field.substr(res_field.lastIndexOf('.') + 1).toLowerCase();
        var allowedExtensions = ['study'];
        event.preventDefault();
        if (res_field.length > 0)
        {
            if( allowedExtensions.indexOf(extension) === -1 )
            {
                event.preventDefault();
                alert('Invalid file Format. Only ' + allowedExtensions.join(', ') + ' allowed.');
                return false;
            }
        }
        else{
            //Submit form
            $("#importForm").submit();
        }
    } else {
        //Prevent default and display error
        event.preventDefault();
        alert("Upload file cannot exceed <?php echo number_format(Yii::app()->params['maxUploadFileSize'] / 1048576, 1) . ' MB'; ?>");
        return false;
    }
});*/
</script>
