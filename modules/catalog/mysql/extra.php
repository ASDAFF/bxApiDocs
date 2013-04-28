<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/catalog/general/extra.php");


/**
 * 
 *
 *
 *
 *
 * @return mixed 
 *
 * @static
 * @link http://dev.1c-bitrix.ru/api_help/catalog/classes/cextra/index.php
 * @author Bitrix
 */
class CExtra extends CAllExtra
{
	
	/**
	 * <p>Добавляет новую запись в таблицу наценок</p>
	 *
	 *
	 *
	 *
	 * @param array $arFields  Ассоциативный массив параметров записи с ключами: <ul> <li>NAME -
	 * название наценки;</li> <li>PERCENTAGE - процент наценки (может быть как
	 * положительным, так и отрицательным)</li> </ul>
	 *
	 *
	 *
	 * @return bool <p>Возвращает <i>true</i> в случае успешного сохранения и <i>false</i> - в
	 * противном случае </p>
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/catalog/classes/cextra/cextra__add.937250e4.php
	 * @author Bitrix
	 */
	public static function Add($arFields)
	{
		global $DB;

		if (!CExtra::CheckFields('ADD', $arFields, 0))
			return false;

		$intID = '';
		if (!CExtra::PrepareInsert($arFields,$intID))
			return false;

		$res = $DB->Insert('b_catalog_extra', $arFields, "File: ". __FILE__ ."<br>Line: ". __LINE__,false,$intID);

		unset($GLOBALS["MAIN_EXTRA_LIST_CACHE"]);
		return $res;
	}

	
	/**
	 * <p>Метод возвращает список наценок в соответсвии с фильтром и условиями сортировки.</p>
	 *
	 *
	 *
	 *
	 * @param array $arOrder = array() Массив вида array(by1=&gt;order1[, by2=&gt;order2 [, ..]]), где by - поле для сортировки,
	 * может принимать значения: <ul> <li> <b>ID</b> - код (ID) наценки</li> <li> <b>NAME</b> -
	 * название наценки</li> <li> <b>PERCENTAGE</b> - величина наценки</li> </ul> поле order
	 * - направление сортировки, может принимать значения: <ul> <li> <b>asc</b> -
	 * по возрастанию</li> <li> <b>desc</b> - по убыванию</li> </ul> Необязательный. По
	 * умолчанию данные не сортируются.
	 *
	 *
	 *
	 * @param array $arFilter = array() Массив параметров, по которым строится фильтр выборки. Имеет вид:
	 * <pre class="syntax">array( "[модификатор1][оператор1]название_поля1" =&gt;
	 * "значение1", "[модификатор2][оператор2]название_поля2" =&gt; "значение2",
	 * . . . )</pre> Удовлетворяющие фильтру записи возвращаются в
	 * результате, а записи, которые не удовлетворяют условиям фильтра,
	 * отбрасываются. <br> Допустимыми являются следующие модификаторы:
	 * <ul> <li> <b>!</b> - отрицание;</li> <li> <b>+</b> - значения null, 0 и пустая строка
	 * так же удовлетворяют условиям фильтра.</li> </ul> Допустимыми
	 * являются следующие операторы: <ul> <li> <b>&gt;=</b> - значение поля больше
	 * или равно передаваемой в фильтр величины;</li> <li> <b>&gt;</b> - значение
	 * поля строго больше передаваемой в фильтр величины;</li> <li> <b>&lt;=</b> -
	 * значение поля меньше или равно передаваемой в фильтр величины;</li>
	 * <li> <b>&lt;</b> - значение поля строго меньше передаваемой в фильтр
	 * величины;</li> <li> <b>@</b> - значение поля находится в передаваемом в
	 * фильтр разделенном запятой списке значений;</li> <li> <b>~</b> - значение
	 * поля проверяется на соответствие передаваемому в фильтр
	 * шаблону;</li> <li> <b>%</b> - значение поля проверяется на соответствие
	 * передаваемой в фильтр строке в соответствии с языком запросов.</li>
	 * </ul> "название поля" может принимать значения: <ul> <li> <b>ID</b> - код (ID)
	 * наценки (число)</li> <li> <b>NAME</b> - название наценки (строка)</li> <li>
	 * <b>PERCENTAGE</b> - величина наценки (число)</li> </ul> Значения фильтра -
	 * одиночное значение или массив значений. <br> Необязательное. По
	 * умолчанию наценки не фильтруются.
	 *
	 *
	 *
	 * @param mixed $arGroupBy = false Массив полей для группировки наценок. имеет вид: <pre
	 * class="syntax">array("название_поля1", "группирующая_функция2" =&gt;
	 * "название_поля2", . . .)</pre> В качестве "название_поля<i>N</i>" может
	 * стоять любое поле каталога. В качестве группирующей функции
	 * могут стоять: <ul> <li> <b>COUNT</b> - подсчет количества;</li> <li> <b>AVG</b> -
	 * вычисление среднего значения;</li> <li> <b>MIN</b> - вычисление
	 * минимального значения;</li> <li> <b>MAX</b> - вычисление максимального
	 * значения;</li> <li> <b>SUM</b> - вычисление суммы.</li> </ul> Если массив пустой,
	 * то функция вернет число записей, удовлетворяющих фильтру. <br>
	 * Значение по умолчанию - <i>false</i> - означает, что результат
	 * группироваться не будет.
	 *
	 *
	 *
	 * @param mixed $arNavStartParams = false Массив параметров выборки. Может содержать следующие ключи: <ul>
	 * <li>"<b>nTopCount</b>" - количество возвращаемых функцией записей будет
	 * ограничено сверху значением этого ключа;</li> <li>любой ключ,
	 * принимаемый методом <b> CDBResult::NavQuery</b> в качестве третьего
	 * параметра.</li> </ul> Необязательный. По умолчанию false - наценки не
	 * ограничиваются.
	 *
	 *
	 *
	 * @param array $arSelectFields = array() Массив полей записей, которые будут возвращены методом. Можно
	 * указать только те поля, которые необходимы. Если в массиве
	 * присутствует значение "*", то будут возвращены все доступные поля.
	 * <br> Необязательный. По умолчанию выводятся все поля.
	 *
	 *
	 *
	 * @return CDBResult <p>Объект класса <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">Класс
	 * CDBResult</a>, содержащий ассоциативные массивы с
	 * ключами:</p><h4>Примечания</h4><p>Сохранен старый способ вызова:</p><pre
	 * class="syntax"><b>CDBResult CExtra::GetList(</b> string by, string order <b>);</b></pre><p>где by - поле
	 * сортировки, а order - направление.</p>
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/catalog/classes/cextra/getlist.php
	 * @author Bitrix
	 */
	public static function GetList($arOrder = array(), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array())
	{
		global $DB;

		// for old execution style
		if (!is_array($arOrder) && !is_array($arFilter))
		{
			$arOrder = strval($arOrder);
			$arFilter = strval($arFilter);
			if (!empty($arOrder) && !empty($arFilter))
				$arOrder = array($arOrder => $arFilter);
			else
				$arOrder = array();
			$arFilter = array();
			$arGroupBy = false;
		}

		if (empty($arSelectFields))
			$arSelectFields = array("ID", "NAME", "PERCENTAGE");

		$arFields = array(
			"ID" => array("FIELD" => "E.ID", "TYPE" => "int"),
			"NAME" => array("FIELD" => "E.NAME", "TYPE" => "string"),
			"PERCENTAGE" => array("FIELD" => "E.PERCENTAGE", "TYPE" => "double"),
		);

		$arSqls = CCatalog::PrepareSql($arFields, $arOrder, $arFilter, $arGroupBy, $arSelectFields);

		$arSqls["SELECT"] = str_replace("%%_DISTINCT_%%", "", $arSqls["SELECT"]);

		if (is_array($arGroupBy) && count($arGroupBy)==0)
		{
			$strSql =
				"SELECT ".$arSqls["SELECT"]." ".
				"FROM b_catalog_extra E ".
				"	".$arSqls["FROM"]." ";
			if (strlen($arSqls["WHERE"]) > 0)
				$strSql .= "WHERE ".$arSqls["WHERE"]." ";
			if (strlen($arSqls["GROUPBY"]) > 0)
				$strSql .= "GROUP BY ".$arSqls["GROUPBY"]." ";

			$dbRes = $DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			if ($arRes = $dbRes->Fetch())
				return $arRes["CNT"];
			else
				return false;
		}

		$strSql =
			"SELECT ".$arSqls["SELECT"]." ".
			"FROM b_catalog_extra E ".
			"	".$arSqls["FROM"]." ";
		if (strlen($arSqls["WHERE"]) > 0)
			$strSql .= "WHERE ".$arSqls["WHERE"]." ";
		if (strlen($arSqls["GROUPBY"]) > 0)
			$strSql .= "GROUP BY ".$arSqls["GROUPBY"]." ";
		if (strlen($arSqls["ORDERBY"]) > 0)
			$strSql .= "ORDER BY ".$arSqls["ORDERBY"]." ";

		if (is_array($arNavStartParams) && IntVal($arNavStartParams["nTopCount"])<=0)
		{
			$strSql_tmp =
				"SELECT COUNT('x') as CNT ".
				"FROM b_catalog_extra E ".
				"	".$arSqls["FROM"]." ";
			if (strlen($arSqls["WHERE"]) > 0)
				$strSql_tmp .= "WHERE ".$arSqls["WHERE"]." ";
			if (strlen($arSqls["GROUPBY"]) > 0)
				$strSql_tmp .= "GROUP BY ".$arSqls["GROUPBY"]." ";

			$dbRes = $DB->Query($strSql_tmp, false, "File: ".__FILE__."<br>Line: ".__LINE__);
			$cnt = 0;
			if (strlen($arSqls["GROUPBY"]) <= 0)
			{
				if ($arRes = $dbRes->Fetch())
					$cnt = $arRes["CNT"];
			}
			else
			{
				$cnt = $dbRes->SelectedRowsCount();
			}

			$dbRes = new CDBResult();

			$dbRes->NavQuery($strSql, $cnt, $arNavStartParams);
		}
		else
		{
			if (is_array($arNavStartParams) && IntVal($arNavStartParams["nTopCount"])>0)
				$strSql .= "LIMIT ".intval($arNavStartParams["nTopCount"]);

			$dbRes = $DB->Query($strSql, false, "File: ".__FILE__."<br>Line: ".__LINE__);
		}
		return $dbRes;
	}
}
?>