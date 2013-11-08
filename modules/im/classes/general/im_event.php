<?
IncludeModuleLangFile(__FILE__);

class CIMEvent
{
	public static function OnAddRatingVote($id, $arParams)
	{
		if (CModule::IncludeModule("socialnetwork"))
		{
			$followValue = CSocNetLogFollow::GetExactValueByRating(
				intval($arParams["OWNER_ID"]),
				trim($arParams["ENTITY_TYPE_ID"]),
				intval($arParams["ENTITY_ID"])
			);
			if ($followValue === "N")
				return false;
		}
		if ($arParams['ENTITY_TYPE_ID'] == 'LOG_COMMENT')
		{
			if ($arComment = CSocNetLogComments::GetByID($arParams['ENTITY_ID']))
			{
				if ($arComment['USER_ID'] == $arParams['USER_ID'])
					return false;

				$arEventTmp = CSocNetLogTools::FindLogCommentEventByID($arComment["EVENT_ID"]);
				if (
					$arEventTmp
					&& array_key_exists("CLASS_FORMAT", $arEventTmp)
					&& array_key_exists("METHOD_FORMAT", $arEventTmp)
				)
				{
					$arFIELDS_FORMATTED = call_user_func(array($arEventTmp["CLASS_FORMAT"], $arEventTmp["METHOD_FORMAT"]), $arComment, array());
					$arParams["ENTITY_TITLE"] = strip_tags(str_replace(array("<br>","<br/>","<br />", "#BR#"), Array(" "," ", " ", " "), htmlspecialcharsback($arFIELDS_FORMATTED["EVENT_FORMATTED"]["MESSAGE"])), '<br>');
				}
				else
					$arParams["ENTITY_TITLE"] = strip_tags(str_replace(array("<br>","<br/>","<br />", "#BR#"), Array(" "," ", " ", " "), $arComment["MESSAGE"]), '<br>');

				$arParams['ENTITY_LINK'] = '';

				$arMessageFields = array(
					"MESSAGE_TYPE" => IM_MESSAGE_SYSTEM,
					"TO_USER_ID" => intval($arComment['USER_ID']),
					"FROM_USER_ID" => intval($arParams['USER_ID']),
					"NOTIFY_TYPE" => IM_NOTIFY_FROM,
					"NOTIFY_MODULE" => "main",
					"NOTIFY_EVENT" => "rating_vote",
					"NOTIFY_TAG" => "RATING|".($arParams['VALUE'] >= 0?"":"DL|").$arParams['ENTITY_TYPE_ID']."|".$arParams['ENTITY_ID'],
					"NOTIFY_MESSAGE" => self::GetMessageRatingVote($arParams),
					"NOTIFY_MESSAGE_OUT" => self::GetMessageRatingVote($arParams, true)
				);
				CIMNotify::Add($arMessageFields);
			}
		}
		else
		{
			if ($arParams['OWNER_ID'] == $arParams['USER_ID'])
				return false;

			if (!CModule::IncludeModule("search") || BX_SEARCH_VERSION <= 1)
				return false;

			$CSI = new CSearchItem;
			$res = $CSI->GetList(Array(), Array('=ENTITY_TYPE_ID' => $arParams['ENTITY_TYPE_ID'], '=ENTITY_ID' => $arParams['ENTITY_ID']), Array('URL', 'TITLE', 'BODY', 'PARAM1'));
			if ($arItem = $res->GetNext(true, false))
			{
				$arParams["ENTITY_LINK"] = $arItem['URL'];
				$arParams["ENTITY_PARAM"] = $arItem['PARAM1'];
				$arParams["ENTITY_TITLE"] = trim(strip_tags(str_replace(array("\r\n","\n","\r"), ' ', htmlspecialcharsback($arItem['TITLE']))));
				$arParams["ENTITY_MESSAGE"] = trim(strip_tags(str_replace(array("\r\n","\n","\r"), ' ', htmlspecialcharsback($arItem['BODY']))));

				if ((strlen($arParams["ENTITY_TITLE"]) > 0 || strlen($arParams["ENTITY_MESSAGE"]) > 0 ) && strlen($arParams["ENTITY_LINK"]) > 0)
				{
					if (CModule::IncludeModule("extranet"))
					{
						$arSites = array();
						$extranet_site_id = CExtranet::GetExtranetSiteID();
						$intranet_site_id = CSite::GetDefSite();
						$dbSite = CSite::GetList($by="sort", $order="desc", array("ACTIVE" => "Y"));
						while($arSite = $dbSite->Fetch())
						{
							$arSites[$arSite["ID"]] = array(
								"DIR" => (strlen(trim($arSite["DIR"])) > 0 ? $arSite["DIR"] : "/"),
								"SERVER_NAME" => (strlen(trim($arSite["SERVER_NAME"])) > 0 ? $arSite["SERVER_NAME"] : COption::GetOptionString("main", "server_name", $_SERVER["HTTP_HOST"]))
							);
						}

						$bExtranetUser = false;
						if ($arSites[$extranet_site_id])
						{
							$bExtranetUser = true;
							$rsUser = CUser::GetByID(intval($arParams['OWNER_ID']));
							if ($arUser = $rsUser->Fetch())
								if (intval($arUser["UF_DEPARTMENT"][0]) > 0)
									$bExtranetUser = false;
						}

						if ($bExtranetUser)
						{
							$link = $arParams['ENTITY_LINK'];
							if (substr($link, 0, strlen($arSites[$extranet_site_id]['DIR'])) == $arSites[$extranet_site_id]['DIR'])
								$link = substr($link, strlen($arSites[$extranet_site_id]['DIR']));

							$SiteServerName = $arSites[$extranet_site_id]['SERVER_NAME'].$arSites[$extranet_site_id]['DIR'].ltrim($link, "/");
						}
						else
						{
							$link = $arParams['ENTITY_LINK'];
							if (substr($link, 0, strlen($arSites[$intranet_site_id]['DIR'])) == $arSites[$intranet_site_id]['DIR'])
								$link = substr($link, strlen($arSites[$intranet_site_id]['DIR']));

							$SiteServerName = $arSites[$intranet_site_id]['SERVER_NAME'].$arSites[$intranet_site_id]['DIR'].ltrim($link, "/");
						}

						$arParams['ENTITY_LINK'] = (CMain::IsHTTPS() ? "https" : "http")."://".$SiteServerName;
					}
					else
					{
						if (defined('SITE_SERVER_NAME') && strlen(SITE_SERVER_NAME) > 0)
							$SiteServerName = SITE_SERVER_NAME;
						else
							$SiteServerName = COption::GetOptionString("main", "server_name", $GLOBALS["SERVER_NAME"]);

						if (strlen($SiteServerName) > 0)
							$arParams['ENTITY_LINK'] = (CMain::IsHTTPS() ? "https" : "http")."://".$SiteServerName.$arParams['ENTITY_LINK'];
					}

					$arMessageFields = array(
						"MESSAGE_TYPE" => IM_MESSAGE_SYSTEM,
						"TO_USER_ID" => intval($arParams['OWNER_ID']),
						"FROM_USER_ID" => intval($arParams['USER_ID']),
						"NOTIFY_TYPE" => IM_NOTIFY_FROM,
						"NOTIFY_MODULE" => "main",
						"NOTIFY_EVENT" => "rating_vote",
						"NOTIFY_TAG" => "RATING|".($arParams['VALUE'] >= 0?"":"DL|").$arParams['ENTITY_TYPE_ID']."|".$arParams['ENTITY_ID'],
						"NOTIFY_MESSAGE" => self::GetMessageRatingVote($arParams),
						"NOTIFY_MESSAGE_OUT" => self::GetMessageRatingVote($arParams, true)
					);
					CIMNotify::Add($arMessageFields);
				}
			}
		}
	}

	public static function OnCancelRatingVote($id, $arParams)
	{
		CIMNotify::DeleteByTag("RATING|".$arParams['ENTITY_TYPE_ID']."|".$arParams['ENTITY_ID'], $arParams['USER_ID']);
	}

	private static function GetMessageRatingVote($arParams, $bForMail = false)
	{
		$like = $arParams['VALUE'] >= 0? '_LIKE': '_DISLIKE';

		if ($arParams['ENTITY_TYPE_ID'] == 'FORUM_POST' || $arParams['ENTITY_TYPE_ID'] == 'BLOG_COMMENT')
		{
			$dot = strlen($arParams["ENTITY_MESSAGE"])>=200? '...': '';
			$arParams["ENTITY_MESSAGE"] = substr($arParams["ENTITY_MESSAGE"], 0, 199).$dot;
		}
		else
		{
			$dot = strlen($arParams["ENTITY_TITLE"])>=200? '...': '';
			$arParams["ENTITY_TITLE"] = substr($arParams["ENTITY_TITLE"], 0, 199).$dot;
		}

		if ($bForMail)
		{
			if ($arParams['ENTITY_TYPE_ID'] == 'BLOG_POST')
				$message = str_replace('#LINK#', $arParams["ENTITY_TITLE"], GetMessage('IM_EVENT_RATING_BLOG_POST'.$like).' ('.$arParams['ENTITY_LINK'].')');
			elseif ($arParams['ENTITY_TYPE_ID'] == 'BLOG_COMMENT')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_MESSAGE"], '', ''), GetMessage('IM_EVENT_RATING_COMMENT'.$like).' ('.$arParams['ENTITY_LINK'].')');
			elseif ($arParams['ENTITY_TYPE_ID'] == 'FORUM_TOPIC')
				$message = str_replace('#LINK#', $arParams["ENTITY_TITLE"], GetMessage('IM_EVENT_RATING_FORUM_TOPIC'.$like).' ('.$arParams['ENTITY_LINK'].')');
			elseif ($arParams['ENTITY_TYPE_ID'] == 'FORUM_POST')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_MESSAGE"], '', ''), GetMessage('IM_EVENT_RATING_COMMENT'.$like).' ('.$arParams['ENTITY_LINK'].')');
			elseif ($arParams['ENTITY_TYPE_ID'] == 'IBLOCK_ELEMENT' && $arParams['ENTITY_PARAM'] == 'library')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_TITLE"], '', ''), GetMessage('IM_EVENT_RATING_FILE'.$like).' ('.$arParams['ENTITY_LINK'].')');
			elseif ($arParams['ENTITY_TYPE_ID'] == 'IBLOCK_ELEMENT' && $arParams['ENTITY_PARAM'] == 'photos')
			{
				if (is_numeric($arParams["ENTITY_TITLE"]))
					$message = str_replace(Array('#A_START#', '#A_END#'), Array('', ''), GetMessage('IM_EVENT_RATING_PHOTO1'.$like).' ('.$arParams['ENTITY_LINK'].')');
				else
					$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_TITLE"], '', ''), GetMessage('IM_EVENT_RATING_PHOTO'.$like).' ('.$arParams['ENTITY_LINK'].')');
			}
			else
				$message = str_replace('#LINK#', $arParams["ENTITY_TITLE"], GetMessage('IM_EVENT_RATING_ELSE'.$like).strlen($arParams['ENTITY_LINK'])>0?' ('.$arParams['ENTITY_LINK'].')': '');
		}
		else
		{
			if ($arParams['ENTITY_TYPE_ID'] == 'BLOG_POST')
				$message = str_replace('#LINK#', '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">'.$arParams["ENTITY_TITLE"].'</a>', GetMessage('IM_EVENT_RATING_BLOG_POST'.$like));
			elseif ($arParams['ENTITY_TYPE_ID'] == 'BLOG_COMMENT')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_MESSAGE"], '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">', '</a>'), GetMessage('IM_EVENT_RATING_COMMENT'.$like));
			elseif ($arParams['ENTITY_TYPE_ID'] == 'FORUM_TOPIC')
				$message = str_replace('#LINK#', '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">'.$arParams["ENTITY_TITLE"].'</a>', GetMessage('IM_EVENT_RATING_FORUM_TOPIC'.$like));
			elseif ($arParams['ENTITY_TYPE_ID'] == 'FORUM_POST')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_MESSAGE"], '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">', '</a>'), GetMessage('IM_EVENT_RATING_COMMENT'.$like));
			elseif ($arParams['ENTITY_TYPE_ID'] == 'IBLOCK_ELEMENT' && $arParams['ENTITY_PARAM'] == 'library')
				$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_TITLE"], '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">', '</a>'), GetMessage('IM_EVENT_RATING_FILE'.$like));
			elseif ($arParams['ENTITY_TYPE_ID'] == 'IBLOCK_ELEMENT' && $arParams['ENTITY_PARAM'] == 'photos')
			{
				if (is_numeric($arParams["ENTITY_TITLE"]))
					$message = str_replace(Array('#A_START#', '#A_END#'), Array('<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">', '</a>'), GetMessage('IM_EVENT_RATING_PHOTO1'.$like));
				else
					$message = str_replace(Array('#TITLE#', '#A_START#', '#A_END#'), Array($arParams["ENTITY_TITLE"], '<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">', '</a>'), GetMessage('IM_EVENT_RATING_PHOTO'.$like));
			}
			else
				$message = str_replace('#LINK#', strlen($arParams['ENTITY_LINK'])>0?'<a href="'.$arParams['ENTITY_LINK'].'" class="bx-notifier-item-action">'.$arParams["ENTITY_TITLE"].'</a>': '<i>'.$arParams["ENTITY_TITLE"].'</i>', GetMessage('IM_EVENT_RATING_ELSE'.$like));

		}

		return $message;
	}

	public static function OnUserDelete($ID)
	{
		$ID = intval($ID);
		if ($ID <= 0)
			return false;

		global $DB;

		$arChat = Array();
		$strSQL = "SELECT R.CHAT_ID FROM b_im_chat C, b_im_relation R WHERE R.USER_ID = ".$ID." and R.MESSAGE_TYPE IN ('".IM_MESSAGE_PRIVATE."', '".IM_MESSAGE_SYSTEM."') and R.CHAT_ID = C.ID";
		$dbRes = $DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);
		while ($arRes = $dbRes->Fetch())
			$arChat[$arRes['CHAT_ID']] = $arRes['CHAT_ID'];

		if (count($arChat) > 0)
		{
			$strSQL = "DELETE FROM b_im_chat WHERE ID IN (".implode(',', $arChat).")";
			$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);
		}

		$strSQL = "DELETE FROM b_im_message WHERE AUTHOR_ID = ".$ID;
		$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);

		$strSQL = "DELETE FROM b_im_relation WHERE AUTHOR_ID =".$ID;
		$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);

		$strSQL = "DELETE FROM b_im_recent WHERE USER_ID = ".$ID;
		$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);

		$strSQL = "DELETE FROM b_im_recent WHERE ITEM_TYPE = 'P' and ITEM_ID = ".$ID;
		$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);

		$strSQL = "DELETE FROM b_im_status WHERE USER_ID = ".$ID;
		$DB->Query($strSQL, true, "File: ".__FILE__."<br>Line: ".__LINE__);

		$obCache = new CPHPCache();
		$obCache->CleanDir('/bx/imc/rec');

		return true;
	}

	public static function OnAfterUserUpdate($arParams)
	{
		if ($arParams['ACTIVE'] == 'N')
			CIMMessage::SetReadMessageAll($arParams['ID']);
	}

	public static function OnGetDependentModule()
	{
		return Array(
			'MODULE_ID' => "im",
			'USE' => Array("PUBLIC_SECTION")
		);
	}
}

?>