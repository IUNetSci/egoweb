<?php

/**
 * This is the model class for table "interview".
 *
 * The followings are the available columns in table 'interview':
 * @property integer $id
 * @property integer $random_key
 * @property integer $active
 * @property integer $studyId
 * @property integer $completed
 */
class Interview extends CActiveRecord
{
    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Interview the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'interview';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('studyId', 'required'),
            array('id, active, studyId, completed', 'length', 'max'=>255),
            array('id, active, studyId', 'numerical', 'integerOnly'=>true),
            array('completed', 'default', 'value'=>0),
            array('start_date', 'default',
                'value'=>time(),
                'setOnEmpty'=>true, 'on'=>'insert'
            ),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, active, studyId, completed', 'safe', 'on'=>'search'),
        );
    }

    public function getHasMatches()
    {
        $criteria = array(
			'condition'=>"interviewId1 = $this->id OR interviewId2 = $this->id",
		);
        $matches = MatchedAlters::model()->findAll($criteria);
        if(count($matches) > 0)
            return true;
        return false;
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'random_key' => 'Random Key',
            'active' => 'Active',
            'studyId' => 'Study',
            'completed' => 'Completed',
        );
    }

    public static function getInterviewFromEmail($studyId, $email)
    {
        #OK FOR SQL INJECTION
        $interviewId = q("SELECT interviewId FROM answer WHERE value='$email' AND questionType = 'EGO_ID' AND studyId = $studyId")->queryScalar();
        if ($interviewId)
            return Interview::model()->findByPk($interviewId);
        else
            return false;
    }

    /**
     * retrieves interview (or create new one) from MMIC prime key
     * @param $studyId
     * @param $primekey
     * @param $prefill (Ego ID Prefill)
     * @param $question (Ego Questions Prefill)
     * @return array|bool|CActiveRecord|Interview|mixed|null
     */
    public static function getInterviewFromPrimekey( $studyId, $primekey, $prefill, $questions = array())
    {
        $answers = Answer::model()->findAllByAttributes( array( 'questionType' => 'EGO_ID',
                'studyId' => $studyId ) );

        foreach ( $answers as $answer )
        {
            if ( $answer->value == $primekey )
            {
                return Interview::model()->findByPk( $answer->interviewId );
            }
        }

        $criteria=new CDbCriteria;
        $criteria->condition = ("studyId = $studyId and subjectType = 'EGO_ID' AND answerType != 'RANDOM_NUMBER'");
        $egoQs = Question::model()->findAll($criteria);
        $study = Study::model()->findByPk($studyId);

        if (count($egoQs) == 0){
            return false;
        }

        $interview = new Interview;
        $interview->studyId = $studyId;
        $interview->completed = 0;
        $interview->save();

        $prefill['prime_key'] = $primekey;

        foreach ($egoQs as $egoQ)
        {
            $egoIdQ = new Answer;
            $egoIdQ->interviewId = $interview->id;
            $egoIdQ->studyId = $studyId;
            $egoIdQ->questionType = "EGO_ID";
            $egoIdQ->answerType = $egoQ->answerType;
            $egoIdQ->questionId = $egoQ->id;
            $egoIdQ->skipReason = "NONE";
            if (isset($prefill[$egoQ->title]))
            {
                $egoIdQ->value = $prefill[$egoQ->title];
            }else
            {
                $egoIdQ->skipReason = "DONT_KNOW";
                $egoIdQ->value = $study->valueDontKnow;
            }
            $egoIdQ->save();
        }

		$randoms = Question::model()->findAllByAttributes(array("answerType"=>"RANDOM_NUMBER", "studyId"=>$studyId));
		foreach($randoms as $q){
		    $a = $q->id;
            $answer = new Answer;
            $answer->interviewId = $interview->id;
            $answer->studyId = $studyId;
            $answer->questionType = "EGO_ID";
            $answer->answerType = "RANDOM_NUMBER";
            $answer->questionId = $q->id;
            $answer->skipReason = "NONE";
            $answer->value = mt_rand ($q->minLiteral , $q->maxLiteral);
            $answer->save();
		}

        if(count($questions) > 0)
            $interview->fillQs($questions, $interview->id, $studyId);

        return $interview;
    }

    public static function fillQs($qs, $interviewId, $studyId)
    {
        foreach ($qs as $title=>$value)
        {
            $question = Question::model()->findByAttributes(array("title"=>$title, "studyId"=>$studyId));
            $answer = Answer::model()->findByAttributes(array("interviewId"=>$interviewId, "questionId"=>$question->id));
            if(!$answer)
                continue;
            $answer = new Answer;
            $answer->interviewId = $interviewId;
            $answer->studyId = $studyId;
            $answer->questionType = $question->subjectType;
            $answer->answerType = $question->answerType;
            $answer->questionId = $question->id;
            $answer->skipReason = "NONE";
            if ($value)
            {
                $answer->value = $value;
            }else
            {
                $answer->skipReason = "DONT_KNOW";
                $study = Study::model()->findByPk($studyId);
                $answer->value = $study->valueDontKnow;
            }
            $answer->save();
        }
    }

    public static function countAlters($id)
    {
        $criteria=array(
            'condition'=>"FIND_IN_SET(" . $id .", interviewId)",
        );
        $models = Alters::model()->findAll($criteria);
        return count($models);
    }

    public static function getRespondant($id)
    {
        #OK FOR SQL INJECTION
        $studyId = q("SELECT studyId FROM answer WHERE interviewId = $id")->queryScalar();

        if (!$studyId)
            return 'error';
        #OK FOR SQL INJECTION
        $firstId = q("SELECT id from question WHERE studyId = $studyId and subjectType = 'EGO_ID' ORDER by ordering")->queryScalar();

        if (!$firstId)
            return '';
        $egoIdAnswer = Answer::model()->find(array(
                'condition'=>"interviewId=:interviewId AND questionId = $firstId AND value != ''",
                'params'=>array(':interviewId'=>$id),
            ));

        if (isset($egoIdAnswer->value) && stristr($egoIdAnswer->value, '@'))
            #OK FOR SQL INJECTION
            return q("SELECT name FROM alterList WHERE email = '" .$egoIdAnswer->value . "'")->queryScalar();
        else if (isset($egoIdAnswer->value))
                return $egoIdAnswer->value;
            else
                return '';
    }

    public static function getEgoId($id)
    {
        #OK FOR SQL INJECTION
        $params = new stdClass();
        $params->name = ':id';
        $params->value = $id;
        $params->dataType = PDO::PARAM_INT;

        $interview = q("SELECT * FROM interview where id = :id", array($params))->queryRow();
        $ego_id_questions = q("SELECT * FROM question WHERE subjectType = 'EGO_ID' AND studyId = " . $interview['studyId'] . " AND answerType NOT IN ('STORED_VALUE', 'RANDOM_NUMBER') ORDER BY ordering")->queryAll();
        $egoId = "";
        foreach ($ego_id_questions as $question)
        {
            $headers[] = $question['title'];
        }
        $ego_ids = array();
        foreach ($ego_id_questions as $question)
        {
            if ($question['answerType'] == "MULTIPLE_SELECTION")
            {
                #OK FOR SQL INJECTION
                $optionId = decrypt(q("SELECT value FROM answer WHERE interviewId = " . $interview['id']  . " AND questionId = " . $question['id'])->queryScalar());

                if ($optionId && is_numeric($optionId))
                {
                    //$optionId = decrypt($optionId);
                    #OK FOR SQL INJECTION
                    $ego_ids[] = q("SELECT name FROM questionOption WHERE id = " . $optionId)->queryScalar();
                }
            }else
            {
                $id_response = Answer::model()->findByAttributes(array("interviewId" => $interview['id'], "questionId"=>$question['id']));
                if ($id_response)
                    $ego_ids[] = $id_response->value;
            }
        }

        if (isset($ego_ids))
            $egoId = implode("_", $ego_ids);

        return $egoId;
    }

    public static function multiInterviewIds($interviewId = null, $study = null)
    {
        #OK FOR SQL INJECTION
        $interview = Interview::model()->findByPk((int)$interviewId);
        if ($interview && $study && $study->multiSessionEgoId)
        {
            #OK FOR SQL INJECTION
            $egoValue = decrypt(q("SELECT value FROM answer WHERE interviewId = " . $interview->id . " AND questionId = " . $study->multiSessionEgoId)->queryScalar());
            #OK FOR SQL INJECTION
            $multiIds = q("SELECT id FROM question WHERE title = (SELECT title FROM question WHERE id = " . $study->multiSessionEgoId . ")")->queryColumn();
            if ($multiIds)
            {
                $answers = Answer::model()->findAllByAttributes(array('questionId'=>$multiIds));
                $interviewIds = array();
                foreach ($answers as $answer)
                {
                    if ($answer->value == $egoValue)
                    {
                        $interviewIds[] = $answer->interviewId;
                    }
                }
                #OK FOR SQL INJECTION
                $interviewIds = array_unique($interviewIds);
                return $interviewIds;
            }
        }
        return $interviewId;
    }

    // CORE FUNCTION
    public static function interpretTags($string, $interviewId = null, $alterId1 = null, $alterId2 = null)
    {

        if (!$interviewId)
            return $string;

        #OK FOR SQL INJECTION
        $params = new stdClass();
        $params->name = ':interviewId';
        $params->value = $interviewId;
        $params->dataType = PDO::PARAM_INT;

        $studyId = q("SELECT studyId FROM interview WHERE id = :interviewId", array($params))->queryScalar();
        #OK FOR SQL INJECTION
        $study = Study::model()->findByPk((int)$studyId);

        $interviewId = Interview::multiInterviewIds($interviewId, $study);

        if (is_array($interviewId))
            $interviewId = implode(",", $interviewId);

        // parse out and replace variables
        preg_match_all('#<VAR (.+?) />#ims', $string, $vars);
        foreach ($vars[1] as $var)
        {
            if (preg_match('/:/', $var))
            {
                list($sS, $sQ) = explode(":", $var);

                #OK FOR SQL INJECTION
                $sId = q("SELECT id FROM study WHERE name = '".$sS ."'")->queryScalar();
                $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$sId));
            }else
            {
                $question = Question::model()->findByAttributes(array('title'=>$var, 'studyId'=>$studyId));
            }

            if ($question)
            {
                if ($interviewId != null)
                {
                    $end = " AND interviewId in (". $interviewId .")";
                }else
                {
                    $end = "";
                }
                $criteria=new CDbCriteria;
                $criteria=array(
                    'condition'=>"questionId = " . $question->id . $end,
                    'order'=>'id DESC',
                );
                $lastAnswer = Answer::model()->find($criteria);
            }
            if (isset($lastAnswer))
            {
                if ($question->answerType == "MULTIPLE_SELECTION")
                {
                    $optionIds = explode(",", $lastAnswer->value);
                    $lastAnswer->value = "";
                    $answerArray = array();
                    foreach  ($optionIds as $optionId)
                    {
                        $option = QuestionOption::model()->findbyPk($optionId);
                        if ($option)
                        {
                            $criteria=new CDbCriteria;
                            $criteria=array(
                                'condition'=>"optionId = " . $option->id . " AND interviewId in ($interviewId)",
                            );
                            $otherSpecify = OtherSpecify::model()->find($criteria);
                            if ($otherSpecify)
                                $answerArray[] = $option->name . " (\"" . $otherSpecify->value . "\")";
                            else
                                $answerArray[] = $option->name;
                        }
                    }
                    $lastAnswer->value = implode("; ", $answerArray);
                }
                $string =  preg_replace('#<VAR '.$var.' />#', $lastAnswer->value, $string);
            }else
            {
                $string =  preg_replace('#<VAR '.$var.' />#', '', $string);
            }
        }

        // performs calculations on questions
        preg_match_all('#<CALC (.+?) />#ims', $string, $calcs);
        foreach ($calcs[1] as $calc)
        {
            preg_match('/(\w+)/', $calc, $vars);
            foreach ($vars as $var)
            {
                if (preg_match('/:/', $var))
                {
                    list($sS, $sQ) = explode(":", $var);
                    #OK FOR SQL INJECTION
                    $sId = q("SELECT id FROM study WHERE name = '".$sS ."'")->queryScalar();
                    $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$sId));
                } else
                {
                    $question = Question::model()->findByAttributes(array('title'=>$var, 'studyId'=>$studyId));
                }
                if ($question)
                {
                    if ($interviewId != null)
                    {
                        $end = " AND interviewId in (". $interviewId . ")";
                    }else
                    {
                        $end = "";
                    }
                    $criteria=new CDbCriteria;
                    $criteria=array(
                        'condition'=>"questionId = " . $question->id . $end,
                        'order'=>'id DESC',
                    );
                    $lastAnswer = Answer::model()->find($criteria);
                }
                if (isset($lastAnswer))
                    $logic =  preg_replace('#'.$var.'#', $lastAnswer->value, $calc);
                else
                    $logic =  preg_replace('#'.$var.'#', '', $calc);
            }
            $logic = 'return ' . $logic . ';';

            $calculation = eval($logic);
            $string =  str_replace("<CALC ".$calc." />", $calculation, $string);
        }

        // counts numbers of times question is answered with string
        preg_match_all('#<COUNT (.+?) />#ims', $string, $counts);
        foreach ($counts[1] as $count)
        {
            list($qTitle, $answer) = preg_split('/\s/', $count);
            $answer = str_replace('"', '', $answer);
            if (preg_match('/:/', $qTitle))
            {
                list($sS, $sQ) = explode(":", $qTitle);
                #OK FOR SQL INJECTION
                $sId = q("SELECT id FROM study WHERE name = '".$sS ."'")->queryScalar();
                $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$sId));
            }else
            {
                $question = Question::model()->findByAttributes(array('title'=>$qTitle, 'studyId'=>$studyId));
            }
            $criteria=new CDbCriteria;
            if (!$question)
                continue;

            $theAnswer = array();
            if ($question->answerType == "MULTIPLE_SELECTION")
            {
                $option = QuestionOption::model()->findbyAttributes(array('name'=>$answer, 'questionId'=>$question->id));
                if (!$option)
                    continue;
                if ($interviewId != null)
                {
                    $end = " AND interviewId in (". $interviewId. ")";
                }else
                {
                    $end = "";
                }
                $criteria=array(
                    'condition'=>'questionId = '. $question->id . $end,
                );
                $answers = Answer::model()->findAll($criteria);
                foreach ($answers as $a)
                {
                    if (in_array($option->id, explode(",", $a->value)))
                        $theAnswer[] = $a;
                }
            }else
            {
                $criteria=array(
                    'condition'=>'1 = 1' . $end,
                );
                $answers = Answer::model()->findAll($criteria);
                foreach ($answers as $a)
                {
                    if ($a->value == $answer)
                        $theAnswer[] = $a;
                }
            }
            $string =  str_replace("<COUNT ".$count." />", count($theAnswer), $string);
        }

        // date interpretter
        preg_match_all('#<DATE (.+?) />#ims', $string, $dates);
        foreach ($dates[1] as $date)
        {
            list($qTitle, $amount, $period) = preg_split('/\s/', $date);
            if (preg_match('/:/', $qTitle))
            {
                list($sS, $sQ) = explode(":", $qTitle);
                $study = Study::model()->findByAttributes(array("name"=>$sS));
                $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$study->id));
            }else
            {
                $question = Question::model()->findByAttributes(array('title'=>$qTitle, 'studyId'=>$studyId));
            }
            if(strtolower($qTitle) == "now")
            {
                $answer = new Answer;
                $answer->value = "now";
                $timeFormat = "F jS, Y";
            } else
            {
                if (!$question || $question->answerType != "DATE")
                    continue;
                $criteria=new CDbCriteria;
                if ($interviewId != null)
                    $end = " AND interviewId in (". $interviewId. ")";
                else
                    $end = "";
                $criteria=array(
                    'condition'=>'questionId = '. $question->id . $end,
                );
                $answer = Answer::model()->find($criteria);
                $timeArray = Question::timeBits($question->timeUnits);
                $timeFormat = "";
                if (in_array("BIT_MONTH", $timeArray))
                    $timeFormat = "F ";
                if (in_array("BIT_DAY", $timeArray))
                    $timeFormat .= "jS ";
                if (in_array("BIT_YEAR", $timeArray))
                    $timeFormat .= ", Y";
                if (in_array("BIT_HOUR", $timeArray))
                    $timeFormat .= "h:i A";
            }
            $newDate = date($timeFormat, strtotime($answer->value . " " .$amount . " " . $period));
            $string =  str_replace("<DATE ".$date." />", $newDate, $string);
        }

        // same as count, but limited to specific alter / alter pair questions
        preg_match_all('#<CONTAINS (.+?) />#ims', $string, $containers);
        foreach ($containers[1] as $contains)
        {
            list($qTitle, $answer) = preg_split('/\s/', $contains);
            $answer = str_replace('"', '', $answer);
            if (preg_match('/:/', $qTitle))
            {
                list($sS, $sQ) = explode(":", $qTitle);
                #OK FOR SQL INJECTION
                $sId = q("SELECT id FROM study WHERE name = '".$sS ."'")->queryScalar();
                $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$sId));
            }else
            {
                $question = Question::model()->findByAttributes(array('title'=>$qTitle, 'studyId'=>$studyId));
            }
            $criteria=new CDbCriteria;
            if (!$question)
                continue;
            if ($interviewId != null)
            {
                $end = " AND interviewId in (". $interviewId . ")";
                if (is_numeric($alterId1))
                    $end .= " AND alterId1 = " . $alterId1;
                if (is_numeric($alterId2))
                    $end .= " AND alterId2 = " . $alterId2;
            }else
            {
                $end = "";
            }
            $theAnswer = array();
            if ($question->answerType == "MULTIPLE_SELECTION")
            {
                $option = QuestionOption::model()->findbyAttributes(array('name'=>$answer, 'questionId'=>$question->id));
                if (!$option)
                    continue;
                $criteria=array(
                    'condition'=>'questionId = '. $question->id . $end,
                );
                $answers = Answer::model()->findAll($criteria);
                foreach ($answers as $a)
                {
                    if (in_array($option->id, explode(",", $a->value)))
                        $theAnswer[] = $a->value;
                }
            }else
            {
                $criteria=array(
                    'condition'=>"1 = 1" . $end,
                );
                $answers = Answer::model()->findAll($criteria);
                foreach ($answers as $a)
                {
                    if ($a->value == $answer)
                        $theAnswer[] = $a->value;
                }
            }
            $string =  str_replace("<CONTAINS ".$contains." />", count($theAnswer), $string);
        }

        // parse out and show logics
        preg_match_all('#<IF (.+?) />#ims', $string, $showlogics);
        foreach ($showlogics[1] as $showlogic)
        {
            preg_match('/(.+?) (==|!=|<|>|<=|>=)+ (.+?) \"(.+?)\"/ims', $showlogic, $exp);
            if (count($exp) > 1)
            {
                for ($i = 1; $i < 3; $i++)
                {
                    if ($i == 2 || is_numeric($exp[$i]))
                        continue;
                    if (preg_match("#/>#", $exp[$i]))
                    {
                        $exp[$i] = Interview::interpretTags($exp[$i]);
                    }else
                    {
                        if (preg_match('/:/', $exp[$i]))
                        {
                            list($sS, $sQ) = explode(":", $exp[$i]);
                            #OK FOR SQL INJECTION
                            $sId = q("SELECT id FROM study WHERE name = '".$sS ."'")->queryScalar();
                            $question = Question::model()->findByAttributes(array('title'=>$sQ, 'studyId'=>$sId));
                        } else
                        {
                            $question = Question::model()->findByAttributes(array('title'=>$exp[$i], 'studyId'=>$studyId));
                        }

                        if (!$question)
                        {
                            $exp[$i] = "";
                            continue;
                        }

                        if ($interviewId != null)
                        {
                            $end = " AND interviewId in (". $interviewId .")";
                        } else
                        {
                            $end = "";
                        }

                        $criteria=new CDbCriteria;
                        $criteria=array(
                            'condition'=>"questionId = " . $question->id . $end,
                            'order'=>'id DESC',
                        );
                        $lastAnswer = Answer::model()->find($criteria);
                        $exp[$i] = $lastAnswer->value;
                    }
                }
                $logic = 'return ' . $exp[1] . ' ' . $exp[2] . ' ' . $exp[3] . ';';
                //echo $logic;
                if ($exp[1] && $exp[2] && $exp[3])
                    $show = eval($logic);
                else
                    $show = false;
                if ($show)
                {
                    $string =  str_replace("<IF ".$showlogic." />", $exp[4], $string);
                }else
                {
                    $string =  str_replace("<IF ".$showlogic." />", "", $string);
                }
            }
        }
        return nl2br($string);
    }

    public function exportEgoAlterData($file)
    {
        $ego_id_questions = q("SELECT * FROM question WHERE subjectType = 'EGO_ID' AND studyId = " . $this->studyId . " ORDER BY ordering")->queryAll();
        #OK FOR SQL INJECTION
        $ego_questions = q("SELECT * FROM question WHERE subjectType = 'EGO' AND studyId = " . $this->studyId . " ORDER BY ordering")->queryAll();
        #OK FOR SQL INJECTION
        $alter_questions = q("SELECT * FROM question WHERE subjectType = 'ALTER' AND studyId = " . $this->studyId . " ORDER BY ordering")->queryAll();

        $criteria=new CDbCriteria;
        $criteria->condition = ("studyId = $this->studyId and subjectType = 'NETWORK'");
        $criteria->order = "ordering";
        $network_questions = Question::model()->findAll($criteria);

        $alters = Alters::model()->findAll(array('order'=>'id', 'condition'=>'FIND_IN_SET(:x, interviewId)', 'params'=>array(':x'=>$this->id)));

        if (!$alters)
        {
            $alters = array('0'=>array('id'=>null));
        } else
        {
            if (isset($_POST['expressionId']) && $_POST['expressionId'])
            {
                $stats = new Statistics;
                $stats->initComponents($this->id, $_POST['expressionId']);
            }
        }

        $text = "";
        $count = 1;

        $matchIntId = "";
        $matchUser = "";
		$criteria = array(
			'condition'=>"interviewId1 = $this->id OR interviewId2 = $this->id",
		);
		$match = MatchedAlters::model()->find($criteria);
		if($match){
            if($this->id == $match->interviewId1)
                $matchInt = Interview::model()->findByPk($match->interviewId2);
            else
                $matchInt = Interview::model()->findByPk($match->interviewId1);
            $matchIntId = $match->getMatchId();
            $matchUser = User::getName($match->userId);
        }

        foreach ($alters as $alter)
        {
            $answers = array();
            $headers = array();
            
            $answers[] = $this->studyId;
            $headers[] = "Study ID";
            
            $answers[] = $this->id;
            $headers[] = "Interview ID";
            $ego_ids = array();
            $ego_id_string = array();
            $study = Study::model()->findByPk($this->studyId);
            $optionsRaw = q("SELECT * FROM questionOption WHERE studyId = " . $study->id)->queryAll();
            
            $answers[] = $study->name;
            $headers[] = "Study Name";

            // create an array with option ID as key
            $options = array();
            $optionLabels = array();
            foreach ($optionsRaw as $option)
            {
                $options[$option['id']] = $option['value'];
                $optionLabels[$option['id']] = $option['name'];
            }

            // print_r($optionLabels);
            // die();

            
            $headers[] = "EgoID";

            $headers[] = "Start Time";

            $headers[] = "End Time";

            foreach ($ego_id_questions as $question)
            {

                #OK FOR SQL INJECTION
                $result = Answer::model()->findByAttributes(array("interviewId" => $this->id, "questionId" => $question['id']));
                $answer = $result->value;
                
                $headers[] = $question["title"];
                if($question['answerType'] == "SELECTION" || $question['answerType'] == "MULTIPLE_SELECTION")
                {
                    $headers[] = $question['title'] . " - Name";
                
                }

                if ($question['answerType'] == "MULTIPLE_SELECTION")
                {
                    $optionIds = explode(',', $answer);
                    foreach ($optionIds as $optionId)
                    {
                        if (isset($options[$optionId])){
                            $ego_ids[] = $options[$optionId];
                            if($question['answerType'] != "STORED_VALUE" && $question['answerType'] != "RANDOM_NUMBER")
                                $ego_id_string[] = $optionLabels[$optionId];
                        }else{
                            $ego_ids[] = "MISSING_OPTION ($optionId)";
                            if($question['answerType'] != "STORED_VALUE" && $question['answerType'] != "RANDOM_NUMBER")
                                $ego_id_string[] = "MISSING_OPTION ($optionId)";
                        }
                    }
                    if(!$optionIds){
                        $ego_ids[] = "";
                        if($question['answerType'] != "STORED_VALUE" && $question['answerType'] != "RANDOM_NUMBER")
                            $ego_id_string[] = "";
                    }
                } else
                {
                    $ego_ids[] = str_replace(',', '', $answer);
                    if($question['answerType'] != "STORED_VALUE" && $question['answerType'] != "RANDOM_NUMBER")
                        $ego_id_string[] = str_replace(',', '', $answer);
                }
            }


            $answers[] = implode("_", $ego_id_string);
            $answers[] = date("Y-m-d h:i:s", $this->start_date);
            $answers[] = date("Y-m-d h:i:s", $this->complete_date);

            foreach ($ego_ids as $eid)
            {
                $answers[] = $eid;
                // $headers[] = "ID_number";
            }
            foreach ($ego_questions as $question)
            {
                $answer = Answer::model()->findByAttributes(array("interviewId"=>$this->id, "questionId"=>$question['id']));
                $header = $question["title"];
                if(!$answer){
                    $answers[] = $study->valueNotYetAnswered;
                    $headers[] = $header;
                    continue;
                }

                if ($answer->value !== "" && $answer->skipReason == "NONE" && $answer->value != $study->valueLogicalSkip)
                {
                    if ($question['answerType'] == "SELECTION")
                    {
                        if (isset($options[$answer->value]))
                        {    
                            $answers[] = $options[$answer->value];
                            $headers[] = $header;
                            $answers[] = $optionLabels[$answer->value];
                            $headers[] = $header . " - Name";
                        }
                        else
                        {    
                            $answers[] = "";
                            $answers[] = "";
                            $headers[] = $header;
                            $headers[] = $header . " - Name";
                        }
                    } else if ($question['answerType'] == "MULTIPLE_SELECTION")
                    {
                        $optionIds = explode(',', $answer->value);
                        $list = array();
                        $list2 = array();
                        foreach ($optionIds as $optionId)
                        {
                            if (isset($options[$optionId]))
                            {
                                $list[] = $options[$optionId];
                                $list2[] = $optionLabels[$optionId];
                            }
                        }
                        $answers[] = implode('; ', $list);
                        $answers[] = implode('; ', $list2);
                        $headers[] = $header;
                        $headers[] = $header . " - Name";

                    } else if ($question['answerType'] == "TIME_SPAN")
                    {
                        if(!strstr($answer->value, ";")){
                            $times = array();
                            if(preg_match("/(\d*)\sYEARS/i", $answer->value, $test))
                                $times[] = $test[0];
                            if(preg_match("/(\d*)\sMONTHS/i", $answer->value, $test))
                                $times[] = $test[0];
                            if(preg_match("/(\d*)\sWEEKS/i", $answer->value, $test))
                                $times[] = $test[0];
                            if(preg_match("/(\d*)\sDAYS/i", $answer->value, $test))
                                $times[] = $test[0];
                            if(preg_match("/(\d*)\sHOURS/i", $answer->value, $test))
                                $times[] = $test[0];
                            if(preg_match("/(\d*)\sMINUTES/i", $answer->value, $test))
                                $times[] = $test[0];
                            $answer->value = implode("; ", $times);
                        }
                        $answers[] = $answer->value;
                        $headers[] = $header;
                        
                    } else
                    {
                        $answers[] = $answer->value;
                        $headers[] = $header;
                    }
                } else if ($answer->skipReason == "DONT_KNOW"){
                        $answers[] = $study->valueDontKnow;
                        $headers[] = $header;
                        if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                        {
                            $answers[] = "Don't Know";
                            $headers[] = $header . " - Name";
                        }
                } else if ($answer->skipReason == "REFUSE"){
                        $answers[] = $study->valueRefusal;
                        $headers[] = $header;
                        if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                        {
                            $answers[] = "Refuse";
                            $headers[] = $header . " - Name";
                        }
                } else if($answer->value == $study->valueLogicalSkip)
                {
                    $answers[] = $study->valueLogicalSkip;
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "Logical Skip";
                        $headers[] = $header . " - Name";
                    }
                } else {
                    $answers[] = "";
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "";
                        $headers[] = $header . " - Name";
                    }
                }
            }

            foreach ($network_questions as $question)
            {
                $answer = Answer::model()->findByAttributes(array("interviewId"=>$this->id, "questionId"=>$question->id));
                $header = $question->title;
                if(!$answer){
                    $answers[] = $study->valueNotYetAnswered;
                    $headers[] = $header;
                    continue;
                }
                if ($answer->value !== "" && $answer->skipReason == "NONE" && $answer->value != $study->valueLogicalSkip)
                {
                    if ($question->answerType == "SELECTION")
                    {
                        if (isset($options[$answer]))
                        {     
                            $answers[] = $options[$answer]; 
                            $answers[] = $optionLabels[$answer]; 
                            $headers[] = $header;
                            $headers[] = $header . " - Name";
                        }
                        else
                        {    
                            $answers[] = ""; 
                            $answers[] = "";
                            $headers[] = $header;
                            $headers[] = $header . " - Name";
                        }
                    } else if ($question->answerType == "MULTIPLE_SELECTION")
                    {
                        $optionIds = explode(',', $answer->value);
                        $list = array();
                        $list2 = array();
                        foreach ($optionIds as $optionId)
                        {
                            if (isset($options[$optionId]))
                            {
                                $list[] = $options[$optionId];
                                $list2[] = $optionLabels[$optionId]; 
                            }
                        }
                        $answers[] = implode('; ', $list);
                        $answers[] = implode('; ', $list2);
                        $headers[] = $header;
                        $headers[] = $header . " - Name";
                    } else
                    {
                        $answers[] = $answer->value;
                        $headers[] = $header;
                    }
                } else if ($answer->skipReason == "DONT_KNOW")
                {
                    $answers[] = $study->valueDontKnow;
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "Don't Know";
                        $headers[] = $header . " - Name";
                    }
                } else if ($answer->skipReason == "REFUSE")
                {
                    $answers[] = $study->valueRefusal;
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "Refuse";
                        $headers[] = $header . " - Name";
                    }
                }  else if($answer->value == $study->valueLogicalSkip)
                {
                    $answers[] = $study->valueLogicalSkip;
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "Logical Skip";
                        $headers[] = $header . " - Name";
                    }
                } else {
                    $answers[] = "";
                    $headers[] = $header;
                    if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                    {
                        $answers[] = "";
                        $headers[] = $header . " - Name";
                    }
                }
            }

            if (isset($stats))
            {
                $answers[] = $stats->getDensity();
                $answers[] = $stats->maxDegree();
                $answers[] = $stats->maxBetweenness();
                $answers[] = $stats->maxEigenvector();
                $answers[] = $stats->degreeCentralization();
                $answers[] = $stats->betweennessCentralization();
                $answers[] = count($stats->components);
                $answers[] = count($stats->dyads);
                $answers[] = count($stats->isolates);

                $headers[] = "Density";
                $headers[] = "Max Degree Value";
                $headers[] = "Max Betweenness Value";
                $headers[] = "Max Eigenvector Value";
                $headers[] = "Degree Centralization";
                $headers[] = "Betweenness Centralization";
                $headers[] = "Components";
                $headers[] = "Dyads";
                $headers[] = "Isolates";
            }

            if (isset($alter->id))
            {
                $matchId = "";
                $matchName = "";
        		$criteria = array(
        			'condition'=>"alterId1 = $alter->id OR alterId2 = $alter->id",
        		);

        		$match = MatchedAlters::model()->find($criteria);

                if($match){
                    $matchId = $match->id;
                    $matchName = $match->matchedName;
                }

                $answers[] = $matchIntId;
                $answers[] = $matchUser;
                $answers[] = $count;
                $answers[] = $alter->name;
                $answers[] = $matchName;
                $answers[] = $matchId;
                $headers[] = "Dyad Match ID";
                $headers[] = "Match User";
                $headers[] = "Alter Number";
                $headers[] = "Alter Name";
                $headers[] = "Matched Alter Name";
                $headers[] = "Alter Pair ID";

                foreach ($alter_questions as $question)
                {
                    $answer = Answer::model()->findByAttributes(array("interviewId"=>$this->id, "questionId"=>$question['id'], "alterId1"=>$alter->id));
                    $header = $question['title'];
                    if(!$answer){
                        $answers[] = $study->valueNotYetAnswered;
                        $headers [] = $header;
                        continue;
                    }
                    if ($answer->value != "" && $answer->skipReason == "NONE" && $answer->value != $study->valueLogicalSkip)
                    {
                        if ($question['answerType'] == "SELECTION")
                        {
                            $answers[] = $options[$answer->value];
                            $answers[] = $optionLabels[$answer->value];
                            $headers[] = $header;
                            $headers[] = $header . " - Name";
                        } else if ($question['answerType'] == "MULTIPLE_SELECTION")
                        {
                            $optionIds = explode(',', $answer->value);
                            $list = array();
                            $list2 = array();
                            foreach ($optionIds as $optionId)
                            {
                                if (isset($options[$optionId]))
                                {
                                    $list[] = $options[$optionId];
                                    $list2[] = $optionLabels[$optionId];
                                }
                            }
                            if (count($list) == 0)
                            {    
                                $answers[] = $study->valueNotYetAnswered;
                                $answers[] = $study->valueNotYetAnswered;
                                $headers[] = $header;
                                $headers[] = $header . " - Name";
                            }
                            else
                            {    
                                $answers[] = implode('; ', $list);
                                $answers[] = implode('; ', $list2);
                                $headers[] = $header;
                                $headers[] = $header . " - Name";
                            }
                        } else
                        {
                            $answers[] = $answer->value;
                            $headers[] = $header;
                        }
                    } else if ($answer->skipReason == "DONT_KNOW"){
                            $answers[] = $study->valueDontKnow;
                            $headers[] = $header;
                            if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                            {
                                $answers[] = "Don't Know";
                                $headers[] = $header . " - Name";
                            }
                    } else if ($answer->skipReason == "REFUSE"){
                            $answers[] = $study->valueRefusal;
                            $headers[] = $header;
                            if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                            {
                                $answers[] = "Refuse";
                                $headers[] = $header . " - Name";
                            }
                    } else if($answer->value == $study->valueLogicalSkip)
                    {
                        $answers[] = $study->valueLogicalSkip;
                        $headers[] = $header;
                        if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                        {
                            $answers[] = "Logical Skip";
                            $headers[] = $header . " - Name";
                        }
                    } else {
                        $answers[] = "";
                        $headers[] = $header;
                        if($question['answerType'] == "MULTIPLE_SELECTION" || $question['answerType'] == "SELECTION")
                        {
                            $answers[] = "";
                            $headers[] = $header . " - Name";
                        }
                    }
                }
            }else{
                $answers[] = 0;
                $answers[] = "";
                $headers[] = "";
                $headers[] = "";
                foreach ($alter_questions as $question)
                {
                    $answers[] = $study->valueNotYetAnswered;
                    $headers[] = $question["title"];
                }
            }

            if (isset($stats))
            {
                $answers[] = $stats->getDegree($alter->id);
                $answers[] = $stats->getBetweenness($alter->id);
                $answers[] = $stats->eigenvectorCentrality($alter->id);
                    
                $headers[] = "Degree";
                $headers[] = "Betweenness";
                $headers[] = "Eigenvector";
            }
            fputcsv($file, $headers);
            fputcsv($file, $answers);
            //$text .= implode(',', $answers) . "\n";
            $count++;
        }
        fclose($file);
        //return $text;
    }

    public function exportAlterPairData($file, $study)
    {
		$alters = Alters::model()->findAll(array('order'=>'id', 'condition'=>'FIND_IN_SET(:x, interviewId)', 'params'=>array(':x'=>$this->id)));
		//$alterNames = AlterList::model()->findAllByAttributes(array('interviewId'=>$interview->id));

		$i = 1;
		$alterNum = array();
		foreach($alters as $alter){
			$alterNum[$alter->id] = $i;
			$i++;
		}
		$alters2 = $alters;

		$alter_pair_questions = q("SELECT * FROM question WHERE subjectType = 'ALTER_PAIR' AND studyId = " . $study->id . " ORDER BY ordering")->queryAll();

		$optionsRaw = QuestionOption::model()->findAllByAttributes(array('studyId'=>$study->id));
		// create an array with option ID as key
		$options = array();
		foreach ($optionsRaw as $option){
			$options[$option->id] = $option->value;
		}

		foreach ($alters as $alter){
			array_shift($alters2);
			foreach ($alters2 as $alter2){
				$answers = array();
                #OK FOR SQL INJECTION
				$realId1 = q("SELECT id FROM alterList WHERE studyId = " . $study->id . " AND name = '" . addslashes($alter['name']) . "'")->queryScalar();
                #OK FOR SQL INJECTION
                $realId2 = q("SELECT id FROM alterList WHERE studyId = " . $study->id . " AND name = '" . addslashes($alter2['name']) . "'")->queryScalar();
				$answers[] = $this->id;
				$answers[] = Interview::getEgoId($this->id);
                $answers[] = $alterNum[$alter->id];
				$answers[] = str_replace(",", ";", $alter->name);
                $answers[] = $alterNum[$alter2->id];
				$answers[] = $alter2->name;
				foreach ($alter_pair_questions as $question){
                    #OK FOR SQL INJECTION
					$answer = decrypt(q("SELECT value FROM answer WHERE interviewId = " . $this->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter->id . " AND alterId2 = " . $alter2->id)->queryScalar());
                    #OK FOR SQL INJECTION
                    $skipReason =  q("SELECT skipReason FROM answer WHERE interviewId = " . $this->id . " AND questionId = " . $question['id'] . " AND alterId1 = " . $alter->id . " AND alterId2 = " . $alter2->id)->queryScalar();
					if($answer != "" && $skipReason == "NONE"){
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
							if(!$answer)
							    $answer = $study->valueNotYetAnswered;
							$answers[] = $answer;
						}
					} else if (!$answer && ($skipReason == "DONT_KNOW" || $skipReason == "REFUSE")) {
						if($skipReason == "DONT_KNOW")
							$answers[] = $study->valueDontKnow;
						else
							$answers[] = $study->valueRefusal;
					}
				}
                fputcsv($file, $answers);
			}
		}
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria=new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('active', $this->active);
        $criteria->compare('studyId', $this->studyId);
        $criteria->compare('completed', $this->completed);

        return new CActiveDataProvider($this, array(
                'criteria'=>$criteria,
            ));
    }
}
