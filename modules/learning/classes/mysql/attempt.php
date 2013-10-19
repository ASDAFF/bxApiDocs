<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/learning/classes/general/attempt.php");

// 2012-04-14 Checked/modified for compatibility with new data model

/**
 * 
 *
 *
 *
 *
 * @return mixed 
 *
 * @static
 * @link http://dev.1c-bitrix.ru/api_help/learning/classes/ctestattempt/index.php
 * @author Bitrix
 */
class CTestAttempt extends CAllTestAttempt
{
	// 2012-04-13 Checked/modified for compatibility with new data model
	public static function DoInsert($arInsert, $arFields)
	{
		global $DB;

		if (strlen($arInsert[0]) <= 0 || strlen($arInsert[0])<= 0)		// BUG ?
			return false;

		$strSql =
			"INSERT INTO b_learn_attempt(DATE_START, ".$arInsert[0].") ".
			"VALUES(".$DB->CurrentTimeFunction().", ".$arInsert[1].")";

		if($DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__))
			return $DB->LastID();

		return false;
	}


	// 2012-04-14 Checked/modified for compatibility with new data model
	public static function _CreateAttemptQuestionsSQLFormer($ATTEMPT_ID, $arTest, $clauseAllChildsLessons, $courseLessonId)
	{
		$strSql =
		"INSERT INTO b_learn_test_result (ATTEMPT_ID, QUESTION_ID)
		SELECT " . ($ATTEMPT_ID + 0) . " ,Q.ID
		FROM b_learn_lesson L
		INNER JOIN b_learn_question Q ON L.ID = Q.LESSON_ID
		WHERE (L.ID IN (" . $clauseAllChildsLessons . ") OR (L.ID = " . ($courseLessonId + 0) . ") ) 
		AND Q.ACTIVE = 'Y' "
		. ($arTest["INCLUDE_SELF_TEST"] != "Y" ? "AND Q.SELF = 'N' " : "").
		"ORDER BY " . ($arTest["RANDOM_QUESTIONS"] == "Y" ? CTest::GetRandFunction() : "Q.SORT ").
		($arTest["QUESTIONS_AMOUNT"] > 0 ? "LIMIT " . ($arTest["QUESTIONS_AMOUNT"] + 0) : "");

		return ($strSql);
	}


	// 2012-04-14 Checked/modified for compatibility with new data model
	
	/**
	 * <p>Создаёт план вопросов для указанной попытки.</p>
	 *
	 *
	 *
	 *
	 * @param int $ATTEMPT_ID  Идентификатор попытки.
	 *
	 *
	 *
	 * @return bool <p>Метод возвращает <i>true</i>, если создание плана вопросов прошло
	 * успешно. При возникновении ошибки метод вернёт <i>false</i>, а в
	 * исключениях будут содержаться ошибки.</p>
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if (CModule::IncludeModule("learning"))
	 * {
	 *     $ATTEMPT_ID = 563;
	 * 
	 *     $success = CTestAttempt::CreateAttemptQuestions($ATTEMPT_ID);
	 * 
	 *     if($success)
	 *     {
	 *         echo "Questions have been created.";
	 *     }
	 *     else
	 *     {
	 *         if($ex = $APPLICATION-&gt;GetException())
	 *             echo "Error: ".$ex-&gt;GetString();
	 *     }
	 * 
	 * }
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul><li> <a href="http://dev.1c-bitrix.ru/api_help/learning/classes/ctestresult/index.php">CTestResult</a>::<a
	 * href="http://dev.1c-bitrix.ru/api_help/learning/classes/ctestresult/add.php">Add</a> </li></ul><a name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/learning/classes/ctestattempt/createattemptquestions.php
	 * @author Bitrix
	 */
	public static function CreateAttemptQuestions($ATTEMPT_ID)
	{
		// This function generates database-specific SQL code
		$arCallbackSqlFormer = array ('CTestAttempt', '_CreateAttemptQuestionsSQLFormer');

		return (self::_CreateAttemptQuestions($arCallbackSqlFormer, $ATTEMPT_ID));
	}


	// 2012-04-13 Checked/modified for compatibility with new data model
	final protected static function _GetListSQLFormer ($sSelect, $obUserFieldsSql, $bCheckPerm, $USER, $arFilter, $strSqlSearch)
	{
		$oPermParser = new CLearnParsePermissionsFromFilter ($arFilter);

		$strSql =
		"SELECT DISTINCT ".
		$sSelect." ".
		$obUserFieldsSql->GetSelect()." ".
		"FROM b_learn_attempt A ".
		"INNER JOIN b_learn_test T ON A.TEST_ID = T.ID ".
		"INNER JOIN b_user U ON U.ID = A.STUDENT_ID ".
		"LEFT JOIN b_learn_course C ON C.ID = T.COURSE_ID ".
		"LEFT JOIN b_learn_test_mark TM ON A.TEST_ID = TM.TEST_ID ".
		$obUserFieldsSql->GetJoin("A.ID") .
		" WHERE 
			(TM.SCORE IS NULL 
			OR TM.SCORE = 
				(SELECT MIN(SCORE) 
					FROM b_learn_test_mark 
					WHERE SCORE >= 
						CASE WHEN A.STATUS = 'F' 
							THEN 1.0*A.SCORE/A.MAX_SCORE*100 
							ELSE 0 
						END 
						AND TEST_ID = A.TEST_ID
				)
			) ";

		if ($oPermParser->IsNeedCheckPerm())
			$strSql .= " AND C.LINKED_LESSON_ID IN (" . $oPermParser->SQLForAccessibleLessons() . ") ";

		$strSql .= $strSqlSearch;

		return ($strSql);
	}
}
