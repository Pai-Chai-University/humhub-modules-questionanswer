<?php

/**
 * Connected Communities Initiative
 * Copyright (C) 2016 Queensland University of Technology
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace humhub\modules\questionanswer\models;

use humhub\components\ActiveRecord;
use humhub\modules\questionanswer\notifications\NewComment;
use humhub\modules\user\models\User;
use yii\helpers\Url;
use Yii;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\search\interfaces\Searchable;

/**
 * This is the model class for table "question".
 *
 * The followings are the available columns in table 'question':
 * @property integer $id
 * @property integer $question_id
 * @property integer $parent_id
 * @property string $post_title
 * @property string $post_text
 * @property string $post_type
 * @property string $created_at
 * @property integer $created_by
 * @property string $updated_at
 * @property integer $updated_by
 */
class Comment extends ContentActiveRecord
{

    /**
     * @inheritdoc
     */
    public $autoAddToWall = false;


    /**
	 * @return string the associated database table name
	 */
	public static function tableName()
	{
		return 'question';
	}

    public function getQuestion()
    {
        return $this->hasOne(Question::class, ['id' => 'question_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getParent()
    {

        // When the `parent_id` and `question_id` are the same, it's a comment on the question
        if($this->parent_id == $this->question_id) {
            return $this->hasOne(Question::class, ['id' => 'parent_id']);
        }

        // Otherwise it's a comment on an answer
        return $this->hasOne(Answer::class, ['id' => 'parent_id']);

    }


    /**
	 * Set default scope so that
	 * only comments are retrieved 
	 */
	public static function find()
	{
		return parent::find()->andWhere(['post_type' => 'comment']);
	}



    /** 
     * Filters results by the question_id
     * @param $question_id
     */
    public function question($question_id)
    {
        $this->getDbCriteria()->mergeWith(array(
            'condition'=>"question_id=:question_id", 
            'params' => array(':question_id' => $question_id)
        ));

        return $this;
    }
    
	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return [
			[['post_text', 'post_type'], 'required'],
			[['post_type'], 'string', 'max' => 255],
			[['created_at', 'updated_at'], 'safe'],
			[['question_id', 'parent_id', 'created_by', 'updated_by'], 'integer'],
		];
	}


	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'question_id' => 'Question',
			'parent_id' => 'Parent',
			'post_title' => 'Post Title',
			'post_text' => 'Post Text',
			'post_type' => 'Post Type',
			'created_at' => 'Created At',
			'created_by' => 'Created By',
			'updated_at' => 'Updated At',
			'updated_by' => 'Updated By',
		);
	}

    /**
     * Returns URL to the Question
     *
     * @return string
     */
    public function getUrl()
    {
        $params = [
            '/questionanswer/question/view',
            'id' => $this->question_id,
            '#' => 'post-' . $this->parent_id
        ];

        if(get_class($this->question->space) == \humhub\modules\space\models\Space::class) {
            $params['sguid'] = $this->question->space->guid;
        }

        return Url::toRoute($params);
    }

    /**
     * @inheritdoc
     */
    public function getContentName()
    {
        return "Comment";
    }

    /**
     * @inheritdoc
     */

    public function getContentDescription()
    {
        return $this->post_text;
    }


    /**
     * After Save, notify user
     */
    public function afterSave($insert, $changedAttributes)
    {
        if($insert) {
            NewComment::instance()->from($this->user)->about($this)->send($this->parent->user);
        }
        return parent::afterSave($insert, $changedAttributes);
    }



}
