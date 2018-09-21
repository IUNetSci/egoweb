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

<div class="view" style="">
	<h2>Interviews</h2>
	<table class="table">
		<tr>
			<th>Ego ID</th>
			<th>Study</th>
		</tr>
		
	<?php foreach($interviews as $interview) { ?>
		<!-- <pre>
<?php print_r($interview) ?>
		</pre> -->
		<tr>
			<td><?php echo $interview['egoId'] ?></td>
			<td>
			<?php echo CHtml::link(
				CHtml::encode(Study::getName($interview['studyId'])),
				Yii::app()->createUrl('data/study/'.$interview['studyId'])
				); ?>
			</td>
		</tr>
	<?php } ?>
	</table>
</div>
