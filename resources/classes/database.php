<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Copyright (C) 2010 - 2015
	All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/
include "root.php";

//define the database class
	if (!class_exists('database')) {
		class database {
			public $db;
			public $driver;
			public $type;
			public $host;
			public $port;
			public $db_name;
			public $username;
			public $password;
			public $path;
			public $table;
			public $where; //array
			public $order_by; //array
			public $order_type;
			public $limit;
			public $offset;
			public $fields;
			public $count;
			public $sql;
			public $result;

			public function connect() {

				if (strlen($this->type) == 0 && strlen($this->db_name) == 0) {
					//include config.php
						include "root.php";
						if (file_exists($_SERVER["PROJECT_ROOT"]."/resources/config.php")) {
							include $_SERVER["PROJECT_ROOT"]."/resources/config.php";
						} elseif (file_exists($_SERVER["PROJECT_ROOT"]."/resources/config.php")) {
							include $_SERVER["PROJECT_ROOT"]."/resources/config.php";
						} elseif (file_exists("/etc/fusionpbx/config.php")){
							//linux
							include "/etc/fusionpbx/config.php";
						} elseif (file_exists("/usr/local/etc/fusionpbx/config.php")){
							//bsd
							include "/usr/local/etc/fusionpbx/config.php";
						}

					//backwards compatibility
						if (isset($dbtype)) { $db_type = $dbtype; }
						if (isset($dbhost)) { $db_host = $dbhost; }
						if (isset($dbport)) { $db_port = $dbport; }
						if (isset($dbname)) { $db_name = $dbname; }
						if (isset($dbusername)) { $db_username = $dbusername; }
						if (isset($dbpassword)) { $db_password = $dbpassword; }
						if (isset($dbfilepath)) { $db_path = $db_file_path; }
						if (isset($dbfilename)) { $db_name = $dbfilename; }

					//set defaults
						if (!isset($this->driver) && isset($db_type)) { $this->driver = $db_type; }
						if (!isset($this->type) && isset($db_type)) { $this->type = $db_type; }
						if (!isset($this->host) && isset($db_host)) { $this->host = $db_host; }
						if (!isset($this->port) && isset($db_port)) { $this->port = $db_port; }
						if (!isset($this->db_name) && isset($db_name)) { $this->db_name = $db_name; }
						if (!isset($this->username) && isset($db_username)) { $this->username = $db_username; }
						if (!isset($this->password) && isset($db_password)) { $this->password = $db_password; }
						if (!isset($this->path) && isset($db_path)) { $this->path = $db_path; }
				}
				if (strlen($this->driver) == 0) {
					$this->driver = $this->type;
				}

				if ($this->driver == "sqlite") {
					if (strlen($this->db_name) == 0) {
						$server_name = $_SERVER["SERVER_NAME"];
						$server_name = str_replace ("www.", "", $server_name);
						$db_name_short = $server_name;
						$this->db_name = $server_name.'.db';
					}
					else {
						$db_name_short = $this->db_name;
					}
					$this->path = realpath($this->path);
					if (file_exists($this->path.'/'.$this->db_name)) {
						$this->db = new PDO('sqlite:'.$this->path.'/'.$this->db_name); //sqlite 3
					}
					else {
						echo "not found";
					}
				}

				if ($this->driver == "mysql") {
					try {
						//mysql pdo connection
							if (strlen($this->host) == 0 && strlen($this->port) == 0) {
								//if both host and port are empty use the unix socket
								$this->db = new PDO("mysql:host=$this->host;unix_socket=/var/run/mysqld/mysqld.sock;dbname=$this->db_name", $this->username, $this->password);
							}
							else {
								if (strlen($this->port) == 0) {
									//leave out port if it is empty
									$this->db = new PDO("mysql:host=$this->host;dbname=$this->db_name;", $this->username, $this->password, array(
									PDO::ATTR_ERRMODE,
									PDO::ERRMODE_EXCEPTION
									));
								}
								else {
									$this->db = new PDO("mysql:host=$this->host;port=$this->port;dbname=$this->db_name;", $this->username, $this->password, array(
									PDO::ATTR_ERRMODE,
									PDO::ERRMODE_EXCEPTION
									));
								}
							}
					}
					catch (PDOException $error) {
						print "error: " . $error->getMessage() . "<br/>";
						die();
					}
				}

				if ($this->driver == "pgsql") {
					//database connection
					try {
						if (strlen($this->host) > 0) {
							if (strlen($this->port) == 0) { $this->port = "5432"; }
							$this->db = new PDO("pgsql:host=$this->host port=$this->port dbname=$this->db_name user=$this->username password=$this->password");
						}
						else {
							$this->db = new PDO("pgsql:dbname=$this->db_name user=$this->username password=$this->password");
						}
					}
					catch (PDOException $error) {
						print "error: " . $error->getMessage() . "<br/>";
						die();
					}
				}

				if ($this->driver == "odbc") {
					//database connection
						try {
							$this->db = new PDO("odbc:".$this->db_name, $this->username, $this->password);
						}
						catch (PDOException $e) {
							echo 'Connection failed: ' . $e->getMessage();
						}
				}
			}

			public function tables() {
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
					if ($this->type == "sqlite") {
						$sql = "SELECT name FROM sqlite_master ";
						$sql .= "WHERE type='table' ";
						$sql .= "order by name;";
					}
					if ($this->type == "pgsql") {
						$sql = "select table_name as name ";
						$sql .= "from information_schema.tables ";
						$sql .= "where table_schema='public' ";
						$sql .= "and table_type='BASE TABLE' ";
						$sql .= "order by table_name ";
					}
					if ($this->type == "mysql") {
						$sql = "show tables";
					}
					if ($this->type == "mssql") {
						$sql = "SELECT * FROM sys.Tables order by name asc";
					}
					$prep_statement = $this->db->prepare(check_sql($sql));
					$prep_statement->execute();
					$tmp = $prep_statement->fetchAll(PDO::FETCH_NAMED);
					if ($this->type == "pgsql" || $this->type == "sqlite" || $this->type == "mssql") {
						foreach ($tmp as &$row) {
							$result[]['name'] = $row['name'];
						}
					}
					if ($this->type == "mysql") {
						foreach ($tmp as &$row) {
							$table_array = array_values($row);
							$result[]['name'] = $table_array[0];
						}
					}
					return $result;
			}

			public function table_info() {
				//public $db;
				//public $type;
				//public $table;
				//public $name;

				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//get the table info
					if (strlen($this->table) == 0) { return false; }
					if ($this->type == "sqlite") {
						$sql = "PRAGMA table_info(".$this->table.");";
					}
					if ($this->type == "pgsql") {
						$sql = "SELECT ordinal_position, ";
						$sql .= "column_name, ";
						$sql .= "data_type, ";
						$sql .= "column_default, ";
						$sql .= "is_nullable, ";
						$sql .= "character_maximum_length, ";
						$sql .= "numeric_precision ";
						$sql .= "FROM information_schema.columns ";
						$sql .= "WHERE table_name = '".$this->table."' ";
						$sql .= "and table_catalog = '".$this->db_name."' ";
						$sql .= "ORDER BY ordinal_position; ";
					}
					if ($this->type == "mysql") {
						$sql = "DESCRIBE ".$this->table.";";
					}
					if ($this->type == "mssql") {
						$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".$this->table."'";
					}
					$prep_statement = $this->db->prepare($sql);
					$prep_statement->execute();
				//set the result array
					return $prep_statement->fetchAll(PDO::FETCH_ASSOC);
			}

			public function fields() {
				//public $db;
				//public $type;
				//public $table;
				//public $name;

				//get the table info
					$table_info = $this->table_info();

				//set the list of fields
					if ($this->type == "sqlite") {
						foreach($table_info as $row) {
							$result[]['name'] = $row['name'];
						}
					}
					if ($this->type == "pgsql") {
						foreach($table_info as $row) {
							$result[]['name'] = $row['column_name'];
						}
					}
					if ($this->type == "mysql") {
						foreach($table_info as $row) {
							$result[]['name'] = $row['Field'];
						}
					}
					if ($this->type == "mssql") {
						foreach($table_info as $row) {
							$result[]['name'] = $row['COLUMN_NAME'];
						}
					}

				//return the result array
					return $result;
			}

			//public function disconnect() {
			//	return null;
			//}

			public function find() {
				//connect;
				//table;
				//where;
				//order_by;
				//limit;
				//offset;

				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//get data from the database
					$sql = "select * from ".$this->table." ";
					if ($this->where) {
						$i = 0;
						foreach($this->where as $row) {
							if ($i == 0) {
								$sql .= 'where '.$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							else {
								$sql .= "and ".$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							$i++;
						}
					}
					if (count($this->order_by) > 0) {
						$sql .= "order by ";
						$i = 1;
						foreach($this->order_by as $row) {
							if (count($this->order_by) == $i) {
								$sql .= $row['name']." ".$row['order']." ";
							}
							else {
								$sql .= $row['name']." ".$row['order'].", ";
							}
							$i++;
						}
					}
					if ($this->limit) {
						$sql .= " limit ".$this->limit." offset ".$this->offset." ";
					}
					//echo $sql;
					$prep_statement = $this->db->prepare($sql);
					if ($prep_statement) {
						$prep_statement->execute();
						return $prep_statement->fetchAll(PDO::FETCH_ASSOC);
					}
					else {
						return false;
					}
			}

			// Use this function to execute complex queries
			public function execute(){
					$sql = $this->sql;
					//echo $sql;
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//get data from the database
					$prep_statement = $this->db->prepare($sql);
					if ($prep_statement) {
						$prep_statement->execute();
						return $prep_statement->fetchAll(PDO::FETCH_ASSOC);
					}
					else {
						return false;
					}
			}

			public function add(){
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//add data to the database
					$sql = "insert into ".$this->table;
					$sql .= " (";
					$i = 1;
					foreach($this->fields as $name => $value) {
						if (count($this->fields) == $i) {
							$sql .= $name." ";
						}
						else {
							$sql .= $name.", ";
						}
						$i++;
					}
					$sql .= ") ";
					$sql .= "values ";
					$sql .= "(";
					$i = 1;
					foreach($this->fields as $name => $value) {
						if (count($this->fields) == $i) {
							if (strlen($value) > 0) {
								$sql .= "'".$value."' ";
							}
							else {
								$sql .= "'".$value."' ";
							}
						}
						else {
							if (strlen($value) > 0) {
								$sql .= "'".$value."', ";
							}
							else {
								$sql .= "null, ";
							}
						}
						$i++;
					}
					$sql .= ")";
				//execute the query, show exceptions
					$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					try {
						$this->sql = $sql;
						$this->db->exec($sql);
					}
					catch(PDOException $e) {
						echo "<b>Error:</b><br />\n";
						echo "<table>\n";
						echo "<tr>\n";
						echo "<td>\n";
						echo $e->getMessage();
						echo "</td>\n";
						echo "</tr>\n";
						echo "</table>\n";
					}
					unset($this->fields);
					unset($sql);
			}

			public function update() {
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//udate the database
					$sql = "update ".$this->table." set ";
					$i = 1;
					foreach($this->fields as $name => $value) {
						if (count($this->fields) == $i) {
							if (strlen($name) > 0 && $value == null) {
								$sql .= $name." = null ";
							}
							else {
								$sql .= $name." = '".$value."' ";
							}
						}
						else {
							if (strlen($name) > 0 && $value == null) {
								$sql .= $name." = null, ";
							}
							else {
								$sql .= $name." = '".$value."', ";
							}
						}
						$i++;
					}
					$i = 0;
					foreach($this->where as $row) {
						if ($i == 0) {
							$sql .= 'where '.$row['name']." ".$row['operator']." '".$row['value']."' ";
						}
						else {
							$sql .= "and ".$row['name']." ".$row['operator']." '".$row['value']."' ";
						}
						$i++;
					}
					$this->db->exec(check_sql($sql));
					unset($this->fields);
					unset($this->where);
					unset($sql);
			}

			public function delete(){
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//delete from the database
					$sql = "delete from ".$this->table." ";
					if ($this->where) {
						$i = 0;
						foreach($this->where as $row) {
							if ($i == 0) {
								$sql .= "where ".$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							else {
								$sql .= "and ".$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							$i++;
						}
					}
					//echo $sql."<br>\n";
					$prep_statement = $this->db->prepare($sql);
					$prep_statement->execute();
					unset($sql);
					unset($this->where);
			}

			public function count() {
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//get the number of rows
					$sql = "select count(*) as num_rows from ".$this->table." ";
					if ($this->where) {
						$i = 0;
						foreach($this->where as $row) {
							if ($i == 0) {
								$sql .= "where ".$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							else {
								$sql .= "and ".$row['name']." ".$row['operator']." '".$row['value']."' ";
							}
							$i++;
						}
					}
					unset($this->where);
					$prep_statement = $this->db->prepare(check_sql($sql));
					if ($prep_statement) {
						$prep_statement->execute();
						$row = $prep_statement->fetch(PDO::FETCH_ASSOC);
						if ($row['num_rows'] > 0) {
							return $row['num_rows'];
						}
						else {
							return 0;
						}
					}
					unset($prep_statement);
			} //count

			public function find_new() {

				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}
				//set the name
					if (isset($array['name'])) {
						$this->name = $array['name'];
					}
				//set the uuid
					if (isset($array['uuid'])) {
						$this->uuid = $array['uuid'];
					}
				//build the query
					$sql = "SELECT * FROM v_".$this->name." ";
					if (isset($this->uuid)) {
						//get the specific uuid
							$sql .= "WHERE ".$this->singular($this->name)."_uuid = '".$this->uuid."' ";
					}
					else {
						//where
							if (is_array($array['where'])) {
								$i = 0;
								foreach($array['where'] as $row) {
									if ($i == 0) {
										$sql .= "WHERE ".$row['name']." ".$row['operator']." '".$row['value']."' ";
									}
									else {
										$sql .= "AND ".$row['name']." ".$row['operator']." '".$row['value']."' ";
									}
									$i++;
								}
							}
						//order by
							if (is_array($array['order_by'])) {
								$sql .= "ORDER BY ".$array['order_by']." ";
							}
						//limit
							if (isset($array['limit'])) {
								$sql .= "LIMIT ".$array['limit']." ";
							}
						//offset
							if (isset($array['offset'])) {
								$sql .= "OFFSET ".$array['offset']." ";
							}
					}
				//execute the query, and return the results
					try {
						$prep_statement = $this->db->prepare(check_sql($sql));
						$prep_statement->execute();
						$message["message"] = "OK";
						$message["code"] = "200";
						$message["details"][$m]["name"] = $this->name;
						$message["details"][$m]["message"] = "OK";
						$message["details"][$m]["code"] = "200";
						if ($this->debug["sql"]) {
							$message["details"][$m]["sql"] = $sql;
						}
						$this->message = $message;
						$this->result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
						unset($prep_statement);
						$m++;
						return $this;
					}
					catch(PDOException $e) {
						$message["message"] = "Bad Request";
						$message["code"] = "400";
						$message["details"][$m]["name"] = $this->name;
						$message["details"][$m]["message"] = $e->getMessage();
						$message["details"][$m]["code"] = "400";
						if ($this->debug["sql"]) {
							$message["details"][$m]["sql"] = $sql;
						}
						$this->message = $message;
						$this->result = '';
						$m++;
						return $this;
					}
			}

			public function delete_new($uuid = null, $array = null) {
				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}

				//delete a specific uuid
					if (permission_exists($this->singular($this->name).'_delete')) {
						if (isset($api_uuid)) {
							//start the atomic transaction
								$this->db->beginTransaction();
							//delete the primary data
								$primary_key_name = $this->singular($this->name)."_uuid";
								$sql = "DELETE FROM v_".$this->name." ";
								$sql .= "WHERE ".$this->singular($this->name)."_uuid = '".$uuid."' ";
								$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
								try {
									$this->db->query(check_sql($sql));
									$message["message"] = "OK";
									$message["code"] = "200";
									$message["details"][$m]["name"] = $this->name;
									$message["details"][$m]["message"] = "OK";
									$message["details"][$m]["code"] = "200";
									if ($this->debug["sql"]) {
										$message["details"][$m]["sql"] = $sql;
									}
									$this->message = $message;
									$m++;
								}
								catch(PDOException $e) {
									$message["message"] = "Bad Request";
									$message["code"] = "400";
									$message["details"][$m]["name"] = $this->name;
									$message["details"][$m]["message"] = $e->getMessage();
									$message["details"][$m]["code"] = "400";
									if ($this->debug["sql"]) {
										$message["details"][$m]["sql"] = $sql;
									}
									$this->message = $message;
									$m++;
								}
							//delete the related data
								$relations = $this->get_relations($this->name);
								foreach ($relations as &$row) {
									$schema_name = $row['table'];
									if (substr($schema_name, 0,2) == "v_") {
										$schema_name = substr($schema_name, 2);
									}
									if (permission_exists($this->singular($schema_name).'_delete')) {
										$sql = "DELETE FROM ".$row['table']." ";
										$sql .= "WHERE ".$row['key']['field']." = '".$uuid."' ";
										$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
										try {
											$this->db->query(check_sql($sql));
											$message["details"][$m]["name"] = $schema_name;
											$message["details"][$m]["message"] = "OK";
											$message["details"][$m]["code"] = "200";
											if ($this->debug["sql"]) {
												$message["details"][$m]["sql"] = $sql;
											}
											$this->message = $message;
											$m++;
										}
										catch(PDOException $e) {
											if ($message["code"] = "200") {
												$message["message"] = "Bad Request";
												$message["code"] = "400";
											}
											$message["details"][$m]["name"] = $schema_name;
											$message["details"][$m]["message"] = $e->getMessage();
											$message["details"][$m]["code"] = "400";
											if ($this->debug["sql"]) {
												$message["details"][$m]["sql"] = $sql;
											}
											$this->message = $message;
											$m++;
										}
										unset ($sql);
									}
								}
							//commit the atomic transaction
								if ($message["code"] == "200") {
									$this->db->commit();
								}
						}
					}
					else {
						$message["name"] = $this->name;
						$message["message"] = "Forbidden";
						$message["code"] = "403";
						$message["line"] = __line__;
						$this->message = $message;
						$m++;
					}
			}

			private function normalize_array($array, $name) {
				//get the depth of the array
					$depth = $this->array_depth($array);
				//before normalizing the array
					//echo "before: ".$depth."<br />\n";
					//echo "<pre>\n";
					//print_r($array);
					//echo "</pre>\n";
				//normalize the array
					if ($depth == 1) {
						$return_array[$name][] = $array;
					} else if ($depth == 2) {
						$return_array[$name] = $array;
					//} else if ($depth == 3) {
					//	$return_array[$name][] = $array;
					} else {
						$return_array = $array;
					}
					unset($array);
				//after normalizing the array
					$depth = $this->array_depth($new_array);
					//echo "after: ".$depth."<br />\n";
					//echo "<pre>\n";
					//print_r($new_array);
					//echo "</pre>\n";
				//return the array
					return $return_array;
			}

			public function uuid($uuid) {
				$this->uuid = $uuid;
				return $this;
			}

			public function save($array) {

				//return the array
					if (!is_array($array)) { echo "not an array"; return false; }

				//set the message id
					$m = 0;

				//set the app name
					if (!isset($this->app_name)) {
						$this->app_name = $this->name;
					}

				//normalize the array structure
					//$new_array = $this->normalize_array($array, $this->name);
					//unset($array);
					$new_array = $array;

				//connect to the database if needed
					if (!$this->db) {
						$this->connect();
					}

				//debug sql
					$this->debug["sql"] = true;

				//start the atomic transaction
//					$this->db->beginTransaction();

				//debug info
					//echo "<pre>\n";
					//print_r($new_array);
					//echo "</pre>\n";
					//exit;

				//loop through the array
					foreach ($new_array as $schema_name => $schema_array) {

						$this->name = $schema_name;
						foreach ($schema_array as $schema_id => $array) {

							//set the variables
								$table_name = "v_".$this->name;
								$parent_key_name = $this->singular($this->name)."_uuid";

							//if the uuid is set then set parent key exists and value 
								//determine if the parent_key_exists
								$parent_key_exists = false;
								if (isset($array[$parent_key_name])) {
									$this->uuid = $array[$parent_key_name];
									$parent_key_value = $this->uuid;
									$parent_key_exists = true;
								}
								else {
									if (isset($this->uuid)) {
										$parent_key_exists = true;
										$parent_key_value = $this->uuid;
									}
									else {
										$parent_key_value = uuid();
									}
								}

							//get the parent field names
								$parent_field_names = array();
								foreach ($array as $key => $value) {
									if (!is_array($value)) {
										$parent_field_names[] = $key;
									}
								}

							//determine action update or delete and get the original data
								if ($parent_key_exists) {
									$sql = "SELECT ".implode(", ", $parent_field_names)." FROM ".$table_name." ";
									$sql .= "WHERE ".$parent_key_name." = '".$this->uuid."' ";
									$prep_statement = $this->db->prepare($sql);
									if ($prep_statement) {
										//get the data
											try {
												$prep_statement->execute();
												$result = $prep_statement->fetchAll(PDO::FETCH_ASSOC);
											}
											catch(PDOException $e) {
												echo 'Caught exception: ',  $e->getMessage(), "<br/><br/>\n";
												echo $sql;
												exit;
											}

										//set the action
											if (count($result) > 0) {
												$action = "update";
												$old_array[$schema_name] = $result;
											}
											else {
												$action = "add";
											}
									}
									unset($prep_statement);
									unset($result);
								}
								else {
									$action = "add";
								}

							//add a record
								if ($action == "add") {

									if (permission_exists($this->singular($this->name).'_add')) {

											$sql = "INSERT INTO v_".$this->name." ";
											$sql .= "(";
											if (!$parent_key_exists) {
												$sql .= $parent_key_name.", ";
											}
											//foreach ($parent_field_names as $field_name) {
											//		$sql .= check_str($field_name).", ";
											//}
											foreach ($array as $array_key => $array_value) {
												if (!is_array($array_value)) {
													$sql .= check_str($array_key).", ";
												}
											}
											$sql .= ") ";
											$sql .= "VALUES ";
											$sql .= "(";
											if (!$parent_key_exists) {
												$sql .= "'".$parent_key_value."', ";
											}
											foreach ($array as $array_key => $array_value) {
												if (!is_array($array_value)) {
													if (strlen($array_value) == 0) {
														$sql .= "null, ";
													}
													else {
														$sql .= "'".check_str($array_value)."', ";
													}
												}
											}
											$sql .= ");";
											$sql = str_replace(", )", ")", $sql);
											$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
											try {
												$this->db->query(check_sql($sql));
												$message["message"] = "OK";
												$message["code"] = "200";
												$message["uuid"] = $parent_key_value;
												$message["details"][$m]["name"] = $this->name;
												$message["details"][$m]["message"] = "OK";
												$message["details"][$m]["code"] = "200";
												$message["details"][$m]["uuid"] = $parent_key_value;
												if ($this->debug["sql"]) {
													$message["details"][$m]["sql"] = $sql;
												}
												$this->message = $message;
												$m++;
											}
											catch(PDOException $e) {
												$message["message"] = "Bad Request";
												$message["code"] = "400";
												$message["details"][$m]["name"] = $this->name;
												$message["details"][$m]["message"] = $e->getMessage();
												$message["details"][$m]["code"] = "400";
												if ($this->debug["sql"]) {
													$message["details"][$m]["sql"] = $sql;
												}
												$this->message = $message;
												$m++;
											}
											unset($sql);
									}
									else {
										$message["name"] = $this->name;
										$message["message"] = "Forbidden";
										$message["code"] = "403";
										$message["line"] = __line__;
										$this->message[] = $message;
										$m++;
									}
								}

							//edit a specific uuid
								if ($action == "update") {
									if (permission_exists($this->singular($this->name).'_edit')) {

										//parent data
											$sql = "UPDATE v_".$this->name." SET ";
											foreach ($array as $array_key => $array_value) {
												if (!is_array($array_value) && $array_key != $parent_key_name) {
													if (strlen($array_value) == 0) {
														$sql .= check_str($array_key)." = null, ";
													}
													else {
														$sql .= check_str($array_key)." = '".check_str($array_value)."', ";
													}
												}
											}
											$sql .= "WHERE ".$parent_key_name." = '".$parent_key_value."' ";
											$sql = str_replace(", WHERE", " WHERE", $sql);
											$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
											try {
												$this->db->query(check_sql($sql));
												$message["message"] = "OK";
												$message["code"] = "200";
												$message["uuid"] = $parent_key_value;
												$message["details"][$m]["name"] = $this->name;
												$message["details"][$m]["message"] = "OK";
												$message["details"][$m]["code"] = "200";
												$message["details"][$m]["uuid"] = $parent_key_value;
												if ($this->debug["sql"]) {
													$message["details"][$m]["sql"] = $sql;
												}
												$this->message = $message;
												$m++;
												unset($sql);
											}
											catch(PDOException $e) {
												$message["message"] = "Bad Request";
												$message["code"] = "400";
												$message["details"][$m]["name"] = $this->name;
												$message["details"][$m]["message"] = $e->getMessage();
												$message["details"][$m]["code"] = "400";
												if ($this->debug["sql"]) {
													$message["details"][$m]["sql"] = $sql;
												}
												$this->message = $message;
												$m++;
											}
									}
									else {
										$message["name"] = $this->name;
										$message["message"] = "Forbidden";
										$message["code"] = "403";
										$message["line"] = __line__;
										$this->message = $message;
										$m++;
									}
								}

							//unset the variables
								unset($sql, $action);

							//child data
								foreach ($array as $key => $value) {

									if (is_array($value)) {
											$table_name = "v_".$key;

											foreach ($value as $id => $row) {
												//prepare the variables
													$child_name = $this->singular($key);
													$child_key_name = $child_name."_uuid";
			
												//determine if the parent key exists in the child array
													$parent_key_exists = false;
													if (!isset($array[$parent_key_name])) {
														$parent_key_exists = true;
													}

												//determine if the uuid exists
													$uuid_exists = false;
													foreach ($row as $k => $v) {
														if ($child_key_name == $k) {
															if (strlen($v) > 0) {
																$child_key_value = $v;
																$uuid_exists = true;
																break;
															}
														}
														else {
															$uuid_exists = false;
														}
													}

												//get the child field names
													$child_field_names = array();
													foreach ($row as $k => $v) {
														if (!is_array($v)) {
															$child_field_names[] = $k;
														}
													}

												//determine sql update or delete and get the original data
													if ($uuid_exists) {
														$sql = "SELECT ". implode(", ", $child_field_names)." FROM ".$table_name." ";
														$sql .= "WHERE ".$child_key_name." = '".$child_key_value."' ";
														$prep_statement = $this->db->prepare($sql);
														if ($prep_statement) {
															//get the data
																$prep_statement->execute();
																$child_array = $prep_statement->fetch(PDO::FETCH_ASSOC);
															//set the action
																if (is_array($child_array)) {
																	$action = "update";
																}
																else {
																	$action = "add";
																}
															//add to the parent array
																if (is_array($child_array)) {
																	$old_array[$schema_name][$schema_id][$key][] = $child_array;
																}
														}
														unset($prep_statement);
													}
													else {
														$action = "add";
													}

												//update the data
													if ($action == "update") {
														if (permission_exists($child_name.'_edit')) {
															$sql = "UPDATE ".$table_name." SET ";
															foreach ($row as $k => $v) {
																//if (!is_array($v) && $k != $child_key_name) { //original
																if (!is_array($v) && ($k != $parent_key_name || $k != $child_key_name)) {
																	if (strlen($v) == 0) {
																		$sql .= check_str($k)." = null, ";
																	}
																	else {
																		$sql .= check_str($k)." = '".check_str($v)."', ";
																	}
																}
															}
															$sql .= "WHERE ".$parent_key_name." = '".$this->uuid."' ";
															$sql .= "AND ".$child_key_name." = '".$child_key_value."' ";
															$sql = str_replace(", WHERE", " WHERE", $sql);
															$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
															try {
																$this->db->query(check_sql($sql));
																$message["details"][$m]["name"] = $key;
																$message["details"][$m]["message"] = "OK";
																$message["details"][$m]["code"] = "200";
																$message["details"][$m]["uuid"] = $child_key_value;
																if ($this->debug["sql"]) {
																	$message["details"][$m]["sql"] = $sql;
																}
																$this->message = $message;
																$m++;
															}
															catch(PDOException $e) {
																if ($message["code"] = "200") {
																	$message["message"] = "Bad Request";
																	$message["code"] = "400";
																}
																$message["details"][$m]["name"] = $key;
																$message["details"][$m]["message"] = $e->getMessage();
																$message["details"][$m]["code"] = "400";
																if ($this->debug["sql"]) {
																	$message["details"][$m]["sql"] = $sql;
																}
																$this->message = $message;
																$m++;
															}
														}
														else {
															$message["name"] = $child_name;
															$message["message"] = "Forbidden";
															$message["code"] = "403";
															$message["line"] = __line__;
															$this->message = $message;
															$m++;
														}
													} //action update

											//add the data
												if ($action == "add") {
													if (permission_exists($child_name.'_add')) {
														//determine if child or parent key exists
														$child_key_name = $this->singular($child_name).'_uuid';
														$parent_key_exists = false;
														$child_key_exists = false;
														foreach ($row as $k => $v) {
															if ($k == $parent_key_name) {
																$parent_key_exists = true; 
															}
															if ($k == $child_key_name) {
																$child_key_exists = true;
																$child_key_value = $v;
															}
														}
														if (!$child_key_value) {
															$child_key_value = uuid();
														}
														//build the insert
														$sql = "INSERT INTO ".$table_name." ";
														$sql .= "(";
														if (!$parent_key_exists) {
															$sql .= $this->singular($parent_key_name).", ";
														}
														if (!$child_key_exists) {
															$sql .= $this->singular($child_key_name).", ";
														}
														foreach ($row as $k => $v) {
															if (!is_array($v)) {
																$sql .= check_str($k).", ";
															}
														}
														$sql .= ") ";
														$sql .= "VALUES ";
														$sql .= "(";
														if (!$parent_key_exists) {
															$sql .= "'".$parent_key_value."', ";
														}
														if (!$child_key_exists) {
															$sql .= "'".$child_key_value."', ";
														}
														foreach ($row as $k => $v) {
															if (!is_array($v)) {
																if (strlen($v) == 0) {
																	$sql .= "null, ";
																}
																else {
																	$sql .= "'".check_str($v)."', ";
																}
															}
														}
														$sql .= ");";
														$sql = str_replace(", )", ")", $sql);
														$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
														try {
															$this->db->query(check_sql($sql));
															$message["details"][$m]["name"] = $key;
															$message["details"][$m]["message"] = "OK";
															$message["details"][$m]["code"] = "200";
															$message["details"][$m]["uuid"] = $child_key_value;
															if ($this->debug["sql"]) {
																$message["details"][$m]["sql"] = $sql;
															}
															$this->message = $message;
															$m++;
														}
														catch(PDOException $e) {
															if ($message["code"] = "200") {
																$message["message"] = "Bad Request";
																$message["code"] = "400";
															}
															$message["details"][$m]["name"] = $key;
															$message["details"][$m]["message"] = $e->getMessage();
															$message["details"][$m]["code"] = "400";
															if ($this->debug["sql"]) {
																$message["details"][$m]["sql"] = $sql;
															}
															$this->message = $message;
															$m++;
														}
													}
													else {
														$message["name"] = $child_name;
														$message["message"] = "Forbidden";
														$message["code"] = "403";
														$message["line"] = __line__;
														$this->message = $message;
														$m++;
													}
												} //action add

											//unset the variables
												unset($sql, $action, $child_key_name, $child_key_value);
										} // foreach value

									} //is array
								} //foreach array

						} // foreach schema_array
					}  // foreach main array

				//return the before and after data
					//log this in the future
					if (is_array($old_array)) {
						//normalize the array structure
							//$old_array = $this->normalize_array($old_array, $this->name);

						//debug info
							//echo "<pre>\n";
							//print_r($old_array);
							//echo "</pre>\n";
							//exit;
					}
					//$message["new"] = $new_array;
					//$message["new"]["md5"] = md5(json_encode($new_array));
					$this->message = $message;

				//commit the atomic transaction
//					$this->db->commit();

				//get the domain uuid
					$domain_uuid = $_SESSION['domain_uuid'];

				//log the transaction results
					if (file_exists($_SERVER["PROJECT_ROOT"]."/app/database_transactions/app_config.php")) {
						$sql = "insert into v_database_transactions ";
						$sql .= "(";
						$sql .= "database_transaction_uuid, ";
						$sql .= "domain_uuid, ";
						$sql .= "user_uuid, ";
						if (isset($this->app_uuid)) {
							$sql .= "app_uuid, ";
						}
						$sql .= "app_name, ";
						$sql .= "transaction_code, ";
						$sql .= "transaction_address, ";
						//$sql .= "transaction_type, ";
						$sql .= "transaction_date, ";
						$sql .= "transaction_old, ";
						$sql .= "transaction_new, ";
						$sql .= "transaction_result ";
						$sql .= ")";
						$sql .= "values ";
						$sql .= "(";
						$sql .= "'".uuid()."', ";
						$sql .= "'".$domain_uuid."', ";
						$sql .= "'".$_SESSION['user_uuid']."', ";
						if (isset($this->app_uuid)) {
							$sql .= "'".$this->app_uuid."', ";
						}
						$sql .= "'".$this->app_name."', ";
						$sql .= "'".$message["code"]."', ";
						$sql .= "'".$_SERVER['REMOTE_ADDR']."', ";
						//$sql .= "'$transaction_type', ";
						$sql .= "now(), ";
						$sql .= "'".check_str(json_encode($old_array, JSON_PRETTY_PRINT))."', ";
						$sql .= "'".check_str(json_encode($new_array, JSON_PRETTY_PRINT))."', ";
						$sql .= "'".check_str(json_encode($this->message, JSON_PRETTY_PRINT))."' ";
						$sql .= ")";
						$this->db->exec(check_sql($sql));
						unset($sql);
					}
			} //save method

			//define singular function to convert a word in english to singular
			private function singular($word) {
				//"-es" is used for words that end in "-x", "-s", "-z", "-sh", "-ch" in which case you add
				if (substr($word, -2) == "es") {
					if (substr($word, -3, 1) == "x") {
						return substr($word,0,-2);
					}
					if (substr($word, -3, 1) == "s") {
						return substr($word,0,-2);
					}
					elseif (substr($word, -3, 1) == "z") {
						return substr($word,0,-2);
					}
					elseif (substr($word, -4, 2) == "sh") {
						return substr($word,0,-2);
					}
					elseif (substr($word, -4, 2) == "ch") {
						return substr($word,0,-2);
					}
					else {
						return rtrim($word, "s");
					}
				}
				else {
					return rtrim($word, "s");
				}
			}

			public function get_apps() {
				//get the $apps array from the installed apps from the core and mod directories
					$config_list = glob($_SERVER["DOCUMENT_ROOT"] . PROJECT_PATH . "/*/*/app_config.php");
					$x = 0;
					foreach ($config_list as &$config_path) {
						include($config_path);
						$x++;
					}
					$_SESSION['apps'] = $apps;
			}

			public function array_depth($array) {
				if (is_array($array)) {
					foreach ($array as $value) {
						if (!isset($depth)) { $depth = 1; }
						if (is_array($value)) {
							$depth = $this->array_depth($value) + 1;
						}
					}
				}
				else {
					$depth = 0;
				}
				return $depth;
			}

			public function domain_uuid_exists($name) {
				//get the $apps array from the installed apps from the core and mod directories
					if (!is_array($_SESSION['apps'])) {
						$this->get_apps();
					}
				//search through all fields to see if domain_uuid exists
					foreach ($_SESSION['apps'] as $x => &$app) {
						foreach ($app['db'] as $y => &$row) {
							if ($row['table'] == $name) {
								foreach ($row['fields'] as $z => $field) {
									if ($field['name'] == "domain_uuid") {
										return true;
									}
								}
							}
						}
					}
				//not found
					return false;
			}

		} //class database
	} //!class_exists

if (!function_exists('php_md5')) {
	function php_md5($string) {
		return md5($string);
	}
}

if (!function_exists('php_unix_time_stamp')) {
	function php_unix_time_stamp($string) {
		return strtotime($string);
	}
}

if (!function_exists('php_now')) {
	function php_now() {
		return date("Y-m-d H:i:s");
	}
}

if (!function_exists('php_left')) {
	function php_left($string, $num) {
		return substr($string, 0, $num);
	}
}

if (!function_exists('php_right')) {
	function php_right($string, $num) {
		return substr($string, (strlen($string)-$num), strlen($string));
	}
}

//example usage
/*
//find
	require_once "resources/classes/database.php";
	$database = new database;
	$database->domain_uuid = $_SESSION["domain_uuid"];
	$database->type = $db_type;
	$database->table = "v_extensions";
	$where[0]['name'] = 'domain_uuid';
	$where[0]['value'] = $_SESSION["domain_uuid"];
	$where[0]['operator'] = '=';
	$database->where = $where;
	$order_by[0]['name'] = 'extension';
	$database->order_by = $order_by;
	$database->order_type = 'desc';
	$database->limit = '2';
	$database->offset = '0';
	$database->find();
	print_r($database->result);
//insert
	require_once "resources/classes/database.php";
	$database = new database;
	$database->domain_uuid = $_SESSION["domain_uuid"];
	$database->type = $db_type;
	$database->table = "v_ivr_menus";
	$fields[0]['name'] = 'domain_uuid';
	$fields[0]['value'] = $_SESSION["domain_uuid"];
	$database->add();
	print_r($database->result);
*/
?>
