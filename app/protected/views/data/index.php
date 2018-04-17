<?php
/* @var $this DataController */
$this->pageTitle = "Data Processing";
?>

<div class="view" style="">
<h2>Studies</h2>
<?php foreach($studies as $data): ?>
	<?php echo CHtml::link(
		CHtml::encode(Study::getName($data->id)),
		Yii::app()->createUrl('data/study/'.$data->id)
		); ?><br>
<?php endforeach; ?>
</div>
