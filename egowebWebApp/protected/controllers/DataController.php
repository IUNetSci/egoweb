<?php

class DataController extends Controller
{


	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			//'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('index', 'exportego', 'savenote', 'noteexists','exportalterpair', 'exportalterlist', 'exportother', 'visualize', 'study', 'ajaxAdjacencies'),
				'users'=>array('@'),
			),
			array('allow',  // deny all users
				'users'=>array('*'),
			),
		);
	}

    public function actionStudy($id)
    {
        #OK FOR SQL INJECTION
        $params = new stdClass();
        $params->name = ':id';
        $params->value = $id;
        $params->dataType = PDO::PARAM_INT;

        $interviews = q("SELECT * FROM interview WHERE studyId = :id",array($params))->queryAll();
        #OK FOR SQL INJECTION
        $study = Study::model()->findByPk((int)$id);
        #OK FOR SQL INJECTION
        $questionIds = q("SELECT id FROM question WHERE subjectType = 'ALTER_PAIR' AND studyId = :id",array($params))->queryColumn();
        $expressions = array();
        if(count($questionIds) > 0){
            $questionIds = implode(",", $questionIds);
            $criteria = array(
                'condition'=>"studyId = " . $study->id ." AND questionId in ($questionIds)",
            );
            $expressions = CHtml::listData(
                Expression::model()->findAll($criteria),
                'id',
                function($post) {return CHtml::encode(substr($post->name,0,40));}
            );
        }
        $this->render('study', array(
            'study'=>$study,
            'interviews'=>$interviews,
            'expressions'=>$expressions,
        ));
    }

    public function actionVisualize()
    {
        $graphs = array();
        if(isset($_GET['interviewId'])){
            #OK FOR SQL INJECTION
            $params = new stdClass();
            $params->name = ':id';
            $params->value = $_GET["interviewId"];
            $params->dataType = PDO::PARAM_INT;

            $studyId = q("SELECT studyId FROM interview WHERE id = :id",array($params))->queryScalar();
            $params->value = $studyId;

            if( !$studyId ){
                echo "No studyId found for interviewId = ".$_GET['interviewId'];
                return;
            }

            #OK FOR SQL INJECTION
            $questionIds = q("SELECT id FROM question WHERE subjectType = 'ALTER_PAIR' AND studyId = :id",array($params))->queryColumn();

            $questionIds = implode(",", $questionIds);
            if(!$questionIds)
                $questionIds = 0;
            $alter_pair_expression_ids = q("SELECT id FROM expression WHERE studyId = :id AND questionId in (" . $questionIds . ")",array($params))->queryColumn();

            if (count($alter_pair_expression_ids) < 1 ) {
                echo "NO ALTER PAIR EXPRESSION IDS FOUND FOR QUESTION IDS ".(string)$questionIds;
                $alter_pair_expressions = array();
            }
            else{
                $all_expression_ids = $alter_pair_expression_ids;
                foreach($alter_pair_expression_ids as $id){
                    #OK FOR SQL INJECTION
                    $all_expression_ids = array_merge(q("SELECT id FROM expression WHERE FIND_IN_SET($id, value)")->queryColumn(),$all_expression_ids);
                }
                #OK FOR SQL INJECTION
                $alter_pair_expressions = q("SELECT * FROM expression WHERE id in (" . implode(",",$all_expression_ids) . ")")->queryAll();
            }

            if(isset($_GET['print'])){
                $this->renderPartial('print',
                    array(
                        'graphs'=>$graphs,
                        'studyId'=>$studyId,
                        'alter_pair_expressions'=> $alter_pair_expressions,
                        'interviewId'=>$_GET['interviewId'],
                    ), false, true
                );
            }else{
                $this->render('visualize',
                    array(
                        'graphs'=>$graphs,
                        'studyId'=>$studyId,
                        'alter_pair_expressions'=> $alter_pair_expressions,
                        'interviewId'=>$_GET['interviewId'],
                    )
                );

            }

        }
    }


	public function actionIndex()
	{
        $studies = Study::model()->findAll();

		$this->render('index', array(
			'studies'=>$studies,
		));
	}

	public function actionExportego()
	{
		if(!isset($_POST['studyId']) || $_POST['studyId'] == "")
			die("nothing to export");

		if(isset($_POST['expressionId']))
			$expressionId = $_POST['expressionId'];
		else
			$expressionId = $study->adjacencyExpressionId;

        #OK FOR SQL INJECTION
		$study = Study::model()->findByPk((int)$_POST['studyId']);
        #OK FOR SQL INJECTION
        $optionsRaw = q("SELECT * FROM questionOption WHERE studyId = " . $study->id)->queryAll();

		// create an array with option ID as key
		$options = array();
		foreach ($optionsRaw as $option){
			$options[$option['id']] = $option['value'];
		}
		// fetch questions
        #OK FOR SQL INJECTION
		$ego_id_questions = q("SELECT * FROM question WHERE subjectType = 'EGO_ID' AND studyId = " . $study->id . " ORDER BY ordering")->queryAll();
        #OK FOR SQL INJECTION
        $ego_questions = q("SELECT * FROM question WHERE subjectType = 'EGO' AND studyId = " . $study->id . " ORDER BY ordering")->queryAll();
        #OK FOR SQL INJECTION
        $alter_questions = q("SELECT * FROM question WHERE subjectType = 'ALTER' AND studyId = " . $study->id . " ORDER BY ordering")->queryAll();

		// start generating export file
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".seoString($study->name)."-ego-alter-data".".csv");
		header("Content-Type: application/force-download");

		$headers = array();
		$headers[] = 'Interview ID';
		$headers[] = "EgoID";
		foreach ($ego_id_questions as $question){
			$headers[] = $question['title'];
		}
		foreach ($ego_questions as $question){
			$headers[] = $question['title'];
		}
		$headers[] = "Alter Name";
		foreach ($alter_questions as $question){
			$headers[] = $question['title'];
		}
		if($expressionId){
			$headers[] = "Density";
			$headers[] = "Max Degree Value";
			$headers[] = "Max Betweenness Value";
			$headers[] = "Max Eigenvector Value";
			$headers[] = "Degree Centralization";
			$headers[] = "Betweenness Centralization";
			$headers[] = "Components";
			$headers[] = "Dyads";
			$headers[] = "Isolates";
			$headers[] = "Degree";
			$headers[] = "Betweenness";
			$headers[] = "Eigenvector";
		}

		echo implode(',', $headers) . "\n";

		$interviews = Interview::model()->findAllByAttributes(array('studyId'=>$_POST['studyId']));
		foreach ($interviews as $interview){
			if(!isset($_POST['export'][$interview->id]))
				continue;

            #OK FOR SQL INJECTION
			$alters = q("SELECT * FROM alters WHERE interviewId = " . $interview->id)->queryAll();
			if(!$alters){
				$alters = array('0'=>array('id'=>null));
			}else{
				if(isset($_POST['noAlters']) && $_POST['noAlters'] == 1)
					continue;
			}

			foreach($alters as &$alter){
				if($alter['name']!="")
					$alter['name'] = decrypt($alter['name']);
			}	

			if($expressionId){
				$stats = new Statistics;
				$stats->initComponents($interview->id, $expressionId);
			}

			foreach ($alters as $alter){
				$answers = array();
				$answers[] = $interview->id;
				$ego_ids = array();
				foreach ($ego_id_questions as $question){
                    #OK FOR SQL INJECTION
					$ego_ids[] = q("SELECT value FROM answer WHERE interviewId = " . $interview->id  . " AND questionId = " . $question['id'])->queryScalar();
				}
				foreach($ego_ids as &$ego_id){
					if ($ego_id!="")
						$ego_id = decrypt($ego_id);
				}				
				$answers[] = implode("_", $ego_ids);
				foreach($ego_ids as $ego_id)
					$answers[] = $ego_id;
				foreach ($ego_questions as $question){
                    #OK FOR SQL INJECTION
					$answer = q("SELECT value FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'])->queryScalar();
                    #OK FOR SQL INJECTION
                    $skipReason =  q("SELECT skipReason FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'])->queryScalar();
					if($answer && $skipReason == "NONE"){
						if($question['answerType'] == "SELECTION"){
							if(isset($options[$answer]))
								$answers[] = $options[$answer];
							else
								$answers[] = "";
						}else if($question['answerType'] == "MULTIPLE_SELECTION"){
							$optionIds = explode(',', $answer);
							$list = array();
							foreach($optionIds as $optionId){
								if(isset($options[$optionId]))
								$list[] = $options[$optionId];
							}
							$answers[] = implode('; ', $list);
						}else{
							$answers[] = $answer;
						}
					} else if (!$answer && ($skipReason == "DONT_KNOW" || $skipReason == "REFUSE")) {
						if($skipReason == "DONT_KNOW")
							$answers[] = $study->valueDontKnow;
						else
							$answers[] = $study->valueRefusal;
					} else {
						if(is_numeric($question['answerReasonExpressionId']) && !Expression::evalExpression($question['answerReasonExpressionId'], $interview->id))
							$answers[] = $study->valueLogicalSkip;
						else
							$answers[] = $study->valueNotYetAnswered;
					}
				}
				if(is_numeric($alter['id'])){
					$answers[] = $alter['name'];
					foreach ($alter_questions as $question){
						$expression = new Expression;
                        #OK FOR SQL INJECTION
						$answer = q("SELECT value FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter['id'])->queryScalar();
                        #OK FOR SQL INJECTION
                        $skipReason =  q("SELECT skipReason FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter['id'])->queryScalar();
						if($answer && $skipReason == "NONE"){
							if($question['answerType'] == "SELECTION"){
								$answers[] = $options[$answer];
							}else if($question['answerType'] == "MULTIPLE_SELECTION"){
								$optionIds = explode(',', $answer);
								$list = array();
								foreach($optionIds as $optionId){
									if(isset($options[$optionId]))
										$list[] = $options[$optionId];
								}
								if(count($list) == 0)
									$answers[] = $study->valueNotYetAnswered;
								else
									$answers[] = implode('; ', $list);
							}else{
								$answers[] = $answer;
							}
						} else if (!$answer && ($skipReason == "DONT_KNOW" || $skipReason == "REFUSE")) {
							if($skipReason == "DONT_KNOW")
								$answers[] = $study->valueDontKnow;
							else
								$answers[] = $study->valueRefusal;
						} else {
							if(is_numeric($question['answerReasonExpressionId']) && !$expression->evalExpression($question['answerReasonExpressionId'], $interview->id, $alter['id']))
								$answers[] = $study->valueLogicalSkip;
							else
								$answers[] = $study->valueNotYetAnswered;
						}
					}
				}
				if($expressionId){
					$answers[] = $stats->getDensity();
					$answers[] = $stats->maxDegree();
					$answers[] = $stats->maxBetweenness();
					$answers[] = $stats->maxEigenvector();
					$answers[] =  $stats->degreeCentralization();
					$answers[] = $stats->betweennessCentralization();
					$answers[] = count($stats->components);
					$answers[] = count($stats->dyads);
					$answers[] = count($stats->isolates);
					$answers[] = $stats->getDegree($alter['id']);
					$answers[] = $stats->getBetweenness($alter['id']);
					$answers[] = $stats->eigenvectorCentrality($alter['id']);
				}
				echo implode(',', $answers) . "\n";
				flush();
			}

		}
		Yii::app()->end();

	}

	public function actionExportalterpair()
	{
		if(!isset($_POST['studyId']) || $_POST['studyId'] == "")
			die("nothing to export");

		$study = Study::model()->findByPk((int)$_POST['studyId']);
        #OK FOR SQL INJECTION
		$optionsRaw = q("SELECT * FROM questionOption WHERE studyId = " . $study->id)->queryAll();

		// create an array with option ID as key
		$options = array();
		foreach ($optionsRaw as $option){
			$options[$option['id']] = $option['value'];
		}

        #OK FOR SQL INJECTION
		$alter_pair_questions = q("SELECT * FROM question WHERE subjectType = 'ALTER_PAIR' AND studyId = " . $study->id . " ORDER BY ordering")->queryAll();
        #OK FOR SQL INJECTION
        $alterCount = q("SELECT count(id) FROM `alterList` WHERE studyId = " . $study->id)->queryScalar();
		if($alterCount > 0)
			$idNumber = "Id";
		else
			$idNumber = "#";

		// start generating export file
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".seoString($study->name)."-alter-pair-data".".csv");
		header("Content-Type: application/force-download");
		$headers = array();
		$headers[] = 'Interview ID';
		$headers[] = 'EgoID';
		$headers[] = "Alter 1 " . $idNumber;
		$headers[] = "Alter 1 Name";
		$headers[] = "Alter 2 " . $idNumber;
		$headers[] = "Alter 2 Name";
		foreach ($alter_pair_questions as $question){
			$headers[] = $question['title'];
		}
		echo implode(',', $headers) . "\n";

		$interviews = Interview::model()->findAllByAttributes(array('studyId'=>$_POST['studyId']));
		foreach ($interviews as $interview){
			if(!isset($_POST['export'][$interview->id]))
				continue;
            #OK FOR SQL INJECTION
			$alters = q("SELECT * FROM alters WHERE interviewId = " . $interview->id)->queryAll();
			foreach($alters as &$alter){
				if($alter['name']!="")
					$alter['name'] = decrypt($alter['name']);
			}			
			$i = 1;
			$alterNum = array();
			foreach($alters as $alter){
				$alterNum[$alter['id']] = $i;
				$i++;
			}
			$alters2 = $alters;
			foreach ($alters as $alter){
				array_shift($alters2);
				foreach ($alters2 as $alter2){
					$answers = array();
                    #OK FOR SQL INJECTION
					$realId1 = q("SELECT id FROM alterList WHERE studyId = " . $study->id . " AND name = '" . addslashes($alter['name']) . "'")->queryScalar();
                    #OK FOR SQL INJECTION
                    $realId2 = q("SELECT id FROM alterList WHERE studyId = " . $study->id . " AND name = '" . addslashes($alter2['name']) . "'")->queryScalar();
					$answers[] = $interview->id;
					$answers[] = Interview::getEgoId($interview->id);
					if(is_numeric($realId1))
						$answers[] = $realId1;
					else
						$answers[] = $alterNum[$alter['id']];
					$answers[] = $alter['name'];
					if(is_numeric($realId2))
						$answers[] = $realId2;
					else
						$answers[] = $alterNum[$alter2['id']];
					$answers[] = $alter2['name'];
					foreach ($alter_pair_questions as $question){
                        #OK FOR SQL INJECTION
						$answer = q("SELECT value FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter['id'] . " AND alterId2 = " . $alter2['id'])->queryScalar();
                        #OK FOR SQL INJECTION
                        $skipReason =  q("SELECT skipReason FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter['id'] . " AND alterId2 = " . $alter2['id'])->queryScalar();
						if($answer && $skipReason == "NONE"){
							if($question['answerType'] == "SELECTION"){
								$answers[] = $options[$answer];
							}else if($question['answerType'] == "MULTIPLE_SELECTION"){
								$optionIds = explode(',', $answer);
								$list = array();
								foreach($optionIds as $optionId){
									if(isset($options[$optionId]))
									$list[] = $options[$optionId];
								}
								if(count($list) == 0)
									$answers[] = $study->valueNotYetAnswered;
								else
									$answers[] = implode('; ', $list);
							}else{
								$answers[] = $answer;
							}
						} else if (!$answer && ($skipReason == "DONT_KNOW" || $skipReason == "REFUSE")) {
							if($skipReason == "DONT_KNOW")
								$answers[] = $study->valueDontKnow;
							else
								$answers[] = $study->valueRefusal;
						} else {
							$expression = new Expression;
							if(is_numeric($question['answerReasonExpressionId']) && !$expression->evalExpression($question['answerReasonExpressionId'], $interview->id, $alter['id'], $alter2['id']))
								$answers[] = $study->valueLogicalSkip;
							else
								$answers[] = $study->valueNotYetAnswered;
						}
					}
					echo implode(',', $answers) . "\n";
					flush();
				}
			}
		}
		Yii::app()->end();
	}

	public function actionExportother()
	{
		if(!isset($_POST['studyId']) || $_POST['studyId'] == "")
			die("nothing to export");

		$study = Study::model()->findByPk((int)$_POST['studyId']);
        #OK FOR SQL INJECTION
		$optionsRaw = q("SELECT * FROM questionOption WHERE studyId = " . $study->id)->queryAll();

		// create an array with option ID as key
		$options = array();
		foreach ($optionsRaw as $option){
			$options[$option['id']] = $option['value'];
		}

		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".seoString($study->name)."-other-specify-data".".csv");
		header("Content-Type: application/force-download");
		$headers = array();
		$headers[] = 'INTERVIEW ID';
		$headers[] = "EGO ID";
		$headers[] = "QUESTION";
		$headers[] = "ALTER ID";
		$headers[] = "RESPONSE";
		echo implode(',', $headers) . "\n";

        #OK FOR SQL INJECTION
		$other_qs = q("SELECT * FROM question WHERE otherSpecify = 1 AND studyId = ".$study->id)->queryAll();
		$interviews = Interview::model()->findAllByAttributes(array('studyId'=>$_POST['studyId']));

		foreach ($interviews as $interview){
			if(!isset($_POST['export'][$interview->id]))
				continue;
			foreach($other_qs as $question){
				$answer = array();
				if($question['subjectType'] == "ALTER"){
					$alters = Alters::model()->findAllByAttributes(array('interviewId'=>$interview->id));
					foreach($alters as $alter){
                        #OK FOR SQL INJECTION
						$response = q("SELECT otherSpecifyText FROM answer WHERE questionId = " . $question['id'] . " AND interviewId = " . $interview->id . "AND alterId1 = " . $alter->id)->queryScalar();
						$responses = array();
						foreach(preg_split('/;;/', $response) as $other){
					    	if($other && strstr($other, ':')){
						    	list($key, $val) = preg_split('/:/', $other);
						    	$responses[] = $options[$key] . ":" . '"'.$val.'"';
						    }
						}
						if(count($responses) > 0)
							$response = implode(";; ", $responses);
						$answer[] = $interview->id;
						$answer[] = Interview::getRespondant($interview->id);
						$answer[] = $question['title'];
						if($alter->name!="")
						         $answer[] = decrypt($alter->name);
						//$answer[] = $alter->name;
						if($response!="")
						         $answer[] = decrypt($response);
						//$answer[] = $response;
						echo implode(',', $answer) . "\n";
						flush();
					}
				}else{
                    #OK FOR SQL INJECTION
					$response = q("SELECT otherSpecifyText FROM answer WHERE questionId = " . $question['id'] . " AND interviewId = " . $interview->id)->queryScalar();
					$responses = array();
					foreach(preg_split('/;;/', $response) as $other){
					    if($other && strstr($other, ':')){
					    	list($key, $val) = preg_split('/:/', $other);
					    	$responses[] = $options[$key] . ":" . '"'.$val.'"';
					    }
					}
					if(count($responses) > 0)
						$response = implode("; ", $responses);
					$answer[] = $interview->id;
					$answer[] = Interview::getRespondant($interview->id);
					$answer[] = $question['title'];
					$answer[] = "";
						if($response!="")
						         $answer[] = decrypt($response);
					//$answer[] = $response;
					echo implode(',', $answer) . "\n";
					flush();
				}
			}
		}
		Yii::app()->end();
	}

	public function actionExportalterlist()
	{
		if(!isset($_POST['studyId']) || $_POST['studyId'] == "")
			die("nothing to export");

		$study = Study::model()->findByPk((int)$_POST['studyId']);
        #OK FOR SQL INJECTION
		$alters = q("SELECT * FROM alterList WHERE studyId = " . $study->id)->queryAll();

		// start generating export file
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".seoString($study->name)."-predefined-alters".".csv");
		header("Content-Type: application/force-download");

		$headers = array();
		$headers[] = 'Study ID';
		$headers[] = "Alter ID";
		$headers[] = "Alter Name";
		$headers[] = "Alter Email";
		$headers[] = "Link With Key";
		echo implode(',', $headers) . "\n";

		foreach($alters as $alter){
			$row = array();
			$key = "key=".User::hashPassword($alter['email']);
			$row[] = $study->id;
			$row[] = $alter['id'];
			if($alter['name']!="")
				$row[] = decrypt($alter['name']);
			else
			$row[] = $alter['name'];
			if($alter['email']!="")
				$row[] = decrypt($alter['email']);
			else
			$row[] = $alter['email'];
			$row[] =  Yii::app()->getBaseUrl(true) . "/interviewing/".$study->id."?".$key;
			echo implode(',', $row) . "\n";
		}
		Yii::app()->end();
	}

    public function actionSavegraph()
    {
        if($_POST['Graph']){
            $graph = Graph::model()->findByAttributes(array("interviewId"=>$_POST['Graph']['interviewId'],"expressionId"=>$_POST['Graph']['expressionId']));
            if(!$graph)
                $graph = new Graph;
            $graph->attributes = $_POST['Graph'];
            if($graph->save()){
                echo "success";
                //$url =  "graphId=" . $graph->id . "&interviewId=" . $graph->interviewId . "&expressionId=".$graph->expressionId."&params=".urlencode($graph->params);
                //Yii::app()->request->redirect($this->createUrl("/data/visualize?" . $url));
            }
        }
    }

	public function actionDeletegraph()
	{
		if(isset($_GET['id'])){
			$graph = Graph::model()->findByPk($_GET['id']);
			if($graph)
				$graph->delete();
		}
	}

	public function actionGetnote()
	{
		if(isset($_GET['interviewId']) && isset($_GET['expressionId']) && isset($_GET['alterId'])){
			$model = Note::model()->findByAttributes(array(
				'interviewId' => (int)$_GET['interviewId'],
				'expressionId' => (int)$_GET['expressionId'],
				'alterId' => $_GET['alterId']
			));
			if(!$model){
				$model = new Note;
				$model->interviewId = $_GET['interviewId'];
				$model->expressionId = $_GET['expressionId'];
				$model->alterId = $_GET['alterId'];
			}
			$this->renderPartial('_form_note', array('model'=>$model, 'ajax'=>true), false, false);
		}
	}

	public function actionSavenote()
	{
		if(isset($_POST['Note'])){
			$new = false;
			if($_POST['Note']['id']){
				$note = Note::model()->findByPk((int)$_POST['Note']['id']);
			}else{
				$note = new Note;
				$new = true;
			}
			$note->attributes = $_POST['Note'];
			if(!$note->save())
				print_r($note->errors);

			echo $note->alterId;
		}
	}

	public function actionDeletenote()
	{
		if(isset($_POST['Note'])){

			$note = Note::model()->findByPk((int)$_POST['Note']['id']);
			$alterId = $note->alterId;
			if($note){
				$note->delete();
				echo $alterId;
			}
		}
	}

	public function actionDeleteinterviews(){
		if(!isset($_POST['export']))
			return false;
		foreach($_POST['export'] as $interviewId=>$selected){
			if($selected){
				$model = Interview::model()->findByPk((int)$interviewId);
				if($model){
					$answers = Answer::model()->findAllByAttributes(array("interviewId"=>$interviewId));
					foreach($answers as $answer)
						$answer->delete();
					$alters = Alters::model()->findAllByAttributes(array("interviewId"=>$interviewId));
					foreach($alters as $alter)
						$alter->delete();
					$model->delete();
				}
			}
		}
		Yii::app()->request->redirect(Yii::app()->request->urlReferrer);
	}
}
