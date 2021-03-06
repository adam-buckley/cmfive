<?php
class Report extends DbObject {
	var $title;			// report title
	var $module;	// module report pertains to
	var $category; 		// category of report given by Lookup
	var $description;	// description of report
	var $report_code; 	// the 'code' describing the report
	var $sqltype;		// determine type of statement: select/update/insert/delete
	var $is_approved;	// has the Report Admin approved this report
	var $is_deleted;	// is report deleted

	var $_modifiable;	// employ the modifiable aspect

	// actual table name
	function getDbTableName() {
		return "report";
	}

	// return a category title using lookup with type: ReportCategory
	function getCategoryTitle() {
		$c = $this->Report->getObject("Lookup",array("type"=>"ReportCategory","code"=>$this->category));
		return $c->title;
	}

	// build form of parameters for generating report
	function getReportCriteria() {
		// set form header
		$arr = array(array("Select Report Criteria","section"));
		$arr[] = array("Description","static","description",$this->description);

		// build array of all contents within any [[...]]
		preg_match_all("/\[\[.*?\]\]/",preg_replace("/\n/"," ",$this->report_code), $form);

		// if we've found elements meeting that style ....
		if ($form) {
			// foreach of the elements ...
			foreach ($form as $element) {
				// if there is actually an element ...
				if ($element) {
					// it will be as an array so ....
					foreach ($element as $f) {
						// element enclosed in [[...]]. dump [[ & ]]
						$patterns = array();
						$patterns[0] = "/\[\[\s*/";
						$patterns[1] = "/\s*\]\]/";
						$replacements = array();
						$replacements[0] = "";
						$replacements[1] = "";
						$f = preg_replace($patterns, $replacements, $f);

						// split element on ||. rules provide for at most 4 parts in strict order
						list($name,$type,$label,$sql) = preg_split("/\|\|/", $f);
						$name = trim($name);
						$type = trim($type);
						$label = trim($label);
						$sql = trim($sql);

						$sql = $this->Report->putSpecialSQL($sql);

						// do something different based on form element type
						switch ($type) {
							case "select":
								if ($sql != "") {
									// if sql exists, check SQL is valid
									$flgsql = $this->Report->getcheckSQL($sql);

									// if valid SQL ...
									if ($flgsql) {
										//get returns for display as dropdown
										$values = $this->Report->getFormDatafromSQL($sql);
									}
									else {
										// there is a problem, say as much
										$values = array("SQL error");
									}
								}
								else {
									// there is a problem, say as much
									$values = array("No SQL statement");
								}
								// complete array which becomes form dropdown
								$arr[] = array($label,$type,$name,$_REQUEST[$name],$values);
								break;
							case "checkbox":
							case "text":
							case "date":
							default:
								// complete array which becomes other form element type
								$arr[] = array($label,$type,$name,$_REQUEST[$name]);
						}
					}
				}
			}
		}
		// get the selection of output formats as array
		$format = $this->Report->selectReportFormat();
		// merge arrays to give all parameter form requirements
		$arr = array_merge($arr,$format);
		// return form
		return $arr;
	}

	// generate the report based on selected parameters
	function getReportData() {
		// build array of all contents within any @@...@@
		//		preg_match_all("/@@[a-zA-Z0-9_\s\|,;\(\)\{\}<>\/\-='\.@:%\+\*\$]*?@@/",preg_replace("/\n/"," ",$this->report_code), $arrsql);
		preg_match_all("/@@.*?@@/",preg_replace("/\n/"," ",$this->report_code), $arrsql);

		// if we have statements, continue ...
		if ($arrsql) {
			// foreach array element ...
			foreach ($arrsql as $strsql) {
				// if element exists ....
				if ($strsql) {
					// it will be as an array, so ...
					foreach ($strsql as $sql) {
						// strip our delimiters, remove newlines
						$sql = preg_replace("/@@/", "", $sql);
						$sql = preg_replace("/[\r\n]+/", " ", $sql);

						// split into title and statement fields
						list($stitle,$sql) = preg_split("/\|\|/", $sql);
						$title = array(trim($stitle));
						$sql = trim($sql);

						// determine type of SQL statement, eg. select, insert, etc.
						$arrsql = preg_split("/\s+/", $sql);
						$action = strtolower($arrsql[0]);

						$crumbs = array(array());
						// each form element should correspond to a field in our SQL where clause ... substitute
						// do not use $_REQUEST because it includes unwanted cookies
						foreach (array_merge($_GET, $_POST) as $name => $value) {
							// convert input dates to yyyy-mm-dd for query
							if (startsWith($name,"dt_"))
							$value = $this->Report->date2db($value);

							// substitute place holder with form value
							$sql = str_replace("{{".$name."}}", $value, $sql);

							// list parameters for display
							if (($name != SESSION_NAME) && ($name != "format"))
							$crumbs[0][] = $value;
						}

						// if our SQL is still intact ...
						if ($sql != "") {
							// check the SQL statement for special parameter replacements
							$sql = $this->Report->putSpecialSQL($sql);
							// check the SQL statement for validity
							$flgsql = $this->Report->getcheckSQL($sql);

							// if valid SQL ...
							if ($flgsql) {
								// starter arrays
								$hds = array();
								$flds = array();
								$line = array();

								// run SQL and return recordset
								if ($action == "select") {
									$rows = $this->Report->getRowsfromSQL($sql);

									// if we have a recordset ...
									if ($rows) {
										// iterate ...
										foreach ($rows as $row) {
											// if row actually exists
											if ($row) {
												// foreach field/column ...
												foreach ($row as $name => $value) {
													// build our headings array
													$hds[$name] = $name;
													// build a fields array
													$flds[] = $value;
												}
												// put fields array into a line array and reset field array for next record
												$line[] = $flds;
												unset($flds);
											}
										}
										// wrap headings array appropriately
										$hds = array($hds);
										// merge to create completed report for display
										$tbl = array_merge($crumbs,$title,$hds,$line);
										$alltbl[] = $tbl;
										unset($line);
										unset($hds);
										unset($crumbs);
										unset($tbl);
									}
									else {
										$alltbl[] = array(array("No Data Returned for selections"), $stitle, array("Results"), array("No data returned for selections"));

									}
								}
								else {
									// create headings
									$hds = array(array("Status","Message"));
										
									// other SQL types do not return recordset so treat differently from SELECT
									try {
										$this->startTransaction();
										$rows = $this->Report->getExefromSQL($sql);
										$this->commitTransaction();
										$line = array(array("SUCCESS","SQL has completed successfully"));
									}
									catch (Exception $e) {
										// SQL returns errors so clean up and return error
										$this->rollbackTransaction();
										$this->_db->clear_sql();
										$line = array(array("ERROR","A SQL error was encountered: " . $e->getMessage()));
									}
									$tbl = array_merge($crumbs,$title,$hds,$line);
									$alltbl[] = $tbl;
									unset($line);
									unset($hds);
									unset($crumbs);
									unset($tbl);
								}
							}
							else {
								// if we fail the SQL check, say as much
								$alltbl = array(array("ERROR"),array("There is a problem with your SQL statement:".$sql));
							}
						}
						else {
							// if we fail the SQL check, say as much
							$alltbl = array(array("ERROR"),array("There is a problem with your SQL statement"));
						}
					}
				}
			}
		}
		else {
			$alltbl = array(array("ERROR"),array("There is a problem with your SQL statement"));
		}
		return $alltbl;
	}
}
