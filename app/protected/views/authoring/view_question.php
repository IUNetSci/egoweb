<?php
    $this->pageTitle = $study->name;
?>
<h1><?php echo $model->subjectType; ?> Questions</h1>

<?php Yii::app()->clientScript->registerScriptFile(Yii::app()->baseUrl.'/js/summernote.js'); ?>

<script>

function changeAType(answerSelect) {
	var value = answerSelect.options[answerSelect.selectedIndex].value;
	if(value == 'TIME_SPAN')
		$(".weeks").show();
	if(value == 'DATE'){
		value = 'TIME_SPAN';
		$(".weeks").hide();
	}
	var model_id = $(answerSelect).attr('id').substring(2);
	$('.panel-' + model_id).hide();
	$('.panel-' + model_id + "#" +value).show();
}

function changeStyle(styleSelect, model_id, subjectType) {
	if(subjectType == 'EGO')
		return;
	if(styleSelect.is(':checked'))
		jQuery('.panel-'+model_id+'#ALTER_STYLE').show();
	else
		jQuery('.panel-'+model_id+'#ALTER_STYLE').hide();
}

clickOption = [];
optionPanel = '';
questionPanel = '';

function loadData(id, form){
	$("#data-" + id).html('');
	url = "/authoring/ajaxload?form=" + form + "&questionId=" + id + "&_=" + "<?php echo uniqid(); ?>";
	$.get(url, function(data){
		$("#data-" + id).html(data);
		$("#data-" + id).height($("#data-" + id + ":first-child").height())
	});
}

function initList(){
	$('.optionLink').click(function(e){clickOption[$(this).parent().parent().attr('id')] = true;
});


setTimeout(function(){
	$('#new').accordion({
		collapsible: true,
		heightStyle: "content",
		active: false,
	});
$('.items')
.accordion({
	collapsible: true,
	header: "> div > h3",
	heightStyle: "content",
	active: false,
	beforeActivate: function (event, ui) {
		if(typeof ui.newPanel.parent().attr('id') != "undefined"){
			if(clickOption[ui.newPanel.parent().attr('id')] == true){
				loadData(ui.newPanel.parent().attr('id'), "_form_option");
				optionPanel = ui.newPanel.parent().attr('id');
				questionPanel = '';
			}else{
				loadData(ui.newPanel.parent().attr('id'), "_form_question");
				questionPanel = ui.newPanel.parent().attr('id');
				optionPanel = "";
			}
		}else{
			if(clickOption[questionPanel] == true && !optionPanel){
            	event.stopImmediatePropagation();
				event.preventDefault();
				loadData(questionPanel, "_form_option");
				optionPanel = questionPanel;
				for(k in clickOption)
					clickOption[k] = false;
				questionPanel = '';
			}
		}
	},
	activate: function (event, ui){
		for(k in clickOption)
			clickOption[k] = false;
	}
})
.sortable({
	axis: "y",
	handle: "h3",
	scroll:false,
	stop: function( event, ui ) {
		ord = [];
		// $('.items > div').each(function(index){
		// 	ord.push("reorder[" + index + "]=" + $(this).attr('id'));
		// });
		// $.get('/authoring/ajaxreorder?' + ord.join('&'), function(data){
		// 	console.log(data);
		// });
        var reorder = {};
		$('.items > div').each(function(index){
			// ord.push("reorder[" + index + "]=" + $(this).attr('id'));
            reorder[index] = $(this).attr('id');
		});
        var csrf = $("input[name=YII_CSRF_TOKEN]").val();
        $.ajax({
            url: "/authoring/ajaxreorder",
            method: "POST",
            data: {
                "reorder": reorder,
                "YII_CSRF_TOKEN": csrf
            },
            success: function(data){
                console.debug("Success:", data);
            },
            error: function(x, status, error){
                console.debug("Error:", error);
            },
        });
		// console.log(ord.join('&'));
        console.debug(reorder);
		ui.item.children( "h3" ).triggerHandler( "focusout" );
	}
});
}, 50);

}
$(function(){
	initList();

});
</script>
<div id='new'>
	<h3>New</h3>
	<div>
		<?php $this->renderPartial('_form_question', array('model'=>$model, 'ajax'=>false), false, false); ?>
	</div>
</div>
<br>
<div id='question-list'>
<?php $this->widget('zii.widgets.CListView', array(
	'dataProvider'=>$dataProvider,
	'itemView'=>'_view_question',
	'summaryText'=>'',
)); ?>
</div>
