<?php
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage main
 * @copyright 2001-2013 Bitrix
 */

IncludeModuleLangFile(__FILE__);

class CAllFile
{
	
	/**
	 * <p>Функция предназначена для подготовки данных перед вставкой/обновлением записи в БД, содержащей ссылку на файл. Функция сохраненяет файл на диск и возвращает во входном массиве ID сохраненного файла для последующей вставки/обновления записи в БД.</p> <p><b>Примечание</b>: до версии 7.1.0 аналогичные действия выполняли функции CDatabase::PrepareInsert и CDatabse::PrepareUpdate. Начиная с версии 7.1.0 действия по сохранению файлов и вставки записи в БД разнесены по разным функциям.</p>
	 *
	 *
	 *
	 *
	 * @param array &$arFields  <p>Массив значений полей в формате "имя поля1"=&gt;"значение1", "имя
	 * поля2"=&gt;"значение2" [, ...]. <br> При вставке файла, привязанного к
	 * записи, "значение" должно быть представлено в виде массива <br> Array(
	 * <br> "name" =&gt; "название файла", <br> "size" =&gt; "размер", <br> "tmp_name" =&gt;
	 * "временный путь на сервере", <br> "type" =&gt; "тип загружаемого файла", <br>
	 *   "del" =&gt; "флажок, удалить ли существующий файл (непустая строка)",  
	 * <br> "MODULE_ID" =&gt; "название модуля"); <br><br> Массив такого вида может быть
	 * получен, например, объединением массивов $HTTP_POST_FILES[имя поля] и
	 * Array("del" =&gt; ${"имя поля"."_del"}, "MODULE_ID" = "название модуля"); </p> <p>Значение
	 * передается по ссылке.</p>
	 *
	 *
	 *
	 * @param string $field  Название поля в массиве <em>arFields</em>, где содержится файл.
	 *
	 *
	 *
	 * @param string $dir  Имя папки (будет находится внутри папки upload) для хранения файлов.
	 *
	 *
	 *
	 * @return bool <p><em>True</em> в случае удачи, <em>false</em> при неудаче. В значении элемента
	 * массива <em>$arFields[$field]</em> будет возвращен ID сохраненного файла из
	 * таблицы b_file. Если файл был удален, в этом поле будет возвращено
	 * <em>false</em>. При неудаче элемент массива <em>$arFields[$field]</em> будет удален
	 * (unset).</p><a name="examples"></a>
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?<br>$arFields["ATTACH_IMG"] = $_FILE["ATTACH_IMG"];<br>$arFields["ATTACH_IMG"]["MODULE_ID"] = "blog";<br><br>CFile::SaveForDB($arFields, "ATTACH_IMG", "blog");<br>$arInsert = $DB-&gt;PrepareInsert("b_blog_post", $arFields);<br><br>?&gt;
	 * </pre>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/savefordb.php
	 * @author Bitrix
	 */
	public static function SaveForDB(&$arFields, $field, $strSavePath)
	{
		$arFile = $arFields[$field];
		if(isset($arFile) && is_array($arFile))
		{
			if($arFile["name"] <> '' || $arFile["del"] <> '' || array_key_exists("description", $arFile))
			{
				$res = CFile::SaveFile($arFile, $strSavePath);
				if($res !== false)
				{
					$arFields[$field] = (intval($res) > 0? $res : false);
					return true;
				}
			}

		}
		unset($arFields[$field]);
		return false;
	}

	static public function checkForDb($arFields, $field)
	{
		if(isset($arFields[$field]) && is_array($arFields[$field]))
		{
			return self::validateFile($dummy, $arFields[$field]);
		}
		else
		{
			return "";
		}
	}

	protected function validateFile(&$strFileName, $arFile, $bForceMD5=false)
	{
		$strFileName = GetFileName($arFile["name"]);

		//File is going to be deleted
		if(isset($arFile["del"]) && $arFile["del"] <> "")
		{
			//There is no new file as replacement
			if($strFileName == "")
				return "";
		}

		if($arFile["name"] == "")
			return "";

		if (COption::GetOptionInt("main", "disk_space") > 0)
		{
			$quota = new CDiskQuota();
			if (!$quota->checkDiskQuota($arFile))
				return GetMessage("FILE_BAD_QUOTA");
		}

		$io = CBXVirtualIo::GetInstance();
		if($bForceMD5 != true && COption::GetOptionString("main", "save_original_file_name", "N") == "Y")
		{
			if(COption::GetOptionString("main", "translit_original_file_name", "N") == "Y")
				$strFileName = CUtil::translit($strFileName, LANGUAGE_ID, array("max_len"=>1024, "safe_chars"=>".", "replace_space" => '-'));

			if(COption::GetOptionString("main", "convert_original_file_name", "Y") == "Y")
				$strFileName = $io->RandomizeInvalidFilename($strFileName);
		}

		if(!$io->ValidateFilenameString($strFileName))
			return GetMessage("MAIN_BAD_FILENAME1");

		//check for double extension vulnerability
		$strFileName = RemoveScriptExtension($strFileName);
		if($strFileName == '')
			return GetMessage("FILE_BAD_FILENAME");

		if(strlen($strFileName) > 255)
			return GetMessage("MAIN_BAD_FILENAME_LEN");

		//check .htaccess etc.
		if(IsFileUnsafe($strFileName))
			return GetMessage("FILE_BAD_TYPE");

		//nginx returns octet-stream for .jpg
		if(GetFileNameWithoutExtension($strFileName) == '')
			return GetMessage("FILE_BAD_FILENAME");

		return "";
	}

	public static function SaveFile($arFile, $strSavePath, $bForceMD5=false, $bSkipExt=false)
	{
		$strFileName = GetFileName($arFile["name"]);	/* filename.gif */

		if(isset($arFile["del"]) && $arFile["del"] <> '')
		{
			CFile::DoDelete($arFile["old_file"]);
			if($strFileName == '')
				return "NULL";
		}

		if($arFile["name"] == '')
		{
			if(isset($arFile["description"]) && intval($arFile["old_file"])>0)
			{
				CFile::UpdateDesc($arFile["old_file"], $arFile["description"]);
			}
			return false;
		}

		if (array_key_exists("content", $arFile))
		{
			if (!array_key_exists("size", $arFile))
				$arFile["size"] = CUtil::BinStrlen($arFile["content"]);
		}
		else
		{
			$arFile["size"] = filesize($arFile["tmp_name"]);
		}

		$arFile["ORIGINAL_NAME"] = $strFileName;

		$io = CBXVirtualIo::GetInstance();
		if (self::validateFile($strFileName, $arFile, $bForceMD5) !== "")
			return false;

		$upload_dir = COption::GetOptionString("main", "upload_dir", "upload");

		if($arFile["type"] == "image/pjpeg" || $arFile["type"] == "image/jpg")
			$arFile["type"] = "image/jpeg";

		//.jpe is not image type on many systems
		if(strtolower(GetFileExtension($strFileName)) == "jpe")
			$strFileName = substr($strFileName, 0, -4).".jpg";

		$bExternalStorage = false;
		foreach(GetModuleEvents("main", "OnFileSave", true) as $arEvent)
		{
			if(ExecuteModuleEventEx($arEvent, array(&$arFile, $strFileName, $strSavePath, $bForceMD5, $bSkipExt)))
			{
				$bExternalStorage = true;
				break;
			}
		}

		if(!$bExternalStorage)
		{
			$newName = '';
			if($bForceMD5 != true && COption::GetOptionString("main", "save_original_file_name", "N")=="Y")
			{
				$dir_add = '';
				$i=0;
				while(true)
				{
					$dir_add = substr(md5(uniqid(mt_rand(), true)), 0, 3);
					if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$dir_add."/".$strFileName))
						break;
					if($i>=25)
					{
						$j=0;
						while(true)
						{
							$dir_add = substr(md5(mt_rand()), 0, 3)."/".substr(md5(mt_rand()), 0, 3);
							if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$dir_add."/".$strFileName))
								break;
							if($j>=25)
							{
								$dir_add = substr(md5(mt_rand()), 0, 3)."/".md5(mt_rand());
								break;
							}
							$j++;
						}
						break;
					}
					$i++;
				}
				if(substr($strSavePath, -1, 1) <> "/")
					$strSavePath .= "/".$dir_add;
				else
					$strSavePath .= $dir_add."/";

				$newName = $strFileName;
			}
			else
			{
				$strFileExt = ($bSkipExt == true? '' : strrchr($strFileName, "."));
				while(true)
				{
					$newName = md5(uniqid(mt_rand(), true)).$strFileExt;
					if(substr($strSavePath, -1, 1) <> "/")
						$strSavePath .= "/".substr($newName, 0, 3);
					else
						$strSavePath .= substr($newName, 0, 3)."/";

					if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$newName))
						break;
				}
			}

			$arFile["SUBDIR"] = $strSavePath;
			$arFile["FILE_NAME"] = $newName;
			$strDirName = $_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/";
			$strDbFileNameX = $strDirName.$newName;
			$strPhysicalFileNameX = $io->GetPhysicalName($strDbFileNameX);

			CheckDirPath($strDirName);

			if(is_set($arFile, "content"))
			{
				$f = fopen($strPhysicalFileNameX, "ab");
				if(!$f)
					return false;
				if(!fwrite($f, $arFile["content"]))
					return false;
				fclose($f);
			}
			elseif(
				!copy($arFile["tmp_name"], $strPhysicalFileNameX)
				&& !move_uploaded_file($arFile["tmp_name"], $strPhysicalFileNameX)
			)
			{
				CFile::DoDelete($arFile["old_file"]);
				return false;
			}

			if(isset($arFile["old_file"]))
				CFile::DoDelete($arFile["old_file"]);

			@chmod($strPhysicalFileNameX, BX_FILE_PERMISSIONS);

			$imgArray = CFile::GetImageSize($strDbFileNameX);

			if(is_array($imgArray))
			{
				$arFile["WIDTH"] = $imgArray[0];
				$arFile["HEIGHT"] = $imgArray[1];
			}
			else
			{
				$arFile["WIDTH"] = 0;
				$arFile["HEIGHT"] = 0;
			}
		}


		/****************************** QUOTA ******************************/
		if (COption::GetOptionInt("main", "disk_space") > 0)
		{
			CDiskQuota::updateDiskQuota("file", $arFile["size"], "insert");
		}
		/****************************** QUOTA ******************************/

		$NEW_IMAGE_ID = CFile::DoInsert(array(
			"HEIGHT" => $arFile["HEIGHT"],
			"WIDTH" => $arFile["WIDTH"],
			"FILE_SIZE" => $arFile["size"],
			"CONTENT_TYPE" => $arFile["type"],
			"SUBDIR" => $arFile["SUBDIR"],
			"FILE_NAME" => $arFile["FILE_NAME"],
			"MODULE_ID" => $arFile["MODULE_ID"],
			"ORIGINAL_NAME" => $arFile["ORIGINAL_NAME"],
			"DESCRIPTION" => isset($arFile["description"])? $arFile["description"]: '',
			"HANDLER_ID" => isset($arFile["HANDLER_ID"])? $arFile["HANDLER_ID"]: '',
		));

		CFile::CleanCache($NEW_IMAGE_ID);
		return $NEW_IMAGE_ID;
	}

	public static function DoInsert($arFields)
	{
		global $DB;
		$strSql =
			"INSERT INTO b_file(HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, MODULE_ID, ORIGINAL_NAME, DESCRIPTION, HANDLER_ID) ".
			"VALUES('".intval($arFields["HEIGHT"])."', '".intval($arFields["WIDTH"])."', '".intval($arFields["FILE_SIZE"])."', '".
				$DB->ForSql($arFields["CONTENT_TYPE"], 255)."' , '".$DB->ForSql($arFields["SUBDIR"], 255)."', '".
				$DB->ForSQL($arFields["FILE_NAME"], 255)."', '".$DB->ForSQL($arFields["MODULE_ID"], 50)."', '".
				$DB->ForSql($arFields["ORIGINAL_NAME"], 255)."', '".$DB->ForSQL($arFields["DESCRIPTION"], 255)."', ".
				($arFields["HANDLER_ID"]? "'".$DB->ForSql($arFields["HANDLER_ID"], 50)."'": "null").") ";
		$DB->Query($strSql);
		return $DB->LastID();
	}

	public static function CleanCache($ID)
	{
		global $CACHE_MANAGER;

		$ID = intval($ID);
		if (CACHED_b_file!==false)
		{
			$bucket_size = intval(CACHED_b_file_bucket_size);
			if ($bucket_size <= 0)
				$bucket_size = 10;
			$bucket = intval($ID/$bucket_size);
			$CACHE_MANAGER->Clean("b_file".$bucket, "b_file");
		}
	}

	public static function GetFromCache($FILE_ID)
	{
		global $CACHE_MANAGER, $DB;

		$bucket_size = intval(CACHED_b_file_bucket_size);
		if($bucket_size <= 0)
			$bucket_size = 10;

		$bucket = intval($FILE_ID/$bucket_size);
		if($CACHE_MANAGER->Read(CACHED_b_file, $cache_id="b_file".$bucket, "b_file"))
		{
			$arFiles = $CACHE_MANAGER->Get($cache_id);
		}
		else
		{
			$arFiles = array();
			$rs = $DB->Query("
				SELECT f.*,".$DB->DateToCharFunction("f.TIMESTAMP_X")." as TIMESTAMP_X FROM b_file f
				WHERE f.ID between ".($bucket*$bucket_size)." AND ".(($bucket+1)*$bucket_size-1)
			);
			while($ar = $rs->Fetch())
			{
				$ar["~src"] = '';
				foreach(GetModuleEvents("main", "OnGetFileSRC", true) as $arEvent)
				{
					$ar["~src"] = ExecuteModuleEventEx($arEvent, array($ar));
					if($ar["~src"])
						break;
				}
				$arFiles[$ar["ID"]] = $ar;
			}
			$CACHE_MANAGER->Set($cache_id, $arFiles);
		}
		return $arFiles;
	}

	
	/**
	 * <p>Функция возвращает информацию по одному зарегистрированному файлу в виде объекта класса <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">CDBResult</a>.</p>
	 *
	 *
	 *
	 *
	 * @param int $file_id  Цифровой идентификатор файла.
	 *
	 *
	 *
	 * @return CDBResult 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsElements = GetIBlockElementListEx($IBLOCK_TYPE, $IBLOCK_ID, false, array($ELEMENT_SORT_FIELD =&gt; $ELEMENT_SORT_ORDER, "ID" =&gt; "ASC"), false, $arrFilter)):
	 *     $rsElements-&gt;NavStart($PAGE_ELEMENT_COUNT);
	 * 	while ($obElement = $rsElements-&gt;GetNextElement()):
	 * 		$arElement = $obElement-&gt;GetFields();
	 * 		$rsFile = <b>CFile::GetByID</b>($arElement["PREVIEW_PICTURE"]);
	 * 		$arFile = $rsFile-&gt;Fetch();
	 * 		$arrImages[$arElement["ID"]][] = $arFile;
	 * 	endwhile;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/index.php">Поля файла</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/getpath.php">CFile::GetPath</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/inputfile.php">CFile::InputFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/savefile.php">CFile::SaveFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/copyfile.php">CFile::CopyFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/delete.php">CFile::Delete</a> </li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/getbyid.php
	 * @author Bitrix
	 */
	public static function GetByID($FILE_ID)
	{
		global $DB;
		$FILE_ID = intval($FILE_ID);
		if(CACHED_b_file===false)
		{
			$strSql = "SELECT f.*,".$DB->DateToCharFunction("f.TIMESTAMP_X")." as TIMESTAMP_X FROM b_file f WHERE f.ID=".$FILE_ID;
			$z = $DB->Query($strSql, false, "FILE: ".__FILE__."<br>LINE: ".__LINE__);
		}
		else
		{
			$arFiles = CFile::GetFromCache($FILE_ID);
			$z = new CDBResult;
			$z->InitFromArray(array_key_exists($FILE_ID, $arFiles)? array($arFiles[$FILE_ID]) : array());
		}
		return $z;
	}

	
	/**
	 * <p>Функция возвращает отфильтрованную и отсортированную выборку зарегистрированных файлов в виде объекта класса <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">CDBResult</a>.</p> <p><b>Примечание</b>: некоторые поля фильтра (SUBDIR, FILE_NAME) и сортировка обрабатываются начиная с версии 10.0.6 главного модуля.</p>
	 *
	 *
	 *
	 *
	 * @param array $arrayarOrder = array() Массив, содержащий признак сортировки в виде пар
	 * "поле"=&gt;"направление". Поддерживаются следующие поля: ID, TIMESTAMP_X,
	 * MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME. Направление
	 * сортировки может принимать значения "ASC", "DESC". Если параметр пуст,
	 * то выборка будет отсортирована по полю ID по возрастанию.
	 *
	 *
	 *
	 * @param array $arrayarFilter = array() Массив, содержащий фильтр в виде пар "поле"=&gt;"значение".
	 * Поддерживаются следующие поля фильтра: MODULE_ID, ID, SUBDIR, FILE_NAME,
	 * ORIGINAL_NAME, CONTENT_TYPE. Если указать в начале поля символ @, то можно
	 * передать несколько значений через запятую (применяется оператор
	 * IN), например: "@ID"=&gt;"1,2,3,4,5". <br><br>
	 *
	 *
	 *
	 * @return CDBResult <p>Объект типа  <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">CDBResult</a>.
	 * </p>
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?<br>//найдем самые большие файлы ядра<br>$res = CFile::GetList(array("FILE_SIZE"=&gt;"desc"), array("MODULE_ID"=&gt;"main"));<br>while($res_arr = $res-&gt;GetNext())<br>	echo $res_arr["SUBDIR"]."/".$res_arr["FILE_NAME"]." = ".$res_arr["FILE_SIZE"]."&lt;br&gt;";<br>?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">CDBResult</a> </li> <li><a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/index.php">Класс CFile</a></li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/cfilegetlist.php
	 * @author Bitrix
	 */
	public static function GetList($arOrder = array(), $arFilter = array())
	{
		global $DB;
		$arSqlSearch = array();
		$arSqlOrder = array();
		$strSqlSearch = $strSqlOrder = "";

		if(is_array($arFilter))
		{
			foreach($arFilter as $key => $val)
			{
				$key = strtoupper($key);

				$strOperation = '';
				if(substr($key, 0, 1)=="@")
				{
					$key = substr($key, 1);
					$strOperation = "IN";
					$arIn = explode(',', $val);
					$val = '';
					foreach($arIn as $v)
						$val .= ($val <> ''? ',':'')."'".$DB->ForSql(trim($v))."'";
				}
				else
				{
					$val = $DB->ForSql($val);
				}

				if($val == '')
					continue;

				switch($key)
				{
					case "MODULE_ID":
					case "ID":
					case "SUBDIR":
					case "FILE_NAME":
					case "ORIGINAL_NAME":
					case "CONTENT_TYPE":
						if ($strOperation == "IN")
							$arSqlSearch[] = "f.".$key." IN (".$val.")";
						else
							$arSqlSearch[] = "f.".$key." = '".$val."'";
					break;
				}
			}
		}
		if(!empty($arSqlSearch))
			$strSqlSearch = " WHERE (".implode(") AND (", $arSqlSearch).")";

		if(is_array($arOrder))
		{
			static $aCols = array("ID"=>1, "TIMESTAMP_X"=>1, "MODULE_ID"=>1, "HEIGHT"=>1, "WIDTH"=>1, "FILE_SIZE"=>1, "CONTENT_TYPE"=>1, "SUBDIR"=>1, "FILE_NAME"=>1, "ORIGINAL_NAME"=>1);
			foreach($arOrder as $by => $ord)
			{
				$by = strtoupper($by);
				if(array_key_exists($by, $aCols))
					$arSqlOrder[] = "f.".$by." ".(strtoupper($ord) == "DESC"? "DESC":"ASC");
			}
		}
		if(empty($arSqlOrder))
			$arSqlOrder[] = "f.ID ASC";
		$strSqlOrder = " ORDER BY ".implode(", ", $arSqlOrder);

		$strSql =
			"SELECT f.*, ".$DB->DateToCharFunction("f.TIMESTAMP_X")." as TIMESTAMP_X ".
			"FROM b_file f ".
			$strSqlSearch.
			$strSqlOrder;

		$res = $DB->Query($strSql, false, "FILE: ".__FILE__."<br> LINE: ".__LINE__);

		return $res;
	}

	public static function GetFileSRC($arFile, $upload_dir = false, $external = true)
	{
		$src = '';
		if($external)
		{
			foreach(GetModuleEvents("main", "OnGetFileSRC", true) as $arEvent)
			{
				$src = ExecuteModuleEventEx($arEvent, array($arFile));
				if($src)
					break;
			}
		}

		if(!$src)
		{
			if($upload_dir === false)
				$upload_dir = COption::GetOptionString("main", "upload_dir", "upload");

			$src = "/".$upload_dir."/".$arFile["SUBDIR"]."/".$arFile["FILE_NAME"];

			$src = str_replace("//", "/", $src);
			if(defined("BX_IMG_SERVER"))
				$src = BX_IMG_SERVER.$src;
		}

		return $src;
	}

	
	/**
	 * <br> Функция возвращает массив описывающий файл с заданным идентификатором или <i>false</i>, если файла с таким идентификатором не существует. <br>
	 *
	 *
	 *
	 *
	 * @param int $FILE_ID    Идентификатор файла. <br>
	 *
	 *
	 *
	 * @return mixed 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * <br><br>//Покажем изображение анонса элемента инфоблока<br>//$ELEMENT_ID - идентификатор этого элемента<br>if(CModule::IncludeModule('iblock'))<br>{<br>  $rsElement = CIBlockElement::GetList(<br>    array(),<br>    array("=ID"=&gt;$ELEMENT_ID),<br>    false,<br>    false,<br>    array("PREVIEW_PICTURE")<br>  );<br>  if($arElement = $rsElement-&gt;Fetch())<br>  {<br>    $arFile = CFile::GetFileArray($arElement["PREVIEW_PICTURE"]);<br>    if($arFile)<br>       echo '&lt;img src="'.$arFile["SRC"].'" /&gt;';<br>  }<br>}<br><br>
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <p><a href="http://dev.1c-bitrix.ru/api_help/main/reference/cfile/index.php">Класс CFile</a></p><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/getfilearray.php
	 * @author Bitrix
	 */
	public static function GetFileArray($FILE_ID, $upload_dir = false)
	{
		if(!is_array($FILE_ID) && intval($FILE_ID) > 0)
		{
			if(CACHED_b_file===false)
			{
				$res = CFile::GetByID($FILE_ID, true);
				$arFile = $res->Fetch();
			}
			else
			{
				$res = CFile::GetFromCache($FILE_ID);
				$arFile = $res[$FILE_ID];
			}

			if($arFile)
			{
				if(array_key_exists("~src", $arFile))
				{
					if($arFile["~src"])
						$arFile["SRC"] = $arFile["~src"];
					else
						$arFile["SRC"] = CFile::GetFileSRC($arFile, $upload_dir, false/*It is known file is local*/);
				}
				else
				{
					$arFile["SRC"] = CFile::GetFileSRC($arFile, $upload_dir);
				}

				return $arFile;
			}
		}
		return false;
	}

	public static function ConvertFilesToPost($source, &$target, $field=false)
	{
		if($field === false)
		{
			foreach($source as $field => $sub_source)
			{
				CAllFile::ConvertFilesToPost($sub_source, $target, $field);
			}
		}
		else
		{
			foreach($source as $id => $sub_source)
			{
				if(!array_key_exists($id, $target))
					$target[$id] = array();
				if(is_array($sub_source))
					CAllFile::ConvertFilesToPost($sub_source, $target[$id], $field);
				else
					$target[$id][$field] = $sub_source;
			}
		}
	}

	
	/**
	 * <p>Функция копирует зарегистрированный файл и возвращает ID нового файла копии.</p>
	 *
	 *
	 *
	 *
	 * @param int $file_id  Цифровой идентификатор файла предназначенного для копирования.
	 *
	 *
	 *
	 * @return int 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsElements = GetIBlockElementListEx($IBLOCK_TYPE, $IBLOCK_ID, false, array($ELEMENT_SORT_FIELD =&gt; $ELEMENT_SORT_ORDER, "ID" =&gt; "ASC"), false, $arrFilter)):
	 *     $rsElements-&gt;NavStart($PAGE_ELEMENT_COUNT);
	 * 	while ($obElement = $rsElements-&gt;GetNextElement()):
	 * 		$arElement = $obElement-&gt;GetFields();
	 * 		$arrNewImages[$arElement["ID"]][] = <b>CFile::CopyFile</b>($arElement["PREVIEW_PICTURE"]);
	 * 	endwhile;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul><li> <a href="http://dev.1c-bitrix.ruapi_help/main/functions/file/copydirfiles.php">CopyDirFiles</a> </li></ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/copyfile.php
	 * @author Bitrix
	 */
	public static function CopyFile($FILE_ID, $bRegister = true, $newPath = "")
	{
		global $DB;

		$err_mess = "FILE: ".__FILE__."<br>LINE: ";
		$z = CFile::GetByID($FILE_ID);
		if($zr = $z->Fetch())
		{
			/****************************** QUOTA ******************************/
			if (COption::GetOptionInt("main", "disk_space") > 0)
			{
				$quota = new CDiskQuota();
				if (!$quota->checkDiskQuota($zr))
					return false;
			}
			/****************************** QUOTA ******************************/

			$strNewFile = '';
			$bSaved = false;
			$bExternalStorage = false;
			foreach(GetModuleEvents("main", "OnFileCopy", true) as $arEvent)
			{
				if($bSaved = ExecuteModuleEventEx($arEvent, array(&$zr, $newPath)))
				{
					$bExternalStorage = true;
					break;
				}
			}

			$io = CBXVirtualIo::GetInstance();

			if(!$bExternalStorage)
			{
				$strDirName = $_SERVER["DOCUMENT_ROOT"]."/".(COption::GetOptionString("main", "upload_dir", "upload"));
				$strDirName = rtrim(str_replace("//","/",$strDirName), "/");

				$zr["SUBDIR"] = trim($zr["SUBDIR"], "/");
				$zr["FILE_NAME"] = ltrim($zr["FILE_NAME"], "/");

				$strOldFile = $strDirName."/".$zr["SUBDIR"]."/".$zr["FILE_NAME"];

				if(strlen($newPath))
					$strNewFile = $strDirName."/".ltrim($newPath, "/");
				else
					$strNewFile = $strDirName."/".$zr["SUBDIR"]."/".md5(uniqid(mt_rand())).strrchr($zr["FILE_NAME"], ".");

				$zr["FILE_NAME"] = bx_basename($strNewFile);
				$zr["SUBDIR"] = substr($strNewFile, strlen($strDirName)+1, -(strlen(bx_basename($strNewFile)) + 1));

				if(strlen($newPath))
					CheckDirPath($strNewFile);

				$bSaved = copy($io->GetPhysicalName($strOldFile), $io->GetPhysicalName($strNewFile));
			}

			if($bSaved)
			{
				if($bRegister)
				{
					$arFields = array(
						"TIMESTAMP_X" => $DB->GetNowFunction(),
						"MODULE_ID" => "'".$DB->ForSql($zr["MODULE_ID"], 50)."'",
						"HEIGHT" => intval($zr["HEIGHT"]),
						"WIDTH" => intval($zr["WIDTH"]),
						"FILE_SIZE" => intval($zr["FILE_SIZE"]),
						"ORIGINAL_NAME" => "'".$DB->ForSql($zr["ORIGINAL_NAME"], 255)."'",
						"DESCRIPTION" => "'".$DB->ForSql($zr["DESCRIPTION"], 255)."'",
						"CONTENT_TYPE" => "'".$DB->ForSql($zr["CONTENT_TYPE"], 255)."'",
						"SUBDIR" => "'".$DB->ForSql($zr["SUBDIR"], 255)."'",
						"FILE_NAME" => "'".$DB->ForSql($zr["FILE_NAME"], 255)."'",
						"HANDLER_ID" => $zr["HANDLER_ID"]? intval($zr["HANDLER_ID"]): "null",
					);
					$NEW_FILE_ID = $DB->Insert("b_file",$arFields, $err_mess.__LINE__);

					if (COption::GetOptionInt("main", "disk_space") > 0)
						CDiskQuota::updateDiskQuota("file", $zr["FILE_SIZE"], "copy");

					CFile::CleanCache($NEW_FILE_ID);

					return $NEW_FILE_ID;
				}
				else
				{
					if(!$bExternalStorage)
						return substr($strNewFile, strlen(rtrim($_SERVER["DOCUMENT_ROOT"], "/")));
					else
						return $bSaved;
				}
			}
			else
			{
				return false;
			}
		}
		return 0;
	}

	
	/**
	 * <p>Функция обновляет описание к зарегистрированному файлу. Возвращает объект класса <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cdbresult/index.php">CDBResult</a>.</p>
	 *
	 *
	 *
	 *
	 * @param int $file_id  Цифровой идентификатор файла.
	 *
	 *
	 *
	 * @param string $description  Новое описание к файлу.
	 *
	 *
	 *
	 * @return CDBResult 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsElements = GetIBlockElementListEx($IBLOCK_TYPE, $IBLOCK_ID, false, array($ELEMENT_SORT_FIELD =&gt; $ELEMENT_SORT_ORDER, "ID" =&gt; "ASC"), false, $arrFilter)):
	 *     $rsElements-&gt;NavStart($PAGE_ELEMENT_COUNT);
	 * 	while ($obElement = $rsElements-&gt;GetNextElement()):
	 * 		$arElement = $obElement-&gt;GetFields();
	 * 		<b>CFile::UpdateDesc</b>($arElement["PREVIEW_PICTURE"], "Element # ".$arElement["ID"]);
	 * 	endwhile;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/functions/file/rewritefile.php">RewriteFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cmain/savefilecontent.php">CMain::SaveFileContent</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/getbyid.php">CFile::GetByID</a> </li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/updatedesc.php
	 * @author Bitrix
	 */
	public static function UpdateDesc($ID, $desc)
	{
		global $DB;
		$DB->Query("UPDATE b_file SET DESCRIPTION='".$DB->ForSql($desc, 255)."' WHERE ID=".intval($ID));
		CFile::CleanCache($ID);
	}

	
	/**
	 * <p>Функция возвращает HTML код предназначенный для загрузки нового, либо замены существующего файла.</p>
	 *
	 *
	 *
	 *
	 * @param string $input_file_name  Название поля для ввода файла:<br>&lt;input type="file" name="<i>input_file_name</i>" ... &gt;
	 *
	 *
	 *
	 * @param int $input_file_width  Ширина поля для ввода файла:<br>&lt;input type="file" size="<i>input_file_width</i>" ... &gt;
	 *
	 *
	 *
	 * @param int $file_id  Цифровой идентификатор существующего файла.
	 *
	 *
	 *
	 * @param string $file_path = false Путь к папке от корня сайта в которой хранятся файлы. Например:
	 * "/upload/iblock/".<br>Необязательный. По умолочанию false - путь берется из
	 * настроек системы.
	 *
	 *
	 *
	 * @param int $file_max_size = 0 Максимальный размер файла (байт):<br> &lt;input type="hidden" name="MAX_FILE_SIZE"
	 * value="<i>file_max_size</i>"&gt; <br>Необязательный. По умолчанию "0" - без
	 * ограничений.
	 *
	 *
	 *
	 * @param string $file_type = "IMAGE" Тип файла, если "IMAGE", то в информацию по файлу будет добавлена
	 * ширина и высота изображения. <br>Необязательный. По умолчанию "IMAGE".
	 *
	 *
	 *
	 * @param string $add_to_input_file = "class=typefile" Произвольный HTML который будет добавлен в поле для ввода
	 * файла:<br>&lt;input type="file" <i>add_to_input_file</i> ... &gt; <br>Необязательный. По
	 * умолчанию "class=typefile" - стандартный класс для полей ввода файлов в
	 * административной части.
	 *
	 *
	 *
	 * @param int $input_description_width = 0 Ширина поля ввода для комментария к файлу:<br>&lt;input type="text"
	 * size="<i>input_description_width</i>" ... &gt; <br>Необязательный. По умолчанию "0" - поле
	 * ввода для комментария к файлу не показывать.
	 *
	 *
	 *
	 * @param string $add_to_input_description = "class=typeinput" Произвольный HTML который будет добавлен в поле ввода для
	 * комментария к файлу:<br>&lt;input type="text" <i>add_to_input_description</i> ... &gt;
	 * <br>Необязательный. По умолчанию "class=typeinput" - стандартный класс для
	 * однострочных элементов ввода в административной части.
	 *
	 *
	 *
	 * @param string $add_to_checkbox_delete = "" Произвольный HTML который будет добавлен в поле типа "checkbox" для
	 * удаления файла: <br>&lt;input type="checkbox" name="<i>input_file_name</i>_del" value="Y"
	 * <i>add_to_checkbox_delete</i> ... &gt; <br>Необязательный.
	 *
	 *
	 *
	 * @param bool $show_file_info = true Флаг позволяющий включить, либо отключить показ информации по
	 * файлу (размер, ширину, высоту). <br>Необязательный. По умолчанию -
	 * "true" - информацию по файлу показать.
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;tr valign="top"&gt;
	 *     &lt;td align="right"&gt;&lt;font class="tablefieldtext"&gt;&lt;?=GetMessage("VOTE_IMAGE")?&gt;&lt;/font&gt;&lt;/td&gt;
	 *     &lt;td&gt;&lt;font class="tablebodytext"&gt;&lt;?
	 *     echo <b>CFile::InputFile</b>("IMAGE_ID", 20, $str_IMAGE_ID);
	 *     if (strlen($str_IMAGE_ID)&gt;0):
	 *         ?&gt;&lt;br&gt;&lt;?echo CFile::ShowImage($str_IMAGE_ID, 200, 200, "border=0", "", true);
	 *     endif;
	 *     ?&gt;&lt;/font&gt;
	 *     &lt;/td&gt;
	 * &lt;/tr&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul><li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/savefile.php">CFile::SaveFile</a> </li></ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/inputfile.php
	 * @author Bitrix
	 */
	public static function InputFile($strFieldName, $int_field_size, $strImageID, $strImageStorePath=false, $int_max_file_size=0, $strFileType="IMAGE", $field_file="class=typefile", $description_size=0, $field_text="class=typeinput", $field_checkbox="", $bShowNotes = true, $bShowFilePath = true)
	{
		$strReturn1 = "";
		if($int_max_file_size != 0)
			$strReturn1 .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"".$int_max_file_size."\" /> ";

		$strReturn1 .= ' <input name="'.$strFieldName.'" '.$field_file.'  size="'.$int_field_size.'" type="file" />';
		$strReturn2 = '<span class="bx-input-file-desc">';
		$strDescription = "";
		$db_img_arr = CFile::GetFileArray($strImageID, $strImageStorePath);

		if($db_img_arr)
		{
			$strDescription = $db_img_arr["DESCRIPTION"];

			if(($p=strpos($strFieldName, "["))>0)
			{
				$strDelName = substr($strFieldName, 0, $p)."_del".substr($strFieldName, $p);
			}
			else
			{
				$strDelName = $strFieldName."_del";
			}

			if($bShowNotes)
			{
				if($bShowFilePath)
				{
					$filePath = $db_img_arr["SRC"];
				}
				else
				{
					$filePath = $db_img_arr['ORIGINAL_NAME'];
				}
				$io = CBXVirtualIo::GetInstance();
				if($io->FileExists($_SERVER["DOCUMENT_ROOT"].$db_img_arr["SRC"]) || $db_img_arr["HANDLER_ID"])
				{
					$strReturn2 .= "<br>&nbsp;".GetMessage("FILE_TEXT").": ".htmlspecialcharsEx($filePath);
					if(strtoupper($strFileType)=="IMAGE")
					{
						$intWidth = intval($db_img_arr["WIDTH"]);
						$intHeight = intval($db_img_arr["HEIGHT"]);
						if($intWidth>0 && $intHeight>0)
						{
							$strReturn2 .= "<br>&nbsp;".GetMessage("FILE_WIDTH").": $intWidth";
							$strReturn2 .= "<br>&nbsp;".GetMessage("FILE_HEIGHT").": $intHeight";
						}
					}
					$strReturn2 .= "<br>&nbsp;".GetMessage("FILE_SIZE").": ".CFile::FormatSize($db_img_arr["FILE_SIZE"]);
				}
				else
				{
					$strReturn2 .= "<br>".GetMessage("FILE_NOT_FOUND").": ".htmlspecialcharsEx($filePath);
				}
			}
			$strReturn2 .= "<br><input ".$field_checkbox." type=\"checkbox\" name=\"".$strDelName."\" value=\"Y\" id=\"".$strDelName."\" /> <label for=\"".$strDelName."\">".GetMessage("FILE_DELETE")."</label>";
		}

		$strReturn2 .= '</span>';

		return $strReturn1.(
			$description_size > 0?
			'<br><input type="text" value="'.htmlspecialcharsbx($strDescription).'" name="'.$strFieldName.'_descr" '.$field_text.' size="'.$description_size.'" title="'.GetMessage("MAIN_FIELD_FILE_DESC").'" />'
			:''
		).$strReturn2;
	}
	/**
	 * @param float $size
	 * @param int $precision
	 * @return string
	 */
	public static function FormatSize($size, $precision = 2)
	{
		static $a = array("b", "Kb", "Mb", "Gb", "Tb");
		$pos = 0;
		while($size >= 1024 && $pos < 4)
		{
			$size /= 1024;
			$pos++;
		}
		return round($size, $precision)." ".GetMessage("FILE_SIZE_".$a[$pos]);
	}

	public static function GetImageExtensions()
	{
		return "jpg,bmp,jpeg,jpe,gif,png";
	}

	public static function GetFlashExtensions()
	{
		return "swf";
	}

	
	/**
	 * <p>Функция проверяет расширение и заданный MIME тип файла. Если расширение и MIME тип файла соответствуют изображению, то возвращает "true", иначе "false".</p>
	 *
	 *
	 *
	 *
	 * @param string $file_name  Краткое имя файла (без пути).
	 *
	 *
	 *
	 * @param mixed $mime_type = false MIME тип файла (например, "image/").<br>Необязательный. По умолчанию - "false"
	 * - проверку на MIME тип не делать.
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsFiles = CTicket::GetFileList($v1="s_id", $v2="asc", array("HASH"=&gt;$hash))) :
	 *   if ($arFile = $rsFiles-&gt;Fetch()) :
	 *     $filename = $_SERVER["DOCUMENT_ROOT"]."/".COption::GetOptionString("main", "upload_dir", "upload")."/".$arFile["SUBDIR"]."/".$arFile["FILE_NAME"];
	 *     if ($f = fopen($filename, "rb"))
	 *     {
	 *       $is_image = <b>CFile::IsImage</b>($arFile["FILE_NAME"], $arFile["CONTENT_TYPE"]);
	 *       // если изображение то
	 *       if ($is_image) 
	 *       {
	 *         // отдадим как изображение
	 *         header("Content-type: ".$arFile["CONTENT_TYPE"]);
	 *         header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0"); 
	 *         header("Expires: 0"); 
	 *         header("Pragma: public"); 
	 *         while ($buffer = fread($f, 4096)) echo $buffer;
	 *       }
	 *       else // иначе
	 *       {
	 *         // отдадим как текст
	 *         header("Content-type: text/html; charset=".LANG_CHARSET);
	 *         header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0"); 
	 *         header("Expires: 0"); 
	 *         header("Pragma: public"); 
	 *         echo "&lt;pre&gt;";
	 *         while ($buffer = fread($f, 4096)) echo htmlspecialchars($buffer);
	 *         echo "&lt;/pre&gt;";
	 *       }
	 *       fclose ($f);
	 *       die();
	 *     }
	 *   endif;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkimagefile.php">CFile::CheckImageFile</a>
	 * </li> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkfile.php">CFile::CheckFile</a> </li>
	 * </ul><a name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/isimage.php
	 * @author Bitrix
	 */
	public static function IsImage($filename, $mime_type=false)
	{
		$ext = strtolower(GetFileExtension($filename));
		if($ext <> '')
		{
			if(in_array($ext, explode(",", CFile::GetImageExtensions())))
				if($mime_type === false || strpos($mime_type, "image/") === 0)
					return true;
		}
		return false;
	}

	
	/**
	 * <p>Функция проверяет что файл является картинкой и проверяет ее параметры. В случае ошибки функция вернет строку с текстом ошибки.</p>
	 *
	 *
	 *
	 *
	 * @param array $file  Массив с данными файла формата:<br><pre>Array( "name" =&gt; "название файла",
	 * "size" =&gt; "размер", "tmp_name" =&gt; "временный путь к файлу на сервере", "type"
	 * =&gt; "тип загружаемого файла", "del" =&gt; "флаг: удалить ли существующий
	 * файл из базы данных (Y|N)", "MODULE_ID" =&gt; "название модуля")</pre> Массив
	 * такого вида может быть получен, например, объединением массивов
	 * $_FILES[имя поля] и Array("del" =&gt; ${"имя поля"."_del"}, "MODULE_ID" = "название
	 * модуля").
	 *
	 *
	 *
	 * @param int $max_size = 0 Максимальный размер файла (байт).<br>Необязательный. По умолчанию -
	 * "0" - без ограничений.
	 *
	 *
	 *
	 * @param int $max_width = 0 Максимальная ширина картинки (пикселей).<br>Необязательный. По
	 * умолчанию - "0" - без ограничений.
	 *
	 *
	 *
	 * @param int $max_height = 0 Максимальная высота картинки (пикселей). <br>Необязательный. По
	 * умолчанию - "0" - без ограничений.
	 *
	 *
	 *
	 * @param array $possible_typies = array() Массив символьных идентификаторов типов файлов; допустимые
	 * следующие идентификаторы: <ul> <li> <b>IMAGE</b> - изображение; </li> <li>
	 * <b>FLASH</b> - flash файл. </li> </ul> Параметр необязательный. По умолчанию -
	 * пустой массив (допустимы только файлы типа <b>IMAGE</b>).
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * $arrFile = array_merge(
	 *     $_FILES["ATTACHED_IMAGE"], 
	 *     array("del" =&gt; ${"ATTACHED_IMAGE_del"}, "MODULE_ID" =&gt; "forum"));
	 * 
	 * $res = <b>CFile::CheckImageFile</b>($arrFile, 200, 50, 50);
	 * if (strlen($res)&gt;0) $strError .= $res."&lt;br&gt;";
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkfile.php">CFile::CheckFile</a> </li> <li>
	 * <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/isimage.php">CFile::IsImage</a> </li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/checkimagefile.php
	 * @author Bitrix
	 */
	public static function CheckImageFile($arFile, $iMaxSize=0, $iMaxWidth=0, $iMaxHeight=0, $access_typies=array())
	{
		if($arFile["name"] == "")
		{
			return "";
		}

		$file_type = GetFileType($arFile["name"]);

		// IMAGE by default
		if(!in_array($file_type, $access_typies))
		{
			$file_type = "IMAGE";
		}

		switch ($file_type)
		{
			case "FLASH":
				$res = CFile::CheckFile($arFile, $iMaxSize, "application/x-shockwave-flash", CFile::GetFlashExtensions());
				break;
			default:
				$res = CFile::CheckFile($arFile, $iMaxSize, "image/", CFile::GetImageExtensions());
		}

		if($res <> '')
		{
			return $res;
		}

		$imgArray = CFile::GetImageSize($arFile["tmp_name"], true);

		if(is_array($imgArray))
		{
			$intWIDTH = $imgArray[0];
			$intHEIGHT = $imgArray[1];
		}
		else
		{
			return GetMessage("FILE_BAD_FILE_TYPE").".<br>";
		}

		//check for dimensions
		if($iMaxWidth > 0 && ($intWIDTH > $iMaxWidth || $intWIDTH == 0) || $iMaxHeight > 0 && ($intHEIGHT > $iMaxHeight || $intHEIGHT == 0))
		{
			return GetMessage("FILE_BAD_MAX_RESOLUTION")." (".$iMaxWidth." * ".$iMaxHeight." ".GetMessage("main_include_dots").").<br>";
		}

		return null;
	}

	
	/**
	 * <p>Функция проверяет размер, расширение и mime тип файла. В случае ошибки функция вернет строку с текстом ошибки.</p>
	 *
	 *
	 *
	 *
	 * @param array $file  Массив с данными файла формата:<br><br><pre>Array( "name" =&gt; "название файла",
	 * "size" =&gt; "размер", "tmp_name" =&gt; "временный путь к файлу на сервере", "type"
	 * =&gt; "тип загружаемого файла", "del" =&gt; "флаг - удалить ли существующий
	 * файл из базы данных (Y|N)", "MODULE_ID" =&gt; "название модуля")</pre> Массив
	 * такого вида может быть получен, например, объединением массивов
	 * $_FILES[имя поля] и Array("del" =&gt; ${"имя поля"."_del"}, "MODULE_ID" = "название
	 * модуля").
	 *
	 *
	 *
	 * @param int $max_size = 0 Максимальный размер файла (байт).<br>Необязательный. По умолчанию -
	 * "0" - без ограничений.
	 *
	 *
	 *
	 * @param string $mime_types = false Разрешенный mime тип файла (например, "image/"). Проверка
	 * осуществляется по данным полученным от посетителя, поэтому для
	 * более безопасной проверки используйте <i>extensions</i>.
	 * <br>Необязательный. По умолчанию - "false" - без ограничений.
	 *
	 *
	 *
	 * @param string $extensions = false Перечисленные через запятую разрешенные расширения
	 * файла.<br>Необязательный. По умолчанию - "false" - без ограничений.
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * $arrFile = array_merge(
	 *     $_FILES["ATTACHED_IMAGE"], 
	 *     array("del" =&gt; ${"ATTACHED_IMAGE_del"}, "MODULE_ID" =&gt; "forum"));
	 * 
	 * $res = <b>CFile::CheckFile</b>($arrFile, 200, "image/", CFile::GetImageExtensions());
	 * if (strlen($res)&gt;0) $strError .= $res."&lt;br&gt;";
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkimagefile.php">CFile::CheckImageFile</a>
	 * </li> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/isimage.php">CFile::IsImage</a> </li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/checkfile.php
	 * @author Bitrix
	 */
	public static function CheckFile($arFile, $intMaxSize=0, $strMimeType=false, $strExt=false)
	{
		if($arFile["name"] == "")
		{
			return "";
		}

		if(($error = self::validateFile($dummy, $arFile)) <> '')
		{
			return $error;
		}

		if($intMaxSize > 0 && intval($arFile["size"]) > $intMaxSize)
		{
			return GetMessage("FILE_BAD_SIZE")." (".CFile::FormatSize($intMaxSize).").";
		}

		$strFileExt = '';
		if($strExt)
		{
			$strFileExt = GetFileExtension($arFile["name"]);
			if($strFileExt == '')
			{
				return GetMessage("FILE_BAD_TYPE");
			}
		}

		//Check mime_type and ext
		if($strMimeType !== false && substr($arFile["type"], 0, strlen($strMimeType)) != $strMimeType)
		{
			return GetMessage("FILE_BAD_TYPE");
		}

		if($strExt === false)
		{
			return "";
		}

		$IsExtCorrect = true;
		if($strExt)
		{
			$IsExtCorrect = false;
			$tok = strtok($strExt,",");
			while($tok)
			{
				if(strtolower(trim($tok)) == strtolower($strFileExt))
				{
					$IsExtCorrect=true;
					break;
				}
				$tok = strtok(",");
			}
		}

		if($IsExtCorrect)
		{
			return "";
		}

		return GetMessage("FILE_BAD_TYPE")." (".strip_tags($strFileExt).")";
	}

	public static function ShowFile($iFileID, $max_file_size=0, $iMaxW=0, $iMaxH=0, $bPopup=false, $sParams=false, $sPopupTitle=false, $iSizeWHTTP=0, $iSizeHHTTP=0)
	{
		$strResult = "";

		$arFile = CFile::GetFileArray($iFileID);
		if($arFile)
		{
			$max_file_size = intval($max_file_size);
			if($max_file_size<=0)
				$max_file_size = 1000000000;

			$ct = $arFile["CONTENT_TYPE"];
			if($max_file_size>=$arFile["FILE_SIZE"] && (substr($ct, 0, 6) == "video/" || substr($ct, 0, 6) == "audio/"))
				$strResult =
					'<OBJECT ID="WMP64" WIDTH="'.($iMaxW>0?$iMaxW:'250').'" HEIGHT="'.(substr($ct, 0, 6) == "audio/"?'45':($iMaxH>0?$iMaxH:'220')).'" CLASSID="CLSID:22D6f312-B0F6-11D0-94AB-0080C74C7E95" STANDBY="Loading Windows Media Player components..." TYPE="application/x-oleobject"> '.
					'<PARAM NAME="AutoStart" VALUE="false"> '.
					'<PARAM NAME="ShowDisplay" VALUE="false">'.
					'<PARAM NAME="ShowControls" VALUE="true" >'.
					'<PARAM NAME="ShowStatusBar" VALUE="0">'.
					'<PARAM NAME="FileName" VALUE="'.$arFile["SRC"].'"> '.
					'</OBJECT>';
			elseif($max_file_size>=$arFile["FILE_SIZE"] && substr($ct, 0, 6) == "image/")
				$strResult = CFile::ShowImage($arFile, $iMaxW, $iMaxH, $sParams, "", $bPopup, $sPopupTitle, $iSizeWHTTP, $iSizeHHTTP);
			else
				$strResult = ' [ <a href="'.$arFile["SRC"].'" title="'.GetMessage("FILE_FILE_DOWNLOAD").'">'.GetMessage("FILE_DOWNLOAD").'</a> ] ';
		}
		return $strResult;
	}

	public static function DisableJSFunction($b=true)
	{
		global $SHOWIMAGEFIRST;
		$SHOWIMAGEFIRST = $b;
	}

	public static function OutputJSImgShw()
	{
		global $SHOWIMAGEFIRST;
		if(!defined("ADMIN_SECTION") && $SHOWIMAGEFIRST!==true)
		{
			echo
'<script type="text/javascript">
function ImgShw(ID, width, height, alt)
{
	var scroll = "no";
	var top=0, left=0;
	var w, h;
	if(navigator.userAgent.toLowerCase().indexOf("opera") != -1)
	{
		w = document.body.offsetWidth;
		h = document.body.offsetHeight;
	}
	else
	{
		w = screen.width;
		h = screen.height;
	}
	if(width > w-10 || height > h-28)
		scroll = "yes";
	if(height < h-28)
		top = Math.floor((h - height)/2-14);
	if(width < w-10)
		left = Math.floor((w - width)/2-5);
	width = Math.min(width, w-10);
	height = Math.min(height, h-28);
	var wnd = window.open("","","scrollbars="+scroll+",resizable=yes,width="+width+",height="+height+",left="+left+",top="+top);
	wnd.document.write(
		"<html><head>"+
		"<"+"script type=\"text/javascript\">"+
		"function KeyPress(e)"+
		"{"+
		"	if (!e) e = window.event;"+
		"	if(e.keyCode == 27) "+
		"		window.close();"+
		"}"+
		"</"+"script>"+
		"<title>"+(alt == ""? "'.GetMessage("main_js_img_title").'":alt)+"</title></head>"+
		"<body topmargin=\"0\" leftmargin=\"0\" marginwidth=\"0\" marginheight=\"0\" onKeyDown=\"KeyPress(arguments[0])\">"+
		"<img src=\""+ID+"\" border=\"0\" alt=\""+alt+"\" />"+
		"</body></html>"
	);
	wnd.document.close();
	wnd.focus();
}
</script>';

			$SHOWIMAGEFIRST=true;
		}
	}

	function _GetImgParams($strImage, $iSizeWHTTP=0, $iSizeHHTTP=0)
	{
		global $arCloudImageSizeCache;

		$io = CBXVirtualIo::GetInstance();

		if(strlen($strImage) <= 0)
			return false;

		$strAlt = '';
		if(intval($strImage)>0)
		{
			$db_img_arr = CFile::GetFileArray($strImage);
			if($db_img_arr)
			{
				$strImage = $db_img_arr["SRC"];
				$intWidth = intval($db_img_arr["WIDTH"]);
				$intHeight = intval($db_img_arr["HEIGHT"]);
				$strAlt = $db_img_arr["DESCRIPTION"];
			}
			else
			{
				return false;
			}
		}
		else
		{
			if(!preg_match("#^https?://#", $strImage))
			{
				if($io->FileExists($_SERVER["DOCUMENT_ROOT"].$strImage))
				{
					$arSize = CFile::GetImageSize($_SERVER["DOCUMENT_ROOT"].$strImage);
					$intWidth = intval($arSize[0]);
					$intHeight = intval($arSize[1]);
					$strAlt = "";
				}
				else
				{
					return false;
				}
			}
			elseif(array_key_exists($strImage, $arCloudImageSizeCache))
			{
				$intWidth = $arCloudImageSizeCache[$strImage][0];
				$intHeight = $arCloudImageSizeCache[$strImage][1];
			}
			else
			{
				$intWidth = intval($iSizeWHTTP);
				$intHeight = intval($iSizeHHTTP);
				$strAlt = "";
			}
		}

		return array(
			"SRC"=>$strImage,
			"WIDTH"=>$intWidth,
			"HEIGHT"=>$intHeight,
			"ALT"=>$strAlt,
		);
	}

	
	/**
	 * <p>Функция возвращает путь от корня к зарегистрированному файлу.</p>
	 *
	 *
	 *
	 *
	 * @param int $file_id  ID файла.
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsElements = GetIBlockElementListEx($IBLOCK_TYPE, $IBLOCK_ID, false, array($ELEMENT_SORT_FIELD =&gt; $ELEMENT_SORT_ORDER, "ID" =&gt; "ASC"), false, $arrFilter)):
	 *   $rsElements-&gt;NavStart($PAGE_ELEMENT_COUNT);
	 *   while ($obElement = $rsElements-&gt;GetNextElement()):
	 *     $arElement = $obElement-&gt;GetFields();
	 *     $arImagesPath[$arElement["PREVIEW_PICTURE"]] = <b>CFile::GetPath</b>($arElement["PREVIEW_PICTURE"]);
	 *   endwhile;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul><li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/getbyid.php">CFile::GetByID</a> </li></ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/getpath.php
	 * @author Bitrix
	 */
	public static function GetPath($img_id)
	{
		$res = CFile::_GetImgParams($img_id);
		return $res["SRC"];
	}

	
	/**
	 * <p>Функция возвращает HTML для показа изображения.</p>
	 *
	 *
	 *
	 *
	 * @param mixed $image  ID файла или путь к файлу на текущем сайте либо URL к файлу лежащем
	 * на другом сайте. Если задается путь к файлу на текущем сайте, то
	 * его необходимо задавать относительно корня.
	 *
	 *
	 *
	 * @param int $max_width = 0 Максимальная ширина изображения. Если ширина картинки больше
	 * <i>max_width</i>, то она будет пропорционально смаштабирована. <br>
	 * Необязательный. По умолчанию - "0" - без ограничений.
	 *
	 *
	 *
	 * @param int $max_height = 0 Максимальная высота изображения. Если высота картинки больше
	 * <i>max_height</i>, то она будет пропорционально смаштабирована. <br>
	 * Необязательный. По умолчанию - "0" - без ограничений.
	 *
	 *
	 *
	 * @param string $image_params = "border=0" Произвольный HTML добавляемый в тэг IMG: <br> &lt;img <i>image_params</i> ...&gt; <br>
	 * Необязательный. По умолчанию "border=0". Если в этом параметре
	 * передать атрибут alt="текст", то в теге &lt;img&gt; будет использовано
	 * это значение (с версии 8.5.1). Иначе, если картинка имеет описание в
	 * таблице b_file, для атрибута alt будет использовано это описание.
	 *
	 *
	 *
	 * @param string $url = "" Ссылка для перехода при нажатии на картинку. <br> Необязательный.
	 * По умолчанию "" - не выводить ссылку.
	 *
	 *
	 *
	 * @param bool $popup = false Открывать ли при клике на изображении дополнительное popup окно с
	 * увеличенным изображением. <br> Необязательный. По умолчанию - "false".
	 *
	 *
	 *
	 * @param string $popup_alt = false Текст всплывающей подсказки на изображении (только если <i>popup</i> =
	 * true) <br> Необязательный. По умолчанию выводится фраза "Увеличить" на
	 * языке страницы.
	 *
	 *
	 *
	 * @param int $image_width = 0 Ширина изображения (в пикселах) (только если в параметре <i>image</i>
	 * задан URL начинающийся с "http://") <br> Необязательный. По умолчанию "0".
	 *
	 *
	 *
	 * @param int $image_height = 0 Высота изображения (в пикселах) (только если в параметре <i>image</i>
	 * задан URL начинающийся с "http://") <br> Необязательный. По умолчанию "0".
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;tr valign="top"&gt;
	 *     &lt;td align="right"&gt;&lt;font class="tablefieldtext"&gt;&lt;?echo 
	 * 	GetMessage("VOTE_IMAGE")?&gt;&lt;/font&gt;&lt;/td&gt;
	 *     &lt;td&gt;&lt;font class="tablebodytext"&gt;&lt;?
	 *     echo CFile::InputFile("IMAGE_ID", 20, $str_IMAGE_ID);
	 *     if (strlen($str_IMAGE_ID)&gt;0):
	 *         ?&gt;&lt;br&gt;&lt;?
	 *         echo <b>CFile::ShowImage</b>($str_IMAGE_ID, 200, 200, "border=0", "", true);
	 *     endif;
	 *     ?&gt;&lt;/font&gt;
	 *     &lt;/td&gt;
	 * &lt;/tr&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/show2images.php">CFile::Show2Images</a> </li>
	 * </ul><a name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/showimage.php
	 * @author Bitrix
	 */
	public static function ShowImage($strImage, $iMaxW=0, $iMaxH=0, $sParams=null, $strImageUrl="", $bPopup=false, $sPopupTitle=false, $iSizeWHTTP=0, $iSizeHHTTP=0, $strImageUrlTemplate="")
	{
		if(is_array($strImage))
		{
			$arImgParams = $strImage;
			$iImageID = isset($arImgParams['ID']) ? intval($arImgParams['ID']) : 0;
		}
		else
		{
			$arImgParams = CFile::_GetImgParams($strImage, $iSizeWHTTP, $iSizeHHTTP);
			$iImageID = intval($strImage);
		}

		if(!$arImgParams)
			return "";

		$iMaxW = intval($iMaxW);
		$iMaxH = intval($iMaxH);
		$intWidth = $arImgParams['WIDTH'];
		$intHeight = $arImgParams['HEIGHT'];
		if(
			$iMaxW > 0 && $iMaxH > 0
			&& ($intWidth > $iMaxW || $intHeight > $iMaxH)
		)
		{
			$coeff = ($intWidth/$iMaxW > $intHeight/$iMaxH? $intWidth/$iMaxW : $intHeight/$iMaxH);
			$iHeight = intval(roundEx($intHeight/$coeff));
			$iWidth = intval(roundEx($intWidth/$coeff));
		}
		else
		{
			$coeff = 1;
			$iHeight = $intHeight;
			$iWidth = $intWidth;
		}

		$strImageUrlTemplate = strval($strImageUrlTemplate);
		if($strImageUrlTemplate === '' || $iImageID <= 0)
		{
			$strImage = $arImgParams['SRC'];
		}
		else
		{
			$strImage = CComponentEngine::MakePathFromTemplate($strImageUrlTemplate, array('file_id' => $iImageID));
		}
		$strImage = htmlspecialcharsbx($strImage);

		if(GetFileType($strImage) == "FLASH")
		{
			$strReturn = '
				<object
					classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000"
					codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0"
					id="banner"
					WIDTH="'.$iWidth.'"
					HEIGHT="'.$iHeight.'"
					ALIGN="">
						<PARAM NAME="movie" VALUE="'.$strImage.'" />
						<PARAM NAME="quality" VALUE="high" />
						<PARAM NAME="bgcolor" VALUE="#FFFFFF" />
						<embed
							src="'.$strImage.'"
							quality="high"
							bgcolor="#FFFFFF"
							WIDTH="'.$iWidth.'"
							HEIGHT="'.$iHeight.'"
							NAME="banner"
							ALIGN=""
							TYPE="application/x-shockwave-flash"
							PLUGINSPAGE="http://www.macromedia.com/go/getflashplayer">
						</embed>
				</object>
			';
		}
		else
		{
			$strAlt = $arImgParams['ALT']? $arImgParams['ALT']: $arImgParams['DESCRIPTION'];

			if($sParams === null || $sParams === false)
				$sParams = 'border="0" alt="'.htmlspecialcharsEx($strAlt).'"';
			elseif(!preg_match('/(^|\\s)alt\\s*=\\s*(["\']?)(.*?)(\\2)/is', $sParams))
				$sParams .= ' alt="'.htmlspecialcharsEx($strAlt).'"';

			if($coeff === 1 || !$bPopup)
			{
				$strReturn = '<img src="'.$strImage.'" '.$sParams.' width="'.$iWidth.'" height="'.$iHeight.'" />';
			}
			else
			{
				if($sPopupTitle === false)
					$sPopupTitle = GetMessage('FILE_ENLARGE');

				if(strlen($strImageUrl)>0)
				{
					$strReturn =
						'<a href="'.$strImageUrl.'" title="'.$sPopupTitle.'" target="_blank">'.
						'<img src="'.$strImage.'" '.$sParams.' width="'.$iWidth.'" height="'.$iHeight.' title="'.htmlspecialcharsEx($sPopupTitle).'" />'.
						'</a>';
				}
				else
				{
					CFile::OutputJSImgShw();

					$strReturn =
						'<a title="'.$sPopupTitle.'" onclick="ImgShw(\''.CUtil::addslashes($strImage).'\', '.$intWidth.', '.$intHeight.', \''.CUtil::addslashes(htmlspecialcharsEx(htmlspecialcharsEx($strAlt))).'\'); return false;" href="'.$strImage.'" target="_blank">'.
						'<img src="'.$strImage.'" '.$sParams.' width="'.$iWidth.'" height="'.$iHeight.'" />'.
						'</a>';
				}
			}
		}

		return $bPopup? $strReturn : print_url($strImageUrl, $strReturn);
	}

	
	/**
	 * <p>Функция возвращает HTML для показа изображения при клике на которое в отдельном popup-окне отображается другое изображение.</p>
	 *
	 *
	 *
	 *
	 * @param mixed $image1  ID файла или путь к файлу на текущем сайте либо URL к файлу лежащем
	 * на другом сайте. Если задается путь к файлу на текущем сайте, то
	 * его необходимо задавать относительно корня. В данном параметре
	 * задается изображение для первоначального показа.
	 *
	 *
	 *
	 * @param mixed $image2  ID файла или путь к файлу на текущем сайте либо URL к файлу лежащем
	 * на другом сайте. Если задается путь к файлу на текущем сайте, то
	 * его необходимо задавать относительно корня. В данном параметре
	 * задается изображение для показа в popup-окне.
	 *
	 *
	 *
	 * @param int $max_width = 0 Максимальная ширина первоначального изображения. Если ширина
	 * картинки больше <i>max_width</i>, то она будет пропорционально
	 * смаштабирована. <br>Необязательный. По умолчанию - "0" - без
	 * ограничений.
	 *
	 *
	 *
	 * @param int $max_height = 0 Максимальная высота первоначального изображения. Если высота
	 * картинки больше <i>max_height</i>, то она будет пропорционально
	 * смаштабирована. <br>Необязательный. По умолчанию - "0" - без
	 * ограничений.
	 *
	 *
	 *
	 * @param string $image_params = "border=0" Произвольный HTML добавляемый в тэг IMG первоначального
	 * изображения:<br> &lt;img <i>image_params</i> ...&gt; <br>Необязательный. По
	 * умолчанию "border=0".
	 *
	 *
	 *
	 * @param string $popup_alt = false Текст всплывающей подсказки на изображении.<br>Необязательный. По
	 * умолчанию выводится фраза "Нажмите чтобы увеличить" на языке
	 * страницы.
	 *
	 *
	 *
	 * @param int $image_width = 0 Ширина изображения (в пикселах) (только если в параметре <i>image</i>
	 * задан URL начинающийся с "http://") <br>Необязательный. По умолчанию "0".
	 *
	 *
	 *
	 * @param int $image_height = 0 Высота изображения (в пикселах) (только если в параметре <i>image</i>
	 * задан URL начинающийся с "http://") <br>Необязательный. По умолчанию "0".
	 *
	 *
	 *
	 * @return string 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * if ($rsElements = GetIBlockElementListEx($IBLOCK_TYPE, $IBLOCK_ID, false, array($ELEMENT_SORT_FIELD =&gt; $ELEMENT_SORT_ORDER, "ID" =&gt; "ASC"), false, $arrFilter)):
	 *     $rsElements-&gt;NavStart($PAGE_ELEMENT_COUNT);
	 * 	while ($obElement = $rsElements-&gt;GetNextElement()):
	 * 		$arElement = $obElement-&gt;GetFields();
	 * 		$image1 = intval($arElement["PREVIEW_PICTURE"])&lt;=0 ? $arElement["DETAIL_PICTURE"] : $arElement["PREVIEW_PICTURE"];
	 * 		$image2 = intval($arElement["DETAIL_PICTURE"])&lt;=0 ? $arElement["PREVIEW_PICTURE"] : $arElement["DETAIL_PICTURE"];
	 * 		echo <b>CFile::Show2Images</b>($image1, $image2, 150, 150, "hspace='0' vspace='0' border='0' title='".$arElement["NAME"]."'", true);
	 * 	endwhile;
	 * endif;
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul><li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/showimage.php">CFile::ShowImage</a> </li></ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/show2images.php
	 * @author Bitrix
	 */
	public static function Show2Images($strImage1, $strImage2, $iMaxW=0, $iMaxH=0, $sParams=false, $sPopupTitle=false, $iSizeWHTTP=0, $iSizeHHTTP=0)
	{
		if(!($arImgParams = CFile::_GetImgParams($strImage1, $iSizeWHTTP, $iSizeHHTTP)))
			return "";

		$strImage1 = htmlspecialcharsbx($arImgParams["SRC"]);
		$intWidth = $arImgParams["WIDTH"];
		$intHeight = $arImgParams["HEIGHT"];
		$strAlt = $arImgParams["ALT"];

		if($sParams == false)
			$sParams = 'border="0" alt="'.htmlspecialcharsEx($strAlt).'"';
		elseif(!preg_match("/(^|\\s)alt\\s*=\\s*([\"']?)(.*?)(\\2)/is", $sParams))
			$sParams .= ' alt="'.htmlspecialcharsEx($strAlt).'"';

		if(
			$iMaxW > 0 && $iMaxH > 0
			&& ($intWidth > $iMaxW || $intHeight > $iMaxH)
		)
		{
			$coeff = ($intWidth/$iMaxW > $intHeight/$iMaxH? $intWidth/$iMaxW : $intHeight/$iMaxH);
			$iHeight = intval(roundEx($intHeight/$coeff));
			$iWidth = intval(roundEx($intWidth/$coeff));
		}
		else
		{
			$iHeight = $intHeight;
			$iWidth = $intWidth;
		}

		if($arImgParams = CFile::_GetImgParams($strImage2, $iSizeWHTTP, $iSizeHHTTP))
		{
			if($sPopupTitle === false)
				$sPopupTitle = GetMessage("FILE_ENLARGE");

			$strImage2 = htmlspecialcharsbx($arImgParams["SRC"]);
			$intWidth2 = $arImgParams["WIDTH"];
			$intHeight2 = $arImgParams["HEIGHT"];
			$strAlt2 = $arImgParams["ALT"];

			CFile::OutputJSImgShw();

			$strReturn =
				"<a title=\"".$sPopupTitle."\" onclick=\"ImgShw('".CUtil::addslashes($strImage2)."','".$intWidth2."','".$intHeight2."', '".CUtil::addslashes(htmlspecialcharsEx(htmlspecialcharsEx($strAlt2)))."'); return false;\" href=\"".$strImage2."\" target=_blank>".
				"<img src=\"".$strImage1."\" ".$sParams." width=".$iWidth." height=".$iHeight." /></a>";
		}
		else
		{
			$strReturn = "<img src=\"".$strImage1."\" ".$sParams." width=".$iWidth." height=".$iHeight." />";
		}

		return $strReturn;
	}

	
	/**
	 * <p>Функция формирует массив описывающий файл. Структура массива аналогична структуре массива $_FILES[имя] (или $HTTP_POST_FILES[имя]). Данный массив может быть использован в функциях <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/savefile.php">SaveFile</a>, <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkfile.php">CheckFile</a>, <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkimagefile.php">CheckImageFile</a>. Структура возвращаемого массива:<br><br></p> <pre>Array( "name" =&gt; "название файла", "size" =&gt; "размер", "tmp_name" =&gt; "временный путь на сервере", "type" =&gt; "тип загружаемого файла")</pre>
	 *
	 *
	 *
	 *
	 * @param mixed $file  одно из значений: <ul> <li>ID файла</li> <li>абсолютный путь к файлу</li> <li>URL
	 * к файлу лежащем на другом сайте.</li> </ul>
	 *
	 *
	 *
	 * @param mixed $mime_type = false MIME тип файла (например, "image/gif").<br>Необязательный. По умолчанию -
	 * "false" - MIME тип файла будет определён автоматически.
	 *
	 *
	 *
	 * @return array 
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?
	 * $arFile = <b>CFile::MakeFileArray</b>($_SERVER["DOCUMENT_ROOT"]."/images/screen.gif");
	 * $arFile["MODULE_ID"] = "support";
	 * $arFields = array(
	 *   "CREATED_MODULE_NAME"   =&gt; "mail",
	 *   "MODIFIED_MODULE_NAME"  =&gt; "mail",
	 *   "OWNER_SID"             =&gt; "user@mail.ru",
	 *   "SOURCE_SID"            =&gt; "email",
	 *   "MESSAGE_AUTHOR_SID"    =&gt; "user@mail.ru",
	 *   "MESSAGE_SOURCE_SID"    =&gt; "email",
	 *   "TITLE"                 =&gt; "title",
	 *   "MESSAGE"               =&gt; "message"
	 *   "FILES"                 =&gt; array($arFile)
	 *   );
	 * //$TICKET_ID = 866;
	 * $NEW_TICKET_ID = CTicket::Set($arFields, $MESSAGE_ID, $TICKET_ID, "N");
	 * ?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <ul> <li> <a href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/savefile.php">CFile::SaveFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkfile.php">CFile::CheckFile</a> </li> <li> <a
	 * href="http://dev.1c-bitrix.ruapi_help/main/reference/cfile/checkimagefile.php">CFile::CheckImageFile</a> </li> </ul><a
	 * name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/makefilearray.php
	 * @author Bitrix
	 */
	public static function MakeFileArray($path, $mimetype=false)
	{
		$io = CBXVirtualIo::GetInstance();
		$arFile = array();

		if(intval($path)>0)
		{
			$res = CFile::GetByID($path);
			if($ar = $res->Fetch())
			{
				$bExternalStorage = false;
				foreach(GetModuleEvents("main", "OnMakeFileArray", true) as $arEvent)
				{
					if(ExecuteModuleEventEx($arEvent, array($ar, &$arFile)))
					{
						$bExternalStorage = true;
						break;
					}
				}

				if(!$bExternalStorage)
				{
					$arFile["name"] = (strlen($ar['ORIGINAL_NAME'])>0?$ar['ORIGINAL_NAME']:$ar['FILE_NAME']);
					$arFile["size"] = $ar['FILE_SIZE'];
					$arFile["type"] = $ar['CONTENT_TYPE'];
					$arFile["description"] = $ar['DESCRIPTION'];
					$arFile["tmp_name"] = $io->GetPhysicalName(preg_replace("#[\\\\\\/]+#", "/", $_SERVER['DOCUMENT_ROOT'].'/'.(COption::GetOptionString('main', 'upload_dir', 'upload')).'/'.$ar['SUBDIR'].'/'.$ar['FILE_NAME']));
				}
				return $arFile;
			}
		}

		$path = preg_replace("#(?<!:)[\\\\\\/]+#", "/", $path);

		if(strlen($path) == 0 || $path == "/")
			return NULL;

		if(preg_match("#^(http[s]?)://#", $path))
		{
			$temp_path = '';
			$bExternalStorage = false;
			foreach(GetModuleEvents("main", "OnMakeFileArray", true) as $arEvent)
			{
				if(ExecuteModuleEventEx($arEvent, array($path, &$temp_path)))
				{
					$bExternalStorage = true;
					break;
				}
			}

			if(!$bExternalStorage)
			{
				$temp_path = CFile::GetTempName('', bx_basename($path));
				$ob = new CHTTP;
				$ob->follow_redirect = true;
				if($ob->Download($path, $temp_path))
					$arFile = CFile::MakeFileArray($temp_path);
			}
			elseif($temp_path)
			{
				$arFile = CFile::MakeFileArray($temp_path);
			}
		}
		elseif(preg_match("#^(ftp[s]?|php)://#", $path))
		{
			if($fp = fopen($path,"rb"))
			{
				$content = "";
				while(!feof($fp))
					$content .= fgets($fp, 4096);

				if(strlen($content) > 0)
				{
					$temp_path = CFile::GetTempName('', bx_basename($path));
					if (RewriteFile($temp_path, $content))
						$arFile = CFile::MakeFileArray($temp_path);
				}

				fclose($fp);
			}
		}
		else
		{
			if(!file_exists($path))
			{
				if (file_exists($_SERVER["DOCUMENT_ROOT"].$path))
					$path = $_SERVER["DOCUMENT_ROOT"].$path;
				else
					return NULL;
			}

			if(is_dir($path))
				return NULL;

			$arFile["name"] = $io->GetLogicalName(bx_basename($path));
			$arFile["size"] = filesize($path);
			$arFile["tmp_name"] = $path;
			$arFile["type"] = $mimetype;

			if(strlen($arFile["type"])<=0)
				$arFile["type"] = CFile::GetContentType($path, true);
		}

		if(strlen($arFile["type"])<=0)
			$arFile["type"] = "unknown";

		return $arFile;
	}

	public static function GetTempName($dir_name = false, $file_name = '')
	{
		return CTempFile::GetFileName($file_name);
	}

	public static function ChangeSubDir($module_id, $old_subdir, $new_subdir)
	{
		global $DB, $CACHE_MANAGER;

		if ($old_subdir!=$new_subdir)
		{
			$strSql = "
				UPDATE b_file
				SET SUBDIR = REPLACE(SUBDIR,'".$DB->ForSQL($old_subdir)."','".$DB->ForSQL($new_subdir)."')
				WHERE MODULE_ID='".$DB->ForSQL($module_id)."'
			";

			if($rs = $DB->Query($strSql, false, __LINE__))
			{
				$from = "/".COption::GetOptionString("main", "upload_dir", "upload")."/".$old_subdir;
				$to = "/".COption::GetOptionString("main", "upload_dir", "upload")."/".$new_subdir;
				CopyDirFiles($_SERVER["DOCUMENT_ROOT"].$from, $_SERVER["DOCUMENT_ROOT"].$to, true, true, true);
				//Reset All b_file cache
				$CACHE_MANAGER->CleanDir("b_file");
			}
		}
	}

	
	/**
	 * <p>Фукция является оберткой CFile::ResizeImageFile. Изменяет размеры графического файла. </p>
	 *
	 *
	 *
	 *
	 * @param &$arFil $e  массив файлов
	 *
	 *
	 *
	 * @param $arSiz $e  массив размеров файла
	 *
	 *
	 *
	 * @param $resizeTyp $e = BX_RESIZE_IMAGE_PROPORTIONAL тип расширения изменённого файла
	 *
	 *
	 *
	 * @return mixed <p></p>
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * <br><br>
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <p></p><a name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/resizeimage.php
	 * @author Bitrix
	 */
	public static function ResizeImage(&$arFile, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL)
	{
		$sourceFile = $arFile["tmp_name"];
		$destinationFile = CTempFile::GetFileName(basename($sourceFile));

		CheckDirPath($destinationFile);

		if (CFile::ResizeImageFile($sourceFile, $destinationFile, $arSize, $resizeType))
		{
			$arFile["tmp_name"] = $destinationFile;
			$arImageSize = CFile::GetImageSize($destinationFile);
			$arFile["type"] = $arImageSize["mime"];
			$arFile["size"] = filesize($arFile["tmp_name"]);

			return true;
		}

		return false;
	}

	public static function ResizeImageDeleteCache($arFile)
	{
		$temp_dir = CTempFile::GetAbsoluteRoot()."/";
		if(strpos($arFile["tmp_name"], $temp_dir) === 0)
			if(file_exists($arFile["tmp_name"]))
				unlink($arFile["tmp_name"]);
	}

	public static function ResizeImageGet($file, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL, $bInitSizes = false, $arFilters = false, $bImmediate = false, $jpgQuality = false)
	{
		if (!is_array($file) && intval($file) > 0)
		{
			$file = CFile::GetFileArray($file);
		}

		if (!is_array($file) || !array_key_exists("FILE_NAME", $file) || strlen($file["FILE_NAME"]) <= 0)
			return false;

		if ($resizeType != BX_RESIZE_IMAGE_EXACT && $resizeType != BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
			$resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;

		if (!is_array($arSize))
			$arSize = array();
		if (!array_key_exists("width", $arSize) || intval($arSize["width"]) <= 0)
			$arSize["width"] = 0;
		if (!array_key_exists("height", $arSize) || intval($arSize["height"]) <= 0)
			$arSize["height"] = 0;
		$arSize["width"] = intval($arSize["width"]);
		$arSize["height"] = intval($arSize["height"]);

		$uploadDirName = COption::GetOptionString("main", "upload_dir", "upload");

		$imageFile = "/".$uploadDirName."/".$file["SUBDIR"]."/".$file["FILE_NAME"];
		$arImageSize = false;
		$bFilters = is_array($arFilters) && !empty($arFilters);

		if (
			($arSize["width"] <= 0 || $arSize["width"] >= $file["WIDTH"])
			&& ($arSize["height"] <= 0 || $arSize["height"] >= $file["HEIGHT"])
		)
		{
			if($bFilters)
			{
				//Only filters. Leave size unchanged
				$arSize["width"] = $file["WIDTH"];
				$arSize["height"] = $file["HEIGHT"];
				$resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;
			}
			else
			{
				global $arCloudImageSizeCache;
				$arCloudImageSizeCache[$file["SRC"]] = array($file["WIDTH"], $file["HEIGHT"]);

				return array(
					"src" => $file["SRC"],
					"width" => intval($file["WIDTH"]),
					"height" => intval($file["HEIGHT"]),
					"size" => $file["FILE_SIZE"],
				);
			}
		}

		$io = CBXVirtualIo::GetInstance();
		$cacheImageFile = "/".$uploadDirName."/resize_cache/".$file["SUBDIR"]."/".$arSize["width"]."_".$arSize["height"]."_".$resizeType.(is_array($arFilters)? md5(serialize($arFilters)): "")."/".$file["FILE_NAME"];

		$cacheImageFileCheck = $cacheImageFile;
		if ($file["CONTENT_TYPE"] == "image/bmp")
			$cacheImageFileCheck .= ".jpg";

		static $cache = array();
		$cache_id = $cacheImageFileCheck;
		if(isset($cache[$cache_id]))
		{
			return $cache[$cache_id];
		}
		elseif (!file_exists($io->GetPhysicalName($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck)))
		{
			/****************************** QUOTA ******************************/
			$bDiskQuota = true;
			if (COption::GetOptionInt("main", "disk_space") > 0)
			{
				$quota = new CDiskQuota();
				$bDiskQuota = $quota->checkDiskQuota($file);
			}
			/****************************** QUOTA ******************************/

			if ($bDiskQuota)
			{
				if(!is_array($arFilters))
					$arFilters = array(
						array("name" => "sharpen", "precision" => 15),
					);

				$sourceImageFile = $_SERVER["DOCUMENT_ROOT"].$imageFile;
				$cacheImageFileTmp = $_SERVER["DOCUMENT_ROOT"].$cacheImageFile;
				$bNeedResize = true;
				$callbackData = null;

				foreach(GetModuleEvents("main", "OnBeforeResizeImage", true) as $arEvent)
				{
					if(ExecuteModuleEventEx($arEvent, array(
						$file,
						array($arSize, $resizeType, array(), false, $arFilters, $bImmediate),
						&$callbackData,
						&$bNeedResize,
						&$sourceImageFile,
						&$cacheImageFileTmp,
					)))
						break;
				}

				if ($bNeedResize && CFile::ResizeImageFile($sourceImageFile, $cacheImageFileTmp, $arSize, $resizeType, array(), $jpgQuality, $arFilters))
				{
					$cacheImageFile = substr($cacheImageFileTmp, strlen($_SERVER["DOCUMENT_ROOT"]));

					/****************************** QUOTA ******************************/
					if (COption::GetOptionInt("main", "disk_space") > 0)
						CDiskQuota::updateDiskQuota("file", filesize($io->GetPhysicalName($cacheImageFileTmp)), "insert");
					/****************************** QUOTA ******************************/
				}
				else
				{
					$cacheImageFile = $imageFile;
				}

				foreach(GetModuleEvents("main", "OnAfterResizeImage", true) as $arEvent)
				{
					if(ExecuteModuleEventEx($arEvent, array(
						$file,
						array($arSize, $resizeType, array(), false, $arFilters),
						&$callbackData,
						&$cacheImageFile,
						&$cacheImageFileTmp,
						&$arImageSize,
					)))
						break;
				}
			}
			else
			{
				$cacheImageFile = $imageFile;
			}

			$cacheImageFileCheck = $cacheImageFile;
		}

		if ($bInitSizes && !is_array($arImageSize))
		{
			$arImageSize = CFile::GetImageSize($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck);

			$f = $io->GetFile($_SERVER["DOCUMENT_ROOT"].$cacheImageFileCheck);
			$arImageSize[2] = $f->GetFileSize();
		}

		$cache[$cache_id] = array(
			"src" => $cacheImageFileCheck,
			"width" => intval($arImageSize[0]),
			"height" => intval($arImageSize[1]),
			"size" => $arImageSize[2],
		);
		return $cache[$cache_id];
	}

	public static function ResizeImageDelete($arImage)
	{
		$io = CBXVirtualIo::GetInstance();
		$upload_dir = COption::GetOptionString("main", "upload_dir", "upload");
		$disk_space = COption::GetOptionInt("main", "disk_space");
		$delete_size = 0;

		$d = $io->GetDirectory($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/resize_cache/".$arImage["SUBDIR"]);

		/** @var CBXVirtualFileFileSystem|CBXVirtualDirectoryFileSystem $dir_entry */
		foreach($d->GetChildren() as $dir_entry)
		{
			if($dir_entry->IsDirectory())
			{
				$f = $io->GetFile($dir_entry->GetPathWithName()."/".$arImage["FILE_NAME"]);
				if($f->IsExists())
				{
					if ($disk_space > 0)
					{
						$fileSizeTmp = $f->GetFileSize();
						if ($io->Delete($f->GetPathWithName()))
							$delete_size += $fileSizeTmp;
					}
					else
					{
						$io->Delete($f->GetPathWithName());
					}
				}
				@rmdir($io->GetPhysicalName($dir_entry->GetPathWithName()));
			}
		}
		@rmdir($io->GetPhysicalName($d->GetPathWithName()));

		return $delete_size;
	}

	public static function ImageCreateFromBMP($filename)
	{
		if(!$f1 = fopen($filename,"rb"))
			return false;

		//1 : read and parse HEADER
		$FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
		if ($FILE['file_type'] != 19778)
			return false;

		//2 : read and parse BMP data
		$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
			'/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
			'/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));

		//DDoS protection
		if($BMP['width'] > 65535)
			$BMP['width'] = 65535;
		if($BMP['height'] > 65535)
			$BMP['height'] = 65535;

		$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);

		if($BMP['colors_used'] > 0)
			$BMP['palette_size'] = $BMP['colors_used'];
		else
			$BMP['palette_size'] = $BMP['colors'];

		if ($BMP['size_bitmap'] == 0)
			$BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
		$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
		$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
		$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] = 4-(4*$BMP['decal']);
		if ($BMP['decal'] == 4)
			$BMP['decal'] = 0;

		//3 : Read palette
		$PALETTE = array();
		if ($BMP['colors'] < 16777216)
		{
			$PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
		}

		//4 : Create an image canvas to draw on
		$res = imagecreatetruecolor($BMP['width'],$BMP['height']);
		$VIDE = chr(0);
		if($BMP['bits_per_pixel'] == 32)
		{
			$dPY = $BMP['decal'];
			$width = $BMP['width'];
			$Y = $BMP['height'] - 1;
			while ($Y >= 0)
			{
				$X = 0;
				while($X < $width)
				{
					$COLOR = unpack("C4", fread($f1, 4));
					imagesetpixel($res, $X, $Y, ($COLOR[3]<<16) | ($COLOR[2]<<8) | ($COLOR[1]));
					$X++;
				}
				$Y--;
				if($dPY > 0)
					fread($f1, $dPY);
				if (feof($f1))
					break;
			}
		}
		elseif($BMP['bits_per_pixel'] == 24)
		{
			$dPY = $BMP['decal'];
			$width = $BMP['width'];
			$Y = $BMP['height'] - 1;
			while ($Y >= 0)
			{
				$X = 0;
				while($X < $width)
				{
					$COLOR = unpack("V", fread($f1, 3).$VIDE);
					imagesetpixel($res, $X, $Y, $COLOR[1]);
					$X++;
				}
				$Y--;
				if($dPY > 0)
					fread($f1, $dPY);
				if (feof($f1))
					break;
			}
		}
		elseif($BMP['bits_per_pixel'] == 16 && $BMP['compression'] == 0)
		{
			fseek($f1, $FILE['bitmap_offset'], SEEK_SET);
			$dPY = $BMP['decal'];
			$width = $BMP['width'];
			$Y = $BMP['height'] - 1;
			while ($Y >= 0)
			{
				$X = 0;
				while($X < $width)
				{
					$COLOR = unpack("C2", fread($f1, 2));
					$R = ($COLOR[2] >> 2)  & 0x1f;
					$G = (($COLOR[2] & 0x03) << 3) | ($COLOR[1] >> 5);
					$B = $COLOR[1] & 0x1f;
					imagesetpixel($res, $X, $Y, (($R*8)<<16) | (($G*8)<<8) | ($B*8));
					$X++;
				}
				$Y--;
				if($dPY > 0)
					fread($f1, $dPY);
				if (feof($f1))
					break;
			}
		}
		elseif($BMP['bits_per_pixel'] == 16)
		{
			fseek($f1, $FILE['bitmap_offset'], SEEK_SET);
			$dPY = $BMP['decal'];
			$width = $BMP['width'];
			$Y = $BMP['height'] - 1;
			while ($Y >= 0)
			{
				$X = 0;
				while($X < $width)
				{
					$COLOR = unpack("C2", fread($f1, 2));
					$R = $COLOR[2] >> 3;
					$G = ($COLOR[2] & 0x07) << 3 | ($COLOR[1] >> 5);
					$B = $COLOR[1] & 0x1f;
					imagesetpixel($res, $X, $Y, (($R*8)<<16) | (($G*4)<<8) | ($B*8));
					$X++;
				}
				$Y--;
				if($dPY > 0)
					fread($f1, $dPY);
				if (feof($f1))
					break;
			}
		}
		elseif($BMP['bits_per_pixel'] == 8)
		{
			fseek($f1, $FILE['bitmap_offset'], SEEK_SET);
			$dPY = $BMP['decal'];
			$width = $BMP['width'];
			$Y = $BMP['height'] - 1;
			while ($Y >= 0)
			{
				$X = 0;
				while($X < $width)
				{
					$COLOR = unpack("n", $VIDE.fread($f1, 1));
					imagesetpixel($res, $X, $Y, $PALETTE[$COLOR[1]+1]);
					$X++;
				}
				$Y--;
				if($dPY > 0)
					fread($f1, $dPY);
				if (feof($f1))
					break;
			}
		}
		elseif ($BMP['bits_per_pixel'] == 4)
		{
			$IMG = fread($f1, $BMP['size_bitmap']);
			$P = 0;
			$Y = $BMP['height']-1;
			while ($Y >= 0)
			{
				$X = 0;
				$COLORS = unpack("H*", cutil::binsubstr($IMG, floor($P), floor($P)+$BMP['width']*$BMP['bytes_per_pixel']));
				while ($X < $BMP['width'])
				{
					$C = hexdec($COLORS[1][$X]);
					imagesetpixel($res, $X, $Y, $PALETTE[$C+1]);
					$X++;
					$P += $BMP['bytes_per_pixel'];
				}
				$Y--;
				$P += $BMP['decal'];
				if (feof($f1))
					break;
			}
		}
		elseif ($BMP['bits_per_pixel'] == 1)
		{
			$COLORS = unpack("H*", fread($f1,$BMP['size_bitmap']));
			$i = 0;
			$P = 0;
			$Y = $BMP['height']-1;
			while ($Y >= 0)
			{
				$i = (int)floor($P)*2;
				$X = 0;
				while ($X < $BMP['width'])
				{
					$C = hexdec($COLORS[1][$i]);
					imagesetpixel($res, $X, $Y, $PALETTE[$C & 8? 2: 1]);
					$X++;
					$P += $BMP['bytes_per_pixel'];
					if ($X < $BMP['width'])
					{
						imagesetpixel($res, $X, $Y, $PALETTE[$C & 4? 2: 1]);
						$X++;
						$P += $BMP['bytes_per_pixel'];
						if ($X < $BMP['width'])
						{
							imagesetpixel($res, $X, $Y, $PALETTE[$C & 2? 2: 1]);
							$X++;
							$P += $BMP['bytes_per_pixel'];
							if ($X < $BMP['width'])
							{
								imagesetpixel($res, $X, $Y, $PALETTE[$C & 1? 2: 1]);
								$X++;
								$P += $BMP['bytes_per_pixel'];
							}
						}
					}
					$i++;
				}
				$Y--;
				$P += $BMP['decal'];
			}
		}
		else
		{
			return false;
		}
		fclose($f1);

		return $res;
	}

	public static function ScaleImage($sourceImageWidth, $sourceImageHeight, $arSize, $resizeType, &$bNeedCreatePicture, &$arSourceSize, &$arDestinationSize)
	{
		if (!is_array($arSize))
			$arSize = array();
		if (!array_key_exists("width", $arSize) || intval($arSize["width"]) <= 0)
			$arSize["width"] = 0;
		if (!array_key_exists("height", $arSize) || intval($arSize["height"]) <= 0)
			$arSize["height"] = 0;
		$arSize["width"] = intval($arSize["width"]);
		$arSize["height"] = intval($arSize["height"]);

		$bNeedCreatePicture = false;
		$arSourceSize = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);
		$arDestinationSize = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);

		if ($sourceImageWidth > 0 && $sourceImageHeight > 0)
		{
			if ($arSize["width"] > 0 && $arSize["height"] > 0)
			{
				switch ($resizeType)
				{
					case BX_RESIZE_IMAGE_EXACT:
						$bNeedCreatePicture = true;

						$ratio = (($sourceImageWidth / $sourceImageHeight) < ($arSize["width"] / $arSize["height"])) ?
							$arSize["width"] / $sourceImageWidth : $arSize["height"] / $sourceImageHeight;

						$x = max(0, round($sourceImageWidth / 2 - ($arSize["width"] / 2) / $ratio));
						$y = max(0, round($sourceImageHeight / 2 - ($arSize["height"] / 2) / $ratio));

						$arDestinationSize["width"] = $arSize["width"];
						$arDestinationSize["height"] = $arSize["height"];

						$arSourceSize["x"] = $x;
						$arSourceSize["y"] = $y;
						$arSourceSize["width"] = round($arSize["width"] / $ratio, 0);
						$arSourceSize["height"] = round($arSize["height"] / $ratio, 0);

						break;
					default:
						if ($resizeType == BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
						{
							$width = Max($sourceImageWidth, $sourceImageHeight);
							$height = Min($sourceImageWidth, $sourceImageHeight);
						}
						else
						{
							$width = $sourceImageWidth;
							$height = $sourceImageHeight;
						}
						$ResizeCoeff["width"] = $arSize["width"] / $width;
						$ResizeCoeff["height"] = $arSize["height"] / $height;

						$iResizeCoeff = Min($ResizeCoeff["width"], $ResizeCoeff["height"]);
						$iResizeCoeff = ((0 < $iResizeCoeff) && ($iResizeCoeff < 1) ? $iResizeCoeff : 1);
						$bNeedCreatePicture = ($iResizeCoeff != 1 ? true : false);

						$arDestinationSize["width"] = max(1, intval($iResizeCoeff * $sourceImageWidth));
						$arDestinationSize["height"] = max(1, intval($iResizeCoeff * $sourceImageHeight));

						$arSourceSize["x"] = 0;
						$arSourceSize["y"] = 0;
						$arSourceSize["width"] = $sourceImageWidth;
						$arSourceSize["height"] = $sourceImageHeight;
						break;
				}
			}
			else
			{
				$arSourceSize = array("x" => 0, "y" => 0, "width" => $sourceImageWidth, "height" => $sourceImageHeight);
				$arDestinationSize = array("x" => 0, "y" => 0, "width" => $sourceImageWidth, "height" => $sourceImageHeight);
			}
		}
	}

	function IsGD2()
	{
		static $bGD2 = false;
		static $bGD2Initial = false;

		if (!$bGD2Initial && function_exists("gd_info"))
		{
			$arGDInfo = gd_info();
			$bGD2 = ((StrPos($arGDInfo['GD Version'], "2.") !== false) ? true : false);
			$bGD2Initial = true;
		}

		return $bGD2;
	}

	
	/**
	 * <p>Функция производит изменение размера графического файла. Если исходный файл с расширением BMP, то файл-результат будет переконвертирован как JPEG и в <b>destinationFile</b> вернется модифицированное имя.</p>
	 *
	 *
	 *
	 *
	 * @param $sourceFil $e  Путь к исходному файлу
	 *
	 *
	 *
	 * @param &$destinationFil $e  Путь к файлу - результату обработки.
	 *
	 *
	 *
	 * @param $arSiz $e  Массив размеров файла
	 *
	 *
	 *
	 * @param $resizeTyp $e = BX_RESIZE_IMAGE_PROPORTIONAL Тип нового файла.
	 *
	 *
	 *
	 * @param $arWaterMar $k = array() массив с параметрами водяного знака, ключи: <ul> <li> <b>text</b> - текст</li>
	 * <li> <b>path_to_font</b> - путь к шрифту (TTF/UTF-8)</li> <li> <b>min_size_picture</b> -
	 * минимальная ширина картинки</li> <li> <b>color</b> - цвет "RRGGBB"</li> <li> <b>size</b> -
	 * размер, "big"/"small"/другое</li> <li> <b>position</b> - "{m|b}{c|r}", где <b>m</b> - центр по
	 * вертикали, <b>b</b> - низ, <b>c</b> - центр по горизонтали, <b>r</b> - правый
	 * край.</li> </ul>
	 *
	 *
	 *
	 * @param $jpgQualit $y = false Величина JPG-сжатия
	 *
	 *
	 *
	 * @param $arFilter $s = false Массив параметров фильтра.
	 *
	 *
	 *
	 * @return mixed <p>описание возвращаемого значения.</p>
	 *
	 *
	 * <h4>Example</h4> 
	 * <pre>
	 * &lt;?<br>Пару строчек примера<br>желательно имеющего не только академическую ценность<br>?&gt;
	 * </pre>
	 *
	 *
	 *
	 * <h4>See Also</h4> 
	 * <a name="examples"></a>
	 *
	 *
	 * @static
	 * @link http://dev.1c-bitrix.ru/api_help/main/reference/cfile/resizeimagefile.php
	 * @author Bitrix
	 */
	public static function ResizeImageFile($sourceFile, &$destinationFile, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL, $arWaterMark = array(), $jpgQuality=false, $arFilters=false)
	{
		$io = CBXVirtualIo::GetInstance();

		if (!$io->FileExists($sourceFile))
			return false;

		$bNeedCreatePicture = false;

		if ($resizeType != BX_RESIZE_IMAGE_EXACT && $resizeType != BX_RESIZE_IMAGE_PROPORTIONAL_ALT)
			$resizeType = BX_RESIZE_IMAGE_PROPORTIONAL;

		if (!is_array($arSize))
			$arSize = array();
		if (!array_key_exists("width", $arSize) || intval($arSize["width"]) <= 0)
			$arSize["width"] = 0;
		if (!array_key_exists("height", $arSize) || intval($arSize["height"]) <= 0)
			$arSize["height"] = 0;
		$arSize["width"] = intval($arSize["width"]);
		$arSize["height"] = intval($arSize["height"]);

		$arSourceSize = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);
		$arDestinationSize = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);

		$arSourceFileSizeTmp = CFile::GetImageSize($sourceFile);
		if (!in_array($arSourceFileSizeTmp[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_BMP)))
			return false;

		if (class_exists("imagick") && function_exists('memory_get_usage'))
		{
			//When memory limit reached we'll try to use ImageMagic
			$memoryNeeded = round(($arSourceFileSizeTmp[0] * $arSourceFileSizeTmp[1] * $arSourceFileSizeTmp['bits'] * ($arSourceFileSizeTmp['channels'] > 0? $arSourceFileSizeTmp['channels']: 1) / 8 + pow(2, 16)) * 1.65);
			$memoryLimit = CUtil::Unformat(ini_get('memory_limit'));
			if ((memory_get_usage() + $memoryNeeded) > $memoryLimit)
			{
				if ($arSize["width"] <= 0 || $arSize["height"] <= 0)
				{
					$arSize["width"] = $arSourceFileSizeTmp[0];
					$arSize["height"] = $arSourceFileSizeTmp[1];
				}
				CFile::ScaleImage($arSourceFileSizeTmp[0], $arSourceFileSizeTmp[1], $arSize, $resizeType, $bNeedCreatePicture, $arSourceSize, $arDestinationSize);
				if ($bNeedCreatePicture)
				{
					$new_image = CTempFile::GetFileName(bx_basename($sourceFile));
					CheckDirPath($new_image);
					$im = new Imagick();
					try
					{
						$im->setSize($arDestinationSize["width"], $arDestinationSize["height"]);
						$im->readImage($io->GetPhysicalName($sourceFile));
						$im->setImageFileName($new_image);
						$im->thumbnailImage($arDestinationSize["width"], $arDestinationSize["height"], true);
						$im->writeImage();
						$im->destroy();
					} catch (ImagickException $e) {
						$new_image = "";
					}

					if($new_image != "")
					{
						$sourceFile = $new_image;
						$arSourceFileSizeTmp = CFile::GetImageSize($io->GetPhysicalName($sourceFile));
					}
				}
			}
		}

		if ($io->Copy($sourceFile, $destinationFile))
		{
			switch ($arSourceFileSizeTmp[2])
			{
				case IMAGETYPE_GIF:
					$sourceImage = imagecreatefromgif($io->GetPhysicalName($sourceFile));
					$bHasAlpha = true;
					break;
				case IMAGETYPE_PNG:
					$sourceImage = imagecreatefrompng($io->GetPhysicalName($sourceFile));
					$bHasAlpha = true;
					break;
				case IMAGETYPE_BMP:
					$sourceImage = CFile::ImageCreateFromBMP($io->GetPhysicalName($sourceFile));
					$bHasAlpha = false;
					break;
				default:
					$sourceImage = imagecreatefromjpeg($io->GetPhysicalName($sourceFile));
					$bHasAlpha = false;
					break;
			}

			$sourceImageWidth = intval(imagesx($sourceImage));
			$sourceImageHeight = intval(imagesy($sourceImage));

			if ($sourceImageWidth > 0 && $sourceImageHeight > 0)
			{
				if ($arSize["width"] <= 0 || $arSize["height"] <= 0)
				{
					$arSize["width"] = $sourceImageWidth;
					$arSize["height"] = $sourceImageHeight;
				}

				CFile::ScaleImage($sourceImageWidth, $sourceImageHeight, $arSize, $resizeType, $bNeedCreatePicture, $arSourceSize, $arDestinationSize);

				if ($bNeedCreatePicture)
				{
					if (CFile::IsGD2())
					{
						$picture = ImageCreateTrueColor($arDestinationSize["width"], $arDestinationSize["height"]);
						if($arSourceFileSizeTmp[2] == IMAGETYPE_PNG)
						{
							$transparentcolor = imagecolorallocatealpha($picture, 0, 0, 0, 127);
							imagefilledrectangle($picture, 0, 0, $arDestinationSize["width"], $arDestinationSize["height"], $transparentcolor);

							imagealphablending($picture, false);
							imagecopyresampled($picture, $sourceImage,
								0, 0, $arSourceSize["x"], $arSourceSize["y"],
								$arDestinationSize["width"], $arDestinationSize["height"], $arSourceSize["width"], $arSourceSize["height"]);
							imagealphablending($picture, true);
						}
						elseif($arSourceFileSizeTmp[2] == IMAGETYPE_GIF)
						{
							imagepalettecopy($picture, $sourceImage);

							//Save transparency for GIFs
							$transparentcolor = imagecolortransparent($sourceImage);
							if($transparentcolor >= 0 && $transparentcolor < imagecolorstotal($sourceImage))
							{
								$RGB = imagecolorsforindex($sourceImage, $transparentcolor);
								$transparentcolor = imagecolorallocate($picture, $RGB["red"], $RGB["green"], $RGB["blue"]);
								imagecolortransparent($picture, $transparentcolor);
								imagefilledrectangle($picture, 0, 0, $arDestinationSize["width"], $arDestinationSize["height"], $transparentcolor);
							}

							imagecopyresampled($picture, $sourceImage,
								0, 0, $arSourceSize["x"], $arSourceSize["y"],
								$arDestinationSize["width"], $arDestinationSize["height"], $arSourceSize["width"], $arSourceSize["height"]);
						}
						else
						{
							imagecopyresampled($picture, $sourceImage,
								0, 0, $arSourceSize["x"], $arSourceSize["y"],
								$arDestinationSize["width"], $arDestinationSize["height"], $arSourceSize["width"], $arSourceSize["height"]);
						}
					}
					else
					{
						$picture = ImageCreate($arDestinationSize["width"], $arDestinationSize["height"]);
						imagecopyresized($picture, $sourceImage,
							0, 0, $arSourceSize["x"], $arSourceSize["y"],
							$arDestinationSize["width"], $arDestinationSize["height"], $arSourceSize["width"], $arSourceSize["height"]);
					}
				}
				else
				{
					$picture = $sourceImage;
				}

				if(is_array($arFilters))
				{
					foreach($arFilters as $arFilter)
						$bNeedCreatePicture |= CFile::ApplyImageFilter($picture, $arFilter, $bHasAlpha);
				}

				if(is_array($arWaterMark))
				{
					$arWaterMark["name"] = "watermark";
					$bNeedCreatePicture |= CFile::ApplyImageFilter($picture, $arWaterMark, $bHasAlpha);
				}

				if ($bNeedCreatePicture)
				{
					if($io->FileExists($destinationFile))
						$io->Delete($destinationFile);
					switch ($arSourceFileSizeTmp[2])
					{
						case IMAGETYPE_GIF:
							imagegif($picture, $io->GetPhysicalName($destinationFile));
							break;
						case IMAGETYPE_PNG:
							imagealphablending($picture, false );
							imagesavealpha($picture, true);
							imagepng($picture, $io->GetPhysicalName($destinationFile));
							break;
						default:
							if ($arSourceFileSizeTmp[2] == IMAGETYPE_BMP)
								$destinationFile .= ".jpg";
							if($jpgQuality === false)
								$jpgQuality = intval(COption::GetOptionString('main', 'image_resize_quality', '95'));
							if($jpgQuality <= 0 || $jpgQuality > 100)
								$jpgQuality = 95;
							imagejpeg($picture, $io->GetPhysicalName($destinationFile), $jpgQuality);
							break;
					}
					imagedestroy($picture);
				}
			}

			return true;
		}

		return false;
	}

	public static function ApplyImageFilter($picture, $arFilter, $bHasAlpha = true)
	{
		switch($arFilter["name"])
		{
			case "sharpen":
				$precision = intval($arFilter["precision"]);
				if($precision > 0)
				{
					$k = 1/$precision;
					$mask = array(
						array( -$k,    -$k, -$k),
						array( -$k, 1+8*$k, -$k),
						array( -$k,    -$k, -$k)
					);

					if(function_exists("imageconvolution") && !$bHasAlpha)
						imageconvolution($picture, $mask, 1, 0);
					else
						CFile::imageconvolution($picture, $mask, 1, 0);
				}
				return true; //Image was modified
			case "watermark":
				return CFile::WaterMark($picture, $arFilter);
		}
		return null;
	}

	public static function imageconvolution($picture, $matrix, $div = 1, $offset = 0)
	{
		$sx = imagesx($picture);
		$sy = imagesy($picture);
		$backup = imagecreatetruecolor($sx, $sy);
		imagealphablending($backup, false);
		imagecopy($backup, $picture, 0, 0, 0, 0, $sx, $sy);

		for($y = 0; $y < $sy; ++$y)
		{
			for($x = 0; $x < $sx; ++$x)
			{
				$alpha = (imagecolorat($backup, $x, $y) >> 24) & 0xFF;
				$new_r = $new_g = $new_b = 0;

				for ($j = 0; $j < 3; ++$j)
				{
					$yv = $y - 1 + $j;
					if($yv < 0)
						$yv = 0;
					elseif($yv >= $sy)
						$yv = $sy - 1;

					for ($i = 0; $i < 3; ++$i)
					{
						$xv = $x - 1 + $i;
						if($xv < 0)
							$xv = 0;
						elseif($xv >= $sx)
							$xv = $sx - 1;

						$m = $matrix[$j][$i];
						$rgb = imagecolorat($backup, $xv, $yv);
						$new_r += (($rgb >> 16) & 0xFF) * $m;
						$new_g += (($rgb >> 8) & 0xFF) * $m;
						$new_b += ($rgb & 0xFF) * $m;
					}
				}

				$new_r = ($new_r > 255)? 255 : (($new_r < 0)? 0: $new_r);
				$new_g = ($new_g > 255)? 255 : (($new_g < 0)? 0: $new_g);
				$new_b = ($new_b > 255)? 255 : (($new_b < 0)? 0: $new_b);

				$new_pxl = imagecolorallocatealpha($picture, $new_r, $new_g, $new_b, $alpha);
				imagesetpixel($picture, $x, $y, $new_pxl);
			}
		}
		imagedestroy($backup);
	}

	public static function ViewByUser($arFile, $arOptions = array())
	{
		/** @global CMain $APPLICATION */
		global $APPLICATION;
		$fastDownload = (COption::GetOptionString('main', 'bx_fast_download', 'N') == 'Y');

		$content_type = "";
		$specialchars = false;
		$force_download = false;
		$cache_time = 10800;
		$fromClouds = false;

		if(is_array($arOptions))
		{
			if(array_key_exists("content_type", $arOptions))
				$content_type = $arOptions["content_type"];
			if(array_key_exists("specialchars", $arOptions))
				$specialchars = $arOptions["specialchars"];
			if(array_key_exists("force_download", $arOptions))
				$force_download = $arOptions["force_download"];
			if(array_key_exists("cache_time", $arOptions))
				$cache_time = intval($arOptions["cache_time"]);
		}

		if($content_type == '')
		{
			if($arFile["tmp_name"] <> '')
				$content_type = CFile::GetContentType($arFile["tmp_name"], true);
			else
				$content_type = "text/html; charset=".LANG_CHARSET;
		}

		if($force_download)
			$specialchars = false;

		if($cache_time < 0)
			$cache_time = 0;

		if(is_array($arFile))
		{
			if(array_key_exists("SRC", $arFile))
			{
				$filename = $arFile["SRC"];
			}
			elseif(array_key_exists("tmp_name", $arFile))
			{
				$filename = "/".ltrim(substr($arFile["tmp_name"], strlen($_SERVER["DOCUMENT_ROOT"])), "/");
			}
			else
			{
				$filename = CFile::GetFileSRC($arFile);
			}
		}
		else
		{
			if($arFile = CFile::GetFileArray($arFile))
				$filename = $arFile["SRC"];
			else
				$filename = '';
		}

		if($filename == '')
			return false;

		if($arFile["ORIGINAL_NAME"] <> '')
			$name = $arFile["ORIGINAL_NAME"];
		elseif($arFile["name"] <> '')
			$name = $arFile["name"];
		else
			$name = $arFile["FILE_NAME"];
		if(array_key_exists("EXTENSION_SUFFIX", $arFile) && $arFile["EXTENSION_SUFFIX"] <> '')
			$name = substr($name, 0, -strlen($arFile["EXTENSION_SUFFIX"]));

		// ie filename error fix
		$ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
		if (strpos($ua, "opera") === false && strpos($ua, "msie") !== false)
		{
			if (SITE_CHARSET != "UTF-8")
				$name = $APPLICATION->ConvertCharset($name, SITE_CHARSET, "UTF-8");
			$name = str_replace(" ", "%20", $name);
			$name = urlencode($name);
			$name = str_replace(array("%2520", "%2F"), array("%20", "/"), $name);
		}
		else
		{
			$name = str_replace(array("\n", "\r"), '', $name);
		}

		$io = CBXVirtualIo::GetInstance();

		$src = null;
		if(substr($filename, 0, 1) == "/")
		{
			$src = fopen($io->GetPhysicalName($_SERVER["DOCUMENT_ROOT"].$filename), "rb");
			if(!$src)
				return false;
		}
		else
		{
			if(!$fastDownload)
			{
				$src = new CHTTP;
				$src->follow_redirect = true;
			}
			elseif(intval($arFile['HANDLER_ID']) > 0)
			{
				$fromClouds = true;
			}
		}

		$APPLICATION->RestartBuffer();
		while(ob_end_clean());

		$cur_pos = 0;
		$filesize = intval($arFile["FILE_SIZE"]) > 0 ? $arFile["FILE_SIZE"] : $arFile["size"];
		$size = $filesize-1;
		$p = strpos($_SERVER["HTTP_RANGE"], "=");
		if(intval($p)>0)
		{
			$bytes = substr($_SERVER["HTTP_RANGE"], $p+1);
			$p = strpos($bytes, "-");
			if($p !== false)
			{
				$cur_pos = intval(substr($bytes, 0, $p));
				$size = intval(substr($bytes, $p+1));
				if ($size <= 0)
				{
					$size = $filesize - 1;
				}
				if ($cur_pos > $size)
				{
					$cur_pos = 0;
					$size = $filesize - 1;
				}
			}
		}

		if($arFile["tmp_name"] <> '')
			$filetime = filemtime($io->GetPhysicalName($arFile["tmp_name"]));
		else
			$filetime = intval(MakeTimeStamp($arFile["TIMESTAMP_X"]));

		if($_SERVER["REQUEST_METHOD"] == "HEAD")
		{
			CHTTP::SetStatus("200 OK");
			header("Accept-Ranges: bytes");
			header("Content-Length: ".($size-$cur_pos+1));

			if($force_download)
				header("Content-Type: application/force-download; name=\"".$name."\"");
			else
				header("Content-type: ".$content_type);

			if($filetime > 0)
				header("Last-Modified: ".date("r", $filetime));
		}
		else
		{
			$lastModified = '';
			if($cache_time > 0)
			{
				//Handle ETag
				$ETag = md5($filename.$filesize.$filetime);
				if(array_key_exists("HTTP_IF_NONE_MATCH", $_SERVER) && ($_SERVER['HTTP_IF_NONE_MATCH'] === $ETag))
				{
					CHTTP::SetStatus("304 Not Modified");
					header("Cache-Control: private, max-age=".$cache_time.", pre-check=".$cache_time);
					die();
				}
				header("ETag: ".$ETag);

				//Handle Last Modified
				if($filetime > 0)
				{
					$lastModified = gmdate('D, d M Y H:i:s', $filetime).' GMT';
					if(array_key_exists("HTTP_IF_MODIFIED_SINCE", $_SERVER) && ($_SERVER['HTTP_IF_MODIFIED_SINCE'] === $lastModified))
					{
						CHTTP::SetStatus("304 Not Modified");
						header("Cache-Control: private, max-age=".$cache_time.", pre-check=".$cache_time);
						die();
					}
				}
			}

			if($force_download)
			{
				//Disable zlib for old versions of php <= 5.3.0
				//it has broken Content-Length handling
				if(ini_get('zlib.output_compression'))
					ini_set('zlib.output_compression', 'Off');

				if($cur_pos > 0)
					CHTTP::SetStatus("206 Partial Content");
				else
					CHTTP::SetStatus("200 OK");

				header("Content-Type: application/force-download; name=\"".$name."\"");
				header("Content-Disposition: attachment; filename=\"".$name."\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ".($size-$cur_pos+1));
				if(is_resource($src))
				{
					header("Accept-Ranges: bytes");
					header("Content-Range: bytes ".$cur_pos."-".$size."/".$filesize);
				}
			}
			else
			{
				header("Content-type: ".$content_type);
				header("Content-Disposition: inline; filename=\"".$name."\"");
			}

			if($cache_time > 0)
			{
				header("Cache-Control: private, max-age=".$cache_time.", pre-check=".$cache_time);
				if($filetime > 0)
					header('Last-Modified: '.$lastModified);
			}
			else
			{
				header("Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0");
			}

			header("Expires: 0");
			header("Pragma: public");

			// Download from front-end
			if($fastDownload)
			{
				if($fromClouds)
				{
					$filename = preg_replace('~^(http[s]?)(\://)~i', '\\1.' , $filename);
					$cloudUploadPath = COption::GetOptionString('main', 'bx_cloud_upload', '/upload/bx_cloud_upload/');
					header('X-Accel-Redirect: '.$cloudUploadPath.$filename);
				}
				else
				{
					header('X-Accel-Redirect: '.$filename);
				}
			}
			else
			{
				session_write_close();
				if ($specialchars)
				{
					echo "<", "pre" ,">";
					if(is_resource($src))
					{
						while(!feof($src))
							echo htmlspecialcharsbx(fread($src, 32768));
						fclose($src);
					}
					else
					{
						echo htmlspecialcharsbx($src->Get($filename));
					}
					echo "<", "/pre", ">";
				}
				else
				{
					if(is_resource($src))
					{
						fseek($src, $cur_pos);
						while(!feof($src) && ($cur_pos <= $size))
						{
							$bufsize = 131072; //128K
							if($bufsize+$cur_pos > $size)
								$bufsize = $size - $cur_pos + 1;
							$cur_pos += $bufsize;
							echo fread($src, $bufsize);
						}
						fclose($src);
					}
					else
					{
						echo $src->Get($filename);
					}
				}
			}
		}
		die();
	}

	// Params:
	// 	type - text|image
	//	size - big|medium|small|real, for custom resizing can be used 'coefficient', real - only for images
	// 	position - of the watermark on picture can be in one of two available notifications:
	//		 tl|tc|tr|ml|mc|mr|bl|bc|br or topleft|topcenter|topright|centerleft|center|centerright|bottomleft|bottomcenter|bottomright
	public static function Watermark(&$obj, $Params)
	{
		// Image sizes
		$Params["width"] = intval(@imagesx($obj));
		$Params["height"] = intval(@imagesy($obj));

		// Handle position param
		$Params["position"] = strtolower(trim($Params["position"]));
		$arPositions = array("topleft", "topcenter", "topright", "centerleft", "center", "centerright", "bottomleft", "bottomcenter", "bottomright");
		$arPositions2 = array("tl", "tc", "tr", "ml", "mc", "mr", "bl", "bc", "br");
		$position = array('x' => 'right','y' => 'bottom'); // Default position

		if (in_array($Params["position"], $arPositions2))
			$Params["position"] = str_replace($arPositions2, $arPositions, $Params["position"]);

		if (in_array($Params["position"], $arPositions))
		{
			foreach(array('top', 'center', 'bottom') as $k)
			{
				$l = strlen($k);
				if (substr($Params["position"], 0, $l) == $k)
				{
					$position['y'] = $k;
					$position['x'] = substr($Params["position"], $l);
					if ($position['x'] == '')
						$position['x'] = ($k == 'center') ? 'center' : 'right';
				}
			}
		}
		$Params["position"] = $position;

		// Text
		if ($Params['type'] == 'text')
		{
			if (intval($Params["coefficient"]) <= 0)
			{
				if ($Params["size"] == "big")
					$Params["coefficient"] = 7;
				elseif ($Params["size"] == "small")
					$Params["coefficient"] = 2;
				else
					$Params["coefficient"] = 4;
			}

			if (!$Params["coefficient"])
				$Params["coefficient"] = 1;

			$result = CFile::WatermarkText($obj, $Params);
		}
		else // Image
		{
			if ($Params["size"] == "real")
			{
				$Params["fill"] = 'exact';
				$Params["coefficient"] = 1;
			}
			else
			{
				if (floatval($Params["coefficient"]) <= 0)
				{
					if ($Params["size"] == "big")
						$Params["coefficient"] = 0.75;
					elseif ($Params["size"] == "small")
						$Params["coefficient"] = 0.20;
					else
						$Params["coefficient"] = 0.5;
				}
			}

			$result = CFile::WatermarkImage($obj, $Params);
		};

		return $result;
	}

	public static function WatermarkText(&$obj, $Params = array())
	{
		/** @global CMain $APPLICATION */
		global $APPLICATION;

		$text = $Params['text'];
		$font = $Params['font'];
		$color = $Params['color'];

		if (!$obj || empty($text) || !file_exists($font) || !function_exists("gd_info"))
			return false;

		$Params["coefficient"] = intval($Params["coefficient"]);
		$Params["width"] = intval(@imagesx($obj));
		$Params["height"] = intval(@imagesy($obj));

		// Color
		$color = preg_replace("/[^a-z0-9]/is", "", trim($color));
		if (strlen($color) != 6)
			$color = "FF0000";

		$arColor = array("red" => hexdec(substr($color, 0, 2)), "green" => hexdec(substr($color, 2, 2)), "blue" => hexdec(substr($color, 4, 2)));

		$iSize = $Params["width"] * $Params["coefficient"] / 100;
		if ($iSize * strlen($text) * 0.7 > $Params["width"])
			$iSize = intval($Params["width"] / (strlen($text) * 0.7));

		$wm_pos = array(
			"x" => 5, // Left
			"y" => $iSize + 5, // Top
			"width" => (strlen($text) * 0.7 + 1) * $iSize,
			"height" => $iSize
		);

		if (!CFile::IsGD2())
		{
			$wm_pos["width"] = strlen($text) * imagefontwidth(5);
			$wm_pos["height"] = imagefontheight(5);
		}

		if ($Params["position"]['y'] == 'center')
			$wm_pos["y"] = intval(($Params["height"] - $wm_pos["height"]) / 2);
		elseif($Params["position"]['y'] == 'bottom')
			$wm_pos["y"] = intval(($Params["height"] - $wm_pos["height"]));

		if ($Params["position"]['x'] == 'center')
			$wm_pos["x"] = intval(($Params["width"] - $wm_pos["width"]) / 2);
		elseif ($Params["position"]['x'] == 'right')
			$wm_pos["x"] = intval(($Params["width"] - $wm_pos["width"]));

		if ($wm_pos["y"] < 2)
			$wm_pos["y"] = 2;
		if ($wm_pos["x"] < 2)
			$wm_pos["x"] = 2;

		$text_color = imagecolorallocate($obj, $arColor["red"], $arColor["green"], $arColor["blue"]);
		if (CFile::IsGD2())
		{
			if (function_exists("utf8_encode"))
			{
				$text = $APPLICATION->ConvertCharset($text, SITE_CHARSET, "UTF-8");
				if ($Params["use_copyright"] == "Y")
					$text = utf8_encode("&#169; ").$text;
			}
			else
			{
				$text = $APPLICATION->ConvertCharset($text, SITE_CHARSET, "UTF-8");
				if ($Params["use_copyright"] == "Y")
					$text = "© ".$text;
			}

			$result = @imagettftext($obj, $iSize, 0, $wm_pos["x"], $wm_pos["y"], $text_color, $font, $text);
		}
		else
		{
			$result = @imagestring($obj, 3, $wm_pos["x"], $wm_pos["y"], $text, $text_color);
		}
		return $result;
	}

	// Create watermark from image
	// $Params:
	// 	file - abs path to file
	//	alpha_level - opacity
	// 	position - of the watermark
	public static function WatermarkImage(&$obj, $Params = array())
	{
		$file = $Params['file'];

		if (!$obj || empty($file) || !file_exists($file) || !is_file($file) || !function_exists("gd_info"))
			return false;

		$arFile = array("ext" => GetFileExtension($file));
		$Params["width"] = intval(@imagesx($obj));
		$Params["height"] = intval(@imagesy($obj));
		$Params["coefficient"] = floatval($Params["coefficient"]);

		if (!isset($Params["alpha_level"]))
			$Params["alpha_level"] = 100;

		$Params["alpha_level"] = intval($Params["alpha_level"]) / 100;
		$wmWidth = round($Params["width"] * $Params["coefficient"]);
		$wmHeight = round($Params["height"] * $Params["coefficient"]);

		$arFileSizeTmp = CFile::GetImageSize($file);

		if (!in_array($arFileSizeTmp[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_BMP)))
			return false;

		if ($Params["fill"] == 'resize')
		{
			$file_obj_1 = CFile::CreateImage($file, $arFileSizeTmp[2]);
			$arFile["width"] = intval(imagesx($file_obj_1));
			$arFile["height"] = intval(imagesy($file_obj_1));
			if ($arFile["width"] > $wmWidth || $arFile["height"] > $wmHeight)
			{
				$file_1 = $file.'_new.tmp';
				CFile::ResizeImageFile($file, $file_1, array('width' => $wmWidth, 'height' => $wmHeight));
				$file_obj = CFile::CreateImage($file_1, $arFileSizeTmp[2]);
				@imagedestroy($file_obj_1);
			}
			else
			{
				$file_obj = $file_obj_1;
			}
		}
		else
		{
			$file_obj = CFile::CreateImage($file, $arFileSizeTmp[2]);
			if ($Params["fill"] == 'repeat')
				$Params["position"] = array('x' => 'top', 'y' => 'left');
		}

		if (!$file_obj)
			return false;

		$arFile["width"] = intval(@imagesx($file_obj));
		$arFile["height"] = intval(@imagesy($file_obj));

		$wm_pos = array(
			"x" => 2, // Left
			"y" => 2, // Top
			"width" => $arFile["width"],
			"height" => $arFile["height"]
		);

		if ($Params["position"]['y'] == 'center')
			$wm_pos["y"] = intval(($Params["height"] - $wm_pos["height"]) / 2);
		elseif($Params["position"]['y'] == 'bottom')
			$wm_pos["y"] = intval(($Params["height"] - $wm_pos["height"]));

		if ($Params["position"]['x'] == 'center')
			$wm_pos["x"] = intval(($Params["width"] - $wm_pos["width"]) / 2);
		elseif ($Params["position"]['x'] == 'right')
			$wm_pos["x"] = intval(($Params["width"] - $wm_pos["width"]));

		if ($wm_pos["y"] < 2)
			$wm_pos["y"] = 2;
		if ($wm_pos["x"] < 2)
			$wm_pos["x"] = 2;

		for ($y = 0; $y < $arFile["height"]; $y++ )
		{
			for ($x = 0; $x < $arFile["width"]; $x++ )
			{
				$watermark_y = $wm_pos["y"] + $y;
				while (true)
				{
					$watermark_x = $wm_pos["x"] + $x;
					while (true)
					{
						$return_color = NULL;
						$watermark_alpha = $Params["alpha_level"];
						$main_rgb = imagecolorsforindex($obj, imagecolorat($obj, $watermark_x, $watermark_y));
						$watermark_rbg = imagecolorsforindex($file_obj, imagecolorat($file_obj, $x, $y));

						if ($watermark_rbg['alpha'])
						{
							$watermark_alpha = round((( 127 - $watermark_rbg['alpha']) / 127), 2);
							$watermark_alpha = $watermark_alpha * $Params["alpha_level"];
						}

						$res = array();
						foreach(array('red', 'green', 'blue', 'alpha') as $k)
							$res[$k] = round(($main_rgb[$k] * (1 - $watermark_alpha)) + ($watermark_rbg[$k] * $watermark_alpha));

						$return_color = imagecolorexactalpha($obj, $res["red"], $res["green"], $res["blue"], $res["alpha"]);
						if ($return_color == -1)
						{
							$return_color = imagecolorallocatealpha($obj, $res["red"], $res["green"], $res["blue"], $res["alpha"]);
							if ($return_color == -1)
								$return_color = imagecolorclosestalpha($obj, $res["red"], $res["green"], $res["blue"], $res["alpha"]);
						}
						imagesetpixel($obj, $watermark_x, $watermark_y, $return_color);

						$watermark_x += $arFile["width"];
						if ($Params["fill"] != 'repeat' || $watermark_x > $Params["width"])
							break;
					}

					$watermark_y += $arFile["height"];
					if ($Params["fill"] != 'repeat' || $watermark_y > $Params["height"])
						break;
				}
			}
		}

		@imagedestroy($file_obj);
		return true;
	}

	public static function ImageRotate($sourceFile, $angle)
	{
		if (!file_exists($sourceFile) || !is_file($sourceFile))
			return false;

		if (!CFile::IsGD2())
			return false;

		$angle = 360 - $angle;
		$arSourceFileSizeTmp = CFile::GetImageSize($sourceFile);
		if (!in_array($arSourceFileSizeTmp[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_BMP)))
			return false;
		$sourceImage = CFile::CreateImage($sourceFile, $arSourceFileSizeTmp[2]);
		// Rotate image
		$sourceImage = imagerotate($sourceImage, $angle, 0);
		// Delete old file
		unlink($sourceFile);
		switch ($arSourceFileSizeTmp[2])
		{
			case IMAGETYPE_GIF:
				imagegif($sourceImage, $sourceFile);
				break;
			case IMAGETYPE_PNG:
				imagealphablending($sourceImage, false );
				imagesavealpha($sourceImage, true);
				imagepng($sourceImage, $sourceFile);
				break;
			default:
				if ($arSourceFileSizeTmp[2] == IMAGETYPE_BMP)
					$sourceFile .= ".jpg";
				$jpgQuality = intval(COption::GetOptionString('main', 'image_resize_quality', '100'));
				if($jpgQuality <= 0 || $jpgQuality > 100)
					$jpgQuality = 100;
				imagejpeg($sourceImage, $sourceFile, $jpgQuality);
				break;
		}
		imagedestroy($sourceImage);
		return true;
	}

	public static function CreateImage($path, $type = false)
	{
		$sourceImage = false;
		if ($type === false)
		{
			$arSourceFileSizeTmp = CFile::GetImageSize($path);
			$type = $arSourceFileSizeTmp[2];
		}

		if (in_array($type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_BMP)))
		{
			switch ($type)
			{
				case IMAGETYPE_GIF:
					$sourceImage = imagecreatefromgif($path);
					break;
				case IMAGETYPE_PNG:
					$sourceImage = imagecreatefrompng($path);
					break;
				case IMAGETYPE_BMP:
					$sourceImage = CFile::ImageCreateFromBMP($path);
					break;
				default:
					$sourceImage = imagecreatefromjpeg($path);
					break;
			}
		}
		return $sourceImage;
	}

	public static function ExtractImageExif($src)
	{
		/** @global CMain $APPLICATION  */
		global $APPLICATION;

		$arr = array();
		if (function_exists("exif_read_data"))
		{
			if($arr = exif_read_data($src))
			{
				foreach ($arr as $k => $val)
					if (is_string($val) && $val != '')
						$arr[strtolower($k)] = $APPLICATION->ConvertCharset($val, ini_get('exif.encode_unicode'), SITE_CHARSET);
			}
		}
		return $arr;
	}

	public static function ExtractImageIPTC($src)
	{
/* Not implemented yet
		$arr = array();
		if (isset($info["APP13"]))
		{
			if($iptc = iptcparse($info["APP13"]))
			{
				$arr['caption'] = $iptc["2#120"][0];
				$arr['graphic_name'] = $iptc["2#005"][0];
				$arr['urgency'] = $iptc["2#010"][0];
				$arr['category'] = $iptc["2#015"][0];
				$arr['supp_categories'] = $iptc["2#020"][0];
				$arr['spec_instr'] = $iptc["2#040"][0];
				$arr['creation_date'] = $iptc["2#055"][0];
				$arr['photog'] = $iptc["2#080"][0];
				$arr['credit_byline_title'] = $iptc["2#085"][0];
				$arr['city'] = $iptc["2#090"][0];
				$arr['state'] = $iptc["2#095"][0];
				$arr['country'] = $iptc["2#101"][0];
				$arr['otr'] = $iptc["2#103"][0];
				$arr['headline'] = $iptc["2#105"][0];
				$arr['source'] = $iptc["2#110"][0];
				$arr['photo_source'] = $iptc["2#115"][0];

				$arr['caption'] = str_replace("\000", "", $arr['caption']);
				if(isset($iptc["1#090"]) && $iptc["1#090"][0] == "\x1B%G")
					$arr['caption'] = utf8_decode($arr['caption']);
			}
		}
		return $arr;
*/
	}

	public static function GetContentType($path, $bPhysicalName = false)
	{
		$io = CBXVirtualIo::GetInstance();
		$pathX = $bPhysicalName? $path: $io->GetPhysicalName($path);

		if (function_exists("mime_content_type"))
			$type = mime_content_type($pathX);
		else
			$type = "";

		if (strlen($type) <= 0 && function_exists("image_type_to_mime_type"))
		{
			$arTmp = CFile::GetImageSize($pathX, true);
			$type = $arTmp["mime"];
		}

		if (strlen($type) <= 0)
		{
			$arTypes = array(
				"jpeg" => "image/jpeg",
				"jpe" => "image/jpeg",
				"jpg" => "image/jpeg",
				"png" => "image/png",
				"gif" => "image/gif",
				"bmp" => "image/bmp",
			);
			$type = $arTypes[strtolower(substr($pathX, bxstrrpos($pathX, ".") + 1))];
		}

		return $type;
	}

	/*
		This function will protect us from
		scan the whole file in order to
		findout size of the xbm image
		ext/standard/image.c php_getimagetype
	*/
	public static function GetImageSize($path, $bPhysicalName = false)
	{
		$io = CBXVirtualIo::GetInstance();
		$pathX = $bPhysicalName? $path: $io->GetPhysicalName($path);

		$file_handler = fopen($pathX, "rb");
		if(!is_resource($file_handler))
			return false;

		$signature = fread($file_handler, 12);
		fclose($file_handler);

		if(preg_match("/^(
			GIF                    # php_sig_gif
			|\\xff\\xd8\\xff       # php_sig_jpg
			|\\x89\\x50\\x4e       # php_sig_png
			|FWS                   # php_sig_swf
			|CWS                   # php_sig_swc
			|8BPS                  # php_sig_psd
			|BM                    # php_sig_bmp
			|\\xff\\x4f\\xff       # php_sig_jpc
			|II\\x2a\\x00          # php_sig_tif_ii
			|MM\\x00\\x2a          # php_sig_tif_mm
			|FORM                  # php_sig_iff
			|\\x00\\x00\\x01\\x00  # php_sig_ico
			|\\x00\\x00\\x00\\x0c
			\\x6a\\x50\\x20\\x20
			\\x0d\\x0a\\x87\\x0a  # php_sig_jp2
			)/x", $signature))
		{
			/*php_get_wbmp to be added*/
			return getimagesize($pathX);
		}
		else
			return false;
	}
}

global $arCloudImageSizeCache;
$arCloudImageSizeCache = array();
