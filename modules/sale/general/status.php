<?
IncludeModuleLangFile(__FILE__);
$GLOBALS["SALE_STATUS"] = Array();


/**
 * 
 *
 *
 *
 *
 * @return mixed 
 *
 * @static
 * @link http://dev.1c-bitrix.ru/api_help/sale/classes/csalestatus/index.php
 * @author Bitrix
 */
class CAllSaleStatus
{
	public static function GetLangByID($ID, $strLang = LANGUAGE_ID)
	{
		global $DB;
		$ID = $DB->ForSql($ID, 1);
		$strLang = $DB->ForSql($strLang, 2);
		
		if (isset($GLOBALS["SALE_STATUS"]["SALE_STATUS_LANG_CACHE_".$ID."_".$strLang]) && is_array($GLOBALS["SALE_STATUS"]["SALE_STATUS_LANG_CACHE_".$ID."_".$strLang]) && is_set($GLOBALS["SALE_STATUS"]["SALE_STATUS_LANG_CACHE_".$ID."_".$strLang], "STATUS_ID"))
		{
			return $GLOBALS["SALE_STATUS"]["SALE_STATUS_LANG_CACHE_".$ID."_".$strLang];
		}
		else
		{
			$strSql =
				"SELECT * ".
				"FROM b_sale_status_lang ".
				"WHERE STATUS_ID = '".$ID."' ".
				"	AND LID = '".$strLang."' ";
			$db_res = $DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);

			if ($res = $db_res->Fetch())
			{
				$GLOBALS["SALE_STATUS"]["SALE_STATUS_LANG_CACHE_".$ID."_".$strLang] = $res;
				return $res;
			}
		}
		return False;
	}


	public static function CheckFields($ACTION, &$arFields, $ID = "")
	{
		global $DB;

		if ((is_set($arFields, "SORT") || $ACTION=="ADD") && IntVal($arFields["SORT"])<="Y") $arFields["SORT"] = 100;
		if ((is_set($arFields, "ID") || $ACTION=="ADD") && strlen($arFields["ID"])<=0) return false;

		if (is_set($arFields, "ID") && strlen($ID)>0 && $ID!=$arFields["ID"]) return false;
		
		if((is_set($arFields, "ID") && !preg_match("#[A-Za-z]#i", $arFields["ID"])) || (strlen($ID)>0 && !preg_match("#[A-Za-z]#i", $ID)))
		{
			$GLOBALS["APPLICATION"]->ThrowException(GetMessage("SKGS_ID_NOT_SYMBOL"), "ERROR_ID_NOT_SYMBOL");
			return false;
		}

		if ($ACTION=="ADD")
		{
			$arFields["ID"] = $DB->ForSql($arFields["ID"], 1);
			$db_res = $DB->Query("SELECT ID FROM b_sale_status WHERE ID = '".$arFields["ID"]."' ");
			if ($db_res->Fetch()) return false;
		}

		if (is_set($arFields, "LANG"))
		{
			$db_lang = CLangAdmin::GetList(($b="sort"), ($o="asc"), array("ACTIVE" => "Y"));
			while ($arLang = $db_lang->Fetch())
			{
				$bFound = False;
				for ($i = 0; $i<count($arFields["LANG"]); $i++)
				{
					if ($arFields["LANG"][$i]["LID"]==$arLang["LID"] && strlen($arFields["LANG"][$i]["NAME"])>0)
					{
						$bFound = True;
					}
				}
				if (!$bFound) return false;
			}
		}

		return True;
	}

	
	/**
	 * <p>Функция добавляет новый статус заказа с параметрами из массива arFields </p>
	 *
	 *
	 *
	 *
	 * @param array $arFields  Ассоциативный массив параметров нового статуса. Ключами в
	 * массиве являются названия параметров статуса, а значениями -
	 * соответствующие значения.<br> Допустимые ключи: <ul> <li> <b>ID</b> - код
	 * статуса (обязательный), состоит из одной буквы;</li> <li> <b>SORT</b> -
	 * индекс сортировки;</li> <li> <b>LANG</b> - массив ассоциативных массивов
	 * языкозависимых параметров статуса с ключами: <ul> <li> <b>LID</b> -
	 * язык;</li> <li> <b>NAME</b> - название статуса на этом языке;</li> <li> <b>DESCRIPTION</b>
	 * - описание статуса;</li> </ul> </li> <li> <b>PERMS</b> - массив ассоциативных
	 * массивов прав на доступ к изменению заказа в данном статусе с
	 * ключами: <ul> <li> <b>GROUP_ID</b> - группа пользователей;</li> <li> <b>PERM_TYPE</b> - тип
	 * доступа (S - разрешен перевод заказа в данный статус, M - разрешено
	 * изменение заказа в данном статусе).</li> </ul> </li> </ul>
	 *
	 *
	 *
	 * @return string <p>Возвращается код добавленного статуса или <i>false</i> в случае
	 * ошибки.</p>
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/sale/classes/csalestatus/csalestatus__add.c7ce74b1.php
	 * @author Bitrix
	 */
	public static function Add($arFields)
	{
		global $DB;

		if (!CSaleStatus::CheckFields("ADD", $arFields))
			return false;

		$ID = $DB->ForSql($arFields["ID"], 1);

		$db_events = GetModuleEvents("sale", "OnBeforeStatusAdd");
		while ($arEvent = $db_events->Fetch())
			if (ExecuteModuleEventEx($arEvent, Array($ID, &$arFields))===false)
				return false;

		$arInsert = $DB->PrepareInsert("b_sale_status", $arFields);
		$strSql =
			"INSERT INTO b_sale_status(".$arInsert[0].") ".
			"VALUES(".$arInsert[1].")";
		$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);

		for ($i = 0; $i < count($arFields["LANG"]); $i++)
		{
			$arInsert = $DB->PrepareInsert("b_sale_status_lang", $arFields["LANG"][$i]);
			$strSql =
				"INSERT INTO b_sale_status_lang(STATUS_ID, ".$arInsert[0].") ".
				"VALUES('".$ID."', ".$arInsert[1].")";
			$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
		}

		if (isset($arFields["PERMS"]) && is_array($arFields["PERMS"]))
		{
			for ($i = 0; $i < count($arFields["PERMS"]); $i++)
			{
				$arInsert = $DB->PrepareInsert("b_sale_status2group", $arFields["PERMS"][$i]);
				$strSql =
					"INSERT INTO b_sale_status2group(STATUS_ID, ".$arInsert[0].") ".
					"VALUES('".$ID."', ".$arInsert[1].")";
				$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			}
		}

		$events = GetModuleEvents("sale", "OnStatusAdd");
		while ($arEvent = $events->Fetch())
			ExecuteModuleEventEx($arEvent, Array($ID, $arFields));

		return $ID;
	}

	
	/**
	 * <p>Функция изменяет параметры статуса заказа с кодом ID </p>
	 *
	 *
	 *
	 *
	 * @param string $ID  Код статуса.
	 *
	 *
	 *
	 * @param array $arFields  Ассоциативный массив новых параметров статуса. Ключами в массиве
	 * являются названия параметров статуса, а значениями -
	 * соответствующие значения.<br> Допустимые ключи: <ul> <li> <b>ID</b> - код
	 * статуса (обязательный);</li> <li> <b>SORT</b> - индекс сортировки;</li> <li>
	 * <b>LANG</b> - массив ассоциативных массивов языкозависимых параметров
	 * статуса с ключами: <ul> <li> <b>LID</b> - язык;</li> <li> <b>NAME</b> - название
	 * статуса на этом языке;</li> <li> <b>DESCRIPTION</b> - описание статуса;</li> </ul>
	 * </li> <li> <b>PERMS</b> - массив ассоциативных массивов прав на доступ к
	 * изменению заказа в данном статусе с ключами: <ul> <li> <b>GROUP_ID</b> -
	 * группа пользователей;</li> <li> <b>PERM_TYPE</b> - тип доступа (S - разрешен
	 * перевод заказа в данный статус, M - разрешено изменение заказа в
	 * данном статусе).</li> </ul> </li> </ul>
	 *
	 *
	 *
	 * @return string <p>Возвращается код добавленного статуса или <i>false</i> в случае
	 * ошибки.</p>
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/sale/classes/csalestatus/csalestatus__update.145077bd.php
	 * @author Bitrix
	 */
	public static function Update($ID, $arFields)
	{
		global $DB;

		$ID = $DB->ForSql($ID, 1);
		if (!CSaleStatus::CheckFields("UPDATE", $arFields, $ID))
			return false;

		$db_events = GetModuleEvents("sale", "OnBeforeStatusUpdate");
		while ($arEvent = $db_events->Fetch())
			if (ExecuteModuleEventEx($arEvent, Array($ID, &$arFields))===false)
				return false;

		$strUpdate = $DB->PrepareUpdate("b_sale_status", $arFields);
		$strSql = "UPDATE b_sale_status SET ".$strUpdate." WHERE ID = '".$ID."' ";
		$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);

		if (is_set($arFields, "LANG"))
		{
			$DB->Query("DELETE FROM b_sale_status_lang WHERE STATUS_ID = '".$ID."'");

			for ($i = 0; $i<count($arFields["LANG"]); $i++)
			{
				$arInsert = $DB->PrepareInsert("b_sale_status_lang", $arFields["LANG"][$i]);
				$strSql =
					"INSERT INTO b_sale_status_lang(STATUS_ID, ".$arInsert[0].") ".
					"VALUES('".$ID."', ".$arInsert[1].")";
				$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			}
		}

		if (isset($arFields["PERMS"]) && is_array($arFields["PERMS"]))
		{
			$DB->Query("DELETE FROM b_sale_status2group WHERE STATUS_ID = '".$ID."'");

			for ($i = 0; $i < count($arFields["PERMS"]); $i++)
			{
				$arInsert = $DB->PrepareInsert("b_sale_status2group", $arFields["PERMS"][$i]);
				$strSql =
					"INSERT INTO b_sale_status2group(STATUS_ID, ".$arInsert[0].") ".
					"VALUES('".$ID."', ".$arInsert[1].")";
				$DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			}
		}

		$events = GetModuleEvents("sale", "OnStatusUpdate");
		while ($arEvent = $events->Fetch())
			ExecuteModuleEventEx($arEvent, Array($ID, $arFields));

		return $ID;
	}

	
	/**
	 * <p>Функция удаляет статус заказа с кодом ID. Если в базе есть заказы, находящиеся в этом статусе, то этот статус удалить нельзя. </p>
	 *
	 *
	 *
	 *
	 * @param string $ID  Код статуса заказа.
	 *
	 *
	 *
	 * @return bool <p>Возвращается <i>true</i> в случае успешного удаления и <i>false</i> - в
	 * противном случае.</p>
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/sale/classes/csalestatus/csalestatus__delete.11104aab.php
	 * @author Bitrix
	 */
	public static function Delete($ID)
	{
		global $DB;
		$ID = $DB->ForSql($ID, 1);

		$db_res = $DB->Query("SELECT ID FROM b_sale_order WHERE STATUS_ID = '".$ID."'");
		if ($db_res->Fetch())
		{
			$GLOBALS["APPLICATION"]->ThrowException(GetMessage("SKGS_ERROR_DELETE"), "ERROR_DELETE_STATUS_TO_ORDER");
			return false;
		}

		$db_events = GetModuleEvents("sale", "OnBeforeStatusDelete");
		while ($arEvent = $db_events->Fetch())
			if (ExecuteModuleEventEx($arEvent, Array($ID))===false)
				return false;

		$events = GetModuleEvents("sale", "OnStatusDelete");
		while ($arEvent = $events->Fetch())
			ExecuteModuleEventEx($arEvent, Array($ID));

		$DB->Query("DELETE FROM b_sale_status2group WHERE STATUS_ID = '".$ID."'", true);
		$DB->Query("DELETE FROM b_sale_status_lang WHERE STATUS_ID = '".$ID."'", true);
		return $DB->Query("DELETE FROM b_sale_status WHERE ID = '".$ID."'", true);
	}

	public static function CreateMailTemplate($ID)
	{
		$ID = Trim($ID);

		if (strlen($ID) <= 0)
			return False;

		if (!($arStatus = CSaleStatus::GetByID($ID, LANGUAGE_ID)))
			return False;

		$eventType = new CEventType;
		$eventMessage = new CEventMessage;

		$eventType->Delete("SALE_STATUS_CHANGED_".$ID);

		$dbSiteList = CSite::GetList(($b = ""), ($o = ""));
		while ($arSiteList = $dbSiteList->Fetch())
		{
			IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/sale/general/status.php", $arSiteList["LANGUAGE_ID"]);
			$arStatusLang = CSaleStatus::GetLangByID($ID, $arSiteList["LANGUAGE_ID"]);

			$dbEventType = $eventType->GetList(
					array(
							"EVENT_NAME" => "SALE_STATUS_CHANGED_".$ID,
							"LID" => $arSiteList["LANGUAGE_ID"]
						)
				);
			if (!($arEventType = $dbEventType->Fetch()))
			{
				$str  = "";
				$str .= "#ORDER_ID# - ".GetMessage("SKGS_ORDER_ID")."\n";
				$str .= "#ORDER_DATE# - ".GetMessage("SKGS_ORDER_DATE")."\n";
				$str .= "#ORDER_STATUS# - ".GetMessage("SKGS_ORDER_STATUS")."\n";
				$str .= "#EMAIL# - ".GetMessage("SKGS_ORDER_EMAIL")."\n";
				$str .= "#ORDER_DESCRIPTION# - ".GetMessage("SKGS_STATUS_DESCR")."\n";
				$str .= "#TEXT# - ".GetMessage("SKGS_STATUS_TEXT")."\n";
				$str .= "#SALE_EMAIL# - ".GetMessage("SKGS_SALE_EMAIL")."\n";

				$eventTypeID = $eventType->Add(
						array(
								"LID" => $arSiteList["LANGUAGE_ID"],
								"EVENT_NAME" => "SALE_STATUS_CHANGED_".$ID,
								"NAME" => GetMessage("SKGS_CHANGING_STATUS_TO")." \"".$arStatusLang["NAME"]."\"",
								"DESCRIPTION" => $str
							)
					);
			}

			$dbEventMessage = $eventMessage->GetList(
					($b = ""),
					($o = ""),
					array(
							"EVENT_NAME" => "SALE_STATUS_CHANGED_".$ID,
							"SITE_ID" => $arSiteList["LID"]
						)
				);
			if (!($arEventMessage = $dbEventMessage->Fetch()))
			{
				$subject = GetMessage("SKGS_STATUS_MAIL_SUBJ");

				$message  = GetMessage("SKGS_STATUS_MAIL_BODY1");
				$message .= "------------------------------------------\n\n";
				$message .= GetMessage("SKGS_STATUS_MAIL_BODY2");
				$message .= GetMessage("SKGS_STATUS_MAIL_BODY3");
				$message .= "#ORDER_STATUS#\n";
				$message .= "#ORDER_DESCRIPTION#\n";
				$message .= "#TEXT#\n\n";
				$message .= "#SITE_NAME#\n";

				$arFields = Array(
						"ACTIVE" => "Y",
						"EVENT_NAME" => "SALE_STATUS_CHANGED_".$ID,
						"LID" => $arSiteList["LID"],
						"EMAIL_FROM" => "#SALE_EMAIL#",
						"EMAIL_TO" => "#EMAIL#",
						"SUBJECT" => $subject,
						"MESSAGE" => $message,
						"BODY_TYPE" => "text"
					);
				$eventMessageID = $eventMessage->Add($arFields);
			}
		}

		return True;
	}
}
?>